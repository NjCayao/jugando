<?php
// admin/pages/reviews/pending.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';

// Verificar autenticación admin
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$pageTitle = 'Reseñas Pendientes de Aprobación';
$currentPage = 'reviews';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener reseñas pendientes
    $stmt = $db->query("
        SELECT pr.*, 
               u.email, u.first_name, u.last_name,
               p.name as product_name, p.slug as product_slug, p.image as product_image,
               o.order_number, o.payment_date
        FROM product_reviews pr
        INNER JOIN users u ON pr.user_id = u.id
        INNER JOIN products p ON pr.product_id = p.id
        INNER JOIN orders o ON pr.order_id = o.id
        WHERE pr.is_approved = 0
        ORDER BY pr.created_at DESC
    ");
    $pendingReviews = $stmt->fetchAll() ?: [];
    
} catch (Exception $e) {
    logError("Error en pending reviews: " . $e->getMessage());
    $pendingReviews = [];
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
                            <h1 class="m-0">
                                Reseñas Pendientes
                                <?php if (!empty($pendingReviews)): ?>
                                    <span class="badge badge-warning"><?php echo count($pendingReviews); ?></span>
                                <?php endif; ?>
                            </h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/pages/reviews/">Reseñas</a></li>
                                <li class="breadcrumb-item active">Pendientes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <?php if (empty($pendingReviews)): ?>
                        <!-- No hay pendientes -->
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <h3>¡Excelente!</h3>
                                <p class="text-muted">No hay reseñas pendientes de aprobación.</p>
                                <a href="<?php echo ADMIN_URL; ?>/pages/reviews/" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Ver todas las reseñas
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Acciones masivas -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button class="btn btn-success" onclick="approveAll()">
                                            <i class="fas fa-check-double"></i> Aprobar Todas
                                        </button>
                                        <button class="btn btn-danger ml-2" onclick="rejectAll()">
                                            <i class="fas fa-times"></i> Rechazar Todas
                                        </button>
                                    </div>
                                    <div class="text-muted">
                                        <?php echo !empty($pendingReviews) ? count($pendingReviews) : 0; ?> reseña(s) pendiente(s)
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de reseñas pendientes -->
                        <?php foreach ($pendingReviews as $review): ?>
                            <div class="card mb-3" id="review-<?php echo $review['id']; ?>">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0">
                                                <?php echo htmlspecialchars($review['product_name']); ?>
                                                <small class="text-muted ml-2">
                                                    #<?php echo $review['id']; ?>
                                                </small>
                                            </h5>
                                            <small class="text-muted">
                                                Por: <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?> 
                                                (<?php echo htmlspecialchars($review['email']); ?>)
                                                - <?php echo timeAgo($review['created_at']); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $review['product_slug']; ?>" 
                                               target="_blank" class="btn btn-sm btn-info">
                                                <i class="fas fa-external-link-alt"></i> Ver Producto
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-2 text-center">
                                            <?php if ($review['product_image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $review['product_image']; ?>" 
                                                     alt="Producto" class="img-fluid rounded" style="max-height: 100px;">
                                            <?php else: ?>
                                                <div class="bg-light rounded p-3">
                                                    <i class="fas fa-box fa-3x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-10">
                                            <!-- Rating -->
                                            <div class="mb-2">
                                                <strong>Calificación:</strong>
                                                <span class="text-warning ml-2">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['rating']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </span>
                                                <span class="ml-2">(<?php echo $review['rating']; ?>/5)</span>
                                            </div>
                                            
                                            <!-- Comentario -->
                                            <div class="mb-3">
                                                <strong>Comentario:</strong>
                                                <div class="border rounded p-3 mt-2 bg-light">
                                                    <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Información de compra -->
                                            <div class="row text-sm">
                                                <div class="col-md-4">
                                                    <strong>Orden:</strong> 
                                                    <a href="<?php echo ADMIN_URL; ?>/pages/orders/view.php?id=<?php echo $review['order_id']; ?>">
                                                        <?php echo $review['order_number']; ?>
                                                    </a>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Fecha de compra:</strong> 
                                                    <?php echo formatDate($review['payment_date']); ?>
                                                </div>
                                                <div class="col-md-4">
                                                    <strong>Mostrar nombre:</strong> 
                                                    <?php echo $review['show_name'] ? 'Sí' : 'No (Anónimo)'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <button class="btn btn-success" onclick="approveReview(<?php echo $review['id']; ?>)">
                                                <i class="fas fa-check"></i> Aprobar
                                            </button>
                                            <button class="btn btn-warning ml-2" onclick="approveReview(<?php echo $review['id']; ?>, true)">
                                                <i class="fas fa-star"></i> Aprobar y Destacar
                                            </button>
                                            <button class="btn btn-danger ml-2" onclick="rejectReview(<?php echo $review['id']; ?>)">
                                                <i class="fas fa-times"></i> Rechazar
                                            </button>
                                        </div>
                                        <div>
                                            <a href="moderate.php?id=<?php echo $review['id']; ?>" class="btn btn-info">
                                                <i class="fas fa-edit"></i> Moderar
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Modal de Rechazo -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar Reseña</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm">
                        <input type="hidden" id="rejectReviewId" name="review_id">
                        <div class="form-group">
                            <label>Razón del rechazo:</label>
                            <select class="form-control" name="reason" required>
                                <option value="">Seleccionar...</option>
                                <option value="inappropriate">Contenido inapropiado</option>
                                <option value="spam">Spam o publicidad</option>
                                <option value="offensive">Lenguaje ofensivo</option>
                                <option value="fake">Reseña falsa o sospechosa</option>
                                <option value="irrelevant">No relacionado con el producto</option>
                                <option value="other">Otra razón</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Comentarios adicionales (opcional):</label>
                            <textarea class="form-control" name="comments" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmReject()">
                        <i class="fas fa-times"></i> Rechazar Reseña
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.js"></script>

    <script>
    function approveReview(reviewId, featured = false) {
        const data = new FormData();
        data.append('id', reviewId);
        if (featured) data.append('featured', '1');
        
        fetch('ajax/approve_review.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                toastr.success(featured ? 'Reseña aprobada y destacada' : 'Reseña aprobada');
                $('#review-' + reviewId).fadeOut(500, function() {
                    $(this).remove();
                    checkEmpty();
                });
            } else {
                toastr.error(result.message || 'Error al aprobar');
            }
        });
    }

    function rejectReview(reviewId) {
        $('#rejectReviewId').val(reviewId);
        $('#rejectModal').modal('show');
    }

    function confirmReject() {
        const form = document.getElementById('rejectForm');
        const formData = new FormData(form);
        
        fetch('ajax/reject_review.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                toastr.success('Reseña rechazada');
                $('#rejectModal').modal('hide');
                $('#review-' + formData.get('review_id')).fadeOut(500, function() {
                    $(this).remove();
                    checkEmpty();
                });
                form.reset();
            } else {
                toastr.error(result.message || 'Error al rechazar');
            }
        });
    }

    function approveAll() {
        if (!confirm('¿Aprobar todas las reseñas pendientes?')) return;
        
        const reviewIds = [];
        document.querySelectorAll('[id^="review-"]').forEach(el => {
            reviewIds.push(el.id.replace('review-', ''));
        });
        
        const data = new FormData();
        data.append('ids', JSON.stringify(reviewIds));
        
        fetch('ajax/bulk_approve.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                toastr.success(`${result.count} reseñas aprobadas`);
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(result.message || 'Error al aprobar');
            }
        });
    }

    function rejectAll() {
        if (!confirm('¿Rechazar todas las reseñas pendientes?')) return;
        
        const reviewIds = [];
        document.querySelectorAll('[id^="review-"]').forEach(el => {
            reviewIds.push(el.id.replace('review-', ''));
        });
        
        const data = new FormData();
        data.append('ids', JSON.stringify(reviewIds));
        data.append('reason', 'bulk_rejection');
        
        fetch('ajax/bulk_reject.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                toastr.success(`${result.count} reseñas rechazadas`);
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(result.message || 'Error al rechazar');
            }
        });
    }

    function checkEmpty() {
        if ($('[id^="review-"]').length === 0) {
            location.reload();
        }
    }
    </script>
</body>
</html>