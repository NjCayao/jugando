<?php
// admin/pages/donations/view.php - Ver detalle de donación
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$donationId = intval($_GET['id'] ?? 0);

if (!$donationId) {
    setFlashMessage('error', 'Donación no encontrada');
    redirect(ADMIN_URL . '/pages/donations/');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener donación
    $stmt = $db->prepare("
        SELECT d.*, p.name as product_name, p.slug as product_slug
        FROM donations d
        LEFT JOIN products p ON d.product_id = p.id
        WHERE d.id = ?
    ");
    $stmt->execute([$donationId]);
    $donation = $stmt->fetch();
    
    if (!$donation) {
        setFlashMessage('error', 'Donación no encontrada');
        redirect(ADMIN_URL . '/pages/donations/');
    }
    
    // Decodificar datos JSON
    $gatewayResponse = $donation['gateway_response'] ? json_decode($donation['gateway_response'], true) : null;
    $webhookData = $donation['webhook_data'] ? json_decode($donation['webhook_data'], true) : null;
    
} catch (Exception $e) {
    logError("Error obteniendo donación: " . $e->getMessage());
    setFlashMessage('error', 'Error al cargar la donación');
    redirect(ADMIN_URL . '/pages/donations/');
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de Donación | <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
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
                        <h1 class="m-0">Detalle de Donación</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/pages/donations/">Donaciones</a></li>
                            <li class="breadcrumb-item active">Detalle</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <!-- Información Principal -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-heart"></i> Información de la Donación
                                </h3>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">ID Transacción:</dt>
                                    <dd class="col-sm-8">
                                        <code><?php echo htmlspecialchars($donation['transaction_id']); ?></code>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Estado:</dt>
                                    <dd class="col-sm-8">
                                        <?php
                                        $statusBadges = [
                                            'completed' => '<span class="badge badge-success">Completada</span>',
                                            'pending' => '<span class="badge badge-warning">Pendiente</span>',
                                            'failed' => '<span class="badge badge-danger">Fallida</span>',
                                            'refunded' => '<span class="badge badge-secondary">Reembolsada</span>'
                                        ];
                                        echo $statusBadges[$donation['payment_status']] ?? $donation['payment_status'];
                                        ?>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Monto:</dt>
                                    <dd class="col-sm-8">
                                        <h4 class="text-success">$<?php echo number_format($donation['amount'], 2); ?> <?php echo $donation['currency']; ?></h4>
                                        <?php if ($donation['final_amount'] && $donation['final_amount'] != $donation['amount']): ?>
                                            <small class="text-muted">Monto final: $<?php echo number_format($donation['final_amount'], 2); ?></small>
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Método de Pago:</dt>
                                    <dd class="col-sm-8">
                                        <?php echo ucfirst($donation['payment_method']); ?>
                                        <?php if ($donation['external_id']): ?>
                                            <br><small class="text-muted">ID Externo: <?php echo htmlspecialchars($donation['external_id']); ?></small>
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Fecha:</dt>
                                    <dd class="col-sm-8">
                                        <?php echo date('d/m/Y H:i:s', strtotime($donation['created_at'])); ?>
                                        <?php if ($donation['completed_at']): ?>
                                            <br><small class="text-success">Completada: <?php echo date('d/m/Y H:i:s', strtotime($donation['completed_at'])); ?></small>
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Webhook:</dt>
                                    <dd class="col-sm-8">
                                        <?php if ($donation['webhook_received']): ?>
                                            <span class="badge badge-success">Recibido</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No recibido</span>
                                        <?php endif; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        
                        <!-- Información del Donante -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-user"></i> Información del Donante
                                </h3>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-sm-4">Nombre:</dt>
                                    <dd class="col-sm-8">
                                        <?php echo htmlspecialchars($donation['donor_name'] ?: 'Anónimo'); ?>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Email:</dt>
                                    <dd class="col-sm-8">
                                        <?php if ($donation['donor_email']): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($donation['donor_email']); ?>">
                                                <?php echo htmlspecialchars($donation['donor_email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No proporcionado</span>
                                        <?php endif; ?>
                                    </dd>
                                    
                                    <dt class="col-sm-4">IP:</dt>
                                    <dd class="col-sm-8">
                                        <?php echo htmlspecialchars($donation['ip_address'] ?: 'N/A'); ?>
                                    </dd>
                                    
                                    <?php if ($donation['donor_message']): ?>
                                        <dt class="col-sm-4">Mensaje:</dt>
                                        <dd class="col-sm-8">
                                            <blockquote class="quote-secondary">
                                                <p><?php echo nl2br(htmlspecialchars($donation['donor_message'])); ?></p>
                                            </blockquote>
                                        </dd>
                                    <?php endif; ?>
                                </dl>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Información Adicional -->
                    <div class="col-md-6">
                        <?php if ($donation['product_name']): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-box"></i> Producto Relacionado
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <p>
                                        <strong>Producto:</strong> 
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $donation['product_slug']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($donation['product_name']); ?>
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </p>
                                    <p class="text-muted">
                                        Esta donación está relacionada con el producto mencionado.
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Datos Técnicos -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-code"></i> Datos Técnicos
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if ($gatewayResponse): ?>
                                    <h6>Respuesta de la Pasarela:</h6>
                                    <pre class="border p-2" style="max-height: 200px; overflow-y: auto;">
<?php echo json_encode($gatewayResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>
                                    </pre>
                                <?php endif; ?>
                                
                                <?php if ($webhookData): ?>
                                    <h6 class="mt-3">Datos del Webhook:</h6>
                                    <pre class="border p-2" style="max-height: 200px; overflow-y: auto;">
<?php echo json_encode($webhookData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>
                                    </pre>
                                <?php endif; ?>
                                
                                <?php if ($donation['user_agent']): ?>
                                    <h6 class="mt-3">User Agent:</h6>
                                    <p class="text-muted small">
                                        <?php echo htmlspecialchars($donation['user_agent']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Acciones -->
                <div class="row">
                    <div class="col-12">
                        <a href="index.php" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </a>
                        
                        <?php if ($donation['payment_status'] === 'completed' && $donation['donor_email']): ?>
                            <button type="button" class="btn btn-info" onclick="resendThankYouEmail()">
                                <i class="fas fa-envelope"></i> Reenviar Email de Agradecimiento
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($donation['payment_status'] === 'pending'): ?>
                            <button type="button" class="btn btn-warning" onclick="checkPaymentStatus()">
                                <i class="fas fa-sync"></i> Verificar Estado
                            </button>
                        <?php endif; ?>
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
function resendThankYouEmail() {
    if (confirm('¿Reenviar email de agradecimiento al donante?')) {
        // Implementar lógica de reenvío
        alert('Función pendiente de implementación');
    }
}

function checkPaymentStatus() {
    if (confirm('¿Verificar estado del pago con la pasarela?')) {
        // Implementar verificación con la API correspondiente
        alert('Función pendiente de implementación');
    }
}
</script>
</body>
</html>