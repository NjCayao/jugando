<?php
// /admin/pages/reviews/ajax/feature_review.php
require_once '../../../../config/database.php';
require_once '../../../../config/constants.php';
require_once '../../../../config/functions.php';

// Verificar autenticación admin
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$reviewId = intval($_POST['id'] ?? 0);
$featured = intval($_POST['featured'] ?? 0);

if (!$reviewId) {
    echo json_encode(['success' => false, 'message' => 'ID de reseña no válido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Actualizar estado de destacado
    $stmt = $db->prepare("
        UPDATE product_reviews 
        SET is_featured = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$featured, $reviewId]);
    
    // Log de actividad
    $action = $featured ? 'destacó' : 'quitó destacado de';
    logError("Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . " $action reseña #$reviewId", 'admin_activity.log');
    
    echo json_encode([
        'success' => true,
        'message' => $featured ? 'Reseña destacada' : 'Destacado removido'
    ]);
    
} catch (Exception $e) {
    logError("Error actualizando destacado: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
}