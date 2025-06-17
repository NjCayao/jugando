<?php
// api/donations/process.php - Procesador inicial de donaciones
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../config/functions.php';
require_once '../../config/settings.php';

// Verificar que el sitio no esté en mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    redirect(SITE_URL . '/maintenance.php');
}

// Función para generar ID único de transacción
function generateTransactionId() {
    return 'DON_' . time() . '_' . random_int(1000, 9999);
}

try {
    // Validar parámetros básicos
    $amount = floatval($_GET['amount'] ?? 0);
    $method = sanitize($_GET['method'] ?? '');
    $productId = intval($_GET['product_id'] ?? 0);
    
    // Validaciones
    if ($amount <= 0 || $amount > 999) {
        throw new Exception('Monto de donación inválido');
    }
    
    if (!in_array($method, ['mercadopago', 'paypal'])) {
        throw new Exception('Método de pago no válido');
    }
    
    // Verificar que el método esté habilitado
    $methodEnabled = Settings::get($method . '_enabled', '0');
    if ($methodEnabled != '1') {
        throw new Exception('Método de pago no disponible');
    }
    
    // Obtener datos del donante
    $donorData = [
        'name' => sanitize($_GET['donor_name'] ?? ''),
        'email' => sanitize($_GET['donor_email'] ?? ''),
        'message' => sanitize($_GET['donor_message'] ?? '')
    ];
    
    // Validar email si se proporciona
    if (!empty($donorData['email']) && !isValidEmail($donorData['email'])) {
        throw new Exception('Email del donante no válido');
    }
    
    // Obtener información del producto si existe
    $product = null;
    if ($productId > 0) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id, name, slug FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
    }
    
    // Generar ID de transacción único
    $transactionId = generateTransactionId();
    
    // Insertar donación en BD con estado pending
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        INSERT INTO donations (
            transaction_id, amount, currency, payment_method, payment_status,
            donor_name, donor_email, donor_message, 
            product_id, product_name,
            ip_address, user_agent
        ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $transactionId,
        $amount,
        'USD',
        $method,
        $donorData['name'],
        $donorData['email'],
        $donorData['message'],
        $product ? $product['id'] : null,
        $product ? $product['name'] : null,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    $donationId = $db->lastInsertId();
    
    // Procesar según el método de pago
    switch ($method) {
        case 'mercadopago':
            require_once 'mercadopago_process.php';
            processMercadoPagoDonation($donationId, $transactionId, $amount, $donorData, $product);
            break;
            
        case 'paypal':
            require_once 'paypal_process.php';
            processPayPalDonation($donationId, $transactionId, $amount, $donorData, $product);
            break;
            
        default:
            throw new Exception('Método de pago no implementado');
    }

} catch (Exception $e) {
    // Log del error
    logError("Error procesando donación: " . $e->getMessage());
    
    // Actualizar estado de donación si existe
    if (isset($donationId)) {
        try {
            $stmt = $db->prepare("UPDATE donations SET payment_status = 'failed' WHERE id = ?");
            $stmt->execute([$donationId]);
        } catch (Exception $dbError) {
            logError("Error actualizando estado de donación fallida: " . $dbError->getMessage());
        }
    }
    
    // Redirigir a página de error
    $errorMsg = urlencode($e->getMessage());
    redirect(SITE_URL . "/pages/donation-failed.php?error=" . $errorMsg);
}
?>