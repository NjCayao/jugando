<?php
// /admin/pages/reviews/ajax/reject_review.php
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

$reviewId = intval($_POST['review_id'] ?? 0);
$reason = $_POST['reason'] ?? '';
$comments = $_POST['comments'] ?? '';

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
    
    // Rechazar reseña (simplemente la dejamos como no aprobada)
    // Opcionalmente podrías agregar un campo 'is_rejected' a la BD
    $stmt = $db->prepare("
        UPDATE product_reviews 
        SET is_approved = 0, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$reviewId]);
    
    // Enviar email de notificación al usuario
    $reasonText = match($reason) {
        'inappropriate' => 'Contenido inapropiado',
        'spam' => 'Spam o publicidad',
        'offensive' => 'Lenguaje ofensivo',
        'fake' => 'Reseña falsa o sospechosa',
        'irrelevant' => 'No relacionado con el producto',
        'other' => 'Otra razón: ' . $comments,
        default => 'No cumple con nuestras políticas'
    };
    
    EmailSystem::sendTemplateEmail($review['email'], 'review_rejected', [
        '{USER_NAME}' => $review['first_name'],
        '{PRODUCT_NAME}' => $review['product_name'],
        '{REASON}' => $reasonText
    ]);
    
    // Log de actividad
    logError("Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . " rechazó reseña #$reviewId - Razón: $reasonText", 'admin_activity.log');
    
    echo json_encode([
        'success' => true,
        'message' => 'Reseña rechazada'
    ]);
    
} catch (Exception $e) {
    logError("Error rechazando reseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al rechazar la reseña']);
}