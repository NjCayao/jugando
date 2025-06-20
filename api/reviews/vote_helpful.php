<?php
// /api/reviews/vote_helpful.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$reviewId = intval($_POST['review_id'] ?? 0);
$voteType = $_POST['vote_type'] ?? 'helpful'; // helpful o not_helpful

if (!$reviewId) {
    echo json_encode(['success' => false, 'message' => 'Reseña no especificada']);
    exit;
}

if (!in_array($voteType, ['helpful', 'not_helpful'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de voto inválido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar que la reseña existe
    $stmt = $db->prepare("SELECT id FROM product_reviews WHERE id = ? AND is_approved = 1");
    $stmt->execute([$reviewId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Reseña no encontrada']);
        exit;
    }
    
    // Obtener identificador del votante
    $userId = isLoggedIn() ? $_SESSION[SESSION_NAME]['user_id'] : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    if ($userId) {
        // Usuario logueado - verificar por user_id
        $stmt = $db->prepare("SELECT id, vote_type FROM review_votes WHERE review_id = ? AND user_id = ?");
        $stmt->execute([$reviewId, $userId]);
        $existingVote = $stmt->fetch();
        
        if ($existingVote) {
            if ($existingVote['vote_type'] == $voteType) {
                // Mismo voto - eliminar
                $stmt = $db->prepare("DELETE FROM review_votes WHERE id = ?");
                $stmt->execute([$existingVote['id']]);
                $action = 'removed';
            } else {
                // Cambiar voto
                $stmt = $db->prepare("UPDATE review_votes SET vote_type = ? WHERE id = ?");
                $stmt->execute([$voteType, $existingVote['id']]);
                $action = 'changed';
            }
        } else {
            // Nuevo voto
            $stmt = $db->prepare("
                INSERT INTO review_votes (review_id, user_id, vote_type) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$reviewId, $userId, $voteType]);
            $action = 'added';
        }
    } else {
        // Usuario no logueado - verificar por IP
        $stmt = $db->prepare("SELECT id, vote_type FROM review_votes WHERE review_id = ? AND ip_address = ?");
        $stmt->execute([$reviewId, $ipAddress]);
        $existingVote = $stmt->fetch();
        
        if ($existingVote) {
            if ($existingVote['vote_type'] == $voteType) {
                // Mismo voto - eliminar
                $stmt = $db->prepare("DELETE FROM review_votes WHERE id = ?");
                $stmt->execute([$existingVote['id']]);
                $action = 'removed';
            } else {
                // Cambiar voto
                $stmt = $db->prepare("UPDATE review_votes SET vote_type = ? WHERE id = ?");
                $stmt->execute([$voteType, $existingVote['id']]);
                $action = 'changed';
            }
        } else {
            // Nuevo voto
            $stmt = $db->prepare("
                INSERT INTO review_votes (review_id, ip_address, vote_type) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$reviewId, $ipAddress, $voteType]);
            $action = 'added';
        }
    }
    
    // Obtener conteos actualizados
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM review_votes WHERE review_id = ? AND vote_type = 'helpful') as helpful_count,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = ? AND vote_type = 'not_helpful') as not_helpful_count
    ");
    $stmt->execute([$reviewId, $reviewId]);
    $counts = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'counts' => [
            'helpful' => $counts['helpful_count'],
            'not_helpful' => $counts['not_helpful_count']
        ]
    ]);
    
} catch (Exception $e) {
    logError("Error votando reseña: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al registrar tu voto']);
}