<?php
// /admin/pages/reviews/ajax/bulk_approve.php
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

$ids = json_decode($_POST['ids'] ?? '[]', true);

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No se proporcionaron IDs válidos']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $count = 0;
    
    // Obtener información de las reseñas para enviar emails
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $db->prepare("
        SELECT pr.id, u.email, u.first_name, p.name as product_name
        FROM product_reviews pr
        JOIN users u ON pr.user_id = u.id
        JOIN products p ON pr.product_id = p.id
        WHERE pr.id IN ($placeholders) AND pr.is_approved = 0
    ");
    $stmt->execute($ids);
    $reviews = $stmt->fetchAll();
    
    // Aprobar todas las reseñas
    $stmt = $db->prepare("
        UPDATE product_reviews 
        SET is_approved = 1, updated_at = NOW() 
        WHERE id IN ($placeholders) AND is_approved = 0
    ");
    $stmt->execute($ids);
    $count = $stmt->rowCount();
    
    // Enviar emails de notificación
    foreach ($reviews as $review) {
        EmailSystem::sendTemplateEmail($review['email'], 'review_approved', [
            '{USER_NAME}' => $review['first_name'],
            '{PRODUCT_NAME}' => $review['product_name']
        ]);
    }
    
    // Log de actividad
    logError(
        "Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . 
        " aprobó $count reseñas en masa", 
        'admin_activity.log'
    );
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "$count reseñas aprobadas exitosamente"
    ]);
    
} catch (Exception $e) {
    logError("Error en aprobación masiva: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al aprobar las reseñas']);
}