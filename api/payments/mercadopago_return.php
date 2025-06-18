<?php
// api/payments/mercadopago_return.php - Manejo de retorno de MercadoPago
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/payments.php';

try {
    // Obtener parámetros de MercadoPago
    $status = $_GET['status'] ?? '';
    $paymentId = $_GET['payment_id'] ?? '';
    $externalReference = $_GET['external_reference'] ?? '';
    
    // Log de parámetros recibidos
    logError("MercadoPago return params: status=$status, payment_id=$paymentId, external_ref=$externalReference", 'mercadopago_returns.log');
    
    // Validar parámetros
    if (empty($paymentId) && empty($externalReference)) {
        logError("MercadoPago return: Sin identificadores válidos");
        redirect(SITE_URL . '/pages/failed.php?reason=invalid_data');
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Buscar orden por external_reference (order_number)
    if ($externalReference) {
        $stmt = $db->prepare("
            SELECT * FROM orders 
            WHERE order_number = ? AND payment_status IN ('pending', 'completed')
        ");
        $stmt->execute([$externalReference]);
        $order = $stmt->fetch();
    }
    
    if (!$order) {
        logError("MercadoPago return: Orden no encontrada - external_ref: $externalReference");
        redirect(SITE_URL . '/pages/failed.php?reason=unknown');
        exit;
    }
    
    // Si ya está completada, redirigir a success
    if ($order['payment_status'] === 'completed') {
        redirect(SITE_URL . '/pages/success.php?order=' . $order['order_number']);
        exit;
    }
    
    // Procesar según el status
    switch ($status) {
        case 'approved':
            // Completar pago
            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'completed', 
                    payment_id = ?,
                    payment_date = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$paymentId, $order['id']])) {
                logError("MercadoPago pago completado - Orden: {$order['order_number']} - Payment: $paymentId", 'mercadopago_success.log');
                
                // Limpiar carrito si existe
                if (isset($_SESSION['cart'])) {
                    unset($_SESSION['cart']);
                    unset($_SESSION['cart_totals']);
                }
                
                redirect(SITE_URL . '/pages/success.php?order=' . $order['order_number']);
            } else {
                logError("Error actualizando orden {$order['id']} en MercadoPago return");
                redirect(SITE_URL . '/pages/failed.php?reason=update_error');
            }
            break;
            
        case 'pending':
        case 'in_process':
            logError("MercadoPago pago pendiente - Orden: {$order['order_number']}", 'mercadopago_pending.log');
            redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
            break;
            
        case 'rejected':
        case 'cancelled':
            logError("MercadoPago pago rechazado - Orden: {$order['order_number']} - Status: $status", 'mercadopago_failed.log');
            redirect(SITE_URL . '/pages/failed.php?order=' . $order['order_number'] . '&reason=' . $status);
            break;
            
        default:
            logError("MercadoPago status desconocido: $status - Orden: {$order['order_number']}");
            redirect(SITE_URL . '/pages/pending.php?order=' . $order['order_number'] . '&method=mercadopago');
            break;
    }
    
} catch (Exception $e) {
    logError("Error en MercadoPago return: " . $e->getMessage());
    redirect(SITE_URL . '/pages/failed.php?reason=gateway_error');
}
?>