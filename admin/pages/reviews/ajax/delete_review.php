<?php
// /admin/pages/reviews/ajax/delete_review.php
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

if (!$reviewId) {
    echo json_encode(['success' => false, 'message' => 'ID de reseña no válido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información antes de eliminar (para log)
    $stmt = $db->prepare("
        SELECT pr.*, u.email, p.name as product_name
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
    
    // Eliminar reseña (las tablas relacionadas se eliminarán por CASCADE)
    $stmt = $db->prepare("DELETE FROM product_reviews WHERE id = ?");
    $stmt->execute([$reviewId]);
    
    // Log de actividad
    logError(
        "Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . 
        " eliminó reseña #$reviewId de " . $review['email'] . 
        " para producto: " . $review['product_name'], 
        'admin_activity.log'
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Reseña eliminada permanentemente'
    ]);
    
} catch (Exception $e) {
    logError("Error eliminando reseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la reseña']);
}