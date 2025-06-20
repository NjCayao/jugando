<?php
// api/updates/extend_license.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

header('Content-Type: application/json');

try {
    if (!isLoggedIn()) {
        throw new Exception('Debes iniciar sesión');
    }
    
    if (!Settings::get('allow_update_renewal', '1')) {
        throw new Exception('Las renovaciones no están habilitadas');
    }
    
    $user = getCurrentUser();
    $licenseId = intval($_POST['license_id'] ?? 0);
    $months = intval($_POST['months'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    
    if ($licenseId <= 0 || $months <= 0) {
        throw new Exception('Datos inválidos');
    }
    
    if (!in_array($months, [3, 6, 12, 24])) {
        throw new Exception('Período de renovación no válido');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener licencia
    $stmt = $db->prepare("
        SELECT ul.*, p.price as base_price, p.name as product_name
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        WHERE ul.id = ? AND ul.user_id = ? AND ul.is_active = 1
    ");
    $stmt->execute([$licenseId, $user['id']]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('Licencia no encontrada');
    }
    
    // Calcular precio con descuento
    $discount = floatval(Settings::get('update_renewal_discount', '20'));
    $priceFactors = [
        3 => 0.25,
        6 => 0.45,
        12 => 0.8,
        24 => 1.5
    ];
    
    $baseAmount = $license['base_price'] * $priceFactors[$months];
    $finalAmount = $baseAmount * (1 - $discount/100);
    
    // Crear orden de renovación
    $orderNumber = 'REN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    $stmt = $db->prepare("
        INSERT INTO orders (
            user_id, order_number, total_amount, subtotal, currency,
            payment_method, payment_status, customer_email, customer_name,
            is_renewal
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, 1)
    ");
    
    $stmt->execute([
        $user['id'],
        $orderNumber,
        $finalAmount,
        $baseAmount,
        Settings::get('currency', 'USD'),
        $paymentMethod,
        $user['email'],
        $user['first_name'] . ' ' . $user['last_name']
    ]);
    
    $orderId = $db->lastInsertId();
    
    // Respuesta con información de pago
    $response = [
        'success' => true,
        'data' => [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'license_id' => $licenseId,
            'months' => $months,
            'amount' => $finalAmount,
            'currency' => Settings::get('currency', 'USD'),
            'product_name' => $license['product_name'],
            'renewal_type' => 'license_update',
            'payment_urls' => [
                'paypal' => SITE_URL . '/api/payments/paypal_renewal.php?order=' . $orderNumber,
                'mercadopago' => SITE_URL . '/api/payments/mercadopago_renewal.php?order=' . $orderNumber,
                'stripe' => SITE_URL . '/api/payments/stripe_renewal.php?order=' . $orderNumber
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