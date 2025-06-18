<?php
// api/downloads/secure_download.php - Sistema de descargas protegidas
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

try {
    $productId = intval($_GET['id'] ?? 0);
    $orderNumber = $_GET['order'] ?? '';
    $versionId = $_GET['version'] ?? null;
    
    if ($productId <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener producto con su versión actual
    $stmt = $db->prepare("
        SELECT p.*, 
               pv.id as version_id,
               pv.file_path, 
               pv.version, 
               pv.file_size
        FROM products p
        LEFT JOIN product_versions pv ON p.id = pv.product_id AND pv.is_current = 1
        WHERE p.id = ? AND p.is_active = 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Producto no encontrado');
    }
    
    // Si no hay archivo disponible
    if (empty($product['file_path'])) {
        throw new Exception('No hay archivo disponible para este producto');
    }
    
    // Construir la ruta completa del archivo
    $filePath = $product['file_path'];
    
    // Si el archivo existe con la ruta tal cual está guardada, usarla
    if (file_exists($filePath)) {
        // La ruta es absoluta y el archivo existe
        logError("Usando ruta absoluta: $filePath", 'downloads.log');
    } else {
        // Si es una ruta relativa, construir la ruta completa
        $filePath = DOWNLOADS_PATH . '/' . $product['file_path'];
        
        // Si aún no existe, intentar limpiando la ruta
        if (!file_exists($filePath)) {
            $cleanPath = $product['file_path'];
            
            // Si contiene "downloads/products", extraer desde ahí
            if (strpos($cleanPath, 'downloads/products') !== false) {
                $parts = explode('downloads/products', $cleanPath);
                $cleanPath = 'products' . end($parts);
                $filePath = DOWNLOADS_PATH . '/' . $cleanPath;
            }
            
            // Si contiene rutas de Windows con backslashes
            elseif (strpos($cleanPath, '\\') !== false) {
                $parts = explode('\\', $cleanPath);
                $startIndex = array_search('products', $parts);
                if ($startIndex !== false) {
                    $relativePath = implode('/', array_slice($parts, $startIndex));
                    $filePath = DOWNLOADS_PATH . '/' . $relativePath;
                }
            }
            
            // Si es una ruta absoluta de Linux que no existe en local
            elseif (strpos($cleanPath, '/home') === 0 || strpos($cleanPath, '/var') === 0) {
                // Extraer solo la parte desde /downloads/products
                if (preg_match('/\/downloads\/products\/(.+)$/', $cleanPath, $matches)) {
                    $filePath = DOWNLOADS_PATH . '/products/' . $matches[1];
                }
            }
        }
    }
    
    if (!file_exists($filePath)) {
        logError("Archivo no encontrado. Path original: {$product['file_path']}, Path procesado: $filePath", 'downloads.log');
        
        // Dar información más específica del error
        $debugInfo = "Rutas intentadas:\n";
        $debugInfo .= "1. Original: {$product['file_path']}\n";
        $debugInfo .= "2. Con DOWNLOADS_PATH: " . DOWNLOADS_PATH . '/' . $product['file_path'] . "\n";
        $debugInfo .= "3. Procesada final: $filePath\n";
        $debugInfo .= "DOWNLOADS_PATH constante: " . DOWNLOADS_PATH . "\n";
        
        logError($debugInfo, 'downloads.log');
        
        throw new Exception('El archivo del producto no está disponible. Por favor contacta al administrador.');
    }
    
    // Verificar si es producto gratuito
    if ($product['is_free']) {
        // Producto gratuito - permitir descarga directa
        logDownload($productId, 'FREE-' . time(), null);
        
        $downloadName = $product['slug'] . '_v' . $product['version'] . '.zip';
        downloadFile($filePath, $downloadName);
        
    } else {
        // Producto de pago - verificar orden o licencia
        
        // Primero intentar con usuario logueado
        if (isLoggedIn()) {
            $currentUser = getCurrentUser();
            
            // Verificar licencia
            $stmt = $db->prepare("
                SELECT * FROM user_licenses 
                WHERE user_id = ? 
                AND product_id = ? 
                AND is_active = 1
                AND downloads_used < download_limit 
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$currentUser['id'], $productId]);
            $license = $stmt->fetch();
            
            if ($license) {
                // Tiene licencia válida - proceder con descarga
                
                // Incrementar contador de descargas
                $stmt = $db->prepare("
                    UPDATE user_licenses 
                    SET downloads_used = downloads_used + 1, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$license['id']]);
                
                // Log de descarga
                logDownload($productId, $orderNumber ?: 'LICENSE-' . $license['id'], $currentUser['id']);
                
                // Descargar
                $downloadName = $product['slug'] . '_v' . $product['version'] . '.zip';
                downloadFile($filePath, $downloadName);
                exit;
            }
        }
        
        // Si no está logueado o no tiene licencia, verificar por orden
        if (empty($orderNumber)) {
            throw new Exception('Necesitas iniciar sesión o proporcionar un número de orden válido');
        }
        
        // Verificar orden válida
        $stmt = $db->prepare("
            SELECT o.*, oi.product_id
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.order_number = ? 
            AND oi.product_id = ? 
            AND o.payment_status = 'completed'
        ");
        $stmt->execute([$orderNumber, $productId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception('Orden no válida o producto no encontrado en esta orden');
        }
        
        // Verificar que la orden no sea muy antigua (30 días para invitados)
        $orderDate = strtotime($order['created_at']);
        $daysOld = (time() - $orderDate) / (60 * 60 * 24);
        
        if (!isLoggedIn() && $daysOld > 30) {
            throw new Exception('Esta orden ha expirado. Por favor crea una cuenta para acceso permanente a tus descargas.');
        }
        
        // Log de descarga
        logDownload($productId, $orderNumber, $order['user_id']);
        
        // Descargar
        $downloadName = $product['slug'] . '_v' . $product['version'] . '.zip';
        downloadFile($filePath, $downloadName);
    }
    
} catch (Exception $e) {
    // Mostrar página de error o JSON según el contexto
    $errorMessage = $e->getMessage();
    
    if (file_exists(__DIR__ . '/../../pages/download_error.php')) {
        include __DIR__ . '/../../pages/download_error.php';
    } else {
        // Respuesta JSON para AJAX
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
    }
    exit;
}

/**
 * Función para descargar archivo
 */
function downloadFile($filePath, $downloadName) {
    if (!file_exists($filePath)) {
        throw new Exception('Archivo no encontrado en el servidor');
    }
    
    $fileSize = filesize($filePath);
    
    // Detectar MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    // Si no se puede detectar, usar genérico
    if (!$mimeType) {
        $mimeType = 'application/octet-stream';
    }
    
    // Headers para descarga
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Headers adicionales para mejor compatibilidad
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    
    // Limpiar cualquier buffer de salida
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Flush para asegurar que los headers se envíen
    flush();
    
    // Para archivos grandes, usar chunks
    if ($fileSize > 10 * 1024 * 1024) { // Más de 10MB
        $handle = fopen($filePath, 'rb');
        if ($handle !== false) {
            while (!feof($handle) && !connection_aborted()) {
                echo fread($handle, 1024 * 1024); // Leer 1MB a la vez
                flush();
            }
            fclose($handle);
        } else {
            throw new Exception('No se pudo abrir el archivo para descarga');
        }
    } else {
        // Para archivos pequeños, leer completo
        readfile($filePath);
    }
    
    exit;
}

/**
 * Registrar descarga
 */
function logDownload($productId, $orderNumber, $userId = null) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Verificar si existe la tabla download_logs
        $stmt = $db->query("SHOW TABLES LIKE 'download_logs'");
        if ($stmt->rowCount() == 0) {
            // Crear tabla si no existe
            $db->exec("
                CREATE TABLE download_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    product_id INT NOT NULL,
                    order_number VARCHAR(50),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    download_type VARCHAR(20),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_product (product_id),
                    INDEX idx_order (order_number)
                )
            ");
        }
        
        $stmt = $db->prepare("
            INSERT INTO download_logs (
                user_id, product_id, order_number, ip_address, user_agent, 
                download_type, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $downloadType = $userId ? 'registered' : 'guest';
        if (strpos($orderNumber, 'FREE-') === 0) {
            $downloadType = 'free';
        }
        
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