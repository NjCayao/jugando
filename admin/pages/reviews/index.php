<?php
// admin/pages/reviews/index.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación admin
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$pageTitle = 'Gestión de Reseñas';
$currentPage = 'reviews';

// Filtros
$status = $_GET['status'] ?? 'all';
$product = $_GET['product'] ?? '';
$rating = $_GET['rating'] ?? '';
$search = $_GET['search'] ?? '';
$page = intval($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir consulta con filtros
    $where = [];
    $params = [];
    
    if ($status === 'pending') {
        $where[] = "pr.is_approved = 0";
    } elseif ($status === 'approved') {
        $where[] = "pr.is_approved = 1";
    } elseif ($status === 'featured') {
        $where[] = "pr.is_featured = 1";
    }
    
    if ($product) {
        $where[] = "pr.product_id = ?";
        $params[] = $product;
    }
    
    if ($rating) {
        $where[] = "pr.rating = ?";
        $params[] = $rating;
    }
    
    if ($search) {
        $where[] = "(pr.comment LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // Contar total
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.id
        $whereClause
    ";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalReviews = $stmt->fetch()['total'];
    $totalPages = ceil($totalReviews / $perPage);
    
    // Obtener reseñas
    $query = "
        SELECT pr.*, 
               u.email, u.first_name, u.last_name,
               p.name as product_name, p.slug as product_slug,
               o.order_number,
               (SELECT COUNT(*) FROM review_votes WHERE review_id = pr.id AND vote_type = 'helpful') as helpful_count,
               (SELECT COUNT(*) FROM review_reports WHERE review_id = pr.id AND status = 'pending') as pending_reports,
               rr.response as admin_response
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.id
        INNER JOIN products p ON pr.product_id = p.id
        INNER JOIN orders o ON pr.order_id = o.id
        LEFT JOIN review_responses rr ON pr.id = rr.review_id
        $whereClause
        ORDER BY pr.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
    // Obtener estadísticas
    $stats = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_approved = 0 THEN 1 END) as pending,
            COUNT(CASE WHEN is_approved = 1 THEN 1 END) as approved,
            COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured,
            AVG(rating) as avg_rating
        FROM product_reviews
    ")->fetch();
    
    // Obtener productos para filtro
    $products = $db->query("
        SELECT DISTINCT p.id, p.name, COUNT(pr.id) as review_count
        FROM products p
        INNER JOIN product_reviews pr ON p.id = pr.product_id
        GROUP BY p.id
        ORDER BY p.name
    ")->fetchAll();
    
} catch (Exception $e) {
    logError("Error en admin reviews: " . $e->getMessage());
    $reviews = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'featured' => 0, 'avg_rating' => 0];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $pageTitle; ?> | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Gestión de Reseñas</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item active">Reseñas</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <!-- Estadísticas -->
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['total']); ?></h3>
                                    <p>Total Reseñas</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-comments"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['pending']); ?></h3>
                                    <p>Pendientes</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['approved']); ?></h3>
                                    <p>Aprobadas</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-primary">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                                    <p>Rating Promedio</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Filtros</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label class="mr-2">Estado:</label>
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="all">Todas</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Aprobadas</option>
                                        <option value="featured" <?php echo $status === 'featured' ? 'selected' : ''; ?>>Destacadas</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mr-3">
                                    <label class="mr-2">Producto:</label>
                                    <select name="product" class="form-control form-control-sm">
                                        <option value="">Todos</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>" <?php echo $product == $prod['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prod['name']); ?> (<?php echo $prod['review_count']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group mr-3">
                                    <label class="mr-2">Rating:</label>
                                    <select name="rating" class="form-control form-control-sm">
                                        <option value="">Todos</option>
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $rating == $i ? 'selected' : ''; ?>>
                                                <?php echo $i; ?> estrellas
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group mr-3">
                                    <input type="text" name="search" class="form-control form-control-sm" 
                                           placeholder="Buscar..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                                <a href="<?php echo ADMIN_URL; ?>/pages/reviews/" class="btn btn-secondary btn-sm ml-2">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Lista de Reseñas -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Reseñas</h3>
                            <div class="card-tools">
                                <a href="settings.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-cog"></i> Configuración
                                </a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th style="width: 50px">ID</th>
                                            <th>Producto</th>
                                            <th>Usuario</th>
                                            <th>Rating</th>
                                            <th>Comentario</th>
                                            <th>Estado</th>
                                            <th>Fecha</th>
                                            <th style="width: 200px">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($reviews)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center">No se encontraron reseñas</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($reviews as $review): ?>
                                                <tr>
                                                    <td><?php echo $review['id']; ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $review['product_slug']; ?>" target="_blank">
                                                            <?php echo htmlspecialchars($review['product_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($review['email']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="text-warning">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <?php if ($i <= $review['rating']): ?>
                                                                    <i class="fas fa-star"></i>
                                                                <?php else: ?>
                                                                    <i class="far fa-star"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div style="max-width: 300px;">
                                                            <?php echo htmlspecialchars(truncateText($review['comment'], 100)); ?>
                                                        </div>
                                                        <?php if ($review['helpful_count'] > 0): ?>
                                                            <small class="text-success">
                                                                <i class="fas fa-thumbs-up"></i> <?php echo $review['helpful_count']; ?> útil
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($review['pending_reports'] > 0): ?>
                                                            <small class="text-danger ml-2">
                                                                <i class="fas fa-flag"></i> <?php echo $review['pending_reports']; ?> reportes
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($review['is_approved']): ?>
                                                            <span class="badge badge-success">Aprobada</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Pendiente</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($review['is_featured']): ?>
                                                            <span class="badge badge-primary">Destacada</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($review['admin_response']): ?>
                                                            <span class="badge badge-info">Respondida</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo formatDate($review['created_at']); ?><br>
                                                        <small class="text-muted"><?php echo timeAgo($review['created_at']); ?></small>
                                                    </td>
                                                    <td>
                                                        <a href="moderate.php?id=<?php echo $review['id']; ?>" 
                                                           class="btn btn-info btn-sm" title="Moderar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <?php if (!$review['is_approved']): ?>
                                                            <button onclick="quickApprove(<?php echo $review['id']; ?>)" 
                                                                    class="btn btn-success btn-sm" title="Aprobar">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!$review['is_featured']): ?>
                                                            <button onclick="toggleFeatured(<?php echo $review['id']; ?>, true)" 
                                                                    class="btn btn-warning btn-sm" title="Destacar">
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button onclick="toggleFeatured(<?php echo $review['id']; ?>, false)" 
                                                                    class="btn btn-secondary btn-sm" title="Quitar destacado">
                                                                <i class="far fa-star"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        
                                                        <button onclick="deleteReview(<?php echo $review['id']; ?>)" 
                                                                class="btn btn-danger btn-sm" title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer">
                                <nav>
                                    <ul class="pagination pagination-sm m-0 float-right">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                        <?php echo $i; ?>
                                                    </a>
                                                </li>
                                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                                <li class="page-item disabled"><span class="page-link">...</span></li>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.js"></script>

    <script>
    function quickApprove(reviewId) {
        if (confirm('¿Aprobar esta reseña?')) {
            fetch('ajax/approve_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + reviewId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Reseña aprobada');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error(data.message || 'Error al aprobar');
                }
            });
        }
    }

    function toggleFeatured(reviewId, featured) {
        fetch('ajax/feature_review.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + reviewId + '&featured=' + (featured ? 1 : 0)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success(featured ? 'Reseña destacada' : 'Destacado removido');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(data.message || 'Error al actualizar');
            }
        });
    }

    function deleteReview(reviewId) {
        if (confirm('¿Eliminar esta reseña permanentemente?')) {
            fetch('ajax/delete_review.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + reviewId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    toastr.success('Reseña eliminada');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    toastr.error(data.message || 'Error al eliminar');
                }
            });
        }
    }
    </script>
</body>
</html>