<?php
// pages/cart.php - Página completa del carrito (MEJORADA)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/cart.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener datos del carrito
$cartItems = Cart::getItems();
$cartTotals = Cart::getTotals();
$cartEmpty = Cart::isEmpty();

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Revisa los productos en tu carrito de compras">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Hero Header del Carrito -->
    <div class="hero-cards-section py-5 mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="hero-card-content text-center text-lg-start">
                        <h1 class="luxury-title mb-3">
                            <i class="fas fa-shopping-cart me-3"></i>
                            Tu Carrito de Compras
                        </h1>
                        <div class="luxury-divider mx-auto mx-lg-0 mb-4"></div>
                        <?php if (!$cartEmpty): ?>
                            <p class="hero-description mb-0">
                                Tienes <strong><?php echo $cartTotals['items_count']; ?></strong> productos seleccionados por un total de <strong><?php echo formatPrice($cartTotals['total']); ?></strong>
                            </p>
                        <?php else: ?>
                            <p class="hero-description mb-0">
                                Tu carrito está esperando productos increíbles
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="hero-luxury-card">
                        <div class="hero-card-inner">
                            <div class="hero-card-glow"></div>
                            <div class="hero-card-content">
                                <div class="hero-card-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h4 class="hero-card-title">Compra Segura</h4>
                                <p class="hero-card-description mb-0">
                                    Protección total con SSL y métodos de pago seguros
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Breadcrumb Elegante -->
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-4">
            <div class="dashboard-section">
                <div class="section-body-compact">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                                <i class="fas fa-home me-1"></i>Inicio
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            <i class="fas fa-shopping-cart me-1"></i>Carrito de Compras
                        </li>
                    </ol>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="container cart-page mb-5">
        <?php if ($cartEmpty): ?>
            <!-- Carrito Vacío Mejorado -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="crystal-banners-section py-5">
                        <div class="crystal-card">
                            <div class="crystal-inner text-center">
                                <div class="crystal-glow"></div>
                                <div class="crystal-content">
                                    <div class="empty-icon-compact mb-4">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <h3 class="crystal-title-small mb-3">Tu carrito está vacío</h3>
                                    <p class="crystal-description mb-4">
                                        ¡Descubre nuestros increíbles productos y sistemas! 
                                        Tenemos soluciones perfectas esperándote.
                                    </p>
                                    <div class="row g-3 justify-content-center">
                                        <div class="col-auto">
                                            <a href="<?php echo SITE_URL; ?>/productos" class="crystal-btn">
                                                <i class="fas fa-search me-2"></i>Explorar Productos
                                            </a>
                                        </div>
                                        <div class="col-auto">
                                            <a href="<?php echo SITE_URL; ?>/categorias" class="btn btn-outline-light">
                                                <i class="fas fa-th-large me-2"></i>Ver Categorías
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Columna Principal: Items del Carrito -->
                <div class="col-lg-8">
                    <!-- Header de la Lista -->
                    <div class="dashboard-section mb-4">
                        <div class="section-header-compact">
                            <h3 class="section-title-compact mb-0">
                                <div class="section-icon-compact">
                                    <i class="fas fa-list"></i>
                                </div>
                                Productos en tu Carrito
                                <span class="badge bg-primary ms-3"><span class="items-count"><?php echo $cartTotals['items_count']; ?></span> items</span>
                            </h3>
                        </div>
                    </div>

                    <!-- Lista de Productos -->
                    <div class="cart-items">
                        <?php foreach ($cartItems as $productId => $item): ?>
                            <div class="dashboard-section mb-3" data-product-id="<?php echo $productId; ?>">
                                <div class="section-body-compact">
                                    <div class="user-product-item">
                                        <div class="d-flex align-items-start">
                                            <!-- Imagen del Producto -->
                                            <div class="product-image-compact me-3">
                                                <?php if ($item['image']): ?>
                                                    <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                         class="w-100 h-100" style="object-fit: cover; border-radius: 6px;">
                                                <?php else: ?>
                                                    <i class="fas fa-image text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Información del Producto -->
                                            <div class="product-info-compact flex-grow-1">
                                                <h5 class="product-title-compact mb-2">
                                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['slug']; ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                </h5>
                                                
                                                <?php if ($item['category_name']): ?>
                                                    <div class="product-meta-compact mb-2">
                                                        <i class="fas fa-tag text-primary me-1"></i>
                                                        <span class="text-muted"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="license-info-compact">
                                                    <div class="row g-2 align-items-center">
                                                        <!-- Precio Unitario -->
                                                        <div class="col-md-3">
                                                            <small class="text-muted d-block">Precio unitario:</small>
                                                            <?php if ($item['is_free']): ?>
                                                                <span class="badge bg-success" data-unit-price="0">GRATIS</span>
                                                            <?php else: ?>
                                                                <strong class="text-primary" data-unit-price="<?php echo $item['price']; ?>"><?php echo formatPrice($item['price']); ?></strong>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Controles de Cantidad -->
                                                        <div class="col-md-4">
                                                            <small class="text-muted d-block mb-1">Cantidad:</small>
                                                            <div class="input-group input-group-sm">
                                                                <button class="btn btn-outline-primary quantity-btn" type="button" 
                                                                        data-action="decrease" data-product-id="<?php echo $productId; ?>"
                                                                        title="Disminuir cantidad">
                                                                    <i class="fas fa-minus"></i>
                                                                </button>
                                                                <input type="number" class="form-control quantity-input text-center" 
                                                                       value="<?php echo $item['quantity']; ?>" min="1" max="10" 
                                                                       data-product-id="<?php echo $productId; ?>"
                                                                       aria-label="Cantidad del producto">
                                                                <button class="btn btn-outline-primary quantity-btn" type="button" 
                                                                        data-action="increase" data-product-id="<?php echo $productId; ?>"
                                                                        title="Aumentar cantidad">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        
                                                        <!-- Subtotal -->
                                                        <div class="col-md-3">
                                                            <small class="text-muted d-block">Subtotal:</small>
                                                            <?php if ($item['is_free']): ?>
                                                                <span class="badge bg-success product-subtotal">GRATIS</span>
                                                            <?php else: ?>
                                                                <strong class="text-success product-subtotal"><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong>
                                                                <div class="per-unit-price" style="<?php echo $item['quantity'] > 1 ? '' : 'display: none;'; ?>">
                                                                    <small class="text-muted"><?php echo formatPrice($item['price']); ?> c/u</small>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <!-- Acciones -->
                                                        <div class="col-md-2 text-end">
                                                            <button class="btn btn-outline-danger btn-sm remove-item" 
                                                                    data-product-id="<?php echo $productId; ?>" 
                                                                    title="Eliminar del carrito">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Acciones del Carrito -->
                    <div class="dashboard-section">
                        <div class="section-body-compact">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-arrow-left me-2"></i>Continuar Comprando
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <button type="button" class="btn btn-outline-danger w-100" onclick="clearCart()">
                                        <i class="fas fa-trash me-2"></i>Vaciar Carrito
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar: Resumen del Carrito -->
                <div class="col-lg-4">
                    <!-- Resumen del Pedido -->
                    <div class="sidebar-card-compact mb-4">
                        <div class="sidebar-header-compact">
                            <h4 class="mb-0">
                                <i class="fas fa-calculator me-2"></i>Resumen del Pedido
                            </h4>
                        </div>
                        <div class="sidebar-body-compact">
                            <!-- Desglose de precios -->
                            <div class="order-item-compact">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Productos (<span class="items-count"><?php echo $cartTotals['items_count']; ?></span>):</span>
                                    <span class="cart-subtotal">
                                        <?php if ($cartTotals['subtotal'] > 0): ?>
                                            <?php echo formatPrice($cartTotals['subtotal']); ?>
                                        <?php else: ?>
                                            <span class="text-success">GRATIS</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($cartTotals['tax'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Impuestos (<?php echo $cartTotals['tax_rate']; ?>%):</span>
                                        <span class="cart-tax"><?php echo formatPrice($cartTotals['tax']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between">
                                    <h5>Total Final:</h5>
                                    <h5 class="text-success cart-total"><?php echo formatPrice($cartTotals['total']); ?></h5>
                                </div>
                            </div>
                            
                            <!-- Botón de Checkout -->
                            <div class="d-grid mb-3">
                                <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-corporate btn-lg">
                                    <i class="fas fa-credit-card me-2"></i>
                                    <?php echo $cartTotals['total'] > 0 ? 'Proceder al Pago' : 'Confirmar Pedido Gratuito'; ?>
                                </a>
                            </div>
                            
                            <!-- Información de Seguridad -->
                            <div class="text-center">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1 text-success"></i>
                                    Compra 100% segura y protegida
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Beneficios Incluidos -->
                    <div class="sidebar-card-compact">
                        <div class="sidebar-header-compact">
                            <h5 class="mb-0">
                                <i class="fas fa-gift me-2"></i>Lo que Incluye tu Compra
                            </h5>
                        </div>
                        <div class="sidebar-body-compact">
                            <div class="empty-state-compact py-2">
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-2">
                                        <i class="fas fa-download text-primary me-2"></i>
                                        <small>Descarga inmediata</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-code text-primary me-2"></i>
                                        <small>Código fuente completo</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-file-alt text-primary me-2"></i>
                                        <small>Documentación incluida</small>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-sync text-primary me-2"></i>
                                        <small><?php echo DEFAULT_UPDATE_MONTHS; ?> meses de actualizaciones</small>
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-life-ring text-primary me-2"></i>
                                        <small>Soporte técnico profesional</small>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Los event listeners para el carrito ya están en main.js
            updateCartDisplay();
            
            // Añadir efectos visuales
            const cartItems = document.querySelectorAll('.dashboard-section[data-product-id]');
            cartItems.forEach((item, index) => {
                item.style.animationDelay = `${index * 0.1}s`;
                item.classList.add('fade-in-up');
            });
        });
        
        function clearCart() {
            if (!confirm('¿Estás seguro de que quieres vaciar todo el carrito?')) {
                return;
            }
            
            // Añadir efecto de carga
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Vaciando...';
            button.disabled = true;
            
            fetch('/api/cart/clear.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al vaciar el carrito');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        // Almacenar precios unitarios para cálculos
        const productPrices = {};
        
        // Inicializar precios al cargar la página
        document.querySelectorAll('[data-product-id]').forEach(function(item) {
            const productId = item.dataset.productId;
            const priceElement = item.querySelector('[data-unit-price]');
            if (priceElement) {
                productPrices[productId] = parseFloat(priceElement.dataset.unitPrice);
            }
        });
        
        // Mejorar experiencia de cantidad
        document.addEventListener('click', function(e) {
            if (e.target.closest('.quantity-btn')) {
                const btn = e.target.closest('.quantity-btn');
                const input = btn.parentElement.querySelector('.quantity-input');
                const productId = btn.dataset.productId;
                const action = btn.dataset.action;
                
                // Efecto visual
                btn.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    btn.style.transform = 'scale(1)';
                }, 150);
                
                let newValue = parseInt(input.value);
                if (action === 'increase' && newValue < 10) {
                    newValue++;
                } else if (action === 'decrease' && newValue > 1) {
                    newValue--;
                }
                
                input.value = newValue;
                
                // Actualizar precios inmediatamente
                updateProductPrice(productId, newValue);
                updateCartTotals();
                
                // Actualizar carrito en el servidor
                updateCartQuantity(productId, newValue);
            }
        });
        
        // También manejar cambios directos en el input
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('quantity-input')) {
                const input = e.target;
                const productId = input.dataset.productId;
                let newValue = parseInt(input.value);
                
                // Validar rango
                if (newValue < 1) newValue = 1;
                if (newValue > 10) newValue = 10;
                input.value = newValue;
                
                // Actualizar precios
                updateProductPrice(productId, newValue);
                updateCartTotals();
                
                // Actualizar carrito en el servidor
                updateCartQuantity(productId, newValue);
            }
        });
        
        function updateProductPrice(productId, quantity) {
            const item = document.querySelector(`[data-product-id="${productId}"]`);
            if (!item) return;
            
            const unitPrice = productPrices[productId];
            if (!unitPrice || unitPrice === 0) return; // Es gratis
            
            const subtotalElement = item.querySelector('.product-subtotal');
            const newSubtotal = unitPrice * quantity;
            
            if (subtotalElement) {
                subtotalElement.textContent = formatPrice(newSubtotal);
            }
            
            // Actualizar texto de precio por unidad si hay más de 1
            const perUnitElement = item.querySelector('.per-unit-price');
            if (perUnitElement) {
                if (quantity > 1) {
                    perUnitElement.style.display = 'block';
                    perUnitElement.innerHTML = `<small class="text-muted">${formatPrice(unitPrice)} c/u</small>`;
                } else {
                    perUnitElement.style.display = 'none';
                }
            }
        }
        
        function updateCartTotals() {
            let subtotal = 0;
            let itemCount = 0;
            
            // Calcular nuevo subtotal
            document.querySelectorAll('[data-product-id]').forEach(function(item) {
                const productId = item.dataset.productId;
                const unitPrice = productPrices[productId] || 0;
                const quantity = parseInt(item.querySelector('.quantity-input').value);
                
                subtotal += unitPrice * quantity;
                itemCount += quantity;
            });
            
            // Actualizar contador de items
            const itemCountElements = document.querySelectorAll('.items-count');
            itemCountElements.forEach(el => el.textContent = itemCount);
            
            // Actualizar subtotal
            const subtotalElements = document.querySelectorAll('.cart-subtotal');
            subtotalElements.forEach(el => {
                if (subtotal > 0) {
                    el.textContent = formatPrice(subtotal);
                } else {
                    el.innerHTML = '<span class="text-success">GRATIS</span>';
                }
            });
            
            // Calcular impuestos (si aplica)
            const taxRate = 0; // Ajustar según configuración
            const tax = subtotal * (taxRate / 100);
            const total = subtotal + tax;
            
            // Actualizar total
            const totalElements = document.querySelectorAll('.cart-total');
            totalElements.forEach(el => el.textContent = formatPrice(total));
            
            // Actualizar badge del header
            const headerBadge = document.querySelector('.badge');
            if (headerBadge) {
                headerBadge.textContent = `${itemCount} productos`;
            }
        }
        
        function formatPrice(price) {
            return new Intl.NumberFormat('es-PE', {
                style: 'currency',
                currency: 'PEN',
                minimumFractionDigits: 2
            }).format(price);
        }
        
        function updateCartQuantity(productId, quantity) {
            // Hacer petición al servidor para actualizar el carrito
            fetch('/api/cart/update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error actualizando carrito:', data.message);
                    // Revertir cambios locales si hay error
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revertir cambios locales si hay error
                location.reload();
            });
        }
    </script>
</body>
</html>