<?php
// admin/pages/licenses/view.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$licenseId = intval($_GET['id'] ?? 0);

if ($licenseId <= 0) {
    setFlashMessage('error', 'ID de licencia inválido');
    redirect('index.php');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información completa de la licencia
    $stmt = $db->prepare("
        SELECT ul.*, 
               u.email, u.first_name, u.last_name, u.phone, u.country, u.created_at as user_created,
               u.last_login, u.is_verified, u.is_active as user_active,
               p.name as product_name, p.slug as product_slug, p.price,
               c.name as category_name,
               o.order_number, o.total_amount, o.payment_method, o.created_at as purchase_date,
               (SELECT COUNT(*) FROM update_downloads ud WHERE ud.license_id = ul.id) as total_updates,
               (SELECT COUNT(*) FROM download_logs dl WHERE dl.license_id = ul.id) as total_downloads
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN orders o ON ul.order_id = o.id
        WHERE ul.id = ?
    ");
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('Licencia no encontrada');
    }
    
    // Historial de descargas
    $stmt = $db->prepare("
        SELECT dl.*, pv.version
        FROM download_logs dl
        LEFT JOIN product_versions pv ON dl.version_id = pv.id
        WHERE dl.license_id = ?
        ORDER BY dl.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$licenseId]);
    $downloads = $stmt->fetchAll();
    
    // Historial de actualizaciones
    $stmt = $db->prepare("
        SELECT ud.*, pv.version, pv.changelog
        FROM update_downloads ud
        INNER JOIN product_versions pv ON ud.version_id = pv.id
        WHERE ud.license_id = ?
        ORDER BY ud.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$licenseId]);
    $updates = $stmt->fetchAll();
    
    // Renovaciones
    $stmt = $db->prepare("
        SELECT lr.*, a.username as admin_name
        FROM license_renewals lr
        LEFT JOIN admins a ON lr.created_by = a.id
        WHERE lr.license_id = ?
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute([$licenseId]);
    $renewals = $stmt->fetchAll();
    
    // Notificaciones enviadas
    $stmt = $db->prepare("
        SELECT un.*, pv.version
        FROM update_notifications un
        LEFT JOIN product_versions pv ON un.version_id = pv.id
        WHERE un.user_id = ? AND un.product_id = ?
        ORDER BY un.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$license['user_id'], $license['product_id']]);
    $notifications = $stmt->fetchAll();
    
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de Licencia #<?php echo $licenseId; ?> | <?php echo getSetting('site_name', 'MiSistema'); ?></title>
    
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
                            <h1 class="m-0">Licencia #<?php echo $licenseId; ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Licencias</a></li>
                                <li class="breadcrumb-item active">Detalle</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <!-- Información Principal -->
                    <div class="row">
                        <div class="col-md-4">
                            <!-- Usuario -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Información del Usuario</h3>
                                </div>
                                <div class="card-body">
                                    <strong><i class="fas fa-user mr-1"></i> Nombre</strong>
                                    <p class="text-muted">
                                        <?php echo htmlspecialchars($license['first_name'] . ' ' . $license['last_name']); ?>
                                        <?php if ($license['user_verified']): ?>
                                            <i class="fas fa-check-circle text-success" title="Verificado"></i>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <strong><i class="fas fa-envelope mr-1"></i> Email</strong>
                                    <p class="text-muted"><?php echo htmlspecialchars($license['email']); ?></p>
                                    
                                    <?php if ($license['phone']): ?>
                                        <strong><i class="fas fa-phone mr-1"></i> Teléfono</strong>
                                        <p class="text-muted"><?php echo htmlspecialchars($license['phone']); ?></p>
                                    <?php endif; ?>
                                    
                                    <strong><i class="fas fa-globe mr-1"></i> País</strong>
                                    <p class="text-muted"><?php echo htmlspecialchars($license['country']); ?></p>
                                    
                                    <strong><i class="fas fa-calendar mr-1"></i> Registrado</strong>
                                    <p class="text-muted"><?php echo formatDateTime($license['user_created']); ?></p>
                                    
                                    <strong><i class="fas fa-sign-in-alt mr-1"></i> Último Login</strong>
                                    <p class="text-muted">
                                        <?php echo $license['last_login'] ? timeAgo($license['last_login']) : 'Nunca'; ?>
                                    </p>
                                    
                                    <a href="<?php echo ADMIN_URL; ?>/pages/users/view.php?id=<?php echo $license['user_id']; ?>" 
                                       class="btn btn-sm btn-primary btn-block">
                                        <i class="fas fa-eye"></i> Ver Usuario
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Producto -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Información del Producto</h3>
                                </div>
                                <div class="card-body">
                                    <strong><i class="fas fa-box mr-1"></i> Producto</strong>
                                    <p class="text-muted"><?php echo htmlspecialchars($license['product_name']); ?></p>
                                    
                                    <strong><i class="fas fa-tag mr-1"></i> Categoría</strong>
                                    <p class="text-muted"><?php echo htmlspecialchars($license['category_name'] ?: 'Sin categoría'); ?></p>
                                    
                                    <strong><i class="fas fa-dollar-sign mr-1"></i> Precio</strong>
                                    <p class="text-muted"><?php echo formatPrice($license['price']); ?></p>
                                    
                                    <?php if ($license['order_number']): ?>
                                        <strong><i class="fas fa-receipt mr-1"></i> Orden</strong>
                                        <p class="text-muted">#<?php echo htmlspecialchars($license['order_number']); ?></p>
                                        
                                        <strong><i class="fas fa-credit-card mr-1"></i> Método de Pago</strong>
                                        <p class="text-muted"><?php echo ucfirst($license['payment_method']); ?></p>
                                        
                                        <strong><i class="fas fa-calendar-check mr-1"></i> Fecha de Compra</strong>
                                        <p class="text-muted"><?php echo formatDateTime($license['purchase_date']); ?></p>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $license['product_slug']; ?>" 
                                       target="_blank" class="btn btn-sm btn-info btn-block">
                                        <i class="fas fa-external-link-alt"></i> Ver Producto
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Estado de Licencia -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Estado de Licencia</h3>
                                    <div class="card-tools">
                                        <a href="extend.php?id=<?php echo $licenseId; ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-clock"></i> Extender
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <strong><i class="fas fa-toggle-on mr-1"></i> Estado</strong>
                                    <p class="text-muted">
                                        <?php if ($license['is_active']): ?>
                                            <span class="badge badge-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactiva</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <strong><i class="fas fa-download mr-1"></i> Descargas</strong>
                                    <p class="text-muted">
                                        <?php echo $license['downloads_used']; ?> / <?php echo $license['download_limit']; ?>
                                        <div class="progress progress-sm">
                                            <?php 
                                            $downloadPercent = $license['download_limit'] > 0 
                                                ? ($license['downloads_used'] / $license['download_limit']) * 100 
                                                : 0;
                                            ?>
                                            <div class="progress-bar bg-primary" style="width: <?php echo min(100, $downloadPercent); ?>%"></div>
                                        </div>
                                    </p>
                                    
                                    <strong><i class="fas fa-calendar-alt mr-1"></i> Actualizaciones hasta</strong>
                                    <p class="text-muted">
                                        <?php if ($license['update_expires_at']): ?>
                                            <?php 
                                            $updateExpiry = strtotime($license['update_expires_at']);
                                            $daysLeft = ($updateExpiry - time()) / 86400;
                                            ?>
                                            <?php if ($daysLeft < 0): ?>
                                                <span class="text-danger">
                                                    Expirado hace <?php echo abs(round($daysLeft)); ?> días
                                                </span>
                                            <?php elseif ($daysLeft <= 30): ?>
                                                <span class="text-warning">
                                                    <?php echo formatDate($license['update_expires_at']); ?>
                                                    (<?php echo round($daysLeft); ?> días)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-success">
                                                    <?php echo formatDate($license['update_expires_at']); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin límite</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <strong><i class="fas fa-sync-alt mr-1"></i> Total Actualizaciones</strong>
                                    <p class="text-muted"><?php echo number_format($license['total_updates']); ?></p>
                                    
                                    <strong><i class="fas fa-download mr-1"></i> Total Descargas</strong>
                                    <p class="text-muted"><?php echo number_format($license['total_downloads']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="card card-primary card-tabs">
                        <div class="card-header p-0 pt-1">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="pill" href="#downloads" role="tab">
                                        Descargas (<?php echo count($downloads); ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="pill" href="#updates" role="tab">
                                        Actualizaciones (<?php echo count($updates); ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="pill" href="#renewals" role="tab">
                                        Renovaciones (<?php echo count($renewals); ?>)
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" data-toggle="pill" href="#notifications" role="tab">
                                        Notificaciones (<?php echo count($notifications); ?>)
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="card-body">
                            <div class="tab-content">
                                <!-- Descargas -->
                                <div class="tab-pane fade show active" id="downloads" role="tabpanel">
                                    <?php if (empty($downloads)): ?>
                                        <p class="text-muted">No hay descargas registradas</p>
                                    <?php else: ?>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Versión</th>
                                                    <th>Tipo</th>
                                                    <th>IP</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($downloads as $download): ?>
                                                    <tr>
                                                        <td><?php echo formatDateTime($download['created_at']); ?></td>
                                                        <td>
                                                            <?php if ($download['version']): ?>
                                                                v<?php echo htmlspecialchars($download['version']); ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge badge-info">
                                                                <?php echo ucfirst($download['download_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($download['ip_address']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- Actualizaciones -->
                                <div class="tab-pane fade" id="updates" role="tabpanel">
                                    <?php if (empty($updates)): ?>
                                        <p class="text-muted">No hay actualizaciones descargadas</p>
                                    <?php else: ?>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Versión</th>
                                                    <th>Estado</th>
                                                    <th>Detalles</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($updates as $update): ?>
                                                    <tr>
                                                        <td><?php echo formatDateTime($update['created_at']); ?></td>
                                                        <td>
                                                            v<?php echo htmlspecialchars($update['version']); ?>
                                                            <?php if ($update['previous_version']): ?>
                                                                <small class="text-muted">
                                                                    (desde v<?php echo htmlspecialchars($update['previous_version']); ?>)
                                                                </small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($update['download_status'] == 'completed'): ?>
                                                                <span class="badge badge-success">Completada</span>
                                                            <?php elseif ($update['download_status'] == 'failed'): ?>
                                                                <span class="badge badge-danger">Fallida</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-warning">En progreso</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($update['changelog']): ?>
                                                                <button class="btn btn-xs btn-info" 
                                                                        onclick="alert('<?php echo htmlspecialchars(addslashes($update['changelog'])); ?>')">
                                                                    <i class="fas fa-list"></i> Changelog
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if ($update['error_message']): ?>
                                                                <span class="text-danger">
                                                                    <?php echo htmlspecialchars($update['error_message']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- Renovaciones -->
                                <div class="tab-pane fade" id="renewals" role="tabpanel">
                                    <?php if (empty($renewals)): ?>
                                        <p class="text-muted">No hay renovaciones registradas</p>
                                    <?php else: ?>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Tipo</th>
                                                    <th>Meses</th>
                                                    <th>Nueva Expiración</th>
                                                    <th>Por</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($renewals as $renewal): ?>
                                                    <tr>
                                                        <td><?php echo formatDateTime($renewal['created_at']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $types = [
                                                                'purchase' => 'Compra',
                                                                'admin_manual' => 'Admin Manual',
                                                                'promotion' => 'Promoción'
                                                            ];
                                                            echo $types[$renewal['renewal_type']] ?? $renewal['renewal_type'];
                                                            ?>
                                                        </td>
                                                        <td><?php echo $renewal['months_added']; ?> meses</td>
                                                        <td><?php echo formatDate($renewal['new_expiry']); ?></td>
                                                        <td>
                                                            <?php echo $renewal['admin_name'] ?: 'Sistema'; ?>
                                                            <?php if ($renewal['notes']): ?>
                                                                <i class="fas fa-info-circle" 
                                                                   title="<?php echo htmlspecialchars($renewal['notes']); ?>"></i>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>

                                <!-- Notificaciones -->
                                <div class="tab-pane fade" id="notifications" role="tabpanel">
                                    <?php if (empty($notifications)): ?>
                                        <p class="text-muted">No hay notificaciones enviadas</p>
                                    <?php else: ?>
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Fecha</th>
                                                    <th>Tipo</th>
                                                    <th>Versión</th>
                                                    <th>Estado</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($notifications as $notif): ?>
                                                    <tr>
                                                        <td><?php echo formatDateTime($notif['created_at']); ?></td>
                                                        <td>
                                                            <?php 
                                                            $types = [
                                                                'new_version' => '<span class="badge badge-info">Nueva Versión</span>',
                                                                'update_expiring' => '<span class="badge badge-warning">Por Expirar</span>',
                                                                'update_expired' => '<span class="badge badge-danger">Expirada</span>'
                                                            ];
                                                            echo $types[$notif['notification_type']] ?? $notif['notification_type'];
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($notif['version']): ?>
                                                                v<?php echo htmlspecialchars($notif['version']); ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($notif['is_sent']): ?>
                                                                <i class="fas fa-check text-success"></i> Enviada
                                                            <?php else: ?>
                                                                <i class="fas fa-clock text-warning"></i> Pendiente
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
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
</body>
</html>