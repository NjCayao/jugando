<?php
// api/downloads/secure_download.php - Sistema de descargas protegidas
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

try {
    $productId = intval($_GET['id'] ?? 0);
    $orderNumber = $_GET['order'] ?? '';
    
    if ($productId <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener producto
    $stmt = $db->prepare("
        SELECT p.*, pv.file_path, pv.version, pv.file_size
        FROM products p
        LEFT JOIN product_versions pv ON p.id = pv.product_id AND pv.is_current = 1
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Producto no encontrado');
    }
    
    // Verificar si es producto gratuito
    if ($product['is_free']) {
        // Producto gratuito - permitir descarga directa
        if ($product['file_path'] && file_exists($product['file_path'])) {
            downloadFile($product['file_path'], $product['name'] . '_v' . $product['version'] . '.zip');
        } else {
            throw new Exception('Archivo no disponible');
        }
    } else {
        // Producto de pago - verificar orden
        if (empty($orderNumber)) {
            throw new Exception('Número de orden requerido');
        }
        
        // Verificar orden válida
        $stmt = $db->prepare("
            SELECT o.*, oi.product_id
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.order_number = ? AND oi.product_id = ? AND o.payment_status = 'completed'
        ");
        $stmt->execute([$orderNumber, $productId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Orden no válida o no encontrada');
        }
        
        // Si hay usuario logueado, verificar licencia
        if (isLoggedIn()) {
            $currentUser = getCurrentUser();
            
            $stmt = $db->prepare("
                SELECT * FROM user_licenses 
                WHERE user_id = ? AND product_id = ? AND downloads_used < download_limit 
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$currentUser['id'], $productId]);
            $license = $stmt->fetch();
            
            if (!$license) {
                throw new Exception('Licencia expirada o límite de descargas excedido');
            }
            
            // Incrementar contador de descargas
            $stmt = $db->prepare("
                UPDATE user_licenses 
                SET downloads_used = downloads_used + 1, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$license['id']]);
        }
        
        // Proceder con la descarga
        if ($product['file_path'] && file_exists($product['file_path'])) {
            // Log de descarga
            logDownload($productId, $orderNumber, $currentUser['id'] ?? null);
            
            downloadFile($product['file_path'], $product['name'] . '_v' . $product['version'] . '.zip');
        } else {
            throw new Exception('Archivo no disponible');
        }
    }
    
} catch (Exception $e) {
    // Mostrar página de error
    $errorMessage = $e->getMessage();
    include __DIR__ . '/../../pages/download_error.php';
}

/**
 * Función para descargar archivo
 */
function downloadFile($filePath, $downloadName) {
    if (!file_exists($filePath)) {
        throw new Exception('Archivo no encontrado en el servidor');
    }
    
    $fileSize = filesize($filePath);
    $mimeType = 'application/zip';
    
    // Headers para descarga
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Limpiar buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Enviar archivo
    readfile($filePath);
    exit;
}

/**
 * Registrar descarga
 */
function logDownload($productId, $orderNumber, $userId = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO download_logs (
                user_id, product_id, order_number, ip_address, user_agent, 
                download_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $downloadType = $userId ? 'purchase' : 'guest';
        
        $stmt->execute([
            $userId,
            $productId,
            $orderNumber,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $downloadType
        ]);
        
    } catch (Exception $e) {
        logError("Error registrando descarga: " . $e->getMessage());
    }
}
?>