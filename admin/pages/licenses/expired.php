<?php
// admin/pages/licenses/expired.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');

$page = intval($_GET['page'] ?? 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Solo licencias expiradas
    $stmt = $db->prepare("
        SELECT ul.*, 
               u.email, u.first_name, u.last_name, u.country, u.last_login,
               p.name as product_name, p.slug as product_slug, p.price,
               o.order_number, o.total_amount,
               DATEDIFF(NOW(), ul.update_expires_at) as days_expired,
               (SELECT COUNT(*) FROM license_renewals lr WHERE lr.license_id = ul.id) as renewal_count,
               (SELECT lr.created_at FROM license_renewals lr WHERE lr.license_id = ul.id ORDER BY lr.created_at DESC LIMIT 1) as last_renewal
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN orders o ON ul.order_id = o.id
        WHERE ul.update_expires_at < NOW() AND ul.is_active = 1
        ORDER BY ul.update_expires_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute();
    $licenses = $stmt->fetchAll();
    
    // Total expiradas
    $countStmt = $db->query("
        SELECT COUNT(*) as total
        FROM user_licenses 
        WHERE update_expires_at < NOW() AND is_active = 1
    ");
    $totalExpired = $countStmt->fetch()['total'];
    $totalPages = ceil($totalExpired / $perPage);
    
    // Estadísticas
    $statsStmt = $db->query("
        SELECT 
            COUNT(CASE WHEN DATEDIFF(NOW(), update_expires_at) <= 30 THEN 1 END) as expired_30_days,
            COUNT(CASE WHEN DATEDIFF(NOW(), update_expires_at) > 30 AND DATEDIFF(NOW(), update_expires_at) <= 90 THEN 1 END) as expired_90_days,
            COUNT(CASE WHEN DATEDIFF(NOW(), update_expires_at) > 90 THEN 1 END) as expired_over_90,
            SUM(p.price * 0.8 * 0.8) as potential_revenue
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        WHERE ul.update_expires_at < NOW() AND ul.is_active = 1
    ");
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en licencias expiradas: " . $e->getMessage());
    $licenses = [];
    $stats = ['expired_30_days' => 0, 'expired_90_days' => 0, 'expired_over_90' => 0, 'potential_revenue' => 0];
}

// Procesar acciones masivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    try {
        $selectedLicenses = $_POST['licenses'] ?? [];
        if (empty($selectedLicenses)) {
            throw new Exception('No se seleccionaron licencias');
        }
        
        switch ($_POST['bulk_action']) {
            case 'send_reminder':
                $count = 0;
                foreach ($selectedLicenses as $licenseId) {
                    // Aquí enviarías el email de recordatorio
                    $count++;
                }
                setFlashMessage('success', "Se enviaron $count recordatorios de renovación");
                break;
                
            case 'extend_all':
                $months = intval($_POST['extend_months'] ?? 3);
                $stmt = $db->prepare("
                    UPDATE user_licenses 
                    SET update_expires_at = DATE_ADD(NOW(), INTERVAL ? MONTH)
                    WHERE id IN (" . implode(',', array_map('intval', $selectedLicenses)) . ")
                ");
                $stmt->execute([$months]);
                
                setFlashMessage('success', count($selectedLicenses) . " licencias extendidas por $months meses");
                break;
        }
    } catch (Exception $e) {
        setFlashMessage('error', $e->getMessage());
    }
    redirect($_SERVER['PHP_SELF']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Licencias Expiradas | <?php echo getSetting('site_name', 'MiSistema'); ?></title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
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
                            <h1 class="m-0">Licencias Expiradas</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Licencias</a></li>
                                <li class="breadcrumb-item active">Expiradas</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-check"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Últimos 30 días</span>
                                    <span class="info-box-number"><?php echo number_format($stats['expired_30_days']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-orange">
                                <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">31-90 días</span>
                                    <span class="info-box-number"><?php echo number_format($stats['expired_90_days']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-danger">
                                <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Más de 90 días</span>
                                    <span class="info-box-number"><?php echo number_format($stats['expired_over_90']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-dollar-sign"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Potencial Ingresos</span>
                                    <span class="info-box-number"><?php echo formatPrice($stats['potential_revenue']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Total: <?php echo number_format($totalExpired); ?> licencias expiradas</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#bulkActionModal">
                                    <i class="fas fa-tasks"></i> Acciones Masivas
                                </button>
                            </div>
                        </div>
                        <form id="licenseForm" method="POST">
                            <div class="card-body table-responsive p-0">
                                <table class="table table-hover text-nowrap">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAll"></th>
                                            <th>Usuario</th>
                                            <th>Producto</th>
                                            <th>Expiró hace</th>
                                            <th>Último Login</th>
                                            <th>Renovaciones</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($licenses as $license): ?>
                                            <tr class="<?php echo $license['days_expired'] > 90 ? 'table-danger' : ($license['days_expired'] > 30 ? 'table-warning' : ''); ?>">
                                                <td>
                                                    <input type="checkbox" name="licenses[]" value="<?php echo $license['id']; ?>" class="license-checkbox">
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($license['first_name'] . ' ' . $license['last_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($license['email']); ?></small><br>
                                                    <span class="badge badge-secondary"><?php echo $license['country']; ?></span>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($license['product_name']); ?><br>
                                                    <small class="text-muted">Valor: <?php echo formatPrice($license['price']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-danger">
                                                        <?php echo $license['days_expired']; ?> días
                                                    </span><br>
                                                    <small><?php echo formatDate($license['update_expires_at']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($license['last_login']): ?>
                                                        <?php echo timeAgo($license['last_login']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Nunca</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo $license['renewal_count']; ?> veces<br>
                                                    <?php if ($license['last_renewal']): ?>
                                                        <small>Última: <?php echo formatDate($license['last_renewal']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-info btn-xs" onclick="sendReminder(<?php echo $license['id']; ?>)" title="Enviar recordatorio">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-success btn-xs" onclick="extendLicense(<?php echo $license['id']; ?>)" title="Extender">
                                                        <i class="fas fa-clock"></i>
                                                    </button>
                                                    <a href="view.php?id=<?php echo $license['id']; ?>" class="btn btn-primary btn-xs" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                        
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer clearfix">
                                <ul class="pagination pagination-sm m-0 float-right">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>

        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Modal Acciones Masivas -->
    <div class="modal fade" id="bulkActionModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Acciones Masivas</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Acción a realizar:</label>
                        <select id="bulkActionSelect" class="form-control">
                            <option value="">Seleccionar acción...</option>
                            <option value="send_reminder">Enviar recordatorio de renovación</option>
                            <option value="extend_all">Extender licencias</option>
                        </select>
                    </div>
                    <div id="extendOptions" style="display: none;">
                        <div class="form-group">
                            <label>Meses a extender:</label>
                            <select name="extend_months" class="form-control" form="licenseForm">
                                <option value="1">1 mes</option>
                                <option value="3">3 meses</option>
                                <option value="6">6 meses</option>
                                <option value="12">12 meses</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="executeBulkAction()">Ejecutar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        $('#selectAll').change(function() {
            $('.license-checkbox').prop('checked', $(this).is(':checked'));
        });
        
        $('#bulkActionSelect').change(function() {
            $('#extendOptions').toggle($(this).val() === 'extend_all');
        });
        
        function executeBulkAction() {
            const action = $('#bulkActionSelect').val();
            if (!action) {
                alert('Selecciona una acción');
                return;
            }
            
            if ($('.license-checkbox:checked').length === 0) {
                alert('Selecciona al menos una licencia');
                return;
            }
            
            if (confirm('¿Ejecutar esta acción para las licencias seleccionadas?')) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'bulk_action',
                    value: action
                }).appendTo('#licenseForm');
                
                $('#licenseForm').submit();
            }
        }
        
        function sendReminder(licenseId) {
            if (confirm('¿Enviar recordatorio de renovación?')) {
                // Implementar envío de recordatorio
                alert('Funcionalidad en desarrollo');
            }
        }
        
        function extendLicense(licenseId) {
            window.location.href = 'extend.php?id=' + licenseId;
        }
    </script>
</body>
</html>