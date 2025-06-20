<?php
// api/updates/check_updates.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

header('Content-Type: application/json');

try {
    // Verificar si está habilitado el sistema de actualizaciones
    if (Settings::get('update_check_enabled', '1') != '1') {
        throw new Exception('Sistema de actualizaciones deshabilitado');
    }
    
    // Verificar autenticación
    if (!isLoggedIn()) {
        throw new Exception('Debes iniciar sesión');
    }
    
    $user = getCurrentUser();
    $productId = intval($_GET['product_id'] ?? 0);
    
    if ($productId <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener licencia del usuario para este producto
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.slug as product_slug,
               pv.version as current_version, pv.id as current_version_id
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN product_versions pv ON p.id = pv.product_id AND pv.is_current = 1
        WHERE ul.user_id = ? AND ul.product_id = ? AND ul.is_active = 1
    ");
    $stmt->execute([$user['id'], $productId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('No tienes licencia activa para este producto');
    }
    
    // Verificar si la licencia permite actualizaciones
    $updateExpired = false;
    if ($license['update_expires_at']) {
        $updateExpired = strtotime($license['update_expires_at']) < time();
    }
    
    // Obtener versión actual descargada por el usuario
    $stmt = $db->prepare("
        SELECT pv.version, ud.created_at
        FROM update_downloads ud
        INNER JOIN product_versions pv ON ud.version_id = pv.id
        WHERE ud.user_id = ? AND ud.license_id = ? 
        AND ud.download_status = 'completed'
        ORDER BY ud.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $license['id']]);
    $lastDownload = $stmt->fetch();
    
    $userVersion = $lastDownload ? $lastDownload['version'] : $license['current_version'];
    
    // Obtener todas las versiones disponibles
    $stmt = $db->prepare("
        SELECT id, version, changelog, release_notes, created_at, file_size,
               requires_license, min_version_required, is_major_update
        FROM product_versions
        WHERE product_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$productId]);
    $allVersions = $stmt->fetchAll();
    
    // Filtrar versiones más nuevas que la del usuario
    $availableUpdates = [];
    $hasNewVersion = false;
    
    foreach ($allVersions as $version) {
        // Comparar versiones
        if (version_compare($version['version'], $userVersion, '>')) {
            $hasNewVersion = true;
            
            // Verificar si puede descargar esta versión
            $canDownload = true;
            $reason = '';
            
            // Verificar licencia de actualizaciones
            if ($version['requires_license'] && $updateExpired) {
                $canDownload = false;
                $reason = 'Licencia de actualizaciones expirada';
            }
            
            // Verificar versión mínima requerida
            if ($version['min_version_required'] && 
                version_compare($userVersion, $version['min_version_required'], '<')) {
                $canDownload = false;
                $reason = 'Requiere versión ' . $version['min_version_required'] . ' o superior';
            }
            
            // Verificar si esta versión fue lanzada antes de que expire la licencia
            if ($license['update_expires_at'] && 
                strtotime($version['created_at']) > strtotime($license['update_expires_at'])) {
                $canDownload = false;
                $reason = 'Versión lanzada después de expirar tu licencia';
            }
            
            $availableUpdates[] = [
                'id' => $version['id'],
                'version' => $version['version'],
                'changelog' => $version['changelog'],
                'release_notes' => $version['release_notes'],
                'release_date' => formatDate($version['created_at']),
                'file_size' => formatFileSize($version['file_size']),
                'is_major_update' => (bool)$version['is_major_update'],
                'can_download' => $canDownload,
                'reason' => $reason
            ];
        }
    }
    
    // Actualizar última verificación
    $stmt = $db->prepare("
        UPDATE user_licenses 
        SET last_update_check = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$license['id']]);
    
    // Calcular días restantes de actualizaciones
    $updateDaysLeft = null;
    if ($license['update_expires_at']) {
        $daysLeft = (strtotime($license['update_expires_at']) - time()) / 86400;
        $updateDaysLeft = max(0, ceil($daysLeft));
    }
    
    // Respuesta
    $response = [
        'success' => true,
        'data' => [
            'product_name' => $license['product_name'],
            'current_version' => $userVersion,
            'latest_version' => $allVersions[0]['version'] ?? $userVersion,
            'has_updates' => $hasNewVersion,
            'update_count' => count($availableUpdates),
            'updates' => $availableUpdates,
            'license_info' => [
                'update_expires_at' => $license['update_expires_at'],
                'update_expired' => $updateExpired,
                'days_left' => $updateDaysLeft,
                'can_renew' => Settings::get('allow_update_renewal', '1') == '1'
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Función auxiliar para comparar versiones
function version_compare($version1, $version2, $operator) {
    // Convertir versiones a arrays
    $v1_parts = explode('.', $version1);
    $v2_parts = explode('.', $version2);
    
    // Asegurar que ambos tengan la misma cantidad de partes
    $max_parts = max(count($v1_parts), count($v2_parts));
    
    for ($i = count($v1_parts); $i < $max_parts; $i++) {
        $v1_parts[] = '0';
    }
    
    for ($i = count($v2_parts); $i < $max_parts; $i++) {
        $v2_parts[] = '0';
    }
    
    // Comparar parte por parte
    for ($i = 0; $i < $max_parts; $i++) {
        $v1_num = intval($v1_parts[$i]);
        $v2_num = intval($v2_parts[$i]);
        
        if ($v1_num < $v2_num) {
            return ($operator == '<' || $operator == '<=');
        } elseif ($v1_num > $v2_num) {
            return ($operator == '>' || $operator == '>=');
        }
    }
    
    // Son iguales
    return ($operator == '=' || $operator == '==' || $operator == '<=' || $operator == '>=');
}

function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}