<?php
// api/payments/paypal_return.php - Manejo de retorno de PayPal
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/payments.php';

try {
    // Obtener parámetros de PayPal
    $token = $_GET['token'] ?? '';
    $payerId = $_GET['PayerID'] ?? '';
    
    // Log de parámetros recibidos
    logError("PayPal return params: token=$token, PayerID=$payerId", 'paypal_returns.log');
    
    // Validar parámetros requeridos
    if (empty($token) || empty($payerId)) {
        logError("PayPal return: parámetros faltantes - Token: $token, PayerID: $payerId");
        redirect(SITE_URL . '/pages/failed.php?reason=invalid_data');
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Buscar orden por payment_id (token de PayPal) o la más reciente
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE payment_id = ? AND payment_status IN ('pending', 'completed')
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch();
    
    // Si no encuentra por token, buscar la orden más reciente pending/completed
    if (!$order) {
        $stmt = $db->prepare("
            SELECT * FROM orders 
            WHERE payment_status IN ('pending', 'completed')
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $order = $stmt->fetch();
    }
    
    if (!$order) {
        logError("PayPal return: Orden no encontrada para token $token");
        redirect(SITE_URL . '/pages/failed.php?reason=unknown');
        exit;
    }
    
    // Si ya está completada, redirigir a success
    if ($order['payment_status'] === 'completed') {
        logError("PayPal return: Orden {$order['order_number']} ya completada, redirigiendo", 'paypal_returns.log');
        redirect(SITE_URL . '/pages/success.php?order=' . $order['order_number']);
        exit;
    }
    
    // Completar pago
    $stmt = $db->prepare("
        UPDATE orders 
        SET payment_status = 'completed',
            payment_date = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    if ($stmt->execute([$order['id']])) {
        logError("PayPal pago completado - Orden: {$order['order_number']} - Token: $token", 'paypal_success.log');
        
        // Limpiar carrito si existe
        if (isset($_SESSION['cart'])) {
            unset($_SESSION['cart']);
            unset($_SESSION['cart_totals']);
        }
        
        // Redirigir a página de éxito
        redirect(SITE_URL . '/pages/success.php?order=' . $order['order_number']);
    } else {
        logError("Error actualizando orden {$order['id']} en PayPal return");
        redirect(SITE_URL . '/pages/failed.php?reason=update_error');
    }
    
} catch (Exception $e) {
    logError("Error en PayPal return: " . $e->getMessage());
    redirect(SITE_URL . '/pages/failed.php?reason=gateway_error');
}
?>