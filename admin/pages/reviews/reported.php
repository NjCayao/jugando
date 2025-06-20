<?php
// admin/pages/reviews/reported.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';

// Verificar autenticación admin
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$pageTitle = 'Reseñas Reportadas';
$currentPage = 'reviews';

// Filtros
$status = $_GET['status'] ?? 'pending';
$page = intval($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir WHERE clause
    $where = [];
    $params = [];
    
    if ($status && $status !== 'all') {
        $where[] = "rr.status = ?";
        $params[] = $status;
    }
    
    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // Contar total
    $countQuery = "
        SELECT COUNT(DISTINCT rr.id) as total 
        FROM review_reports rr
        $whereClause
    ";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalReports = $stmt->fetch()['total'];
    $totalPages = ceil($totalReports / $perPage);
    
    // Obtener reportes
    $query = "
        SELECT rr.*,
               pr.rating, pr.comment as review_comment, pr.created_at as review_date,
               p.name as product_name, p.slug as product_slug,
               u.first_name as reviewer_first_name, u.last_name as reviewer_last_name, u.email as reviewer_email,
               reporter.first_name as reporter_first_name, reporter.last_name as reporter_last_name, reporter.email as reporter_email,
               admin.full_name as admin_name
        FROM review_reports rr
        INNER JOIN product_reviews pr ON rr.review_id = pr.id
        INNER JOIN products p ON pr.product_id = p.id
        INNER JOIN users u ON pr.user_id = u.id
        LEFT JOIN users reporter ON rr.reporter_id = reporter.id
        LEFT JOIN admins admin ON rr.reviewed_by = admin.id
        $whereClause
        ORDER BY 
            CASE WHEN rr.status = 'pending' THEN 0 ELSE 1 END,
            rr.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll() ?: [];
    
    // Estadísticas
    $stats = $db->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed,
            COUNT(CASE WHEN status = 'actioned' THEN 1 END) as actioned,
            COUNT(CASE WHEN status = 'dismissed' THEN 1 END) as dismissed
        FROM review_reports
    ")->fetch();
    
} catch (Exception $e) {
    logError("Error en reported reviews: " . $e->getMessage());
    $reports = [];
    $stats = ['total' => 0, 'pending' => 0, 'reviewed' => 0, 'actioned' => 0, 'dismissed' => 0];
}

$reasonLabels = [
    'spam' => 'Spam o publicidad',
    'inappropriate' => 'Contenido inapropiado',
    'fake' => 'Reseña falsa',
    'offensive' => 'Lenguaje ofensivo',
    'other' => 'Otra razón'
];

