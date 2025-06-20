<?php
// /api/reviews/verify_purchase.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';

// Solo usuarios logueados
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

$productId = $_GET['product_id'] ?? 0;

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Producto no especificado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION[SESSION_NAME]['user_id'];
    
    // Verificar si el usuario compró el producto
    $stmt = $db->prepare("
        SELECT o.id as order_id, o.order_number, o.payment_date,
               oi.product_name, p.name as current_name
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ? 
        AND oi.product_id = ?
        AND o.payment_status = 'completed'
        ORDER BY o.payment_date DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $productId]);
    $purchase = $stmt->fetch();
    
    if (!$purchase) {
        echo json_encode([
            'success' => false,
            'can_review' => false,
            'message' => 'Debes comprar este producto para dejar una reseña'
        ]);
        exit;
    }
    
    // Verificar si ya tiene una reseña
    $stmt = $db->prepare("
        SELECT id, rating, comment, is_approved, created_at
        FROM product_reviews
        WHERE user_id = ? AND product_id = ?
    ");
    $stmt->execute([$userId, $productId]);
    $existingReview = $stmt->fetch();
    
    if ($existingReview) {
        echo json_encode([
            'success' => true,
            'can_review' => false,
            'has_review' => true,
            'existing_review' => [
                'id' => $existingReview['id'],
                'rating' => $existingReview['rating'],
                'comment' => $existingReview['comment'],
                'is_approved' => $existingReview['is_approved'],
                'created_at' => $existingReview['created_at']
            ],
            'message' => 'Ya has dejado una reseña para este producto'
        ]);
        exit;
    }
    
    // Puede dejar reseña
    echo json_encode([
        'success' => true,
        'can_review' => true,
        'has_review' => false,
        'purchase_info' => [
            'order_id' => $purchase['order_id'],
            'order_number' => $purchase['order_number'],
            'purchase_date' => $purchase['payment_date'],
            'product_name' => $purchase['current_name']
        ]
    ]);
    
} catch (Exception $e) {
    logError("Error verificando compra para reseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al verificar compra']);
}