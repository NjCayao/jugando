<?php
// pages/renew-license.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = getCurrentUser();
$productId = intval($_GET['product'] ?? 0);
$licenseId = intval($_GET['license'] ?? 0);

if (!Settings::get('allow_update_renewal', '1')) {
    setFlashMessage('error', 'Las renovaciones no están habilitadas en este momento');
    redirect('/mis-descargas');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener licencia
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.slug as product_slug, p.price as base_price,
               p.image as product_image, c.name as category_name
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ul.user_id = ? AND (ul.id = ? OR ul.product_id = ?)
        AND ul.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$user['id'], $licenseId, $productId]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('Licencia no encontrada');
    }
    
    // Calcular precios de renovación
    $discount = floatval(Settings::get('update_renewal_discount', '20'));
    $renewalOptions = [
        ['months' => 3, 'price' => $license['base_price'] * 0.25 * (1 - $discount/100), 'save' => 0],
        ['months' => 6, 'price' => $license['base_price'] * 0.45 * (1 - $discount/100), 'save' => 10],
        ['months' => 12, 'price' => $license['base_price'] * 0.8 * (1 - $discount/100), 'save' => 20],
        ['months' => 24, 'price' => $license['base_price'] * 1.5 * (1 - $discount/100), 'save' => 25]
    ];
    
    // Verificar estado actual
    $isExpired = false;
    $daysLeft = null;
    if ($license['update_expires_at']) {
        $expiryTime = strtotime($license['update_expires_at']);
        $daysLeft = ($expiryTime - time()) / 86400;
        $isExpired = $daysLeft < 0;
    }
    
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    redirect('/mis-descargas');
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renovar Licencia - <?php echo htmlspecialchars($siteName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/phase5.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <section class="hero-gradient py-5">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard" class="text-white-50">Dashboard</a></li>
                    <li class="breadcrumb-item active text-white">Renovar Licencia</li>
                </ol>
            </nav>
            
            <h1 class="display-4 fw-bold text-white mb-3">
                <i class="fas fa-sync-alt me-3"></i>Renovar Licencia de Actualizaciones
            </h1>
            <p class="lead text-white-50">
                Mantén tu software actualizado con las últimas mejoras y correcciones
            </p>
        </div>
    </section>
    
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <div class="renewal-card">
                    <div class="renewal-header">
                        <h2><?php echo htmlspecialchars($license['product_name']); ?></h2>
                        <p class="mb-0">Selecciona tu plan de renovación</p>
                    </div>
                    
                    <div class="renewal-body">
                        <?php if ($isExpired): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Tu licencia de actualizaciones expiró hace <?php echo abs(round($daysLeft)); ?> días
                            </div>
                        <?php elseif ($daysLeft !== null && $daysLeft > 0): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Tu licencia actual es válida por <?php echo round($daysLeft); ?> días más
                            </div>
                        <?php endif; ?>
                        
                        <form id="renewalForm" method="POST" action="<?php echo SITE_URL; ?>/api/updates/process_renewal.php">
                            <input type="hidden" name="license_id" value="<?php echo $license['id']; ?>">
                            <input type="hidden" name="selected_plan" id="selectedPlan" value="">
                            
                            <div class="renewal-options">
                                <?php foreach ($renewalOptions as $index => $option): ?>
                                    <div class="renewal-option" data-plan="<?php echo $option['months']; ?>" data-price="<?php echo $option['price']; ?>">
                                        <div class="period"><?php echo $option['months']; ?> meses</div>
                                        <div class="price"><?php echo formatPrice($option['price']); ?></div>
                                        <?php if ($option['save'] > 0): ?>
                                            <div class="discount">Ahorra <?php echo $option['save']; ?>%</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg" id="renewBtn" disabled>
                                    <i class="fas fa-shopping-cart me-2"></i>Proceder al Pago
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card shadow-soft">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="fas fa-gift text-primary me-2"></i>Beneficios de Renovar
                        </h5>
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Acceso a todas las actualizaciones
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Nuevas características y mejoras
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Correcciones de seguridad
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Soporte prioritario
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <?php echo $discount; ?>% de descuento exclusivo
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="card shadow-soft mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Información de Licencia</h5>
                        <table class="table table-sm">
                            <tr>
                                <td>Producto:</td>
                                <td><strong><?php echo htmlspecialchars($license['product_name']); ?></strong></td>
                            </tr>
                            <tr>
                                <td>Estado:</td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-danger">Expirada</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Activa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Expira:</td>
                                <td><?php echo $license['update_expires_at'] ? formatDate($license['update_expires_at']) : 'N/A'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const options = document.querySelectorAll('.renewal-option');
            const selectedPlanInput = document.getElementById('selectedPlan');
            const renewBtn = document.getElementById('renewBtn');
            
            options.forEach(option => {
                option.addEventListener('click', function() {
                    options.forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    const plan = this.getAttribute('data-plan');
                    const price = this.getAttribute('data-price');
                    
                    selectedPlanInput.value = plan;
                    renewBtn.disabled = false;
                    renewBtn.innerHTML = `<i class="fas fa-shopping-cart me-2"></i>Pagar ${formatPrice(price)}`;
                });
            });
        });
        
        function formatPrice(price) {
            return '$' + parseFloat(price).toFixed(2);
        }
    </script>
</body>
</html>