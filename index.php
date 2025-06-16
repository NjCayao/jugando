<?php
// index.php - Página principal CORPORATIVO AZUL
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';
require_once 'config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include 'maintenance.php';
    exit;
}

// Obtener configuraciones del sitio
$siteName = Settings::get('site_name', 'WebSystems Pro');
$siteDescription = Settings::get('site_description', 'Sistemas Web Profesionales para su Empresa');
$siteLogo = Settings::get('site_logo', '');
$siteFavicon = Settings::get('site_favicon', '');

// Obtener productos destacados
try {
    $db = Database::getInstance()->getConnection();

    // Productos destacados
    $stmt = $db->query("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND p.is_featured = 1 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ");
    $featuredProducts = $stmt->fetchAll();

    // Categorías activas
    $stmt = $db->query("
        SELECT c.*, COUNT(p.id) as product_count
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        WHERE c.is_active = 1 
        GROUP BY c.id 
        ORDER BY c.sort_order ASC, c.name ASC
    ");
    $categories = $stmt->fetchAll();

    // Productos recientes
    $stmt = $db->query("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 
        ORDER BY p.created_at DESC 
        LIMIT 8
    ");
    $recentProducts = $stmt->fetchAll();
} catch (Exception $e) {
    $featuredProducts = [];
    $categories = [];
    $recentProducts = [];
    logError("Error en homepage: " . $e->getMessage());
}

// Obtener TODOS los banners activos para el carousel
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'home_slider' AND is_active = 1 
        ORDER BY sort_order ASC, created_at DESC
    ");
    $sliderBanners = $stmt->fetchAll();
} catch (Exception $e) {
    $sliderBanners = [];
}

try {
    $db = Database::getInstance()->getConnection();

    // Banners promocionales 
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'promotion' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $promotionBanners = $stmt->fetchAll();

    // Hero section banners
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'home_hero' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $heroBanners = $stmt->fetchAll();

    // Sidebar banners
    $stmt = $db->query("
        SELECT * FROM banners 
        WHERE position = 'sidebar' AND is_active = 1 
        ORDER BY sort_order ASC
    ");
    $sidebarBanners = $stmt->fetchAll();
} catch (Exception $e) {
    $promotionBanners = [];
    $heroBanners = [];
    $sidebarBanners = [];
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteName); ?> - <?php echo htmlspecialchars($siteDescription); ?></title>

    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($siteDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(Settings::get('site_keywords', 'sistemas web, desarrollo profesional, software empresarial')); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($siteName); ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($siteName); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($siteDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">

    <!-- Favicon -->
    <?php if ($siteFavicon): ?>
        <link rel="icon" type="image/png" href="<?php echo UPLOADS_URL; ?>/logos/<?php echo $siteFavicon; ?>">
    <?php endif; ?>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- 🎨 FUENTES CORPORATIVAS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- 🏢 CSS CORPORATIVO AZUL -->
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main>
        <!-- 🏢 HERO SECTION CORPORATIVO -->
        <section class="hero-carousel-section">
            <?php if (!empty($sliderBanners)): ?>
                <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
                    <!-- Indicadores Corporativos -->
                    <div class="carousel-indicators">
                        <?php foreach ($sliderBanners as $index => $banner): ?>
                            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?php echo $index; ?>"
                                <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?>
                                aria-label="Slide <?php echo $index + 1; ?>"></button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Slides del Carousel -->
                    <div class="carousel-inner">
                        <?php foreach ($sliderBanners as $index => $banner): ?>
                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                <div class="hero-slide">
                                    <!-- Imagen de fondo -->
                                    <?php if ($banner['image']): ?>
                                        <div class="hero-background" style="background-image: url('<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>');"></div>
                                    <?php endif; ?>

                                    <!-- Overlay corporativo -->
                                    <div class="hero-overlay"></div>

                                    <!-- Contenido -->
                                    <div class="container">
                                        <div class="row align-items-center" style="min-height: 500px;">
                                            <div class="col-lg-6">
                                                <div class="hero-content">
                                                    <h1 class="hero-title">
                                                        <?php echo htmlspecialchars($banner['title']); ?>
                                                    </h1>
                                                    <?php if ($banner['subtitle']): ?>
                                                        <h2 class="hero-subtitle">
                                                            <?php echo htmlspecialchars($banner['subtitle']); ?>
                                                        </h2>
                                                    <?php endif; ?>
                                                    <?php if ($banner['description']): ?>
                                                        <p class="hero-description">
                                                            <?php echo htmlspecialchars($banner['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="hero-actions">
                                                        <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                            <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="btn btn-primary">
                                                                <i class="fas fa-rocket me-2"></i><?php echo htmlspecialchars($banner['button_text']); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="#categorias" class="btn btn-outline-light">
                                                            <i class="fas fa-th-large me-2"></i>VER CATEGORÍAS
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-6">
                                                <div class="hero-image">
                                                    <!-- Cuadros corporativos flotantes -->
                                                    <div class="floating-card">
                                                        <i class="fas fa-code fa-2x mb-3" style="color: #1E40AF;"></i>
                                                        <h5>DESARROLLO PROFESIONAL</h5>
                                                        <p>Código limpio y escalable</p>
                                                    </div>
                                                    <div class="floating-card">
                                                        <i class="fas fa-shield-alt fa-2x mb-3" style="color: #1E40AF;"></i>
                                                        <h5>100% SEGURO</h5>
                                                        <p>Sistemas probados y confiables</p>
                                                    </div>
                                                    <div class="floating-card">
                                                        <i class="fas fa-headset fa-2x mb-3" style="color: #1E40AF;"></i>
                                                        <h5>SOPORTE ENTERPRISE</h5>
                                                        <p>Ayuda profesional 24/7</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Controles corporativos -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                        <div class="carousel-control-icon">
                            <i class="fas fa-chevron-left"></i>
                        </div>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                        <div class="carousel-control-icon">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                </div>
            <?php else: ?>
                <!-- Fallback corporativo -->
                <div class="hero-slide">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row align-items-center" style="min-height: 500px;">
                            <div class="col-lg-6">
                                <div class="hero-content">
                                    <h1 class="hero-title">
                                        Sistemas Web Profesionales para su Empresa
                                    </h1>
                                    <h2 class="hero-subtitle">
                                        Desarrollador Certificado
                                    </h2>
                                    <p class="hero-description">
                                        <?php echo htmlspecialchars($siteDescription); ?>.
                                        Soluciones tecnológicas empresariales de vanguardia.
                                    </p>
                                    <div class="hero-actions">
                                        <a href="#productos-destacados" class="btn btn-primary">
                                            <i class="fas fa-rocket me-2"></i>EXPLORAR PRODUCTOS
                                        </a>
                                        <a href="#categorias" class="btn btn-outline-light">
                                            <i class="fas fa-th-large me-2"></i>VER CATEGORÍAS
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="hero-image">
                                    <div class="floating-card">
                                        <i class="fas fa-code fa-2x mb-3" style="color: #1E40AF;"></i>
                                        <h5>DESARROLLO PROFESIONAL</h5>
                                        <p>Código limpio y escalable</p>
                                    </div>
                                    <div class="floating-card">
                                        <i class="fas fa-shield-alt fa-2x mb-3" style="color: #1E40AF;"></i>
                                        <h5>100% SEGURO</h5>
                                        <p>Sistemas probados y confiables</p>
                                    </div>
                                    <div class="floating-card">
                                        <i class="fas fa-headset fa-2x mb-3" style="color: #1E40AF;"></i>
                                        <h5>SOPORTE ENTERPRISE</h5>
                                        <p>Ayuda profesional 24/7</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- 💼 PROMOCIONES CORPORATIVAS -->
        <?php if (!empty($promotionBanners)): ?>
            <section class="promotion-section py-5">
                <div class="container">
                    <!-- <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="section-title fade-in-up">OFERTAS EMPRESARIALES</h2>
                            <p class="section-subtitle fade-in-up">Soluciones especiales para su negocio</p>
                        </div>
                    </div> -->
                    <div class="row g-4">
                        <?php
                        $colClass = count($promotionBanners) == 1 ? 'col-12' : (count($promotionBanners) == 2 ? 'col-lg-6' : (count($promotionBanners) == 3 ? 'col-lg-4' : 'col-lg-3'));
                        ?>
                        <?php foreach ($promotionBanners as $index => $banner): ?>
                            <div class="<?php echo $colClass; ?>">
                                <div class="promo-card fade-in-up" style="animation-delay: <?php echo $index * 0.2; ?>s;">
                                    <?php if ($banner['image']): ?>
                                        <div class="promo-image-container">
                                            <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>"
                                                alt="<?php echo htmlspecialchars($banner['title']); ?>" 
                                                class="promo-image">
                                            <div class="promo-overlay"></div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="promo-content">
                                        <div class="promo-icon">
                                            <i class="fas fa-award"></i>
                                        </div>
                                        <h3 class="promo-title"><?php echo htmlspecialchars($banner['title']); ?></h3>
                                        <?php if ($banner['subtitle']): ?>
                                            <p class="promo-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['description']): ?>
                                            <p class="promo-description"><?php echo htmlspecialchars($banner['description']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                            <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="btn-luxury">
                                                <?php echo htmlspecialchars($banner['button_text']); ?>
                                                <i class="fas fa-arrow-right ms-2"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="promo-glow"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 🎯 HERO CARDS CORPORATIVAS -->
        <?php if (!empty($heroBanners)): ?>
            <section class="hero-cards-section py-5 bg-gradient-luxury">
                <div class="container">
                    <!-- <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="luxury-title text-white fade-in-up">EXPERIENCIA EMPRESARIAL</h2>
                            <div class="luxury-divider"></div>
                        </div>
                    </div> -->
                    <div class="row g-4">
                        <?php foreach ($heroBanners as $index => $banner): ?>
                            <div class="col-lg-<?php echo count($heroBanners) <= 2 ? '4' : '3'; ?>">
                                <div class="hero-luxury-card fade-in-up" style="animation-delay: <?php echo $index * 0.3; ?>s;">
                                    <div class="hero-card-inner">
                                        <div class="hero-card-content">
                                            <div class="hero-card-icon">
                                                <i class="fas fa-briefcase"></i>
                                            </div>
                                            <h4 class="hero-card-title"><?php echo htmlspecialchars($banner['title']); ?></h4>
                                            <?php if ($banner['subtitle']): ?>
                                                <p class="hero-card-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['description']): ?>
                                                <p class="hero-card-description"><?php echo htmlspecialchars($banner['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="hero-card-btn">
                                                    <?php echo htmlspecialchars($banner['button_text']); ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($banner['image']): ?>
                                            <div class="hero-card-bg" style="background-image: url('<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>');"></div>
                                        <?php endif; ?>
                                        <div class="hero-card-glow"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 📊 CATEGORÍAS CORPORATIVAS -->
        <section id="categorias" class="py-5">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title fade-in-up">NUESTRAS CATEGORÍAS</h2>
                        <p class="section-subtitle fade-in-up">Soluciones organizadas por especialidad</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach ($categories as $index => $category): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="category-card fade-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="category-icon">
                                    <?php if ($category['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php else: ?>
                                        <i class="fas fa-folder-open"></i>
                                    <?php endif; ?>
                                </div>
                                <h5 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                                <div class="category-stats mb-3">
                                    <span class="product-count"><?php echo $category['product_count']; ?> Productos</span>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="btn btn-outline-primary">
                                    Explorar <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 💎 PRODUCTOS DESTACADOS CORPORATIVOS -->
        <section id="productos-destacados" class="py-5 bg-light">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title fade-in-up">PRODUCTOS DESTACADOS</h2>
                        <p class="section-subtitle fade-in-up">Los más solicitados por empresas</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach ($featuredProducts as $index => $product): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="product-card fade-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-overlay">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye me-2"></i>Ver Detalles
                                        </a>
                                    </div>
                                    <?php if ($product['is_free']): ?>
                                        <span class="product-badge free">GRATIS</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                    <h5 class="product-title">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h5>
                                    <p class="product-description"><?php echo htmlspecialchars($product['short_description']); ?></p>
                                    <div class="product-footer">
                                        <div class="product-price">
                                            <?php if ($product['is_free']): ?>
                                                <span class="price-free">GRATIS</span>
                                            <?php else: ?>
                                                <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-actions">
                                            <button class="btn btn-outline-primary" onclick="addToCart(<?php echo $product['id']; ?>)" title="Agregar al carrito">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="addToWishlist(<?php echo $product['id']; ?>)" title="Agregar a favoritos">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-5">
                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-corporate btn-lg">
                        VER TODOS LOS PRODUCTOS <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- 📈 ESTADÍSTICAS CORPORATIVAS -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-container">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up">
                                <div class="stat-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-counter="100">0</h3>
                                    <p class="stat-label">Empresas Confían</p>
                                    <p class="stat-code">enterprise_clients.count()</p>
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-counter="5">0</h3>
                                    <p class="stat-label">Años Experiencia</p>
                                    <p class="stat-code">professional_years.total()</p>
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                                <div class="stat-icon">
                                    <i class="fas fa-server"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number">99.9%</h3>
                                    <p class="stat-label">Uptime</p>
                                    <p class="stat-code">system_availability.get()</p>
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                                <div class="stat-icon">
                                    <i class="fas fa-award"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number">★★★★★</h3>
                                    <p class="stat-label">Calificación</p>
                                    <p class="stat-code">client_reviews.average()</p>
                                </div>
                                <div class="stat-glow"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Partículas de código corporativo -->
            <div class="code-particles-bg">
                <div class="particle" style="color: #dc2626;">&bull;</div>
                <div class="particle" style="color: #d97706;">&bull;</div>
                <div class="particle" style="color: #1E40AF;">&bull;</div>
                <div class="particle" style="color: #dc2626;">&bull;</div>
                <div class="particle" style="color: #d97706;">&bull;</div>
                <div class="particle" style="color: #1E40AF;">&bull;</div>
            </div>
        </section>

        <!-- 🔄 PRODUCTOS RECIENTES -->
        <section class="py-5">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title fade-in-up">PRODUCTOS RECIENTES</h2>
                        <p class="section-subtitle fade-in-up">Últimas adiciones al catálogo</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach (array_slice($recentProducts, 0, 4) as $index => $product): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="product-card compact fade-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="product-image">
                                    <?php if ($product['image']): ?>
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>">
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </a>
                                    <?php else: ?>
                                        <div class="no-image">
                                            <i class="fas fa-image"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <h6 class="product-title">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h6>
                                    <div class="product-price">
                                        <?php if ($product['is_free']): ?>
                                            <span class="price-free">GRATIS</span>
                                        <?php else: ?>
                                            <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 🎨 BANNERS LATERALES CORPORATIVOS -->
        <?php if (!empty($sidebarBanners)): ?>
            <section class="crystal-banners-section py-5">
                <div class="container">
                    <!-- <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="crystal-title fade-in-up">SOLUCIONES ESPECIALIZADAS</h2>
                            <div class="crystal-divider"></div>
                        </div>
                    </div> -->
                    <div class="row g-4">
                        <?php foreach ($sidebarBanners as $index => $banner): ?>
                            <div class="col-md-6 col-lg-<?php echo count($sidebarBanners) <= 2 ? '6' : (count($sidebarBanners) == 3 ? '4' : '3'); ?>">
                                <div class="crystal-card fade-in-up" style="animation-delay: <?php echo $index * 0.15; ?>s;">
                                    <div class="crystal-inner">
                                        <?php if ($banner['image']): ?>
                                            <div class="crystal-image">
                                                <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>"
                                                    alt="<?php echo htmlspecialchars($banner['title']); ?>">
                                                <div class="crystal-overlay"></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="crystal-content">
                                            <h5 class="crystal-title-small"><?php echo htmlspecialchars($banner['title']); ?></h5>
                                            <?php if ($banner['subtitle']): ?>
                                                <p class="crystal-subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['description']): ?>
                                                <p class="crystal-description"><?php echo htmlspecialchars($banner['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="crystal-btn">
                                                    <?php echo htmlspecialchars($banner['button_text']); ?>
                                                    <i class="fas fa-arrow-right ms-2"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="crystal-glow"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- 🔝 BACK TO TOP CORPORATIVO -->
    <button class="back-to-top" onclick="scrollToTop()">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script>
        // Configuración global para JavaScript
        window.SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>