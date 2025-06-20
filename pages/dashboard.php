<?php
// pages/dashboard.php - Dashboard del cliente con URLs CORREGIDAS
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Verificar que el usuario está logueado
if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Obtener datos del usuario
$user = getCurrentUser();
if (!$user) {
    logoutUser();
    redirect('/login');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener estadísticas del usuario
    $stats = [
        'total_orders' => 0,
        'total_spent' => 0,
        'total_downloads' => 0,
        'active_licenses' => 0
    ];
    
    // Total de órdenes completadas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as spent
        FROM orders 
        WHERE user_id = ? AND payment_status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $orderStats = $stmt->fetch();
    $stats['total_orders'] = $orderStats['total'];
    $stats['total_spent'] = $orderStats['spent'];
    
    // Total de descargas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM download_logs 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $downloadStats = $stmt->fetch();
    $stats['total_downloads'] = $downloadStats['total'];
    
    // Licencias activas
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM user_licenses 
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$user['id']]);
    $licenseStats = $stmt->fetch();
    $stats['active_licenses'] = $licenseStats['total'];
    
    // Obtener últimas compras
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count
        FROM orders o 
        WHERE o.user_id = ? 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $recentOrders = $stmt->fetchAll();
    
    // Obtener productos con licencia activa
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.slug as product_slug, p.image as product_image,
               c.name as category_name,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count,
               (SELECT version FROM product_versions pv WHERE pv.product_id = p.id AND pv.is_current = 1) as current_version
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ul.user_id = ? AND ul.is_active = 1
        ORDER BY ul.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $userProducts = $stmt->fetchAll();
    
    // Obtener productos destacados/recomendados
    $stmt = $db->query("
        SELECT p.*, c.name as category_name,
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND (p.is_featured = 1 OR p.is_free = 1)
        ORDER BY p.is_featured DESC, p.download_count DESC
        LIMIT 6
    ");
    $recommendedProducts = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en dashboard: " . $e->getMessage());
    $recentOrders = [];
    $userProducts = [];
    $recommendedProducts = [];
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Dashboard - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Dashboard personal - Gestiona tus compras y descargas">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header principal del sitio -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Dashboard Header Compacto -->
    <div class="dashboard-header-compact">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="dashboard-welcome">
                        <div class="d-flex align-items-center">
                            <div class="dashboard-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h2 class="mb-1">¡Hola, <?php echo htmlspecialchars($user['first_name']); ?>! 👋</h2>
                                <p class="mb-1 opacity-90">Bienvenido a tu dashboard personal</p>
                                <small class="opacity-75">Miembro desde <?php echo formatDate($user['created_at'], 'F Y'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0">
                    <div class="d-flex justify-content-lg-end justify-content-center align-items-center" style="position: relative; z-index: 10;">
                        <a href="<?php echo SITE_URL; ?>/perfil" class="btn btn-light me-2" style="position: relative; z-index: 11;">
                            <i class="fas fa-user-edit me-1"></i>Perfil
                        </a>
                        <a href="<?php echo SITE_URL; ?>/logout" class="btn btn-outline-light" style="position: relative; z-index: 11;">
                            <i class="fas fa-sign-out-alt me-1"></i>Salir
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Mostrar mensajes -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas Compactas -->
        <div class="dashboard-stats">
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card-compact">
                        <div class="stats-icon-compact">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stats-number-compact"><?php echo $stats['total_orders']; ?></div>
                        <div class="stats-label-compact">Compras Realizadas</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card-compact">
                        <div class="stats-icon-compact">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="stats-number-compact"><?php echo formatPrice($stats['total_spent']); ?></div>
                        <div class="stats-label-compact">Total Invertido</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card-compact">
                        <div class="stats-icon-compact">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stats-number-compact"><?php echo $stats['total_downloads']; ?></div>
                        <div class="stats-label-compact">Descargas</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stats-card-compact">
                        <div class="stats-icon-compact">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="stats-number-compact"><?php echo $stats['active_licenses']; ?></div>
                        <div class="stats-label-compact">Licencias Activas</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Acciones Rápidas -->
        <div class="quick-actions-compact">
            <h5 class="mb-3">Acciones Rápidas</h5>
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <a href="<?php echo SITE_URL; ?>/productos" class="action-card-compact">
                        <div class="action-icon-compact">
                            <i class="fas fa-store"></i>
                        </div>
                        <h6 class="action-title-compact">Explorar Productos</h6>
                        <p class="action-description-compact">Descubre nuestro catálogo</p>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="<?php echo SITE_URL; ?>/mis-compras" class="action-card-compact">
                        <div class="action-icon-compact">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h6 class="action-title-compact">Mis Compras</h6>
                        <p class="action-description-compact">Historial de pedidos</p>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="<?php echo SITE_URL; ?>/mis-descargas" class="action-card-compact">
                        <div class="action-icon-compact">
                            <i class="fas fa-cloud-download-alt"></i>
                        </div>
                        <h6 class="action-title-compact">Mis Descargas</h6>
                        <p class="action-description-compact">Archivos descargados</p>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="<?php echo SITE_URL; ?>/contacto" class="action-card-compact">
                        <div class="action-icon-compact">
                            <i class="fas fa-life-ring"></i>
                        </div>
                        <h6 class="action-title-compact">Soporte</h6>
                        <p class="action-description-compact">¿Necesitas ayuda?</p>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Mis Productos -->
            <div class="col-lg-8">
                <div class="dashboard-section">
                    <div class="section-header-compact">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="section-title-compact">
                                <div class="section-icon-compact">
                                    <i class="fas fa-box"></i>
                                </div>
                                Mis Productos
                            </h4>
                            <a href="<?php echo SITE_URL; ?>/mis-compras" class="btn btn-corporate btn-sm">Ver Todos</a>
                        </div>
                    </div>
                    <div class="section-body-compact">
                        <?php if (empty($userProducts)): ?>
                            <div class="empty-state-compact">
                                <div class="empty-icon-compact">
                                    <i class="fas fa-box-open"></i>
                                </div>
                                <h5>No tienes productos aún</h5>
                                <p>Explora nuestro catálogo y encuentra el software perfecto para ti</p>
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-corporate">
                                    <i class="fas fa-store me-2"></i>Explorar Productos
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($userProducts as $product): ?>
                                <div class="user-product-item">
                                    <div class="d-flex align-items-start">
                                        <div class="product-image-compact">
                                            <?php if ($product['product_image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['product_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <?php else: ?>
                                                <i class="fas fa-cube text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-info-compact">
                                            <div class="product-meta-compact">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['category_name']); ?>
                                            </div>
                                            <h5 class="product-title-compact">
                                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['product_slug']; ?>">
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </a>
                                            </h5>
                                            <div class="license-info-compact">
                                                <div class="row">
                                                    <div class="col-md-4">
                                                        <strong>Versión:</strong> <?php echo htmlspecialchars($product['current_version'] ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <strong>Descargas:</strong> <?php echo $product['downloads_used']; ?>/<?php echo $product['download_limit']; ?>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <strong>Updates:</strong> 
                                                        <?php if ($product['updates_until']): ?>
                                                            <?php echo formatDate($product['updates_until']); ?>
                                                        <?php else: ?>
                                                            Sin límite
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ms-auto">
                                             <a href="<?php echo SITE_URL; ?>/download/<?php echo $product['product_id']; ?>"
                                                class="btn btn-corporate">
                                                <i class="fas fa-download me-2"></i>Descargar
                                            </a>
                                            <div class="text-center mt-2">
                                                <small class="text-muted"><?php echo $product['version_count']; ?> versiones</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Últimas Compras -->
                <div class="sidebar-card-compact">
                    <div class="sidebar-header-compact">
                        <h5 class="section-title-compact">
                            <div class="section-icon-compact">
                                <i class="fas fa-receipt"></i>
                            </div>
                            Últimas Compras
                        </h5>
                    </div>
                    <div class="sidebar-body-compact">
                        <?php if (empty($recentOrders)): ?>
                            <div class="empty-state-compact">
                                <div class="empty-icon-compact">
                                    <i class="fas fa-receipt"></i>
                                </div>
                                <p>Sin compras aún</p>
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-corporate btn-sm">
                                    <i class="fas fa-store me-1"></i>Explorar
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <div class="order-item-compact">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <strong>#<?php echo $order['order_number']; ?></strong>
                                            <div>
                                                <?php 
                                                $statusClass = '';
                                                switch($order['payment_status']) {
                                                    case 'completed': $statusClass = 'badge bg-success'; break;
                                                    case 'pending': $statusClass = 'badge bg-warning'; break;
                                                    case 'failed': $statusClass = 'badge bg-danger'; break;
                                                    default: $statusClass = 'badge bg-secondary';
                                                }
                                                ?>
                                                <span class="<?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold text-primary"><?php echo formatPrice($order['total_amount']); ?></div>
                                            <small class="text-muted"><?php echo $order['item_count']; ?> productos</small>
                                        </div>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($order['created_at']); ?></small>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="<?php echo SITE_URL; ?>/mis-compras" class="btn btn-outline-primary btn-sm">Ver Todas</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Productos Recomendados -->
                <div class="sidebar-card-compact">
                    <div class="sidebar-header-compact">
                        <h5 class="section-title-compact">
                            <div class="section-icon-compact">
                                <i class="fas fa-star"></i>
                            </div>
                            Recomendados
                        </h5>
                    </div>
                    <div class="sidebar-body-compact">
                        <?php if (empty($recommendedProducts)): ?>
                            <div class="empty-state-compact">
                                <div class="empty-icon-compact">
                                    <i class="fas fa-star"></i>
                                </div>
                                <p>Cargando recomendaciones...</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($recommendedProducts, 0, 3) as $product): ?>
                                <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="recommended-item-compact">
                                    <div class="recommended-image-compact">
                                        <?php if ($product['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-cube text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($product['category_name']); ?></small>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <div>
                                            <?php if ($product['is_free']): ?>
                                                <span class="badge bg-success">GRATIS</span>
                                            <?php else: ?>
                                                <strong class="text-primary"><?php echo formatPrice($product['price']); ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                            <div class="text-center">
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary btn-sm">Ver Más</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animar las estadísticas al cargar
            const statsNumbers = document.querySelectorAll('.stat-number');
            statsNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, ''));
                if (finalValue > 0) {
                    animateCounter(stat, finalValue);
                }
            });
            
            // Auto-dismiss alerts después de 5 segundos
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 20;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                
                // Mantener formato si es precio
                if (element.textContent.includes('$')) {
                    element.textContent = '$' + Math.floor(current).toLocaleString();
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 50);
        }
    </script>
</body>
</html>