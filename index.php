<?php
// index.php - Página principal CYBERPUNK
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
$siteName = Settings::get('site_name', 'MiSistema');
$siteDescription = Settings::get('site_description', 'Plataforma de venta de software');
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
    <meta name="keywords" content="<?php echo htmlspecialchars(Settings::get('site_keywords', 'software, sistemas, php')); ?>">
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
    
    <!-- 🚀 FUENTES CYBERPUNK -->
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Exo+2:wght@400;600;700;800&family=Fira+Code:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- 🎨 CSS FUTURISTA -->
    <link href="<?php echo ASSETS_URL; ?>/css/modern.css" rel="stylesheet">
</head>

<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>

    <!-- Main Content -->
    <main>
        <!-- 🚀 HERO SECTION CYBERPUNK -->
        <section class="hero-carousel-section">
            <?php if (!empty($sliderBanners)): ?>
                <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
                    <!-- Indicadores Cyberpunk -->
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

                                    <!-- Overlay cyberpunk -->
                                    <div class="hero-overlay"></div>

                                    <!-- Contenido -->
                                    <div class="container">
                                        <div class="row align-items-center" style="min-height: 100vh;">
                                            <div class="col-lg-6">
                                                <div class="hero-content">
                                                    <h1 class="hero-title" data-text="<?php echo htmlspecialchars($banner['title']); ?>">
                                                        <?php echo htmlspecialchars($banner['title']); ?>
                                                    </h1>
                                                    <?php if ($banner['subtitle']): ?>
                                                        <h2 class="hero-subtitle">
                                                            > <?php echo htmlspecialchars($banner['subtitle']); ?>
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
                                                    <!-- Cuadros holográficos flotantes -->
                                                    <div class="floating-card">
                                                        <i class="fas fa-code fa-3x mb-3"></i>
                                                        <h5>DESARROLLO PROFESIONAL</h5>
                                                        <p>Código limpio y optimizado</p>
                                                    </div>
                                                    <div class="floating-card">
                                                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                                                        <h5>100% SEGURO</h5>
                                                        <p>Sistemas probados y confiables</p>
                                                    </div>
                                                    <div class="floating-card">
                                                        <i class="fas fa-support fa-3x mb-3"></i>
                                                        <h5>SOPORTE 24/7</h5>
                                                        <p>Ayuda cuando la necesites</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Controles cyberpunk -->
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
                <!-- Fallback cyberpunk -->
                <div class="hero-slide">
                    <div class="hero-overlay"></div>
                    <div class="container">
                        <div class="row align-items-center" style="min-height: 100vh;">
                            <div class="col-lg-6">
                                <div class="hero-content">
                                    <h1 class="hero-title" data-text="BIENVENIDO AL FUTURO">
                                        BIENVENIDO AL <span style="color: #00ffff;">FUTURO</span>
                                    </h1>
                                    <h2 class="hero-subtitle">
                                        > SISTEMA TECNOLÓGICO AVANZADO
                                    </h2>
                                    <p class="hero-description">
                                        <?php echo htmlspecialchars($siteDescription); ?>.
                                        Tecnología de vanguardia para el mundo digital.
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
                                        <i class="fas fa-code fa-3x mb-3"></i>
                                        <h5>DESARROLLO PROFESIONAL</h5>
                                        <p>Código limpio y optimizado</p>
                                    </div>
                                    <div class="floating-card">
                                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                                        <h5>100% SEGURO</h5>
                                        <p>Sistemas probados y confiables</p>
                                    </div>
                                    <div class="floating-card">
                                        <i class="fas fa-support fa-3x mb-3"></i>
                                        <h5>SOPORTE 24/7</h5>
                                        <p>Ayuda cuando la necesites</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- 🔥 PROMOCIONES CYBERPUNK -->
        <?php if (!empty($promotionBanners)): ?>
            <section class="promotion-section py-5" style="background: #111111; border-top: 2px solid #00ffff; border-bottom: 2px solid #00ffff;">
                <div class="container">
                    <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="section-title fade-in-up">PROMOCIONES ESPECIALES</h2>
                            <p class="section-subtitle fade-in-up">Ofertas exclusivas del sistema</p>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php
                        $colClass = count($promotionBanners) == 1 ? 'col-12' : (count($promotionBanners) == 2 ? 'col-lg-6' : (count($promotionBanners) == 3 ? 'col-lg-4' : 'col-lg-3'));
                        ?>
                        <?php foreach ($promotionBanners as $index => $banner): ?>
                            <div class="<?php echo $colClass; ?>">
                                <div class="promo-card fade-in-up" style="animation-delay: <?php echo $index * 0.2; ?>s; background: rgba(0,0,0,0.9); border: 2px solid #ff0080; padding: 2rem; text-align: center; clip-path: polygon(10% 0%, 90% 0%, 100% 10%, 100% 90%, 90% 100%, 10% 100%, 0% 90%, 0% 10%);">
                                    <?php if ($banner['image']): ?>
                                        <div class="promo-image-container mb-3" style="height: 150px; overflow: hidden; border: 1px solid #00ffff;">
                                            <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>"
                                                alt="<?php echo htmlspecialchars($banner['title']); ?>" 
                                                style="width: 100%; height: 100%; object-fit: cover; filter: hue-rotate(180deg) contrast(1.2);">
                                        </div>
                                    <?php endif; ?>
                                    <div class="promo-content">
                                        <div class="promo-icon mb-3" style="width: 60px; height: 60px; background: linear-gradient(45deg, #00ffff, #ff0080); margin: 0 auto; display: flex; align-items: center; justify-content: center; clip-path: polygon(30% 0%, 70% 0%, 100% 30%, 100% 70%, 70% 100%, 30% 100%, 0% 70%, 0% 30%);">
                                            <i class="fas fa-gem" style="color: #000; font-size: 1.5rem;"></i>
                                        </div>
                                        <h3 class="promo-title" style="color: #00ffff; font-family: 'Orbitron', sans-serif; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem;"><?php echo htmlspecialchars($banner['title']); ?></h3>
                                        <?php if ($banner['subtitle']): ?>
                                            <p class="promo-subtitle" style="color: #ff0080; font-family: 'Fira Code', monospace; margin-bottom: 1rem;"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['description']): ?>
                                            <p class="promo-description" style="color: #00ff41; font-family: 'Fira Code', monospace; font-size: 0.9rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($banner['description']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                            <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="btn" style="background: transparent; border: 2px solid #00ffff; color: #00ffff; padding: 0.75rem 1.5rem; text-transform: uppercase; font-family: 'Fira Code', monospace; clip-path: polygon(15% 0%, 85% 0%, 100% 25%, 100% 75%, 85% 100%, 15% 100%, 0% 75%, 0% 25%); transition: all 0.3s ease;">
                                                <?php echo htmlspecialchars($banner['button_text']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 🎮 HERO CARDS CYBERPUNK -->
        <?php if (!empty($heroBanners)): ?>
            <section class="hero-cards-section py-5" style="background: linear-gradient(135deg, #000000 0%, #001122 50%, #000011 100%); position: relative;">
                <div class="container">
                    <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="section-title fade-in-up">EXPERIENCIA PREMIUM</h2>
                            <div style="width: 100px; height: 4px; background: linear-gradient(90deg, #00ffff, #ff0080, #00ffff); margin: 0 auto 3rem; animation: glow-line 2s ease-in-out infinite alternate;"></div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($heroBanners as $index => $banner): ?>
                            <div class="col-lg-<?php echo count($heroBanners) <= 2 ? '6' : '4'; ?>">
                                <div class="hero-luxury-card fade-in-up" style="animation-delay: <?php echo $index * 0.3; ?>s; height: 350px;">
                                    <div class="hero-card-inner" style="background: rgba(0,0,0,0.8); border: 2px solid #8000ff; height: 100%; padding: 2rem; text-align: center; position: relative; clip-path: polygon(5% 0%, 95% 0%, 100% 5%, 100% 95%, 95% 100%, 5% 100%, 0% 95%, 0% 5%); overflow: hidden;">
                                        <div class="hero-card-icon" style="width: 80px; height: 80px; background: linear-gradient(45deg, #8000ff, #ff0080); margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; clip-path: polygon(20% 0%, 80% 0%, 100% 20%, 100% 80%, 80% 100%, 20% 100%, 0% 80%, 0% 20%);">
                                            <i class="fas fa-crown" style="color: #000; font-size: 2rem;"></i>
                                        </div>
                                        <h4 class="hero-card-title" style="color: #8000ff; font-family: 'Orbitron', sans-serif; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem;"><?php echo htmlspecialchars($banner['title']); ?></h4>
                                        <?php if ($banner['subtitle']): ?>
                                            <p class="hero-card-subtitle" style="color: #ff0080; font-family: 'Fira Code', monospace; margin-bottom: 1rem;"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['description']): ?>
                                            <p class="hero-card-description" style="color: #00ff41; font-family: 'Fira Code', monospace; font-size: 0.9rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($banner['description']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                            <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="hero-card-btn" style="background: transparent; border: 1px solid #8000ff; color: #8000ff; padding: 0.75rem 1.5rem; text-decoration: none; text-transform: uppercase; font-family: 'Fira Code', monospace; clip-path: polygon(20% 0%, 80% 0%, 100% 50%, 80% 100%, 20% 100%, 0% 50%); transition: all 0.3s ease;">
                                                <?php echo htmlspecialchars($banner['button_text']); ?>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($banner['image']): ?>
                                            <div class="hero-card-bg" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>'); background-size: cover; background-position: center; opacity: 0.1; z-index: -1; filter: hue-rotate(180deg);"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- 🎯 CATEGORÍAS CYBERPUNK -->
        <section id="categorias" class="py-5" style="background: #111111;">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title fade-in-up">EXPLORAR CATEGORÍAS</h2>
                        <p class="section-subtitle fade-in-up">Sistemas clasificados por tipo</p>
                    </div>
                </div>
                <div class="row g-4">
                    <?php foreach ($categories as $index => $category): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="category-card fade-in-up" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                                <div class="category-icon">
                                    <?php if ($category['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/categories/<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; filter: hue-rotate(180deg);">
                                    <?php else: ?>
                                        <i class="fas fa-folder"></i>
                                    <?php endif; ?>
                                </div>
                                <h5 class="category-title"><?php echo strtoupper(htmlspecialchars($category['name'])); ?></h5>
                                <p class="category-description"><?php echo htmlspecialchars($category['description']); ?></p>
                                <div class="category-stats mb-3">
                                    <span class="product-count"><?php echo $category['product_count']; ?> PRODUCTOS</span>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>" class="btn btn-outline-primary" style="border: 1px solid #00ffff; color: #00ffff; background: transparent; text-transform: uppercase; font-family: 'Fira Code', monospace; clip-path: polygon(15% 0%, 85% 0%, 100% 25%, 100% 75%, 85% 100%, 15% 100%, 0% 75%, 0% 25%);">
                                    ACCEDER <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 💎 PRODUCTOS DESTACADOS CYBERPUNK -->
        <section id="productos-destacados" class="py-5" style="background: #0a0a0a;">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title fade-in-up">PRODUCTOS DESTACADOS</h2>
                        <p class="section-subtitle fade-in-up">Los más populares del sistema</p>
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
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="btn btn-primary" style="background: transparent; border: 2px solid #00ffff; color: #00ffff; clip-path: polygon(20% 0%, 80% 0%, 100% 25%, 100% 75%, 80% 100%, 20% 100%, 0% 75%, 0% 25%);">VER DETALLES</a>
                                    </div>
                                    <?php if ($product['is_free']): ?>
                                        <span class="product-badge free">GRATIS</span>
                                    <?php endif; ?>
                                </div>
                                <div class="product-info">
                                    <div class="product-category"><?php echo strtoupper(htmlspecialchars($product['category_name'])); ?></div>
                                    <h5 class="product-title">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>" class="text-decoration-none">
                                            <?php echo strtoupper(htmlspecialchars($product['name'])); ?>
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
                                            <button class="btn btn-sm" onclick="addToCart(<?php echo $product['id']; ?>)" style="border: 1px solid #00ffff; background: transparent; color: #00ffff; clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                            <button class="btn btn-sm" onclick="addToWishlist(<?php echo $product['id']; ?>)" style="border: 1px solid #ff0080; background: transparent; color: #ff0080; clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);">
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
                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary btn-lg" style="background: transparent; border: 2px solid #00ffff; color: #00ffff; padding: 1rem 2rem; text-transform: uppercase; font-family: 'Orbitron', sans-serif; clip-path: polygon(10% 0%, 90% 0%, 100% 15%, 100% 85%, 90% 100%, 10% 100%, 0% 85%, 0% 15%);">
                        VER TODOS LOS PRODUCTOS <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- 📊 STATS CYBERPUNK -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-container">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up">
                                <div class="stat-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-counter="10000">0</h3>
                                    <p class="stat-label">DESCARGAS</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up" style="animation-delay: 0.1s;">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-counter="3000">0</h3>
                                    <p class="stat-label">USUARIOS</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up" style="animation-delay: 0.2s;">
                                <div class="stat-icon">
                                    <i class="fas fa-code"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number" data-counter="500">0</h3>
                                    <p class="stat-label">PROYECTOS</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 col-6">
                            <div class="stat-card fade-in-up" style="animation-delay: 0.3s;">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-content">
                                    <h3 class="stat-number">4.9</h3>
                                    <p class="stat-label">★★★★★</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 🔮 PRODUCTOS RECIENTES -->
        <section class="py-5" style="background: #111111;">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center mb-5">
                        <h2 class="section-title fade-in-up">PRODUCTOS RECIENTES</h2>
                        <p class="section-subtitle fade-in-up">Últimas adiciones al sistema</p>
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
                                            <?php echo strtoupper(htmlspecialchars($product['name'])); ?>
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

        <!-- 🌟 SIDEBAR BANNERS CYBERPUNK -->
        <?php if (!empty($sidebarBanners)): ?>
            <section class="crystal-banners-section py-5" style="background: #0a0a0a;">
                <div class="container">
                    <div class="row">
                        <div class="col-12 text-center mb-5">
                            <h2 class="section-title fade-in-up">COLECCIÓN EXCLUSIVA</h2>
                            <div style="width: 80px; height: 3px; background: linear-gradient(90deg, #8000ff, #ff0080, #8000ff); margin: 0 auto 3rem;"></div>
                        </div>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($sidebarBanners as $index => $banner): ?>
                            <div class="col-md-6 col-lg-<?php echo count($sidebarBanners) <= 2 ? '6' : (count($sidebarBanners) == 3 ? '4' : '3'); ?>">
                                <div class="crystal-card fade-in-up" style="animation-delay: <?php echo $index * 0.15; ?>s; height: 400px;">
                                    <div class="crystal-inner" style="background: rgba(0,0,0,0.9); border: 1px solid #8000ff; height: 100%; overflow: hidden; position: relative; clip-path: polygon(5% 0%, 95% 0%, 100% 5%, 100% 95%, 95% 100%, 5% 100%, 0% 95%, 0% 5%);">
                                        <?php if ($banner['image']): ?>
                                            <div class="crystal-image" style="height: 200px; overflow: hidden; position: relative;">
                                                <img src="<?php echo UPLOADS_URL; ?>/banners/<?php echo htmlspecialchars($banner['image']); ?>"
                                                    alt="<?php echo htmlspecialchars($banner['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; filter: hue-rotate(180deg) contrast(1.2);">
                                                <div class="crystal-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(45deg, rgba(128, 0, 255, 0.3), rgba(255, 0, 128, 0.3)); opacity: 0; transition: opacity 0.3s ease;"></div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="crystal-content" style="padding: 2rem; text-align: center;">
                                            <h5 class="crystal-title-small" style="color: #8000ff; font-family: 'Orbitron', sans-serif; font-weight: 600; margin-bottom: 1rem; text-transform: uppercase;"><?php echo htmlspecialchars($banner['title']); ?></h5>
                                            <?php if ($banner['subtitle']): ?>
                                                <p class="crystal-subtitle" style="color: #ff0080; font-family: 'Fira Code', monospace; font-weight: 500; margin-bottom: 1rem;"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['description']): ?>
                                                <p class="crystal-description" style="color: #00ff41; font-family: 'Fira Code', monospace; font-size: 0.9rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($banner['description']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($banner['button_text'] && $banner['button_url']): ?>
                                                <a href="<?php echo htmlspecialchars($banner['button_url']); ?>" class="crystal-btn" style="background: transparent; border: 1px solid #8000ff; color: #8000ff; padding: 0.5rem 1rem; text-decoration: none; font-family: 'Fira Code', monospace; font-size: 0.9rem; text-transform: uppercase; clip-path: polygon(15% 0%, 85% 0%, 100% 50%, 85% 100%, 15% 100%, 0% 50%); transition: all 0.3s ease;">
                                                    <?php echo htmlspecialchars($banner['button_text']); ?>
                                                    <i class="fas fa-arrow-right ms-2"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
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

    <!-- 🚀 BACK TO TOP CYBERPUNK -->
    <button class="back-to-top" style="display: none;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts -->
    <script>
        // Configuración global para JavaScript
        window.SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/modern.js"></script>
</body>
</html>