<?php
// pages/product.php - Página individual de producto
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener slug del producto
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Obtener producto
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.slug = ? AND p.is_active = 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        header("HTTP/1.0 404 Not Found");
        include '../404.php';
        exit;
    }

    // Obtener versiones del producto
    $stmt = $db->prepare("
        SELECT version, changelog, created_at, is_current
        FROM product_versions 
        WHERE product_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$product['id']]);
    $versions = $stmt->fetchAll();

    // Obtener productos relacionados (misma categoría)
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1 
        ORDER BY RAND() 
        LIMIT 4
    ");
    $stmt->execute([$product['category_id'], $product['id']]);
    $relatedProducts = $stmt->fetchAll();

    // Incrementar contador de vistas
    $stmt = $db->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$product['id']]);

    // Obtener reseñas (implementar más adelante)
    $reviews = [];
    $averageRating = 0;
    $totalReviews = 0;
} catch (Exception $e) {
    logError("Error en página de producto: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../500.php';
    exit;
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = $product['meta_title'] ?: $product['name'];
$pageDescription = $product['meta_description'] ?: $product['short_description'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>

    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($product['name'] . ', ' . $product['category_name']); ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="product">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/producto/<?php echo $product['slug']; ?>">
    <?php if ($product['image']): ?>
        <meta property="og:image" content="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>">
    <?php endif; ?>

    <!-- Schema.org -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org/",
            "@type": "SoftwareApplication",
            "name": "<?php echo htmlspecialchars($product['name']); ?>",
            "description": "<?php echo htmlspecialchars($pageDescription); ?>",
            "applicationCategory": "<?php echo htmlspecialchars($product['category_name']); ?>",
            "operatingSystem": "Web, Windows, Linux, Mac",
            "offers": {
                "@type": "Offer",
                "price": "<?php echo $product['is_free'] ? '0' : $product['price']; ?>",
                "priceCurrency": "USD",
                "availability": "https://schema.org/InStock"
            }
            <?php if ($product['image']): ?>,
                "image": "<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>"
            <?php endif; ?>
        }
    </script>

    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Breadcrumb -->
    <div class="container mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/productos">Productos</a></li>
                <?php if ($product['category_name']): ?>
                    <li class="breadcrumb-item">
                        <a href="<?php echo SITE_URL; ?>/productos?categoria=<?php echo $product['category_slug']; ?>">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
            </ol>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <div class="row">
            <!-- Contenido Principal -->
            <div class="col-lg-8">
                <!-- Información del Producto -->
                <div class="dashboard-section mb-4">
                    <div class="section-header-compact">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <?php if ($product['category_name']): ?>
                                    <span class="product-category mb-2 d-block"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                <?php endif; ?>
                                <h1 class="section-title-compact mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                                <p class="lead text-muted mb-0"><?php echo htmlspecialchars($product['short_description']); ?></p>
                            </div>
                            <div class="ms-3">
                                <?php if ($product['is_free']): ?>
                                    <span class="product-badge free">GRATIS</span>
                                <?php endif; ?>
                                <?php if ($product['is_featured']): ?>
                                    <span class="badge bg-warning ms-2">DESTACADO</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Galería del Producto -->
                <div class="product-card mb-4">
                    <div class="product-image" style="height: 300px;">
                        <?php if ($product['image']): ?>
                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $product['image']; ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                                class="img-fluid w-100 h-100" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="no-image">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Descripción Principal -->
                <div class="dashboard-section mb-4">
                    <div class="section-header-compact">
                        <h3 class="section-title-compact mb-0">
                            <i class="fas fa-info-circle me-2"></i>Descripción del Producto
                        </h3>
                    </div>
                    <div class="section-body-compact">
                        <div class="user-content">
                            <?php if ($product['description']): ?>
                                <div class="product-description mb-4">
                                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No hay descripción detallada disponible.</p>
                            <?php endif; ?>

                            <!-- Características Principales -->
                            <div class="mt-4">
                                <h5>✨ Características Principales</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-check text-success me-3"></i> Código fuente completo incluido</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-3"></i> Documentación detallada</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-3"></i> Soporte técnico incluido</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-3"></i> Actualizaciones por <?php echo $product['update_months']; ?> meses</li>
                                    <li class="mb-2"><i class="fas fa-check text-success me-3"></i> Hasta <?php echo $product['download_limit']; ?> descargas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs de Información Adicional -->
                <div class="dashboard-section">
                    <div class="section-body-compact">
                        <ul class="nav nav-tabs mb-4" id="productTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="versions-tab" data-bs-toggle="tab" data-bs-target="#versions" type="button">
                                    <i class="fas fa-code-branch me-2"></i>Versiones (<?php echo count($versions); ?>)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                                    <i class="fas fa-star me-2"></i>Reseñas (<?php echo $totalReviews; ?>)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="productTabsContent">
                            <!-- Versiones -->
                            <div class="tab-pane fade show active" id="versions" role="tabpanel">
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($versions)): ?>
                                        <div class="empty-state-compact">
                                            <div class="empty-icon-compact">
                                                <i class="fas fa-code-branch"></i>
                                            </div>
                                            <p>No hay versiones disponibles aún.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($versions as $version): ?>
                                            <div class="user-product-item mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0 product-title-compact">
                                                        Versión <?php echo htmlspecialchars($version['version']); ?>
                                                        <?php if ($version['is_current']): ?>
                                                            <span class="badge bg-success ms-2">Actual</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($version['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <?php if ($version['changelog']): ?>
                                                    <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($version['changelog'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Reseñas -->
                            <div class="tab-pane fade" id="reviews" role="tabpanel">
                                <div class="empty-state-compact">
                                    <div class="empty-icon-compact">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <p>Sistema de reseñas próximamente disponible.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar de Compra -->
            <div class="col-lg-4">
                <div class="sticky-purchase">
                    <!-- Precio y Compra -->
                    <div class="sidebar-card-compact">
                        <div class="sidebar-header-compact">
                            <h5 class="mb-0">
                                <i class="fas fa-shopping-cart me-2"></i>Información de Compra
                            </h5>
                        </div>
                        <div class="sidebar-body-compact text-center">
                            <?php if ($product['is_free']): ?>
                                <div class="price-free mb-3">GRATIS</div>
                                <p class="text-muted mb-3">Descarga gratuita</p>
                            <?php else: ?>
                                <div class="price mb-3"><?php echo formatPrice($product['price']); ?></div>
                                <p class="text-muted mb-3">Pago único - Sin suscripciones</p>
                            <?php endif; ?>

                            <div class="d-grid gap-2">
                                <?php if ($product['is_free']): ?>
                                    <button class="btn btn-success btn-lg" onclick="downloadFree(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-download me-2"></i>Descargar Gratis
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-lg" onclick="addToCart(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-cart-plus me-2"></i>Agregar al Carrito
                                    </button>
                                    <button class="btn btn-success btn-lg" onclick="buyNow(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-credit-card me-2"></i>Comprar Ahora
                                    </button>
                                <?php endif; ?>

                                <button class="btn btn-outline-secondary" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-heart me-2"></i>Agregar a Favoritos
                                </button>

                                <?php if ($product['demo_url']): ?>
                                    <a href="<?php echo htmlspecialchars($product['demo_url']); ?>" target="_blank" class="btn btn-outline-primary">
                                        <i class="fas fa-external-link-alt me-2"></i>Ver Demo
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Producto -->
                    <div class="sidebar-card-compact">
                        <div class="sidebar-header-compact">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>Detalles del Producto
                            </h6>
                        </div>
                        <div class="sidebar-body-compact">
                            <div class="row g-0">
                                <div class="col-6">
                                    <div class="text-center p-2 border-end">
                                        <div class="stats-number-compact text-primary"><?php echo count($versions); ?></div>
                                        <div class="stats-label-compact">Versiones</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2">
                                        <div class="stats-number-compact text-success"><?php echo number_format($product['download_count']); ?></div>
                                        <div class="stats-label-compact">Descargas</div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Categoría:</span>
                                <span class="fw-bold"><?php echo htmlspecialchars($product['category_name'] ?: 'Sin categoría'); ?></span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Vistas:</span>
                                <span class="fw-bold"><?php echo number_format($product['view_count']); ?></span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Publicado:</span>
                                <span class="fw-bold"><?php echo date('d/m/Y', strtotime($product['created_at'])); ?></span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted small">Actualizado:</span>
                                <span class="fw-bold"><?php echo date('d/m/Y', strtotime($product['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Compartir -->
                    <div class="sidebar-card-compact">
                        <div class="sidebar-header-compact">
                            <h6 class="mb-0">
                                <i class="fas fa-share-alt me-2"></i>Compartir
                            </h6>
                        </div>
                        <div class="sidebar-body-compact text-center">
                            <div class="footer-social">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/producto/' . $product['slug']); ?>"
                                    target="_blank" class="social-link">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/producto/' . $product['slug']); ?>&text=<?php echo urlencode($product['name']); ?>"
                                    target="_blank" class="social-link">
                                    <i class="fab fa-twitter"></i>
                                </a>
                                <a href="https://wa.me/?text=<?php echo urlencode($product['name'] . ' - ' . SITE_URL . '/producto/' . $product['slug']); ?>"
                                    target="_blank" class="social-link">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos Relacionados -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="crystal-banners-section py-5 mt-5">
                <div class="container">
                    <div class="text-center mb-5">
                        <h3 class="crystal-title">Productos Relacionados</h3>
                        <div class="crystal-divider"></div>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($relatedProducts as $relatedProduct): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="product-card h-100">
                                    <div class="product-image">
                                        <?php if ($relatedProduct['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $relatedProduct['image']; ?>"
                                                alt="<?php echo htmlspecialchars($relatedProduct['name']); ?>">
                                        <?php else: ?>
                                            <div class="no-image">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="product-overlay">
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $relatedProduct['slug']; ?>" class="btn btn-primary btn-sm">Ver</a>
                                        </div>
                                    </div>
                                    <div class="product-info">
                                        <h6 class="product-title">
                                            <a href="<?php echo SITE_URL; ?>/producto/<?php echo $relatedProduct['slug']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($relatedProduct['name']); ?>
                                            </a>
                                        </h6>
                                        <div class="product-footer">
                                            <div class="product-price">
                                                <?php if ($relatedProduct['is_free']): ?>
                                                    <span class="price-free">GRATIS</span>
                                                <?php else: ?>
                                                    <span class="price"><?php echo formatPrice($relatedProduct['price']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
        // Auto-submit filtros
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.name === 'categoria' || this.name === 'tipo') {
                    document.getElementById('filterForm').submit();
                }
            });
        });

        // Prevenir envío de formulario vacío
        document.querySelector('form.search-box-large')?.addEventListener('submit', function(e) {
            const input = this.querySelector('input[name="q"]');
            if (input.value.trim().length < 2) {
                e.preventDefault();
                alert('Ingresa al menos 2 caracteres para buscar');
                input.focus();
            }
        });

        // Limpiar destacados al cambiar búsqueda
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.addEventListener('focus', function() {
                this.select();
            });
        }

        // Guardar texto original de botones
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('button[onclick*="addToCart"]').forEach(button => {
                button.dataset.originalText = button.innerHTML;
            });
        });
    </script>
</body>

</html>