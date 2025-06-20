<?php
// api/updates/download_update.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

try {
    // Verificar autenticación
    if (!isLoggedIn()) {
        throw new Exception('Debes iniciar sesión para descargar actualizaciones');
    }
    
    $user = getCurrentUser();
    $versionId = intval($_GET['version_id'] ?? 0);
    
    if ($versionId <= 0) {
        throw new Exception('ID de versión inválido');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener información de la versión
    $stmt = $db->prepare("
        SELECT pv.*, p.id as product_id, p.name as product_name, p.slug as product_slug
        FROM product_versions pv
        INNER JOIN products p ON pv.product_id = p.id
        WHERE pv.id = ?
    ");
    $stmt->execute([$versionId]);
    $version = $stmt->fetch();
    
    if (!$version) {
        throw new Exception('Versión no encontrada');
    }
    
    // Verificar licencia del usuario
    $stmt = $db->prepare("
        SELECT ul.*, 
               (SELECT version FROM update_downloads ud 
                INNER JOIN product_versions pv2 ON ud.version_id = pv2.id
                WHERE ud.user_id = ul.user_id AND ud.license_id = ul.id 
                AND ud.download_status = 'completed'
                ORDER BY ud.created_at DESC LIMIT 1) as last_version
        FROM user_licenses ul
        WHERE ul.user_id = ? AND ul.product_id = ? AND ul.is_active = 1
    ");
    $stmt->execute([$user['id'], $version['product_id']]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('No tienes licencia activa para este producto');
    }
    
    // Verificar si puede descargar esta versión
    $canDownload = true;
    $errorMessage = '';
    
    // 1. Verificar si requiere licencia de actualizaciones
    if ($version['requires_license']) {
        if ($license['update_expires_at'] && strtotime($license['update_expires_at']) < time()) {
            $canDownload = false;
            $errorMessage = 'Tu licencia de actualizaciones ha expirado. Por favor, renuévala para continuar.';
        }
        
        // Verificar si la versión fue lanzada después de expirar la licencia
        if ($license['update_expires_at'] && 
            strtotime($version['created_at']) > strtotime($license['update_expires_at'])) {
            $canDownload = false;
            $errorMessage = 'Esta versión fue lanzada después de expirar tu licencia de actualizaciones.';
        }
    }
    
    // 2. Verificar versión mínima requerida
    if ($version['min_version_required'] && $license['last_version']) {
        if (version_compare($license['last_version'], $version['min_version_required'], '<')) {
            $canDownload = false;
            $errorMessage = 'Necesitas tener instalada al menos la versión ' . $version['min_version_required'];
        }
    }
    
    // 3. Verificar límite de descargas
    if ($license['downloads_used'] >= $license['download_limit']) {
        $canDownload = false;
        $errorMessage = 'Has alcanzado el límite de descargas para esta licencia.';
    }
    
    if (!$canDownload) {
        throw new Exception($errorMessage);
    }
    
    // Registrar inicio de descarga
    $stmt = $db->prepare("
        INSERT INTO update_downloads (
            user_id, license_id, version_id, previous_version, 
            ip_address, user_agent, download_status
        ) VALUES (?, ?, ?, ?, ?, ?, 'started')
    ");
    $stmt->execute([
        $user['id'],
        $license['id'],
        $versionId,
        $license['last_version'],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    $downloadId = $db->lastInsertId();
    
    // Construir ruta del archivo
    $filePath = DOWNLOADS_PATH . '/' . $version['file_path'];
    
    // Verificar si existe el archivo
    if (!file_exists($filePath)) {
        // Intentar rutas alternativas (como en secure_download.php)
        $alternativePaths = [
            $version['file_path'],
            DOWNLOADS_PATH . '/products/' . $version['product_slug'] . '/' . $version['version'] . '/' . basename($version['file_path'])
        ];
        
        foreach ($alternativePaths as $path) {
            if (file_exists($path)) {
                $filePath = $path;
                break;
            }
        }
        
        if (!file_exists($filePath)) {
            // Marcar descarga como fallida
            $stmt = $db->prepare("
                UPDATE update_downloads 
                SET download_status = 'failed', 
                    error_message = 'Archivo no encontrado en el servidor',
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$downloadId]);
            
            throw new Exception('El archivo de actualización no está disponible. Contacta al soporte.');
        }
    }
    
    // Preparar descarga
    $fileName = $version['product_slug'] . '_v' . $version['version'] . '_update.zip';
    $fileSize = filesize($filePath);
    
    // Headers para descarga
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Limpiar buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar archivo
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        throw new Exception('No se pudo abrir el archivo para descarga');
    }
    
    // Transmitir archivo en chunks
    while (!feof($handle) && !connection_aborted()) {
        echo fread($handle, 1024 * 1024); // 1MB por chunk
        flush();
    }
    
    fclose($handle);
    
    // Si llegamos aquí, la descarga fue exitosa
    if (!connection_aborted()) {
        // Actualizar estado de descarga
        $stmt = $db->prepare("
            UPDATE update_downloads 
            SET download_status = 'completed',
                completed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$downloadId]);
        
        // Incrementar contador de descargas en la licencia
        $stmt = $db->prepare("
            UPDATE user_licenses 
            SET downloads_used = downloads_used + 1,
                last_version_downloaded = ?
            WHERE id = ?
        ");
        $stmt->execute([$version['version'], $license['id']]);
        
        // Incrementar contador en product_versions
        $stmt = $db->prepare("
            UPDATE product_versions 
            SET download_count = download_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$versionId]);
        
        // Registrar en download_logs general
        $stmt = $db->prepare("
            INSERT INTO download_logs (
                user_id, product_id, version_id, license_id,
                ip_address, user_agent, download_type
            ) VALUES (?, ?, ?, ?, ?, ?, 'update')
        ");
        $stmt->execute([
            $user['id'],
            $version['product_id'],
            $versionId,
            $license['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
    exit;
    
} catch (Exception $e) {
    // Si hay error, intentar actualizar el registro
    if (isset($downloadId)) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                UPDATE update_downloads 
                SET download_status = 'failed',
                    error_message = ?,
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $downloadId]);
        } catch (Exception $updateError) {
            // Ignorar error al actualizar
        }
    }
    
    // Mostrar error
    http_response_code(400);
    
    // Si es una petición AJAX, devolver JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } else {
        // Mostrar página de error
        $errorMessage = $e->getMessage();
        include __DIR__ . '/../../pages/download_error.php';
    }
    exit;
}