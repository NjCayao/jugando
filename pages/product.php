<?php
// pages/product.php - Página individual de producto
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

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

try {
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_reviews,
            AVG(rating) as avg_rating
        FROM product_reviews
        WHERE product_id = ? AND is_approved = 1
    ");
    $stmt->execute([$product['id']]);
    $reviewStats = $stmt->fetch();

    $totalReviews = $reviewStats['total_reviews'] ?? 0;
    $averageRating = $reviewStats['avg_rating'] ? round($reviewStats['avg_rating'], 1) : 0;
} catch (Exception $e) {
    $totalReviews = 0;
    $averageRating = 0;
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

    <link href="<?php echo ASSETS_URL; ?>/css/reviews.css" rel="stylesheet">
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
                                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                                    <i class="fas fa-star me-2"></i>Reseñas (<span id="totalReviewsCount">0</span>)
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
                            <div class="tab-pane fade" id="reviews" role="tabpanel">
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php if (empty($versions)):
                                        $productId = $product['id'];
                                        $productName = $product['name'];
                                        include __DIR__ . '/../includes/components/product_reviews.php';
                                    ?>
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
                                <?php 
    // DEBUG TEMPORAL
    if (isLoggedIn()) {
        $debugUserId = $_SESSION[SESSION_NAME]['user_id'];
        $debugProductId = $product['id'];
        
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin-bottom: 20px;'>";
        echo "<h5>🔍 DEBUG INFO:</h5>";
        echo "Usuario logueado: SÍ (ID: $debugUserId)<br>";
        echo "Producto actual: ID $debugProductId<br>";
        
        // Verificar compra
        $checkStmt = $db->prepare("
            SELECT o.id, o.order_number, oi.product_name 
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = ? AND oi.product_id = ? AND o.payment_status = 'completed'
        ");
        $checkStmt->execute([$debugUserId, $debugProductId]);
        $purchase = $checkStmt->fetch();
        
        if ($purchase) {
            echo "✅ Compra encontrada: Orden #{$purchase['order_number']}<br>";
            
            // Verificar reseña existente
            $reviewStmt = $db->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
            $reviewStmt->execute([$debugUserId, $debugProductId]);
            $existingReview = $reviewStmt->fetch();
            
            if ($existingReview) {
                echo "❌ Ya existe reseña (ID: {$existingReview['id']})<br>";
            } else {
                echo "✅ No hay reseña - DEBERÍA APARECER EL BOTÓN<br>";
            }
        } else {
            echo "❌ No se encontró compra de este producto<br>";
            echo "Nota: Compraste el producto ID 3 (CRM Empresarial)<br>";
        }
        echo "</div>";
    }
    ?>
                                <?php
                                // Variables necesarias para el componente
                                $productId = $product['id'];
                                $productName = $product['name'];

                                // Incluir el widget completo de reseñas
                                if (file_exists(__DIR__ . '/../includes/components/product_reviews.php')) {
                                    include __DIR__ . '/../includes/components/product_reviews.php';
                                } else {
                                    // Si el archivo no existe, mostrar estructura básica
                                ?>
                                    <div class="product-reviews-section">
                                        <!-- Verificar si puede escribir reseña -->
                                        <?php if (isLoggedIn()): ?>

                                            <?php
// DEBUG - Ver qué está pasando
echo "<div class='alert alert-warning'>";
echo "<h5>DEBUG - Verificación de Compra:</h5>";

$userId = $_SESSION[SESSION_NAME]['user_id'];
echo "User ID: $userId<br>";
echo "Product ID: $productId<br>";

// Verificar compra manualmente
$testStmt = $db->prepare("
    SELECT o.id as order_id, o.order_number, oi.product_name
    FROM orders o
    INNER JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ? 
    AND oi.product_id = ?
    AND o.payment_status = 'completed'
");
$testStmt->execute([$userId, $productId]);
$testPurchase = $testStmt->fetch();

if ($testPurchase) {
    echo "✅ COMPRA ENCONTRADA: Order #{$testPurchase['order_number']}<br>";
    echo "Producto: {$testPurchase['product_name']}<br>";
} else {
    echo "❌ NO SE ENCONTRÓ COMPRA<br>";
}

// Verificar si hay reseña
$testStmt2 = $db->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
$testStmt2->execute([$userId, $productId]);
$testReview = $testStmt2->fetch();

if ($testReview) {
    echo "❌ YA EXISTE RESEÑA ID: {$testReview['id']}<br>";
} else {
    echo "✅ NO HAY RESEÑA - PUEDE COMENTAR<br>";
}

echo "</div>";
?>

                                            <?php
                                            // Verificar si compró el producto
                                            try {
                                                $userId = $_SESSION[SESSION_NAME]['user_id'];
                                                $stmt = $db->prepare("
                                                SELECT o.id as order_id
                                                FROM orders o
                                                INNER JOIN order_items oi ON o.id = oi.order_id
                                                WHERE o.user_id = ? 
                                                AND oi.product_id = ?
                                                AND o.payment_status = 'completed'
                                                LIMIT 1
                                            ");
                                                $stmt->execute([$userId, $productId]);
                                                $hasPurchased = $stmt->fetch();

                                                // Verificar si ya dejó reseña
                                                $stmt = $db->prepare("
                        SELECT id FROM product_reviews 
                        WHERE user_id = ? AND product_id = ?
                    ");
                                                $stmt->execute([$userId, $productId]);
                                                $hasReviewed = $stmt->fetch();
                                            } catch (Exception $e) {
                                                $hasPurchased = false;
                                                $hasReviewed = false;
                                            }
                                            ?>

                                            <?php if ($hasPurchased && !$hasReviewed): ?>
                                                <!-- MOSTRAR BOTÓN Y FORMULARIO -->
                                                <div class="text-center mb-4">
                                                    <button class="btn btn-primary btn-lg" onclick="showReviewForm()">
                                                        <i class="fas fa-edit me-2"></i>Escribir Reseña
                                                    </button>
                                                </div>

                                                <!-- Formulario de Reseña (oculto por defecto) -->
                                                <div id="reviewFormContainer" style="display: none;">
                                                    <div class="dashboard-section mb-4">
                                                        <div class="section-header-compact">
                                                            <h4 class="section-title-compact mb-0">
                                                                <i class="fas fa-star me-2"></i>Escribe tu Reseña
                                                            </h4>
                                                        </div>
                                                        <div class="section-body-compact">
                                                            <form id="reviewForm" onsubmit="submitReview(event)">
                                                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">

                                                                <!-- Rating Stars -->
                                                                <div class="mb-4">
                                                                    <label class="form-label fw-bold">Tu Calificación</label>
                                                                    <div class="rating-input">
                                                                        <div class="stars-container" style="font-size: 2rem; color: #ddd;">
                                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                                <i class="fas fa-star star-rating" data-rating="<?php echo $i; ?>"
                                                                                    style="cursor: pointer; margin-right: 5px;"></i>
                                                                            <?php endfor; ?>
                                                                        </div>
                                                                        <input type="hidden" name="rating" id="ratingInput" value="0" required>
                                                                        <div class="invalid-feedback">Por favor selecciona una calificación</div>
                                                                    </div>
                                                                </div>

                                                                <!-- Comment -->
                                                                <div class="mb-4">
                                                                    <label for="reviewComment" class="form-label fw-bold">Tu Comentario</label>
                                                                    <textarea
                                                                        class="form-control"
                                                                        id="reviewComment"
                                                                        name="comment"
                                                                        rows="5"
                                                                        placeholder="Comparte tu experiencia con este producto..."
                                                                        minlength="20"
                                                                        maxlength="1000"
                                                                        required></textarea>
                                                                    <div class="form-text">
                                                                        <span id="charCount">0</span> / 1000 caracteres (mínimo 20)
                                                                    </div>
                                                                </div>

                                                                <!-- Show Name -->
                                                                <div class="mb-4">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" id="showName"
                                                                            name="show_name" value="1" checked>
                                                                        <label class="form-check-label" for="showName">
                                                                            Mostrar mi nombre en la reseña
                                                                        </label>
                                                                    </div>
                                                                </div>

                                                                <!-- Submit -->
                                                                <div class="d-flex justify-content-between">
                                                                    <button type="button" class="btn btn-secondary" onclick="hideReviewForm()">
                                                                        Cancelar
                                                                    </button>
                                                                    <button type="submit" class="btn btn-primary">
                                                                        <i class="fas fa-paper-plane me-2"></i>Enviar Reseña
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>

                                            <?php elseif ($hasReviewed): ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-check-circle me-2"></i>
                                                    Ya has dejado una reseña para este producto.
                                                </div>
                                            <?php elseif (!$hasPurchased): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-shopping-cart me-2"></i>
                                                    Debes comprar este producto para poder dejar una reseña.
                                                </div>
                                            <?php endif; ?>

                                        <?php else: ?>
                                            <!-- Usuario no logueado -->
                                            <div class="text-center mb-4">
                                                <p class="text-muted mb-3">Inicia sesión para dejar tu reseña</p>
                                                <a href="<?php echo SITE_URL; ?>/login?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                                    class="btn btn-primary">
                                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Lista de Reseñas -->
                                        <div id="reviewsList">
                                            <?php
                                            // Mostrar reseñas aprobadas
                                            try {
                                                $stmt = $db->prepare("
                                                SELECT pr.*, u.first_name, u.last_name
                                                FROM product_reviews pr
                                                JOIN users u ON pr.user_id = u.id
                                                WHERE pr.product_id = ? AND pr.is_approved = 1
                                                ORDER BY pr.is_featured DESC, pr.created_at DESC
                                            ");
                                                $stmt->execute([$productId]);
                                                $reviews = $stmt->fetchAll();

                                                if (empty($reviews)) {
                                                    echo '<div class="text-center py-5">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No hay reseñas aún. ¡Sé el primero en compartir tu experiencia!</p>
                              </div>';
                                                } else {
                                                    foreach ($reviews as $review) {
                                                        $displayName = $review['show_name']
                                                            ? $review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.'
                                                            : 'Usuario Anónimo';
                                            ?>
                                                        <div class="review-item mb-3 p-3 border rounded">
                                                            <div class="d-flex justify-content-between mb-2">
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($displayName); ?></strong>
                                                                    <div class="text-warning">
                                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                            <?php if ($i <= $review['rating']): ?>
                                                                                <i class="fas fa-star"></i>
                                                                            <?php else: ?>
                                                                                <i class="far fa-star"></i>
                                                                            <?php endif; ?>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                </div>
                                                                <small class="text-muted"><?php echo timeAgo($review['created_at']); ?></small>
                                                            </div>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                                        </div>
                                            <?php
                                                    }
                                                }
                                            } catch (Exception $e) {
                                                echo '<div class="alert alert-danger">Error al cargar las reseñas</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                <?php
                                }
                                ?>
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

                                    <button class="btn btn-warning" onclick="donateCoffee(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-coffee me-2"></i> Donar un Café al Dev
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

    <script>
        function donateCoffee(productId) {
            // Redirigir a página de donación con el producto como referencia
            window.location.href = window.SITE_URL + '/donar-cafe?producto=' + productId;
        }
    </script>


    <script>
        // Actualizar el contador de reseñas en la pestaña cuando se carguen
        document.addEventListener('DOMContentLoaded', function() {
            // Observer para detectar cuando se actualiza el total de reseñas
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        const totalReviews = document.getElementById('totalReviews');
                        if (totalReviews) {
                            document.getElementById('totalReviewsCount').textContent = totalReviews.textContent;
                        }
                    }
                });
            });

            const targetNode = document.getElementById('totalReviews');
            if (targetNode) {
                observer.observe(targetNode, {
                    childList: true
                });
            }
        });

        // Función helper para mostrar notificaciones (si no existe showCartNotification)
        if (typeof showCartNotification === 'undefined') {
            function showCartNotification(message, type = 'success') {
                const alertClass = type === 'error' ? 'alert-danger' :
                    type === 'warning' ? 'alert-warning' :
                    'alert-success';

                const alert = document.createElement('div');
                alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
                alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 350px;';
                alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

                document.body.appendChild(alert);

                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
        }
    </script>

    <script>
        // Manejo de estrellas
        document.querySelectorAll('.star-rating').forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = index + 1;
                document.getElementById('ratingInput').value = rating;
                updateStars(rating);
            });

            star.addEventListener('mouseenter', function() {
                const rating = index + 1;
                updateStarsHover(rating);
            });
        });

        document.querySelector('.stars-container')?.addEventListener('mouseleave', function() {
            const currentRating = document.getElementById('ratingInput').value;
            updateStars(currentRating);
        });

        function updateStars(rating) {
            document.querySelectorAll('.star-rating').forEach((star, index) => {
                if (index < rating) {
                    star.style.color = '#ffc107';
                } else {
                    star.style.color = '#ddd';
                }
            });
        }

        function updateStarsHover(rating) {
            document.querySelectorAll('.star-rating').forEach((star, index) => {
                if (index < rating) {
                    star.style.color = '#ffc107';
                } else {
                    star.style.color = '#ddd';
                }
            });
        }

        // Contador de caracteres
        document.getElementById('reviewComment')?.addEventListener('input', function() {
            document.getElementById('charCount').textContent = this.value.length;
        });

        // Mostrar/ocultar formulario
        function showReviewForm() {
            document.getElementById('reviewFormContainer').style.display = 'block';
            document.getElementById('reviewFormContainer').scrollIntoView({
                behavior: 'smooth'
            });
        }

        function hideReviewForm() {
            document.getElementById('reviewFormContainer').style.display = 'none';
        }

        // Enviar reseña
        function submitReview(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            // Validar rating
            if (formData.get('rating') == '0') {
                alert('Por favor selecciona una calificación');
                return;
            }

            // Enviar vía AJAX
            fetch(window.SITE_URL + '/api/reviews/add_review.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Reseña enviada exitosamente');
                        form.reset();
                        hideReviewForm();
                        location.reload(); // Recargar para actualizar
                    } else {
                        alert(data.message || 'Error al enviar la reseña');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al enviar la reseña');
                });
        }
    </script>
</body>

</html>