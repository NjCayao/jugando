<?php
// /admin/pages/reviews/ajax/update_report_status.php
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
$status = $_POST['status'] ?? '';

if (!$reportId || !in_array($status, ['reviewed', 'dismissed', 'actioned'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $adminId = $_SESSION[ADMIN_SESSION_NAME]['id'] ?? 1;
    
    // Actualizar estado del reporte
    $stmt = $db->prepare("
        UPDATE review_reports 
        SET status = ?,
            reviewed_by = ?,
            reviewed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $adminId, $reportId]);
    
    $statusMessages = [
        'reviewed' => 'Reporte marcado como revisado',
        'dismissed' => 'Reporte descartado',
        'actioned' => 'Acción tomada sobre el reporte'
    ];
    
    // Log de actividad
    logError(
        "Admin " . $_SESSION[ADMIN_SESSION_NAME]['username'] . 
        " actualizó reporte #$reportId a estado: $status", 
        'admin_activity.log'
    );
    
    echo json_encode([
        'success' => true,
        'message' => $statusMessages[$status] ?? 'Estado actualizado'
    ]);
    
} catch (Exception $e) {
    logError("Error actualizando estado de reporte: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
}