<?php
// api/orders/check_status.php - Verificar estado de una orden
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $orderNumber = $_GET['order'] ?? '';
    
    if (empty($orderNumber)) {
        throw new Exception('Número de orden requerido');
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Obtener estado de la orden
    $stmt = $db->prepare("
        SELECT payment_status, failure_reason, updated_at 
        FROM orders 
        WHERE order_number = ?
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Orden no encontrada');
    }
    
    $response = [
        'success' => true,
        'status' => $order['payment_status'],
        'updated_at' => $order['updated_at']
    ];
    
    // Agregar razón si está fallida
    if ($order['payment_status'] === 'failed' && $order['failure_reason']) {
        $response['reason'] = $order['failure_reason'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    logError("Error verificando estado de orden: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>