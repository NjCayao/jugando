<?php
// /api/reviews/add_review.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

// Solo usuarios logueados
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos
$productId = intval($_POST['product_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');
$showName = isset($_POST['show_name']) ? 1 : 0;

// Validaciones
if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Producto no especificado']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'La calificación debe ser entre 1 y 5 estrellas']);
    exit;
}

$minLength = intval(Settings::get('reviews_min_length', '20'));
$maxLength = intval(Settings::get('reviews_max_length', '1000'));

if (strlen($comment) < $minLength) {
    echo json_encode(['success' => false, 'message' => "El comentario debe tener al menos $minLength caracteres"]);
    exit;
}

if (strlen($comment) > $maxLength) {
    echo json_encode(['success' => false, 'message' => "El comentario no puede exceder $maxLength caracteres"]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $userId = $_SESSION[SESSION_NAME]['user_id'];
    
    // Verificar que compró el producto
    $stmt = $db->prepare("
        SELECT o.id as order_id
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ? 
        AND oi.product_id = ?
        AND o.payment_status = 'completed'
        LIMIT 1
    ");
    $stmt->execute([$userId, $productId]);
    $purchase = $stmt->fetch();
    
    if (!$purchase) {
        echo json_encode(['success' => false, 'message' => 'Debes comprar este producto para dejar una reseña']);
        exit;
    }
    
    // Verificar que no tenga ya una reseña
    $stmt = $db->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ya has dejado una reseña para este producto']);
        exit;
    }
    
    // Determinar si requiere aprobación
    $requiresApproval = Settings::get('reviews_require_approval', '1') == '1';
    $isApproved = $requiresApproval ? 0 : 1;
    
    // Insertar reseña
    $stmt = $db->prepare("
        INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, show_name, is_approved)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $productId,
        $userId,
        $purchase['order_id'],
        $rating,
        $comment,
        $showName,
        $isApproved
    ]);
    
    $reviewId = $db->lastInsertId();
    
    // Obtener información del producto y usuario
    $stmt = $db->prepare("
        SELECT p.name as product_name, u.email, u.first_name
        FROM products p
        CROSS JOIN users u
        WHERE p.id = ? AND u.id = ?
    ");
    $stmt->execute([$productId, $userId]);
    $info = $stmt->fetch();
    
    // Enviar notificación al admin si está habilitado
    if (Settings::get('reviews_notification_email', '1') == '1') {
        $adminEmail = Settings::get('admin_notification_email');
        if ($adminEmail) {
            $subject = "Nueva reseña pendiente de aprobación";
            $message = "Se ha recibido una nueva reseña:\n\n";
            $message .= "Producto: {$info['product_name']}\n";
            $message .= "Usuario: {$info['first_name']} ({$info['email']})\n";
            $message .= "Calificación: $rating estrellas\n";
            $message .= "Comentario: $comment\n\n";
            $message .= "Revisar en: " . ADMIN_URL . "/pages/reviews/pending.php";
            
            mail($adminEmail, $subject, $message, "From: " . Settings::get('from_email'));
        }
    }
    
    echo json_encode([
        'success' => true,
        'review_id' => $reviewId,
        'requires_approval' => $requiresApproval,
        'message' => $requiresApproval 
            ? 'Tu reseña ha sido enviada y será publicada después de ser revisada' 
            : 'Tu reseña ha sido publicada exitosamente'
    ]);
    
} catch (Exception $e) {
    logError("Error agregando reseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al guardar la reseña']);
}