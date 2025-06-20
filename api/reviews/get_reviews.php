<?php
// /api/reviews/get_reviews.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

$productId = intval($_GET['product_id'] ?? 0);
$page = intval($_GET['page'] ?? 1);
$sort = $_GET['sort'] ?? 'recent'; // recent, helpful, rating_high, rating_low

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Producto no especificado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $perPage = intval(Settings::get('reviews_per_page', '10'));
    $offset = ($page - 1) * $perPage;
    
    // Construir ordenamiento
    switch ($sort) {
        case 'helpful':
            $orderBy = "helpful_count DESC, pr.created_at DESC";
            break;
        case 'rating_high':
            $orderBy = "pr.rating DESC, pr.created_at DESC";
            break;
        case 'rating_low':
            $orderBy = "pr.rating ASC, pr.created_at DESC";
            break;
        default: // recent
            $orderBy = "pr.created_at DESC";
    }
    
    // Obtener usuario actual si está logueado
    $currentUserId = isLoggedIn() ? $_SESSION[SESSION_NAME]['user_id'] : null;
    
    // Obtener reseñas aprobadas
    $stmt = $db->prepare("
        SELECT 
            pr.*,
            u.first_name,
            u.last_name,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = pr.id AND vote_type = 'helpful') as helpful_count,
            (SELECT COUNT(*) FROM review_votes WHERE review_id = pr.id AND vote_type = 'not_helpful') as not_helpful_count,
            CASE WHEN ? IS NOT NULL THEN 
                (SELECT vote_type FROM review_votes WHERE review_id = pr.id AND user_id = ?)
            ELSE NULL END as user_vote,
            rr.response as admin_response,
            rr.created_at as response_date,
            a.full_name as admin_name
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.id
        LEFT JOIN review_responses rr ON pr.id = rr.review_id AND rr.is_active = 1
        LEFT JOIN admins a ON rr.admin_id = a.id
        WHERE pr.product_id = ? AND pr.is_approved = 1
        ORDER BY pr.is_featured DESC, $orderBy
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$currentUserId, $currentUserId, $productId, $perPage, $offset]);
    $reviews = $stmt->fetchAll();
    
    // Contar total de reseñas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM product_reviews 
        WHERE product_id = ? AND is_approved = 1
    ");
    $stmt->execute([$productId]);
    $totalReviews = $stmt->fetch()['total'];
    
    // Obtener estadísticas
    $stmt = $db->prepare("
        SELECT 
            AVG(rating) as avg_rating,
            COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
            COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
            COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
            COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
            COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
        FROM product_reviews
        WHERE product_id = ? AND is_approved = 1
    ");
    $stmt->execute([$productId]);
    $stats = $stmt->fetch();
    
    // Formatear reseñas
    $formattedReviews = [];
    foreach ($reviews as $review) {
        $displayName = $review['show_name'] 
            ? $review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.'
            : 'Usuario Anónimo';
            
        $formattedReviews[] = [
            'id' => $review['id'],
            'rating' => $review['rating'],
            'comment' => $review['comment'],
            'author' => $displayName,
            'date' => formatDate($review['created_at']),
            'time_ago' => timeAgo($review['created_at']),
            'is_featured' => $review['is_featured'],
            'helpful_count' => $review['helpful_count'],
            'not_helpful_count' => $review['not_helpful_count'],
            'user_vote' => $review['user_vote'],
            'admin_response' => $review['admin_response'] ? [
                'text' => $review['admin_response'],
                'author' => $review['admin_name'],
                'date' => formatDate($review['response_date'])
            ] : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $formattedReviews,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalReviews / $perPage),
            'total_reviews' => $totalReviews,
            'per_page' => $perPage
        ],
        'stats' => [
            'avg_rating' => round($stats['avg_rating'], 1),
            'total_reviews' => $totalReviews,
            'distribution' => [
                5 => $stats['five_star'],
                4 => $stats['four_star'],
                3 => $stats['three_star'],
                2 => $stats['two_star'],
                1 => $stats['one_star']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    logError("Error obteniendo reseñas: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al cargar las reseñas']);
}