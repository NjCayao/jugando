<?php
// pages/purchases.php - Página de Mis Compras
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

// Parámetros de filtrado y paginación
$status = $_GET['estado'] ?? '';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Construir condiciones WHERE
    $whereConditions = ["o.user_id = ?"];
    $params = [$user['id']];
    
    if ($status && in_array($status, ['pending', 'completed', 'failed', 'refunded'])) {
        $whereConditions[] = "o.payment_status = ?";
        $params[] = $status;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Obtener órdenes del usuario
    $stmt = $db->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as item_count,
               (SELECT GROUP_CONCAT(oi.product_name SEPARATOR ', ') FROM order_items oi WHERE oi.order_id = o.id LIMIT 3) as product_names
        FROM orders o 
        WHERE $whereClause
        ORDER BY o.created_at DESC 
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Contar total de órdenes
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM orders o 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetch()['total'];
    $totalPages = ceil($totalOrders / $perPage);
    
    // Obtener estadísticas del usuario
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as total_spent,
            COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_orders
        FROM orders 
        WHERE user_id = ?
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en página de compras: " . $e->getMessage());
    $orders = [];
    $totalOrders = 0;
    $totalPages = 0;
    $stats = [
        'total_orders' => 0,
        'total_spent' => 0,
        'completed_orders' => 0,
        'pending_orders' => 0
    ];
}

