<?php
// admin/pages/donations/reports.php - Reportes de donaciones (CORREGIDO)
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

// Período de reporte
$period = $_GET['period'] ?? 'month';
$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('m'));

try {
    $db = Database::getInstance()->getConnection();
    
    // Estadísticas generales con valores por defecto
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_donations,
            SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_donations,
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) as total_amount,
            COALESCE(AVG(CASE WHEN payment_status = 'completed' THEN amount ELSE NULL END), 0) as avg_amount,
            COALESCE(MAX(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) as max_amount,
            COALESCE(MIN(CASE WHEN payment_status = 'completed' THEN amount ELSE NULL END), 0) as min_amount
        FROM donations
    ");
    $stmt->execute();
    $generalStats = $stmt->fetch();
    
    // Valores por defecto si no hay datos
    $generalStats['total_donations'] = $generalStats['total_donations'] ?? 0;
    $generalStats['completed_donations'] = $generalStats['completed_donations'] ?? 0;
    $generalStats['total_amount'] = $generalStats['total_amount'] ?? 0;
    $generalStats['avg_amount'] = $generalStats['avg_amount'] ?? 0;
    $generalStats['max_amount'] = $generalStats['max_amount'] ?? 0;
    $generalStats['min_amount'] = $generalStats['min_amount'] ?? 0;
    
    // Donaciones por período
    switch ($period) {
        case 'year':
            $stmt = $db->prepare("
                SELECT 
                    MONTH(created_at) as period,
                    COUNT(*) as count,
                    COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) as total
                FROM donations
                WHERE YEAR(created_at) = ?
                GROUP BY MONTH(created_at)
                ORDER BY period
            ");
            $stmt->execute([$year]);
            break;
            
        case 'month':
        default:
            $stmt = $db->prepare("
                SELECT 
                    DAY(created_at) as period,
                    COUNT(*) as count,
                    COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) as total
                FROM donations
                WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
                GROUP BY DAY(created_at)
                ORDER BY period
            ");
            $stmt->execute([$year, $month]);
            break;
    }
    $periodData = $stmt->fetchAll();
    
    // Si no hay datos, crear array vacío
    if (empty($periodData)) {
        $periodData = [];
    }
    
    // Top donantes
    $stmt = $db->prepare("
        SELECT 
            donor_name,
            donor_email,
            COUNT(*) as donation_count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM donations
        WHERE payment_status = 'completed' 
        AND donor_email IS NOT NULL 
        AND donor_email != ''
        GROUP BY donor_email, donor_name
        ORDER BY total_amount DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topDonors = $stmt->fetchAll();
    
    // Donaciones por método de pago
    $stmt = $db->prepare("
        SELECT 
            payment_method,
            COUNT(*) as count,
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) as total
        FROM donations
        GROUP BY payment_method
    ");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll();
    
    // Si no hay datos de métodos de pago, crear array vacío
    if (empty($paymentMethods)) {
        $paymentMethods = [];
    }
    
    // Productos con más donaciones
    $stmt = $db->prepare("
        SELECT 
            p.name as product_name,
            COUNT(d.id) as donation_count,
            COALESCE(SUM(CASE WHEN d.payment_status = 'completed' THEN d.amount ELSE 0 END), 0) as total_amount
        FROM donations d
        JOIN products p ON d.product_id = p.id
        WHERE d.product_id IS NOT NULL
        GROUP BY d.product_id, p.name
        ORDER BY total_amount DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topProducts = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error generando reportes: " . $e->getMessage());
    // Valores por defecto en caso de error
    $generalStats = [
        'total_donations' => 0,
        'completed_donations' => 0,
        'total_amount' => 0,
        'avg_amount' => 0,
        'max_amount' => 0,
        'min_amount' => 0
    ];
    $periodData = [];
    $topDonors = [];
    $paymentMethods = [];
    $topProducts = [];
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reportes de Donaciones | <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <i class="fas fa-chart-bar text-primary"></i> Reportes de Donaciones
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="index.php">Donaciones</a></li>
                            <li class="breadcrumb-item active">Reportes</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <!-- Filtros de Período -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Período del Reporte</h3>
                    </div>
                    <div class="card-body">
                        <form method="get" class="form-inline">
                            <div class="form-group mr-3">
                                <label class="mr-2">Tipo:</label>
                                <select name="period" class="form-control" onchange="this.form.submit()">
                                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Mensual</option>
                                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Anual</option>
                                </select>
                            </div>
                            
                            <div class="form-group mr-3">
                                <label class="mr-2">Año:</label>
                                <select name="year" class="form-control" onchange="this.form.submit()">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                            <?php echo $y; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <?php if ($period === 'month'): ?>
                                <div class="form-group mr-3">
                                    <label class="mr-2">Mes:</label>
                                    <select name="month" class="form-control" onchange="this.form.submit()">
                                        <?php 
                                        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                                                  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                                        foreach ($months as $i => $monthName): 
                                        ?>
                                            <option value="<?php echo $i + 1; ?>" <?php echo ($i + 1) == $month ? 'selected' : ''; ?>>
                                                <?php echo $monthName; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-success ml-3" onclick="exportReport()">
                                <i class="fas fa-download"></i> Exportar Excel
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Estadísticas Generales -->
                <div class="row">
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-heart"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Donaciones</span>
                                <span class="info-box-number"><?php echo number_format($generalStats['total_donations']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-dollar-sign"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Recaudado</span>
                                <span class="info-box-number">$<?php echo number_format($generalStats['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-chart-line"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Promedio</span>
                                <span class="info-box-number">$<?php echo number_format($generalStats['avg_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-danger"><i class="fas fa-trophy"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Mayor Donación</span>
                                <span class="info-box-number">$<?php echo number_format($generalStats['max_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Gráficos -->
                <div class="row">
                    <!-- Donaciones por Período -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Donaciones por <?php echo $period === 'year' ? 'Mes' : 'Día'; ?></h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($periodData)): ?>
                                    <p class="text-center text-muted">No hay datos para mostrar en este período</p>
                                <?php else: ?>
                                    <canvas id="donationsChart" style="height: 300px;"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Métodos de Pago -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Métodos de Pago</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($paymentMethods)): ?>
                                    <p class="text-center text-muted">No hay datos para mostrar</p>
                                <?php else: ?>
                                    <canvas id="paymentMethodsChart" style="height: 300px;"></canvas>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tablas -->
                <div class="row">
                    <!-- Top Donantes -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-users"></i> Top 10 Donantes
                                </h3>
                            </div>
                            <div class="card-body table-responsive p-0">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Donante</th>
                                            <th>Donaciones</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($topDonors)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No hay donantes registrados</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($topDonors as $i => $donor): ?>
                                                <tr>
                                                    <td><?php echo $i + 1; ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($donor['donor_name'] ?: 'Anónimo'); ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($donor['donor_email']); ?></small>
                                                    </td>
                                                    <td><span class="badge badge-info"><?php echo $donor['donation_count']; ?></span></td>
                                                    <td><strong>$<?php echo number_format($donor['total_amount'], 2); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Productos Populares -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-box"></i> Productos con Más Donaciones
                                </h3>
                            </div>
                            <div class="card-body table-responsive p-0">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Donaciones</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($topProducts)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No hay productos con donaciones</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($topProducts as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                    <td><span class="badge badge-primary"><?php echo $product['donation_count']; ?></span></td>
                                                    <td><strong>$<?php echo number_format($product['total_amount'], 2); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
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

<script>
// Solo crear gráficos si hay datos
<?php if (!empty($periodData)): ?>
// Gráfico de donaciones por período
const periodData = <?php echo json_encode($periodData); ?>;
const labels = periodData.map(item => 
    <?php echo $period === 'year' ? "'Mes ' + item.period" : "'Día ' + item.period"; ?>
);
const amounts = periodData.map(item => item.total);

const ctx1 = document.getElementById('donationsChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Monto ($)',
            data: amounts,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toFixed(2);
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($paymentMethods)): ?>
// Gráfico de métodos de pago
const paymentData = <?php echo json_encode($paymentMethods); ?>;
const ctx2 = document.getElementById('paymentMethodsChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: paymentData.map(item => item.payment_method.toUpperCase()),
        datasets: [{
            data: paymentData.map(item => item.total),
            backgroundColor: ['#4BC0C0', '#FF6384', '#FFCD56']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
<?php endif; ?>

function exportReport() {
    alert('Función de exportación pendiente de implementar');
    // Aquí implementarías la lógica para generar y descargar el Excel
}
</script>
</body>
</html>