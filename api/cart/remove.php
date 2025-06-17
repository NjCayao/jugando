<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/cart.php';

try {
    $productId = intval($_POST['product_id'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }
    
    if (!Cart::hasItem($productId)) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }
    
    $result = Cart::removeItem($productId);
    
    if ($result['success']) {
        $totals = Cart::getTotals();
        
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'cart_count' => Cart::getItemsCount(),
            'cart_empty' => Cart::isEmpty(),
            'totals' => [
                'subtotal' => formatPrice($totals['subtotal']),
                'tax' => formatPrice($totals['tax']),
                'total' => formatPrice($totals['total']),
                'tax_rate' => $totals['tax_rate']
            ]
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error del sistema']);
}
exit;
?>