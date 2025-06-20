<?php
// /admin/pages/reviews/ajax/approve_review.php
require_once '../../../../config/database.php';
require_once '../../../../config/constants.php';
require_once '../../../../config/functions.php';
require_once '../../../../config/email.php';

// Verificar autenticación admin
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$reviewId = intval($_POST['id'] ?? 0);
$featured = isset($_POST['featured']) && $_POST['featured'] == '1';

if (!$reviewId) {
    echo json_encode(['success' => false, 'message' => 'ID de reseña no válido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información de la reseña
    $stmt = $db->prepare("
        SELECT pr.*, u.email, u.first_name, p.name as product_name
        FROM product_reviews pr
        JOIN users u ON pr.user_id = u.id
        JOIN products p ON pr.product_id = p.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$reviewId]);
    $review = $stmt->fetch();
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Reseña no encontrada']);
        exit;
    }
    
    // Aprobar reseña
    $stmt = $db->prepare("
        UPDATE product_reviews 
        SET is_approved = 1, is_featured = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$featured ? 1 : 0, $reviewId]);
    
    // Enviar email de notificación al usuario
    EmailSystem::sendTemplateEmail($review['email'], 'review_approved', [
        '{USER_NAME}' => $review['first_name'],
        '{PRODUCT_NAME}' => $review['product_name']
    ]);
    
    // Log de actividad
    logError("Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . " aprobó reseña #$reviewId", 'admin_activity.log');
    
    echo json_encode([
        'success' => true,
        'message' => $featured ? 'Reseña aprobada y destacada' : 'Reseña aprobada'
    ]);
    
} catch (Exception $e) {
    logError("Error aprobando reseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al aprobar la reseña']);
}