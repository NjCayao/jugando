<?php
// pages/category.php - Página de categoría específica (MEJORADA)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener slug de la categoría
$categorySlug = $_GET['slug'] ?? '';

if (empty($categorySlug)) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit;
}

// Parámetros de filtrado
$search = $_GET['buscar'] ?? '';
$priceMin = floatval($_GET['precio_min'] ?? 0);
$priceMax = floatval($_GET['precio_max'] ?? 1000);
$type = $_GET['tipo'] ?? ''; // 'free', 'paid', 'all'
$sort = $_GET['orden'] ?? 'recientes';
$page = intval($_GET['pagina'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener información de la categoría
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$categorySlug]);
    $category = $stmt->fetch();
    
    if (!$category) {
        header("HTTP/1.0 404 Not Found");
        include '../404.php';
        exit;
    }
    
    // Construir query de productos
    $whereConditions = ["p.is_active = 1", "p.category_id = ?"];
    $params = [$category['id']];
    
    // Filtro por búsqueda
    if ($search) {
        $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.short_description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Filtro por tipo
    if ($type === 'free') {
        $whereConditions[] = "p.is_free = 1";
    } elseif ($type === 'paid') {
        $whereConditions[] = "p.is_free = 0";
    }
    
    // Filtro por precio
    if ($priceMin > 0) {
        $whereConditions[] = "p.price >= ?";
        $params[] = $priceMin;
    }
    if ($priceMax > 0 && $priceMax < 1000) {
        $whereConditions[] = "p.price <= ?";
        $params[] = $priceMax;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Ordenamiento
    $orderBy = match($sort) {
        'nombre' => 'p.name ASC',
        'precio_asc' => 'p.price ASC',
        'precio_desc' => 'p.price DESC',
        'populares' => 'p.download_count DESC, p.created_at DESC',
        default => 'p.created_at DESC'
    };
    
    // Obtener productos de la categoría
    $stmt = $db->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM product_versions pv WHERE pv.product_id = p.id) as version_count
        FROM products p 
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Contar total para paginación
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM products p 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetch()['total'];
    $totalPages = ceil($totalProducts / $perPage);
    
    // Obtener estadísticas de la categoría
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN is_free = 1 THEN 1 ELSE 0 END) as free_products,
            SUM(CASE WHEN is_free = 0 THEN 1 ELSE 0 END) as paid_products,
            AVG(CASE WHEN is_free = 0 THEN price ELSE NULL END) as avg_price,
            MIN(CASE WHEN is_free = 0 THEN price ELSE NULL END) as min_price,
            MAX(CASE WHEN is_free = 0 THEN price ELSE NULL END) as max_price
        FROM products 
        WHERE category_id = ? AND is_active = 1
    ");
    $stmt->execute([$category['id']]);
    $stats = $stmt->fetch();
    
    // Obtener otras categorías
    $stmt = $db->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        WHERE c.is_active = 1 AND c.id != {$category['id']}
        GROUP BY c.id 
        ORDER BY c.name ASC
        LIMIT 8
    ");
    $otherCategories = $stmt->fetchAll();
    
} catch (Exception $e) {
    logError("Error en página de categoría: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../500.php';
    exit;
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = "Categoría: " . $category['name'];
$pageDescription = $category['description'] ?: "Explora todos los productos de la categoría " . $category['name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($category['name'] . ', software, sistemas'); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Hero Header de Categoría -->
    <div class="hero-cards-section py-5 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-card-content">
                        <!-- Breadcrumb Elegante -->
                        <nav aria-label="breadcrumb" class="mb-3">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item">
                                    <a href="<?php echo SITE_URL; ?>" class="text-white-50 text-decoration-none">
                                        <i class="fas fa-home me-1"></i>Inicio
                                    </a>
                                </li>
                                <li class="breadcrumb-item">
                                    <a href="<?php echo SITE_URL; ?>/productos" class="text-white-50 text-decoration-none">
                                        <i class="fas fa-th-large me-1"></i>Productos
                                    </a>
                                </li>
                                <li class="breadcrumb-item active text-white">
                                    <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($category['name']); ?>
                                </li>
                            </ol>
                        </nav>
                        
                        <div class="d-flex align-items-center mb-3">
                            <div class="hero-card-icon me-3">
                                <?php if ($category['image']): ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $category['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($category['name']); ?>"
                                         style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <i class="fas fa-folder"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h1 class="luxury-title mb-2"><?php echo htmlspecialchars($category['name']); ?></h1>
                                <div class="luxury-divider mb-0" style="margin: 0;"></div>
                            </div>
                        </div>
                        
                        <?php if ($category['description']): ?>
                            <p class="hero-description mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="hero-actions">
                            <span class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-cube me-1"></i><?php echo $stats['total_products']; ?> Productos
                            </span>
                            <span class="btn btn-outline-light btn-sm me-2">
                                <i class="fas fa-gift me-1"></i><?php echo $stats['free_products']; ?> Gratuitos
                            </span>
                            <?php if ($stats['paid_products'] > 0): ?>
                                <span class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-tag me-1"></i>Desde <?php echo formatPrice($stats['min_price']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-lg-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="hero-luxury-card">
                                <div class="hero-card-inner">
                                    <div class="hero-card-glow"></div>
                                    <div class="hero-card-content">
                                        <div class="hero-card-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <h4 class="hero-card-title">Total</h4>
                                        <h3 class="hero-card-subtitle"><?php echo $stats['total_products']; ?></h3>
                                        <p class="hero-card-description mb-0">Productos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="hero-luxury-card">
                                <div class="hero-card-inner">
                                    <div class="hero-card-glow"></div>
                                    <div class="hero-card-content">
                                        <div class="hero-card-icon">
                                            <i class="fas fa-gift"></i>
                                        </div>
                                        <h4 class="hero-card-title">Gratis</h4>
                                        <h3 class="hero-card-subtitle"><?php echo $stats['free_products']; ?></h3>
                                        <p class="hero-card-description mb-0">Disponibles</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if ($stats['paid_products'] > 0): ?>
                            <div class="col-6">
                                <div class="hero-luxury-card">
                                    <div class="hero-card-inner">
                                        <div class="hero-card-glow"></div>
                                        <div class="hero-card-content">
                                            <div class="hero-card-icon">
                                                <i class="fas fa-coins"></i>
                                            </div>
                                            <h4 class="hero-card-title">Desde</h4>
                                            <h3 class="hero-card-subtitle"><?php echo formatPrice($stats['min_price']); ?></h3>
                                            <p class="hero-card-description mb-0">Precio mínimo</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="hero-luxury-card">
                                    <div class="hero-card-inner">
                                        <div class="hero-card-glow"></div>
                                        <div class="hero-card-content">
                                            <div class="hero-card-icon">
                                                <i class="fas fa-balance-scale"></i>
                                            </div>
                                            <h4 class="hero-card-title">Promedio</h4>
                                            <h3 class="hero-card-subtitle"><?php echo formatPrice($stats['avg_price']); ?></h3>
                                            <p class="hero-card-description mb-0">Precio medio</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div> -->
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container mb-5">
        <!-- Panel de Filtros Mejorado -->
        <div class="dashboard-section mb-4">
            <div class="section-header-compact">
                <h3 class="section-title-compact mb-0">
                    <div class="section-icon-compact">
                        <i class="fas fa-filter"></i>
                    </div>
                    Filtros de Búsqueda
                    <?php if ($search || $type || $priceMin > 0 || $priceMax < 1000): ?>
                        <span class="badge bg-warning ms-2">Filtros activos</span>
                    <?php endif; ?>
                </h3>
            </div>
            <div class="section-body-compact">
                <form method="GET" class="row g-3" id="filterForm">
                    <input type="hidden" name="slug" value="<?php echo $category['slug']; ?>">
                    
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label">
                            <i class="fas fa-search me-1"></i>Buscar productos
                        </label>
                        <input type="text" class="form-control" name="buscar" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Nombre, descripción...">
                    </div>
                    
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">
                            <i class="fas fa-tag me-1"></i>Tipo
                        </label>
                        <select name="tipo" class="form-select">
                            <option value="" <?php echo $type === '' ? 'selected' : ''; ?>>Todos</option>
                            <option value="free" <?php echo $type === 'free' ? 'selected' : ''; ?>>Solo gratuitos</option>
                            <option value="paid" <?php echo $type === 'paid' ? 'selected' : ''; ?>>Solo de pago</option>
                        </select>
                    </div>
                    
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">
                            <i class="fas fa-dollar-sign me-1"></i>Precio mín
                        </label>
                        <input type="number" class="form-control" name="precio_min" 
                               value="<?php echo $priceMin > 0 ? $priceMin : ''; ?>" 
                               placeholder="0.00" min="0" step="0.01">
                    </div>
                    
                    <div class="col-lg-2 col-md-6">
                        <label class="form-label">
                            <i class="fas fa-dollar-sign me-1"></i>Precio máx
                        </label>
                        <input type="number" class="form-control" name="precio_max" 
                               value="<?php echo $priceMax < 1000 ? $priceMax : ''; ?>" 
                               placeholder="1000.00" min="0" step="0.01">
                    </div>
                    
                    <div class="col-lg-2 col-md-12">
                        <label class="form-label d-block">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-corporate">
                                <i class="fas fa-search me-1"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </form>
                
                <?php if ($search || $type || $priceMin > 0 || $priceMax < 1000): ?>
                    <div class="mt-3 text-center">
                        <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-times me-1"></i>Limpiar Filtros
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Barra de Resultados y Ordenamiento -->
        <div class="dashboard-section mb-4">
            <div class="section-body-compact">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">
                            <?php if ($search): ?>
                                <i class="fas fa-search text-primary me-2"></i>
                                Resultados para "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php else: ?>
                                <i class="fas fa-cube text-primary me-2"></i>
                                Productos en <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Mostrando <strong><?php echo min($perPage, $totalProducts - $offset); ?></strong> de <strong><?php echo $totalProducts; ?></strong> productos
                            <?php if ($totalPages > 1): ?>
                                | Página <strong><?php echo $page; ?></strong> de <strong><?php echo $totalPages; ?></strong>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4">
                        <form method="GET" class="d-flex align-items-center justify-content-md-end">
                            <!-- Mantener parámetros actuales -->
                            <input type="hidden" name="slug" value="<?php echo $category['slug']; ?>">
                            <?php foreach ($_GET as $key => $value): ?>
                                <?php if ($key !== 'orden' && $key !== 'slug'): ?>
                                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <label class="form-label me-2 mb-0">
                                <i class="fas fa-sort me-1"></i>Ordenar:
                            </label>
                            <select name="orden" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="recientes" <?php echo $sort === 'recientes' ? 'selected' : ''; ?>>Más Recientes</option>
                                <option value="nombre" <?php echo $sort === 'nombre' ? 'selected' : ''; ?>>Nombre A-Z</option>
                                <option value="precio_asc" <?php echo $sort === 'precio_asc' ? 'selected' : ''; ?>>Precio ↑</option>
                                <option value="precio_desc" <?php echo $sort === 'precio_desc' ? 'selected' : ''; ?>>Precio ↓</option>
                                <option value="populares" <?php echo $sort === 'populares' ? 'selected' : ''; ?>>Más Populares</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Grid de Productos -->
        <?php if (empty($products)): ?>
            <!-- Estado Vacío Elegante -->
            <div class="crystal-banners-section py-5">
                <div class="crystal-card">
                    <div class="crystal-inner text-center">
                        <div class="crystal-glow"></div>
                        <div class="crystal-content">
                            <div class="empty-icon-compact mb-4">
                                <i class="fas fa-search"></i>
                            </div>
                            <h3 class="crystal-title-small mb-3">No se encontraron productos</h3>
                            <p class="crystal-description mb-4">
                                No hay productos que coincidan con los filtros aplicados en la categoría 
                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                            </p>
                            <div class="row g-3 justify-content-center">
                                <div class="col-auto">
                                    <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="crystal-btn">
                                        <i class="fas fa-refresh me-2"></i>Ver Todos
                                    </a>
                                </div>
                                <div class="col-auto">
                                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary">
                                        <i class="fas fa-th-large me-2"></i>Explorar Categorías
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Grid de Productos Mejorado -->
            <div class="row g-4 mb-5">
                <?php foreach ($products as $index => $product): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="product-card h-100" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                            <div class="product-image">
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="product-overlay">
                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="btn btn-corporate">
                                        <i class="fas fa-eye me-2"></i>Ver Detalles
                                    </a>
                                </div>
                                <?php if ($product['is_free']): ?>
                                    <span class="product-badge free">
                                        <i class="fas fa-gift me-1"></i>GRATIS
                                    </span>
                                <?php endif; ?>
                                <?php if ($product['is_featured']): ?>
                                    <span class="product-badge featured" style="top: 55px;">
                                        <i class="fas fa-star me-1"></i>DESTACADO
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-category">
                                    <i class="fas fa-folder me-1"></i><?php echo htmlspecialchars($category['name']); ?>
                                </div>
                                <h5 class="product-title">
                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h5>
                                <p class="product-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                
                                <div class="product-meta mb-3">
                                    <div class="row g-2 text-center">
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-code-branch text-primary"></i><br>
                                                <strong><?php echo $product['version_count']; ?></strong> versiones
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">
                                                <i class="fas fa-download text-success"></i><br>
                                                <strong><?php echo number_format($product['download_count']); ?></strong> descargas
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="product-footer">
                                    <div class="product-price">
                                        <?php if ($product['is_free']): ?>
                                            <span class="price-free">
                                                <i class="fas fa-gift me-1"></i>GRATIS
                                            </span>
                                        <?php else: ?>
                                            <span class="price">
                                                <i class="fas fa-tag me-1"></i><?php echo formatPrice($product['price']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-actions">
                                        <button class="btn btn-sm btn-outline-primary" onclick="addToCart(<?php echo $product['id']; ?>)" title="Agregar al carrito">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="addToWishlist(<?php echo $product['id']; ?>)" title="Favoritos">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación Mejorada -->
            <?php if ($totalPages > 1): ?>
                <div class="dashboard-section">
                    <div class="section-body-compact">
                        <nav aria-label="Paginación de productos">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => 1])); ?>" title="Primera página">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $page - 1])); ?>" title="Página anterior">
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
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $page + 1])); ?>" title="Página siguiente">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['pagina' => $totalPages])); ?>" title="Última página">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Página <?php echo $page; ?> de <?php echo $totalPages; ?> | 
                                Total: <?php echo $totalProducts; ?> productos
                            </small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Otras Categorías Mejoradas -->
        <?php if (!empty($otherCategories)): ?>
            <div class="promotion-section py-5 mt-5">
                <div class="container">
                    <div class="text-center mb-5">
                        <h3 class="section-title">Explorar Otras Categorías</h3>
                        <div class="luxury-divider mx-auto mb-4"></div>
                        <p class="section-subtitle">Descubre más productos en nuestras diferentes categorías</p>
                    </div>
                    
                    <div class="row g-4">
                        <?php foreach ($otherCategories as $otherCategory): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6">
                                <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $otherCategory['slug']; ?>" class="text-decoration-none">
                                    <div class="promo-card h-100">
                                        <div class="promo-glow"></div>
                                        <div class="promo-icon">
                                            <?php if ($otherCategory['image']): ?>
                                                <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $otherCategory['image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($otherCategory['name']); ?>"
                                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                            <?php else: ?>
                                                <i class="fas fa-folder"></i>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="promo-title"><?php echo htmlspecialchars($otherCategory['name']); ?></h6>
                                        <p class="promo-description">
                                            <?php echo $otherCategory['product_count']; ?> productos disponibles
                                        </p>
                                        <div class="btn-luxury">
                                            <i class="fas fa-arrow-right me-2"></i>Explorar
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
     <script>
        window.SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/modules/cart.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Añadir efectos de animación escalonada a las tarjetas
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach((card, index) => {
                card.classList.add('fade-in-up');
            });
            
            // Auto-submit filtros cuando cambien ciertos campos
            const typeSelect = document.querySelector('select[name="tipo"]');
            if (typeSelect) {
                typeSelect.addEventListener('change', function() {
                    document.getElementById('filterForm').submit();
                });
            }
            
            // Efecto hover mejorado en las tarjetas
            productCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
        });          
        
    </script>
</body>
</html>