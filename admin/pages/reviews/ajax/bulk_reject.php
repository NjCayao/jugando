<?php
// /admin/pages/reviews/ajax/bulk_reject.php
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
$reason = $_POST['reason'] ?? 'bulk_rejection';

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
    
    // Rechazar todas las reseñas (las dejamos como no aprobadas)
    // En un rechazo masivo, simplemente las mantenemos sin aprobar
    $count = count($reviews);
    
    // Enviar emails de notificación
    $reasonText = 'Rechazo masivo - No cumple con las políticas del sitio';
    foreach ($reviews as $review) {
        EmailSystem::sendTemplateEmail($review['email'], 'review_rejected', [
            '{USER_NAME}' => $review['first_name'],
            '{PRODUCT_NAME}' => $review['product_name'],
            '{REASON}' => $reasonText
        ]);
    }
    
    // Opcionalmente, eliminar las reseñas rechazadas
    if ($count > 0) {
        $stmt = $db->prepare("DELETE FROM product_reviews WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
    
    // Log de actividad
    logError(
        "Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . 
        " rechazó $count reseñas en masa", 
        'admin_activity.log'
    );
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "$count reseñas rechazadas exitosamente"
    ]);
    
} catch (Exception $e) {
    logError("Error en rechazo masivo: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al rechazar las reseñas']);
}