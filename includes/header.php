<?php
// includes/header.php - HEADER ELEGANTE Y ORDENADO
$siteName = getSetting('site_name', 'WebSystems Pro');
$siteLogo = getSetting('site_logo', '');

// Obtener menú principal
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT * FROM menu_items 
        WHERE menu_location = 'main' AND is_active = 1 AND parent_id IS NULL 
        ORDER BY sort_order ASC
    ");
    $mainMenuItems = $stmt->fetchAll();
    
    // Obtener submenús
    $subMenus = [];
    foreach ($mainMenuItems as $item) {
        $stmt = $db->prepare("
            SELECT * FROM menu_items 
            WHERE parent_id = ? AND is_active = 1 
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$item['id']]);
        $subMenus[$item['id']] = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $mainMenuItems = [];
    $subMenus = [];
}

// Función helper para procesar URLs
function processMenuUrl($url) {
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        return $url;
    }
    if (strpos($url, '/') === 0) {
        return SITE_URL . $url;
    }
    return SITE_URL . '/' . $url;
}

// Obtener datos del usuario actual
$currentUser = getCurrentUser();
?>

<!-- Favicon de Desarrollador -->
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iIzFFNDBBRiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHN0eWxlPi5jbHMtMXtmaWxsOiMxRTQwQUY7fS5jbHMtMntmaWxsOiMzQjgyRjY7fTwvc3R5bGU+CjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTkuNDEgMTYuNThMNC44MyAxMmw0LjU4LTQuNThMMTIgNmwtNiA2IDYgNi0yLjU5LTEuNDJ6bTUuMTcgMEwxOS4xNyAxMmwtNC41OS00LjU4TDE3IDZsNiA2LTYgNi0yLjU5LTEuNDJ6Ii8+CjxjaXJjbGUgY2xhc3M9ImNscy0yIiBjeD0iMTIiIGN5PSIyMCIgcj0iMiIvPgo8L3N2Zz4K">

<!-- ✅ DEFINIR VARIABLES GLOBALES JAVASCRIPT -->
<script>
    // Variables globales para JavaScript
    window.SITE_URL = '<?php echo SITE_URL; ?>';
    window.UPLOADS_URL = '<?php echo UPLOADS_URL; ?>';
    window.ASSETS_URL = '<?php echo ASSETS_URL; ?>';
    
    // Configuración global del carrito
    window.CART_CONFIG = {
        maxQuantity: 10,
        currency: '<?php echo Settings::get('currency_symbol', 'S/'); ?>',
        apiBase: '<?php echo SITE_URL; ?>/api/cart/'
    };
    
    // Usuario actual (si está logueado)
    window.CURRENT_USER = <?php echo isLoggedIn() ? json_encode([
        'id' => $currentUser['id'] ?? null,
        'name' => $currentUser['first_name'] ?? '',
        'email' => $currentUser['email'] ?? '',
        'logged_in' => true
    ]) : 'null'; ?>;
</script>

<header class="main-header">
    <!-- Quote Bar Simple -->
    <div class="quote-bar">
        <div class="container">
            <div class="quote-content-simple">
                <i class="fas fa-code quote-icon-simple"></i>
                <span class="quote-text-simple">"El mundo cambia... yo solo sigo programando."</span>
            </div>
        </div>
    </div>

    <!-- Top Bar Elegante -->
    <div class="top-bar d-none d-lg-block">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="contact-info-simple">
                        <span class="contact-item-simple">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo getSetting('site_email', 'admin@websystemspro.com'); ?>
                        </span>
                        <?php if (getSetting('contact_phone')): ?>
                            <span class="contact-item-simple">
                                <i class="fas fa-phone me-2"></i>
                                <?php echo getSetting('contact_phone'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="top-bar-right text-end">
                        <!-- Social Links Simple -->
                        <div class="social-links-simple">
                            <?php if (getSetting('facebook_url')): ?>
                                <a href="<?php echo getSetting('facebook_url'); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (getSetting('twitter_url')): ?>
                                <a href="<?php echo getSetting('twitter_url'); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (getSetting('linkedin_url')): ?>
                                <a href="<?php echo getSetting('linkedin_url'); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (getSetting('github_url')): ?>
                                <a href="<?php echo getSetting('github_url'); ?>" target="_blank" class="social-link">
                                    <i class="fab fa-github"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- User Menu Simple -->
                        <div class="user-menu-simple">
                            <?php if (isLoggedIn() && $currentUser): ?>
                                <div class="dropdown">
                                    <a href="#" class="user-link dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-user-circle me-2"></i>
                                        <?php echo htmlspecialchars($currentUser['first_name']); ?>
                                    </a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/dashboard"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/mis-compras"><i class="fas fa-shopping-bag me-2"></i>Mis Compras</a></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/perfil"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/configuracion"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/login" class="user-link">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </a>
                                <a href="<?php echo SITE_URL; ?>/register" class="user-link">
                                    <i class="fas fa-user-plus me-2"></i>Registrarse
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Navigation Simple -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <!-- Logo Simple -->
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <?php if ($siteLogo): ?>
                    <img src="<?php echo UPLOADS_URL; ?>/logos/<?php echo $siteLogo; ?>" alt="<?php echo htmlspecialchars($siteName); ?>" class="logo">
                <?php else: ?>
                    <span class="logo-text"><?php echo htmlspecialchars($siteName); ?></span>
                <?php endif; ?>
            </a>
            
            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($mainMenuItems as $item): ?>
                        <li class="nav-item <?php echo !empty($subMenus[$item['id']]) ? 'dropdown' : ''; ?>">
                            <?php if (!empty($subMenus[$item['id']])): ?>
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <?php if ($item['icon']): ?>
                                        <i class="<?php echo $item['icon']; ?> me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <?php foreach ($subMenus[$item['id']] as $subItem): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo processMenuUrl($subItem['url']); ?>" <?php echo $subItem['target'] == '_blank' ? 'target="_blank"' : ''; ?>>
                                                <?php if ($subItem['icon']): ?>
                                                    <i class="<?php echo $subItem['icon']; ?> me-2"></i>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($subItem['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <a class="nav-link" href="<?php echo processMenuUrl($item['url']); ?>" <?php echo $item['target'] == '_blank' ? 'target="_blank"' : ''; ?>>
                                    <?php if ($item['icon']): ?>
                                        <i class="<?php echo $item['icon']; ?> me-1"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <!-- Search & Cart Simple -->
                <div class="navbar-actions d-flex align-items-center">
                    <!-- Search -->
                    <div class="search-box me-3">
                        <form class="d-flex" action="<?php echo SITE_URL; ?>/buscar" method="GET">
                            <div class="input-group">
                                <input class="form-control" type="search" placeholder="Buscar productos..." name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Cart -->
                    <div class="cart-icon">
                        <button type="button" class="btn btn-outline-primary position-relative" onclick="openCartModal(event)">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count" style="display: none;">
                                0
                            </span>
                        </button>
                    </div>
                    
                    <!-- Mobile User Menu -->
                    <div class="mobile-user d-lg-none ms-3">
                        <?php if (isLoggedIn()): ?>
                            <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-sm btn-primary">
                                <i class="fas fa-user"></i>
                            </a>
                        <?php else: ?>
                            <a href="<?php echo SITE_URL; ?>/login" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-sign-in-alt"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<!-- Modal del Carrito -->
<?php 
// Incluir el carrito solo si existe
if (file_exists(__DIR__ . '/cart_modal.php')) {
    include __DIR__ . '/cart_modal.php';
}
?>