$statusLabels = [
    'pending' => ['text' => 'Pendiente', 'class' => 'warning'],
    'reviewed' => ['text' => 'Revisado', 'class' => 'info'],
    'actioned' => ['text' => 'Acción tomada', 'class' => 'success'],
    'dismissed' => ['text' => 'Descartado', 'class' => 'secondary']
];
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
                            <h1 class="m-0">Reseñas Reportadas</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/pages/reviews/">Reseñas</a></li>
                                <li class="breadcrumb-item active">Reportadas</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <!-- Estadísticas -->
                    <div class="row mb-4">
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
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['reviewed']); ?></h3>
                                    <p>Revisados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['actioned']); ?></h3>
                                    <p>Con Acción</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-secondary">
                                <div class="inner">
                                    <h3><?php echo number_format($stats['dismissed']); ?></h3>
                                    <p>Descartados</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Filtros -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label class="mr-2">Estado:</label>
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="all">Todos</option>
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                        <option value="reviewed" <?php echo $status === 'reviewed' ? 'selected' : ''; ?>>Revisados</option>
                                        <option value="actioned" <?php echo $status === 'actioned' ? 'selected' : ''; ?>>Con acción</option>
                                        <option value="dismissed" <?php echo $status === 'dismissed' ? 'selected' : ''; ?>>Descartados</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Lista de Reportes -->
                    <?php if (empty($reports)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-flag fa-3x text-muted mb-3"></i>
                                <h4>No hay reportes <?php echo $status !== 'all' ? "con estado '$status'" : ''; ?></h4>
                                <p class="text-muted">Los reportes de reseñas inapropiadas aparecerán aquí.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <div class="card mb-3 <?php echo $report['status'] === 'pending' ? 'border-warning' : ''; ?>">
                                <div class="card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0">
                                                Reporte #<?php echo $report['id']; ?>
                                                <span class="badge badge-<?php echo $statusLabels[$report['status']]['class']; ?> ml-2">
                                                    <?php echo $statusLabels[$report['status']]['text']; ?>
                                                </span>
                                            </h5>
                                            <small class="text-muted">
                                                Reportado <?php echo timeAgo($report['created_at']); ?>
                                                <?php if ($report['reporter_email']): ?>
                                                    por <?php echo htmlspecialchars($report['reporter_first_name'] . ' ' . $report['reporter_last_name']); ?>
                                                <?php else: ?>
                                                    por usuario anónimo (IP: <?php echo htmlspecialchars($report['reporter_ip']); ?>)
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-flag"></i> <?php echo $reasonLabels[$report['reason']] ?? $report['reason']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Detalles del reporte -->
                                    <?php if ($report['details']): ?>
                                        <div class="alert alert-light">
                                            <strong>Detalles del reporte:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($report['details'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Reseña reportada -->
                                    <div class="border rounded p-3 bg-light">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div>
                                                <strong>Reseña de:</strong> 
                                                <?php echo htmlspecialchars($report['reviewer_first_name'] . ' ' . $report['reviewer_last_name']); ?>
                                                (<?php echo htmlspecialchars($report['reviewer_email']); ?>)
                                            </div>
                                            <div>
                                                <strong>Producto:</strong> 
                                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $report['product_slug']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($report['product_name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <strong>Calificación:</strong>
                                            <span class="text-warning ml-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $report['rating']): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </span>
                                        </div>
                                        
                                        <div>
                                            <strong>Comentario:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($report['review_comment'])); ?>
                                        </div>
                                        
                                        <small class="text-muted d-block mt-2">
                                            Publicada <?php echo timeAgo($report['review_date']); ?>
                                        </small>
                                    </div>
                                    
                                    <!-- Notas del admin -->
                                    <?php if ($report['admin_notes']): ?>
                                        <div class="alert alert-info mt-3">
                                            <strong>Notas del administrador:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?>
                                            <?php if ($report['admin_name'] && $report['reviewed_at']): ?>
                                                <br><small class="text-muted">
                                                    - <?php echo htmlspecialchars($report['admin_name']); ?>, 
                                                    <?php echo formatDateTime($report['reviewed_at']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <?php if ($report['status'] === 'pending'): ?>
                                                <button class="btn btn-danger" onclick="takeAction(<?php echo $report['id']; ?>, <?php echo $report['review_id']; ?>)">
                                                    <i class="fas fa-ban"></i> Eliminar Reseña
                                                </button>
                                                <button class="btn btn-warning ml-2" onclick="markReviewed(<?php echo $report['id']; ?>)">
                                                    <i class="fas fa-eye"></i> Marcar como Revisado
                                                </button>
                                                <button class="btn btn-secondary ml-2" onclick="dismissReport(<?php echo $report['id']; ?>)">
                                                    <i class="fas fa-times"></i> Descartar Reporte
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="../reviews/moderate.php?id=<?php echo $report['review_id']; ?>" class="btn btn-info">
                                                <i class="fas fa-edit"></i> Ver Reseña
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Modal de Acción -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tomar Acción sobre el Reporte</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="actionForm">
                        <input type="hidden" id="reportId" name="report_id">
                        <input type="hidden" id="reviewId" name="review_id">
                        <input type="hidden" name="action" value="delete_review">
                        
                        <p class="text-danger">
                            <i class="fas fa-exclamation-triangle"></i>
                            ¿Estás seguro de que deseas eliminar esta reseña?
                        </p>
                        
                        <div class="form-group">
                            <label>Notas administrativas:</label>
                            <textarea class="form-control" name="admin_notes" rows="3" 
                                      placeholder="Razón de la acción tomada..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmAction()">
                        <i class="fas fa-ban"></i> Eliminar Reseña
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
    function takeAction(reportId, reviewId) {
        $('#reportId').val(reportId);
        $('#reviewId').val(reviewId);
        $('#actionModal').modal('show');
    }
    
    function confirmAction() {
        const form = document.getElementById('actionForm');
        const formData = new FormData(form);
        
        fetch('ajax/process_report.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                toastr.success('Acción completada exitosamente');
                $('#actionModal').modal('hide');
                setTimeout(() => location.reload(), 1500);
            } else {
                toastr.error(result.message || 'Error al procesar');
            }
        });
    }
    
    function markReviewed(reportId) {
        if (confirm('¿Marcar este reporte como revisado?')) {
            updateReportStatus(reportId, 'reviewed');
        }
    }
    
    function dismissReport(reportId) {
        if (confirm('¿Descartar este reporte?')) {
            updateReportStatus(reportId, 'dismissed');
        }
    }
    
    function updateReportStatus(reportId, status) {
        const formData = new FormData();
        formData.append('report_id', reportId);
        formData.append('status', status);
        
        fetch('ajax/update_report_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                toastr.success('Estado actualizado');
                setTimeout(() => location.reload(), 1000);
            } else {
                toastr.error(result.message || 'Error al actualizar');
            }
        });
    }
    </script>
</body>
</html>