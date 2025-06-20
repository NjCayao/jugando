<?php
// pages/downloads.php - Página de Mis Descargas
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
$category = $_GET['categoria'] ?? '';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener productos con licencias activas del usuario
    $whereConditions = ["ul.user_id = ?", "ul.is_active = 1"];
    $params = [$user['id']];
    
    if ($category) {
        $whereConditions[] = "c.slug = ?";
        $params[] = $category;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.slug as product_slug, p.image as product_image,
            p.short_description, p.is_free, p.demo_url,
            c.name as category_name, c.slug as category_slug,
            o.order_number, o.created_at as purchase_date,
            (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count,
            (SELECT version FROM product_versions pv WHERE pv.product_id = p.id AND pv.is_current = 1) as current_version,
            (SELECT COUNT(*) FROM download_logs dl WHERE dl.user_id = ul.user_id AND dl.product_id = ul.product_id) as total_downloads
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN orders o ON ul.order_id = o.id
        WHERE $whereClause
        ORDER BY ul.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $userProducts = $stmt->fetchAll();
    
    // Contar total de productos
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $perPage);
    
    // Obtener categorías con productos del usuario
    $categoriesStmt = $db->prepare("
        SELECT c.name, c.slug, COUNT(ul.id) as product_count
        FROM categories c
        INNER JOIN products p ON c.id = p.category_id
        INNER JOIN user_licenses ul ON p.id = ul.product_id
        WHERE ul.user_id = ? AND ul.is_active = 1
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $categoriesStmt->execute([$user['id']]);
    $userCategories = $categoriesStmt->fetchAll();
    
    // Obtener estadísticas del usuario
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT ul.product_id) as total_products,
            COUNT(CASE WHEN ul.downloads_used < ul.download_limit THEN 1 END) as available_downloads,
            COUNT(CASE WHEN ul.expires_at IS NULL OR ul.expires_at > NOW() THEN 1 END) as with_updates,
            SUM(ul.downloads_used) as total_downloads_used
        FROM user_licenses ul
        WHERE ul.user_id = ? AND ul.is_active = 1
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en página de descargas: " . $e->getMessage());
    $userProducts = [];
    $totalProducts = 0;
    $totalPages = 0;
    $userCategories = [];
    $stats = [
        'total_products' => 0,
        'available_downloads' => 0,
        'with_updates' => 0,
        'total_downloads_used' => 0
    ];
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Descargas - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Accede a todos tus productos y descargas">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="hero-cards-section">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard" class="text-white-50">Dashboard</a></li>
                    <li class="breadcrumb-item active text-white">Mis Descargas</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="hero-card-content text-white">
                        <h1 class="luxury-title">Mis Descargas</h1>
                        <p class="hero-card-description">
                            Accede a todos tus productos y gestiona tus descargas
                        </p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="hero-actions text-md-end">
                        <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary btn-lg">
                            <i class="fas fa-store me-2"></i>Explorar Productos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <section class="stats-section">
        <div class="container stats-container">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                            <div class="stat-label">Productos Adquiridos</div>
                            <div class="stat-code">products.total</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['available_downloads']; ?></div>
                            <div class="stat-label">Descargas Disponibles</div>
                            <div class="stat-code">downloads.available</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['with_updates']; ?></div>
                            <div class="stat-label">Con Actualizaciones</div>
                            <div class="stat-code">updates.active</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $stats['total_downloads_used']; ?></div>
                            <div class="stat-label">Total Descargado</div>
                            <div class="stat-code">downloads.used</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
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
        
        <!-- Filtros -->
        <?php if (!empty($userCategories)): ?>
            <div class="category-card mb-4">
                <div class="category-content p-4">
                    <h5 class="category-title mb-3">Filtrar por Categoría</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo SITE_URL; ?>/mis-descargas" 
                           class="btn btn-sm <?php echo empty($category) ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Todas (<?php echo $stats['total_products']; ?>)
                        </a>
                        <?php foreach ($userCategories as $cat): ?>
                            <a href="<?php echo SITE_URL; ?>/mis-descargas?categoria=<?php echo urlencode($cat['slug']); ?>" 
                               class="btn btn-sm <?php echo $category === $cat['slug'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?> (<?php echo $cat['product_count']; ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Lista de Productos -->
        <?php if (empty($userProducts)): ?>
            <div class="text-center py-5">
                <div class="category-icon mx-auto mb-4">
                    <i class="fas fa-cloud-download-alt"></i>
                </div>
                <h4 class="category-title">No tienes productos para descargar</h4>
                <p class="category-description mb-4">Cuando compres productos, aparecerán aquí para que puedas descargarlos</p>
                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-corporate btn-lg">
                    <i class="fas fa-store me-2"></i>Explorar Productos
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($userProducts as $product): ?>
                    <div class="col-12">
                        <div class="product-card">
                            <div class="product-info p-4">
                                <div class="row align-items-start">
                                    <div class="col-md-2">
                                        <div class="product-image">
                                            <?php if ($product['product_image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['product_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                     class="img-fluid rounded">
                                            <?php else: ?>
                                                <div class="no-image">
                                                    <i class="fas fa-cube"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                        <h5 class="product-title">
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['product_slug']; ?>">
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </a>
                                        </h5>
                                        <?php if ($product['short_description']): ?>
                                            <p class="product-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                Adquirido: <?php echo formatDate($product['purchase_date']); ?>
                                                <?php if ($product['order_number']): ?>
                                                    | Orden: #<?php echo $product['order_number']; ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-end">
                                            <div class="mb-3">
                                                <span class="badge bg-primary">
                                                    v<?php echo htmlspecialchars($product['current_version'] ?? '1.0'); ?>
                                                </span>
                                                
                                                <?php if ($product['expires_at']): ?>
                                                    <?php if (strtotime($product['expires_at']) > time()): ?>
                                                        <div class="badge bg-success mt-2">
                                                            Licencia activa hasta <?php echo formatDate($product['expires_at']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="badge bg-danger mt-2">
                                                            Licencia expirada
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="badge bg-success mt-2">
                                                        Licencia permanente
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Progress Bar de Descargas -->
                                            <div class="mb-3">
                                                <small class="text-muted d-block">
                                                    Descargas: <?php echo $product['downloads_used']; ?>/<?php echo $product['downloads_limit']; ?>
                                                </small>
                                                <?php
                                                    $percentage = $product['download_limit'] > 0 ? ($product['downloads_used'] / $product['download_limit']) * 100 : 0;
                                                ?>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, $percentage); ?>%"></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Botones de Acción -->
                                            <div class="product-actions">
                                                <!--<?php if ($product['demo_url']): ?>-->
                                                <!--    <a href="<?php echo $product['demo_url']; ?>" target="_blank" -->
                                                <!--       class="btn btn-outline-info btn-sm mb-2">-->
                                                <!--        <i class="fas fa-eye me-1"></i>Demo-->
                                                <!--    </a>-->
                                                <!--<?php endif; ?>-->
                                                
                                                <!--<a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['product_slug']; ?>" -->
                                                <!--   class="btn btn-outline-primary btn-sm mb-2">-->
                                                <!--    <i class="fas fa-info me-1"></i>Detalles-->
                                                <!--</a>-->
                                                
                                                    <?php if ($product['downloads_used'] < $product['download_limit']): ?>
                                                        <button class="btn btn-corporate btn-sm" style="width: 150px !important" onclick="downloadProduct(<?php echo $product['product_id']; ?>)">
                                                            <i class="fas fa-download me-1"></i>Descargar
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-secondary btn-sm" style="width: 150px !important; border-radius: 15px !important;" disabled>
                                                            <i class="fas fa-ban me-1"></i>Límite Alcanzado
                                                        </button>
                                                    <?php endif; ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <?php echo $product['version_count']; ?> versiones disponibles
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-center mt-5">
                    <nav aria-label="Paginación de productos">
                        <ul class="pagination">
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
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function downloadProduct(productId) {
            // Mostrar modal de descarga o redirigir
            if (confirm('¿Deseas descargar este producto? Se contabilizará como una descarga utilizada.')) {
                // Ya está correcto:
                window.location.href = '<?php echo SITE_URL; ?>/download/' + productId;
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
        
        // Animar estadísticas al cargar
        document.addEventListener('DOMContentLoaded', function() {
            const statsNumbers = document.querySelectorAll('.stat-number');
            statsNumbers.forEach(stat => {
                const finalValue = parseInt(stat.textContent);
                if (finalValue > 0) {
                    animateCounter(stat, finalValue);
                }
            });
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
                element.textContent = Math.floor(current);
            }, 50);
        }
    </script>
</body>
</html>