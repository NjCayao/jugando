<?php
// /admin/pages/reviews/ajax/process_report.php
require_once '../../../../config/database.php';
require_once '../../../../config/constants.php';
require_once '../../../../config/functions.php';

// Verificar autenticación admin
if (!isAdmin()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$reportId = intval($_POST['report_id'] ?? 0);
$reviewId = intval($_POST['review_id'] ?? 0);
$action = $_POST['action'] ?? '';
$adminNotes = $_POST['admin_notes'] ?? '';

if (!$reportId || !$reviewId) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $adminId = $_SESSION[ADMIN_SESSION_NAME]['id'] ?? 1;
    
    if ($action === 'delete_review') {
        // Eliminar la reseña reportada
        $stmt = $db->prepare("DELETE FROM product_reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        
        // Actualizar el reporte como accionado
        $stmt = $db->prepare("
            UPDATE review_reports 
            SET status = 'actioned',
                admin_notes = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$adminNotes, $adminId, $reportId]);
        
        $message = 'Reseña eliminada y reporte procesado';
    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        exit;
    }
    
    // Log de actividad
    logError(
        "Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . 
        " procesó reporte #$reportId - Acción: $action", 
        'admin_activity.log'
    );
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    logError("Error procesando reporte: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar el reporte']);
}