// Función para obtener detalles de una orden
function getOrderDetails($orderId, $db) {
    try {
        $stmt = $db->prepare("
            SELECT oi.*, p.slug as product_slug, p.image as product_image
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Compras - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Historial de compras y descargas">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Hero Section Compras -->
    <div class="promotion-section py-5">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Mis Compras</li>
                </ol>
            </nav>
            
            <div class="row align-items-center mb-5">
                <div class="col-lg-8">
                    <div class="promo-card text-start">
                        <div class="d-flex align-items-center mb-3">
                            <div class="promo-icon me-4">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div>
                                <h1 class="promo-title mb-2">Mis Compras</h1>
                                <p class="promo-description mb-0">
                                    Gestiona tu historial de compras y accede a tus descargas de forma rápida y segura
                                </p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <a href="<?php echo SITE_URL; ?>/productos" class="btn-luxury">
                                <i class="fas fa-store me-2"></i>Explorar Productos
                            </a>
                            <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-outline-primary">
                                <i class="fas fa-chart-pie me-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="text-center">
                        <div class="promo-icon mx-auto mb-3" style="width: 120px; height: 120px; font-size: 3rem;">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h4 class="text-primary"><?php echo $stats['total_orders']; ?></h4>
                        <p class="text-muted">Órdenes Totales</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Grid Moderno -->
    <div class="container" style="margin-top: -3rem; position: relative; z-index: 2;">
        <div class="row g-4 mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="promo-card text-center h-100">
                    <div class="promo-icon mx-auto mb-3" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="promo-title"><?php echo $stats['completed_orders']; ?></h3>
                    <p class="promo-subtitle">Compras Exitosas</p>
                    <p class="promo-description">Productos listos para descargar</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="promo-card text-center h-100">
                    <div class="promo-icon mx-auto mb-3" style="background: linear-gradient(135deg, #007bff 0%, #6f42c1 100%);">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 class="promo-title"><?php echo formatPrice($stats['total_spent']); ?></h3>
                    <p class="promo-subtitle">Total Invertido</p>
                    <p class="promo-description">En productos de calidad</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="promo-card text-center h-100">
                    <div class="promo-icon mx-auto mb-3" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="promo-title"><?php echo $stats['pending_orders']; ?></h3>
                    <p class="promo-subtitle">Órdenes Pendientes</p>
                    <p class="promo-description">En proceso de pago</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="promo-card text-center h-100">
                    <div class="promo-icon mx-auto mb-3" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3 class="promo-title"><?php echo $stats['completed_orders']; ?></h3>
                    <p class="promo-subtitle">Descargas Disponibles</p>
                    <p class="promo-description">Acceso inmediato</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Mostrar mensajes -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Filtros Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="crystal-inner">
                    <div class="crystal-content p-4">
                        <h5 class="crystal-title-small mb-4">
                            <i class="fas fa-filter me-2"></i>Filtros
                        </h5>
                        <form method="GET">
                            <div class="mb-4">
                                <label class="form-label fw-bold">Estado de la Orden</label>
                                <select name="estado" class="form-select">
                                    <option value="">Todos los estados</option>
                                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>✅ Completado</option>
                                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>⏳ Pendiente</option>
                                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>❌ Fallido</option>
                                    <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>💰 Reembolsado</option>
                                </select>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="crystal-btn">
                                    <i class="fas fa-search me-2"></i>Buscar
                                </button>
                                <a href="<?php echo SITE_URL; ?>/mis-compras" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Limpiar
                                </a>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-info-circle fa-2x text-primary mb-2"></i>
                            </div>
                            <h6>¿Necesitas Ayuda?</h6>
                            <p class="small text-muted">Contacta nuestro soporte para resolver cualquier duda sobre tus compras</p>
                            <a href="<?php echo SITE_URL; ?>/contacto" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-headset me-1"></i>Soporte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Lista de Órdenes -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="crystal-title">📋 Historial de Compras</h4>
                    <small class="text-muted">
                        Mostrando <?php echo count($orders); ?> de <?php echo $totalOrders; ?> órdenes
                    </small>
                </div>
                
                <?php if (empty($orders)): ?>
                    <div class="crystal-inner">
                        <div class="crystal-content text-center py-5">
                            <div class="mb-4">
                                <i class="fas fa-shopping-cart fa-4x text-muted opacity-50"></i>
                            </div>
                            <h4 class="crystal-title-small">¡Aún no tienes compras!</h4>
                            <p class="crystal-description">
                                Explora nuestro catálogo y encuentra los productos perfectos para tus proyectos
                            </p>
                            <div class="mt-4">
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn-luxury me-3">
                                    <i class="fas fa-store me-2"></i>Explorar Catálogo
                                </a>
                                <a href="<?php echo SITE_URL; ?>/productos?tipo=free" class="btn btn-outline-success">
                                    <i class="fas fa-gift me-2"></i>Ver Gratuitos
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="orders-timeline">
                        <?php foreach ($orders as $index => $order): ?>
                            <div class="crystal-inner mb-4">
                                <div class="crystal-content">
                                    <!-- Header de la Orden -->
                                    <div class="row align-items-center mb-3">
                                        <div class="col-md-3">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <strong style="font-size: 0.8rem;">#<?php echo $index + 1; ?></strong>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($order['order_number']); ?></h6>
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar me-1"></i>
                                                        <?php echo formatDateTime($order['created_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <?php
                                            $statusConfig = match($order['payment_status']) {
                                                'completed' => ['class' => 'success', 'icon' => 'check-circle', 'text' => 'Completado'],
                                                'pending' => ['class' => 'warning', 'icon' => 'clock', 'text' => 'Pendiente'],
                                                'failed' => ['class' => 'danger', 'icon' => 'times-circle', 'text' => 'Fallido'],
                                                'refunded' => ['class' => 'info', 'icon' => 'undo', 'text' => 'Reembolsado'],
                                                default => ['class' => 'secondary', 'icon' => 'question', 'text' => 'Desconocido']
                                            };
                                            ?>
                                            <span class="badge bg-<?php echo $statusConfig['class']; ?> p-2">
                                                <i class="fas fa-<?php echo $statusConfig['icon']; ?> me-1"></i>
                                                <?php echo $statusConfig['text']; ?>
                                            </span>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="h5 mb-1 text-primary fw-bold"><?php echo formatPrice($order['total_amount']); ?></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-credit-card me-1"></i>
                                                    <?php echo ucfirst($order['payment_method']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="btn-group">
                                                <button class="btn btn-outline-primary btn-sm" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-eye me-1"></i>Ver
                                                </button>
                                                <?php if ($order['payment_status'] === 'completed'): ?>
                                                    <a href="<?php echo SITE_URL; ?>/download?order=<?php echo $order['id']; ?>" class="btn btn-success btn-sm">
                                                        <i class="fas fa-download me-1"></i>Descargar
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Productos Preview -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-box me-2 text-muted"></i>
                                            <span class="text-muted"><?php echo $order['item_count']; ?> producto(s):</span>
                                            <span class="ms-2 small"><?php echo htmlspecialchars(truncateText($order['product_names'], 60)); ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Detalles Expandibles -->
                                    <div id="order-details-<?php echo $order['id']; ?>" style="display: none;">
                                        <hr>
                                        <h6 class="mb-3">
                                            <i class="fas fa-list me-2"></i>Productos Comprados
                                        </h6>
                                        <div class="row g-3">
                                            <?php
                                            $orderItems = getOrderDetails($order['id'], $db);
                                            foreach ($orderItems as $item):
                                            ?>
                                                <div class="col-md-6">
                                                    <div class="crystal-inner h-100">
                                                        <div class="crystal-content p-3">
                                                            <div class="d-flex align-items-center">
                                                                <div class="me-3">
                                                                    <div class="rounded" style="width: 50px; height: 50px; background: var(--primary-light); display: flex; align-items: center; justify-content: center;">
                                                                        <?php if ($item['product_image']): ?>
                                                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['product_image']; ?>" 
                                                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                                                 class="rounded" style="width: 100%; height: 100%; object-fit: cover;">
                                                                        <?php else: ?>
                                                                            <i class="fas fa-cube text-primary"></i>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="flex-grow-1">
                                                                    <h6 class="mb-1">
                                                                        <?php if ($item['product_slug']): ?>
                                                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['product_slug']; ?>" class="text-decoration-none">
                                                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                                                        <?php endif; ?>
                                                                    </h6>
                                                                    <small class="text-muted">Cantidad: <?php echo $item['quantity']; ?></small>
                                                                    <div class="fw-bold text-primary"><?php echo formatPrice($item['price']); ?></div>
                                                                </div>
                                                                <?php if ($order['payment_status'] === 'completed' && $item['product_id']): ?>
                                                                    <a href="<?php echo SITE_URL; ?>/download?product=<?php echo $item['product_id']; ?>" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-download"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <?php if (!empty($order['payment_id'])): ?>
                                            <div class="mt-3 p-3 bg-light rounded">
                                                <small class="text-muted">
                                                    <i class="fas fa-receipt me-1"></i>
                                                    <strong>ID de Pago:</strong> <?php echo htmlspecialchars($order['payment_id']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Paginación -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-5">
                            <nav aria-label="Paginación de órdenes">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $page - 1])); ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $page + 1])); ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function toggleOrderDetails(orderId) {
            const details = document.getElementById('order-details-' + orderId);
            const button = event.target.closest('button');
            const icon = button.querySelector('i');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                button.innerHTML = '<i class="fas fa-eye-slash me-1"></i>Ocultar';
            } else {
                details.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                button.innerHTML = '<i class="fas fa-eye me-1"></i>Ver';
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Animaciones suaves al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.crystal-inner');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>