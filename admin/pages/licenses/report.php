<?php
// admin/pages/licenses/report.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$period = $_GET['period'] ?? 'month';
$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

try {
    $db = Database::getInstance()->getConnection();
    
    // Estadísticas generales
    $generalStats = $db->query("
        SELECT 
            COUNT(*) as total_licenses,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_licenses,
            COUNT(CASE WHEN update_expires_at < NOW() THEN 1 END) as expired_licenses,
            COUNT(CASE WHEN update_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
            AVG(downloads_used) as avg_downloads,
            MAX(downloads_used) as max_downloads
        FROM user_licenses
    ")->fetch();
    
    // Licencias por producto
    $productStats = $db->query("
        SELECT 
            p.name as product_name,
            COUNT(ul.id) as license_count,
            COUNT(CASE WHEN ul.is_active = 1 THEN 1 END) as active_count,
            COUNT(CASE WHEN ul.update_expires_at < NOW() THEN 1 END) as expired_count,
            AVG(ul.downloads_used) as avg_downloads,
            SUM(ul.downloads_used) as total_downloads,
            COUNT(DISTINCT ul.user_id) as unique_users
        FROM products p
        LEFT JOIN user_licenses ul ON p.id = ul.product_id
        GROUP BY p.id
        ORDER BY license_count DESC
    ")->fetchAll();
    
    // Renovaciones por período
    $renewalStats = [];
    if ($period === 'month') {
        $stmt = $db->prepare("
            SELECT 
                DAY(created_at) as day,
                COUNT(*) as renewals,
                SUM(amount_paid) as revenue
            FROM license_renewals
            WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
            GROUP BY DAY(created_at)
            ORDER BY day
        ");
        $stmt->execute([$year, $month]);
        $renewalStats = $stmt->fetchAll();
    } else {
        $stmt = $db->prepare("
            SELECT 
                MONTH(created_at) as month,
                COUNT(*) as renewals,
                SUM(amount_paid) as revenue
            FROM license_renewals
            WHERE YEAR(created_at) = ?
            GROUP BY MONTH(created_at)
            ORDER BY month
        ");
        $stmt->execute([$year]);
        $renewalStats = $stmt->fetchAll();
    }
    
    // Top usuarios por licencias
    $topUsers = $db->query("
        SELECT 
            u.email, u.first_name, u.last_name, u.country,
            COUNT(ul.id) as license_count,
            SUM(ul.downloads_used) as total_downloads,
            MIN(ul.created_at) as first_license,
            MAX(ul.created_at) as last_license
        FROM users u
        INNER JOIN user_licenses ul ON u.id = ul.user_id
        GROUP BY u.id
        ORDER BY license_count DESC
        LIMIT 10
    ")->fetchAll();
    
    // Estadísticas de expiración
    $expirationStats = $db->query("
        SELECT 
            CASE 
                WHEN update_expires_at < NOW() THEN 'Expiradas'
                WHEN update_expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY) THEN '0-7 días'
                WHEN update_expires_at < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN '8-30 días'
                WHEN update_expires_at < DATE_ADD(NOW(), INTERVAL 90 DAY) THEN '31-90 días'
                ELSE 'Más de 90 días'
            END as expiry_range,
            COUNT(*) as count
        FROM user_licenses
        WHERE is_active = 1
        GROUP BY expiry_range
        ORDER BY FIELD(expiry_range, 'Expiradas', '0-7 días', '8-30 días', '31-90 días', 'Más de 90 días')
    ")->fetchAll();
    
    // Descargas por mes (últimos 12 meses)
    $downloadTrend = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as download_count
        FROM update_downloads
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ")->fetchAll();
    
} catch (Exception $e) {
    logError("Error en reporte de licencias: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de Licencias | <?php echo getSetting('site_name', 'MiSistema'); ?></title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <?php include '../../includes/navbar.php'; ?>
        <?php include '../../includes/sidebar.php'; ?>

        <div class="content-wrapper">
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Reporte de Licencias</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Licencias</a></li>
                                <li class="breadcrumb-item active">Reportes</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <!-- Filtros -->
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row align-items-end">
                                <div class="col-md-3">
                                    <label>Período:</label>
                                    <select name="period" class="form-control" onchange="this.form.submit()">
                                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Mensual</option>
                                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Anual</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Año:</label>
                                    <select name="year" class="form-control" onchange="this.form.submit()">
                                        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <?php if ($period === 'month'): ?>
                                <div class="col-md-3">
                                    <label>Mes:</label>
                                    <select name="month" class="form-control" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-success" onclick="exportReport()">
                                        <i class="fas fa-download"></i> Exportar Excel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Estadísticas Generales -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h3><?php echo number_format($generalStats['total_licenses']); ?></h3>
                                    <p>Total Licencias</p>
                                </div>
                                <div class="icon"><i class="fas fa-key"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h3><?php echo number_format($generalStats['active_licenses']); ?></h3>
                                    <p>Licencias Activas</p>
                                </div>
                                <div class="icon"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h3><?php echo number_format($generalStats['expiring_soon']); ?></h3>
                                    <p>Expiran Pronto</p>
                                </div>
                                <div class="icon"><i class="fas fa-clock"></i></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h3><?php echo number_format($generalStats['expired_licenses']); ?></h3>
                                    <p>Expiradas</p>
                                </div>
                                <div class="icon"><i class="fas fa-times-circle"></i></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Gráfico de Renovaciones -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Renovaciones <?php echo $period === 'month' ? 'del Mes' : 'del Año'; ?></h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="renewalChart" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Expiración -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Estado de Expiración</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="expirationChart" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Licencias por Producto -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Licencias por Producto</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Total</th>
                                        <th>Activas</th>
                                        <th>Expiradas</th>
                                        <th>Usuarios Únicos</th>
                                        <th>Prom. Descargas</th>
                                        <th>Total Descargas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($productStats as $product): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td><span class="badge bg-primary"><?php echo $product['license_count'] ?? 0; ?></span></td>
                                            <td><span class="badge bg-success"><?php echo $product['active_count'] ?? 0; ?></span></td>
                                            <td><span class="badge bg-danger"><?php echo $product['expired_count'] ?? 0; ?></span></td>
                                            <td><?php echo $product['unique_users'] ?? 0; ?></td>
                                            <td><?php echo number_format($product['avg_downloads'] ?? 0, 1); ?></td>
                                            <td><?php echo number_format($product['total_downloads'] ?? 0); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Usuarios -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Top 10 Usuarios por Licencias</h3>
                                </div>
                                <div class="card-body table-responsive p-0">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>País</th>
                                                <th>Licencias</th>
                                                <th>Descargas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topUsers as $user): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                                    </td>
                                                    <td><?php echo $user['country']; ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $user['license_count']; ?></span></td>
                                                    <td><?php echo $user['total_downloads']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Tendencia de Descargas -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Tendencia de Descargas (12 meses)</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="downloadTrendChart" style="height: 250px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../../includes/footer.php'; ?>
    </div>

    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        // Gráfico de Renovaciones
        const renewalCtx = document.getElementById('renewalChart').getContext('2d');
        new Chart(renewalCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($renewalStats, $period === 'month' ? 'day' : 'month')); ?>,
                datasets: [{
                    label: 'Renovaciones',
                    data: <?php echo json_encode(array_column($renewalStats, 'renewals')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Gráfico de Expiración
        const expirationCtx = document.getElementById('expirationChart').getContext('2d');
        new Chart(expirationCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($expirationStats, 'expiry_range')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($expirationStats, 'count')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(255, 159, 64, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(54, 162, 235, 0.5)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Gráfico de Tendencia
        const trendCtx = document.getElementById('downloadTrendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($downloadTrend, 'month')); ?>,
                datasets: [{
                    label: 'Descargas',
                    data: <?php echo json_encode(array_column($downloadTrend, 'download_count')); ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        function exportReport() {
            window.location.href = 'export.php?format=excel&' + window.location.search.substring(1);
        }
    </script>
</body>
</html>