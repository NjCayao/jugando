<?php
// admin/pages/licenses/index.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación admin
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');

// Parámetros de búsqueda y filtrado
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$product = intval($_GET['product'] ?? 0);
$expiring = $_GET['expiring'] ?? '';
$page = intval($_GET['page'] ?? 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir query
    $whereConditions = [];
    $params = [];
    
    if ($search) {
        $whereConditions[] = "(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR o.order_number LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($status === 'active') {
        $whereConditions[] = "ul.is_active = 1";
    } elseif ($status === 'inactive') {
        $whereConditions[] = "ul.is_active = 0";
    }
    
    if ($product > 0) {
        $whereConditions[] = "ul.product_id = ?";
        $params[] = $product;
    }
    
    if ($expiring === '30days') {
        $whereConditions[] = "ul.update_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
    } elseif ($expiring === 'expired') {
        $whereConditions[] = "ul.update_expires_at < NOW()";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Obtener licencias
    $stmt = $db->prepare("
        SELECT ul.*, 
               u.email, u.first_name, u.last_name, u.country,
               p.name as product_name, p.slug as product_slug,
               o.order_number, o.total_amount, o.created_at as purchase_date,
               (SELECT COUNT(*) FROM update_downloads ud WHERE ud.license_id = ul.id) as update_count
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN orders o ON ul.order_id = o.id
        $whereClause
        ORDER BY ul.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $licenses = $stmt->fetchAll();
    
    // Contar total
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN orders o ON ul.order_id = o.id
        $whereClause
    ");
    $countStmt->execute($params);
    $totalLicenses = $countStmt->fetch()['total'];
    $totalPages = ceil($totalLicenses / $perPage);
    
    // Obtener productos para filtro
    $productsStmt = $db->query("
        SELECT p.id, p.name, COUNT(ul.id) as license_count
        FROM products p
        LEFT JOIN user_licenses ul ON p.id = ul.product_id
        GROUP BY p.id
        ORDER BY p.name
    ");
    $products = $productsStmt->fetchAll();
    
    // Estadísticas
    $statsStmt = $db->query("
        SELECT 
            COUNT(*) as total_licenses,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_licenses,
            COUNT(CASE WHEN update_expires_at < NOW() THEN 1 END) as expired_licenses,
            COUNT(CASE WHEN update_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
            SUM(downloads_used) as total_downloads
        FROM user_licenses
    ");
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en gestión de licencias: " . $e->getMessage());
    $licenses = [];
    $products = [];
    $stats = ['total_licenses' => 0, 'active_licenses' => 0, 'expired_licenses' => 0, 'expiring_soon' => 0];
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'extend':
                $licenseId = intval($_POST['license_id'] ?? 0);
                $months = intval($_POST['months'] ?? 12);
                
                if ($licenseId > 0 && $months > 0) {
                    // Obtener licencia actual
                    $stmt = $db->prepare("SELECT * FROM user_licenses WHERE id = ?");
                    $stmt->execute([$licenseId]);
                    $license = $stmt->fetch();
                    
                    if ($license) {
                        // Calcular nueva fecha
                        $currentExpiry = $license['update_expires_at'] ?? date('Y-m-d H:i:s');
                        if (strtotime($currentExpiry) < time()) {
                            $currentExpiry = date('Y-m-d H:i:s');
                        }
                        
                        $newExpiry = date('Y-m-d H:i:s', strtotime($currentExpiry . " + $months months"));
                        
                        // Actualizar licencia
                        $stmt = $db->prepare("
                            UPDATE user_licenses 
                            SET update_expires_at = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$newExpiry, $licenseId]);
                        
                        // Registrar renovación
                        $stmt = $db->prepare("
                            INSERT INTO license_renewals (
                                user_id, license_id, renewal_type, previous_expiry, 
                                new_expiry, months_added, notes, created_by
                            ) VALUES (?, ?, 'admin_manual', ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $license['user_id'],
                            $licenseId,
                            $license['update_expires_at'],
                            $newExpiry,
                            $months,
                            $_POST['notes'] ?? 'Extensión manual desde admin',
                            $_SESSION[ADMIN_SESSION_NAME]['id']
                        ]);
                        
                        setFlashMessage('success', 'Licencia extendida exitosamente');
                    }
                }
                break;
                
            case 'toggle_status':
                $licenseId = intval($_POST['license_id'] ?? 0);
                if ($licenseId > 0) {
                    $stmt = $db->prepare("
                        UPDATE user_licenses 
                        SET is_active = NOT is_active, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$licenseId]);
                    setFlashMessage('success', 'Estado de licencia actualizado');
                }
                break;
        }
    } catch (Exception $e) {
        setFlashMessage('error', 'Error al procesar acción: ' . $e->getMessage());
    }
    
    redirect($_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Licencias | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    
    <style>
        .license-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .license-active { background: #d4edda; color: #155724; }
        .license-inactive { background: #f8d7da; color: #721c24; }
        .license-expiring { background: #fff3cd; color: #856404; }
        .license-expired { background: #f8d7da; color: #721c24; }
        
        .stat-box {
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            color: white;
            transition: transform 0.2s;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
        }
        
        .action-buttons .btn {
            margin: 0 2px;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
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
                            <h1 class="m-0">Gestión de Licencias</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item active">Licencias</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <!-- Mensajes -->
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
                            <div class="stat-box bg-info">
                                <h3><?php echo number_format($stats['total_licenses']); ?></h3>
                                <p>Total Licencias</p>
                                <i class="fas fa-key fa-2x opacity-50"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box bg-success">
                                <h3><?php echo number_format($stats['active_licenses']); ?></h3>
                                <p>Activas</p>
                                <i class="fas fa-check-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box bg-warning">
                                <h3><?php echo number_format($stats['expiring_soon']); ?></h3>
                                <p>Expiran Pronto</p>
                                <i class="fas fa-clock fa-2x opacity-50"></i>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box bg-danger">
                                <h3><?php echo number_format($stats['expired_licenses']); ?></h3>
                                <p>Expiradas</p>
                                <i class="fas fa-times-circle fa-2x opacity-50"></i>
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
                            <form method="GET" class="row">
                                <div class="col-md-3">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Buscar por email, nombre, orden..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-control">
                                        <option value="">Todos los estados</option>
                                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activas</option>
                                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivas</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="product" class="form-control">
                                        <option value="">Todos los productos</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>" 
                                                    <?php echo $product == $prod['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prod['name']); ?> 
                                                (<?php echo $prod['license_count']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="expiring" class="form-control">
                                        <option value="">Todas las fechas</option>
                                        <option value="30days" <?php echo $expiring === '30days' ? 'selected' : ''; ?>>Expiran en 30 días</option>
                                        <option value="expired" <?php echo $expiring === 'expired' ? 'selected' : ''; ?>>Expiradas</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tabla de licencias -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Licencias (<?php echo number_format($totalLicenses); ?>)</h3>
                            <div class="card-tools">
                                <a href="bulk-extend.php" class="btn btn-success btn-sm">
                                    <i class="fas fa-clock"></i> Extender Múltiples
                                </a>
                                <a href="report.php" class="btn btn-info btn-sm">
                                    <i class="fas fa-chart-bar"></i> Reportes
                                </a>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Producto</th>
                                        <th>Estado</th>
                                        <th>Descargas</th>
                                        <th>Actualizaciones hasta</th>
                                        <th>Compra</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($licenses as $license): ?>
                                        <tr>
                                            <td>#<?php echo $license['id']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($license['first_name'] . ' ' . $license['last_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($license['email']); ?></small>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $license['product_slug']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($license['product_name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($license['is_active']): ?>
                                                    <span class="license-badge license-active">Activa</span>
                                                <?php else: ?>
                                                    <span class="license-badge license-inactive">Inactiva</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $license['downloads_used']; ?>/<?php echo $license['download_limit']; ?>
                                                <div class="progress progress-xs mt-1">
                                                    <?php 
                                                    $downloadPercent = $license['download_limit'] > 0 
                                                        ? ($license['downloads_used'] / $license['download_limit']) * 100 
                                                        : 0;
                                                    ?>
                                                    <div class="progress-bar bg-primary" style="width: <?php echo min(100, $downloadPercent); ?>%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($license['update_expires_at']): ?>
                                                    <?php 
                                                    $updateExpiry = strtotime($license['update_expires_at']);
                                                    $daysLeft = ($updateExpiry - time()) / 86400;
                                                    ?>
                                                    
                                                    <?php if ($daysLeft < 0): ?>
                                                        <span class="license-badge license-expired">
                                                            Expirado hace <?php echo abs(round($daysLeft)); ?> días
                                                        </span>
                                                    <?php elseif ($daysLeft <= 30): ?>
                                                        <span class="license-badge license-expiring">
                                                            Expira en <?php echo round($daysLeft); ?> días
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-success">
                                                            <?php echo formatDate($license['update_expires_at']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin límite</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($license['order_number']): ?>
                                                    <small>
                                                        #<?php echo $license['order_number']; ?><br>
                                                        <?php echo formatDate($license['purchase_date']); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">Manual</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-buttons">
                                                <button class="btn btn-info btn-xs" onclick="viewLicense(<?php echo $license['id']; ?>)" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-xs" onclick="extendLicense(<?php echo $license['id']; ?>)" title="Extender">
                                                    <i class="fas fa-clock"></i>
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="license_id" value="<?php echo $license['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-xs" 
                                                            title="<?php echo $license['is_active'] ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="card-footer clearfix">
                                <ul class="pagination pagination-sm m-0 float-right">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                «
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                »
                                            </a>
                                        </li>
                                    <?php endif; ?>
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

    <!-- Modal Extender Licencia -->
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="extend">
                    <input type="hidden" name="license_id" id="extend_license_id">
                    
                    <div class="modal-header">
                        <h4 class="modal-title">Extender Licencia</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Meses a agregar:</label>
                            <select name="months" class="form-control" required>
                                <option value="1">1 mes</option>
                                <option value="3">3 meses</option>
                                <option value="6">6 meses</option>
                                <option value="12" selected>12 meses</option>
                                <option value="24">24 meses</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Notas (opcional):</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Razón de la extensión..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-clock"></i> Extender Licencia
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    
    <script>
        function extendLicense(licenseId) {
            $('#extend_license_id').val(licenseId);
            $('#extendModal').modal('show');
        }
        
        function viewLicense(licenseId) {
            window.location.href = 'view.php?id=' + licenseId;
        }
        
        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>