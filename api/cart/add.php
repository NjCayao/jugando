<?php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/constants.php';
    require_once __DIR__ . '/../../config/functions.php';
    require_once __DIR__ . '/../../config/settings.php';
    require_once __DIR__ . '/../../config/cart.php';

    $productId = intval($_POST['product_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de producto inválido']);
        exit;
    }
    
    if ($quantity <= 0 || $quantity > 10) {
        echo json_encode(['success' => false, 'message' => 'Cantidad debe ser entre 1 y 10']);
        exit;
    }
    
    // CAMBIAR ESTA LÍNEA POR:
    $result = Cart::addItem($productId, $quantity);
    
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
exit;
?>