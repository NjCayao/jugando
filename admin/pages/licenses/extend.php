<?php
// admin/pages/licenses/extend.php
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
    
    // Obtener licencia
    $stmt = $db->prepare("
        SELECT ul.*, 
               u.email, u.first_name, u.last_name,
               p.name as product_name,
               o.order_number, o.created_at as purchase_date
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN orders o ON ul.order_id = o.id
        WHERE ul.id = ?
    ");
    $stmt->execute([$licenseId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('Licencia no encontrada');
    }
    
    // Obtener historial de renovaciones
    $stmt = $db->prepare("
        SELECT lr.*, a.username as created_by_name
        FROM license_renewals lr
        LEFT JOIN admins a ON lr.created_by = a.id
        WHERE lr.license_id = ?
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute([$licenseId]);
    $renewals = $stmt->fetchAll();
    
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    redirect('index.php');
}

// Procesar extensión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $months = intval($_POST['months'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        
        if ($months <= 0) {
            throw new Exception('Número de meses inválido');
        }
        
        // Calcular nueva fecha
        $currentExpiry = $license['update_expires_at'];
        if (!$currentExpiry || strtotime($currentExpiry) < time()) {
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
            $reason,
            $_SESSION[ADMIN_SESSION_NAME]['id']
        ]);
        
        setFlashMessage('success', 'Licencia extendida exitosamente por ' . $months . ' meses');
        redirect('view.php?id=' . $licenseId);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Extender Licencia | <?php echo getSetting('site_name', 'MiSistema'); ?></title>
    
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
                            <h1 class="m-0">Extender Licencia #<?php echo $licenseId; ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="index.php">Licencias</a></li>
                                <li class="breadcrumb-item active">Extender</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="content">
                <div class="container-fluid">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6">
                            <!-- Información de Licencia -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Información de Licencia</h3>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <tr>
                                            <th width="40%">Usuario:</th>
                                            <td>
                                                <?php echo htmlspecialchars($license['first_name'] . ' ' . $license['last_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($license['email']); ?></small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Producto:</th>
                                            <td><?php echo htmlspecialchars($license['product_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Estado:</th>
                                            <td>
                                                <?php if ($license['is_active']): ?>
                                                    <span class="badge badge-success">Activa</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactiva</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Actualizaciones hasta:</th>
                                            <td>
                                                <?php if ($license['update_expires_at']): ?>
                                                    <?php 
                                                    $isExpired = strtotime($license['update_expires_at']) < time();
                                                    ?>
                                                    <span class="<?php echo $isExpired ? 'text-danger' : 'text-success'; ?>">
                                                        <?php echo formatDate($license['update_expires_at']); ?>
                                                        <?php if ($isExpired): ?>
                                                            (Expirada)
                                                        <?php endif; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin límite</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Descargas:</th>
                                            <td><?php echo $license['downloads_used']; ?> / <?php echo $license['download_limit']; ?></td>
                                        </tr>
                                        <?php if ($license['order_number']): ?>
                                        <tr>
                                            <th>Orden de compra:</th>
                                            <td>#<?php echo $license['order_number']; ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>

                            <!-- Formulario de Extensión -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Extender Licencia</h3>
                                </div>
                                <form method="POST">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Tiempo a agregar:</label>
                                            <select name="months" class="form-control" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="1">1 mes</option>
                                                <option value="3">3 meses</option>
                                                <option value="6">6 meses</option>
                                                <option value="12">12 meses (1 año)</option>
                                                <option value="24">24 meses (2 años)</option>
                                                <option value="36">36 meses (3 años)</option>
                                                <option value="999">Permanente (999 meses)</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label>Razón/Notas:</label>
                                            <textarea name="reason" class="form-control" rows="3" required
                                                      placeholder="Ej: Cortesía por problemas técnicos, Cliente VIP, etc."></textarea>
                                        </div>

                                        <div class="alert alert-info">
                                            <i class="icon fas fa-info"></i>
                                            <?php if ($license['update_expires_at'] && strtotime($license['update_expires_at']) > time()): ?>
                                                El tiempo se agregará a la fecha actual de expiración.
                                            <?php else: ?>
                                                El tiempo se agregará desde la fecha actual.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-clock"></i> Extender Licencia
                                        </button>
                                        <a href="index.php" class="btn btn-default">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <!-- Historial de Renovaciones -->
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Historial de Renovaciones</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($renewals)): ?>
                                        <p class="text-muted">No hay renovaciones previas</p>
                                    <?php else: ?>
                                        <div class="timeline">
                                            <?php foreach ($renewals as $renewal): ?>
                                                <div>
                                                    <i class="fas fa-clock bg-blue"></i>
                                                    <div class="timeline-item">
                                                        <span class="time">
                                                            <i class="fas fa-clock"></i> 
                                                            <?php echo formatDateTime($renewal['created_at']); ?>
                                                        </span>
                                                        <h3 class="timeline-header">
                                                            <?php 
                                                            switch($renewal['renewal_type']) {
                                                                case 'purchase':
                                                                    echo 'Renovación por compra';
                                                                    break;
                                                                case 'admin_manual':
                                                                    echo 'Extensión manual por admin';
                                                                    break;
                                                                case 'promotion':
                                                                    echo 'Promoción';
                                                                    break;
                                                                default:
                                                                    echo ucfirst($renewal['renewal_type']);
                                                            }
                                                            ?>
                                                        </h3>
                                                        <div class="timeline-body">
                                                            <p>
                                                                <strong>Tiempo agregado:</strong> <?php echo $renewal['months_added']; ?> meses<br>
                                                                <strong>Nueva expiración:</strong> <?php echo formatDate($renewal['new_expiry']); ?><br>
                                                                <?php if ($renewal['amount_paid'] > 0): ?>
                                                                    <strong>Monto pagado:</strong> <?php echo formatPrice($renewal['amount_paid']); ?><br>
                                                                <?php endif; ?>
                                                                <?php if ($renewal['notes']): ?>
                                                                    <strong>Notas:</strong> <?php echo htmlspecialchars($renewal['notes']); ?><br>
                                                                <?php endif; ?>
                                                                <?php if ($renewal['created_by_name']): ?>
                                                                    <strong>Por:</strong> <?php echo htmlspecialchars($renewal['created_by_name']); ?>
                                                                <?php endif; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
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