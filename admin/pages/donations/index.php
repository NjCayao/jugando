<?php
// admin/pages/donations/index.php - Lista de donaciones
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

// Paginación
$page = intval($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtros
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$search = sanitize($_GET['search'] ?? '');

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir consulta con filtros
    $where = ["1=1"];
    $params = [];
    
    if ($status) {
        $where[] = "d.payment_status = ?";
        $params[] = $status;
    }
    
    if ($dateFrom) {
        $where[] = "DATE(d.created_at) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = "DATE(d.created_at) <= ?";
        $params[] = $dateTo;
    }
    
    if ($search) {
        $where[] = "(d.donor_name LIKE ? OR d.donor_email LIKE ? OR d.transaction_id LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $whereClause = implode(" AND ", $where);
    
    // Obtener total de donaciones
    $stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM donations d 
        WHERE $whereClause
    ");
    $stmt->execute($params);
    $totalDonations = $stmt->fetch()['total'];
    $totalPages = ceil($totalDonations / $perPage);
    
    // Obtener donaciones
    $stmt = $db->prepare("
        SELECT d.*, p.name as product_name, p.slug as product_slug
        FROM donations d
        LEFT JOIN products p ON d.product_id = p.id
        WHERE $whereClause
        ORDER BY d.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $donations = $stmt->fetchAll();
    
    // Estadísticas
    $stats = [
        'total' => 0,
        'completed' => 0,
        'pending' => 0,
        'failed' => 0,
        'total_amount' => 0
    ];
    
    // Estadísticas generales
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_amount
        FROM donations
        WHERE $whereClause
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();
    
} catch (Exception $e) {
    logError("Error en admin donations: " . $e->getMessage());
    $donations = [];
    $totalPages = 0;
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donaciones | <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/daterangepicker/daterangepicker.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <!-- Navbar -->
    <?php include '../../includes/navbar.php'; ?>
    
    <!-- Sidebar -->
    <?php include '../../includes/sidebar.php'; ?>
    
    <!-- Content -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">
                            <i class="fas fa-coffee text-warning"></i> Donaciones
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                            <li class="breadcrumb-item active">Donaciones</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <!-- Estadísticas -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo number_format($stats['total']); ?></h3>
                                <p>Total Donaciones</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-heart"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3>$<?php echo number_format($stats['total_amount'], 2); ?></h3>
                                <p>Total Recaudado</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-dollar-sign"></i>
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
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3><?php echo number_format($stats['failed']); ?></h3>
                                <p>Fallidas</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-times-circle"></i>
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
                        <form method="get" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Estado</label>
                                        <select name="status" class="form-control">
                                            <option value="">Todos</option>
                                            <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completadas</option>
                                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Fallidas</option>
                                            <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>Reembolsadas</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Fecha Desde</label>
                                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Fecha Hasta</label>
                                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Buscar</label>
                                        <input type="text" name="search" class="form-control" 
                                               placeholder="Nombre, email o ID..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                    <a href="?" class="btn btn-default">
                                        <i class="fas fa-undo"></i> Limpiar
                                    </a>
                                    <a href="reports.php" class="btn btn-info float-right">
                                        <i class="fas fa-chart-bar"></i> Ver Reportes
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Lista de Donaciones -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Lista de Donaciones</h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Donante</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Estado</th>
                                    <th>Producto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($donations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No hay donaciones registradas</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($donations as $donation): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($donation['transaction_id']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('d/m/Y H:i', strtotime($donation['created_at'])); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($donation['donor_name'] ?: 'Anónimo'); ?></strong>
                                                <?php if ($donation['donor_email']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($donation['donor_email']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong>$<?php echo number_format($donation['amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php
                                                $methodIcons = [
                                                    'paypal' => '<i class="fab fa-paypal text-primary"></i>',
                                                    'mercadopago' => '<i class="fas fa-credit-card text-info"></i>',
                                                    'stripe' => '<i class="fab fa-stripe text-dark"></i>'
                                                ];
                                                echo $methodIcons[$donation['payment_method']] ?? '';
                                                echo ' ' . ucfirst($donation['payment_method']);
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadges = [
                                                    'completed' => '<span class="badge badge-success">Completada</span>',
                                                    'pending' => '<span class="badge badge-warning">Pendiente</span>',
                                                    'failed' => '<span class="badge badge-danger">Fallida</span>',
                                                    'refunded' => '<span class="badge badge-secondary">Reembolsada</span>'
                                                ];
                                                echo $statusBadges[$donation['payment_status']] ?? $donation['payment_status'];
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($donation['product_name']): ?>
                                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $donation['product_slug']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($donation['product_name']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $donation['id']; ?>" 
                                                   class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <div class="card-footer clearfix">
                            <ul class="pagination pagination-sm m-0 float-right">
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&search=<?php echo urlencode($search); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
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
</body>
</html>