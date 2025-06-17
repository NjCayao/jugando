<?php
// includes/cart_modal.php - Modal del carrito usando CSS existente
require_once __DIR__ . '/../config/cart.php';

// Obtener datos del carrito
$cartItems = Cart::getItems();
$cartTotals = Cart::getTotals();
$cartEmpty = Cart::isEmpty();
?>

<!-- Modal del Carrito con clases CSS existentes -->
<div class="cart-modal-overlay" id="cartModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.5); z-index: 999999; backdrop-filter: blur(5px);">
    <div class="cart-modal-container" style="position: absolute; top: 80px; right: 20px; width: 400px; max-width: 90vw; max-height: 80vh; z-index: 1000000;">
        <div class="sidebar-card-compact">
            <!-- Header del Modal -->
            <div class="sidebar-header-compact">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Tu Carrito
                        <span class="badge bg-primary ms-2" id="modal-cart-count"><?php echo $cartTotals['items_count']; ?></span>
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="closeCartModal()" style="border-radius: 50%; width: 35px; height: 35px; padding: 0;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Contenido del Modal -->
            <div class="sidebar-body-compact" style="max-height: 400px; overflow-y: auto;" id="cart-modal-content">
                <?php if ($cartEmpty): ?>
                    <!-- Carrito Vacío usando clases existentes -->
                    <div class="empty-state-compact text-center py-4">
                        <div class="empty-icon-compact mb-3">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h6 class="mb-2">Tu carrito está vacío</h6>
                        <small class="text-muted">Agrega productos para comenzar</small>
                        <div class="mt-3">
                            <a href="<?php echo SITE_URL; ?>/productos" class="crystal-btn" onclick="closeCartModal()">
                                <i class="fas fa-search me-2"></i>Explorar
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Items del carrito usando user-product-item -->
                    <?php foreach ($cartItems as $productId => $item): ?>
                        <div class="user-product-item mb-2" data-product-id="<?php echo $productId; ?>">
                            <div class="d-flex align-items-start">
                                <!-- Imagen usando product-image-compact -->
                                <div class="product-image-compact me-3">
                                    <?php if ($item['image']): ?>
                                        <img src="<?php echo UPLOADS_URL; ?>/products/<?php echo $item['image']; ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                                    <?php else: ?>
                                        <i class="fas fa-image text-muted"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Información usando product-info-compact -->
                                <div class="product-info-compact flex-grow-1">
                                    <h6 class="product-title-compact mb-1" style="font-size: 0.9rem;">
                                        <a href="<?php echo SITE_URL; ?>/producto/<?php echo $item['slug']; ?>" 
                                           class="text-decoration-none" onclick="closeCartModal()">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </a>
                                    </h6>
                                    
                                    <?php if ($item['category_name']): ?>
                                        <div class="product-meta-compact mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Controles usando license-info-compact -->
                                    <div class="license-info-compact">
                                        <div class="row g-2 align-items-center">
                                            <!-- Precio -->
                                            <div class="col-12 mb-2">
                                                <?php if ($item['is_free']): ?>
                                                    <span class="badge bg-success">GRATIS</span>
                                                <?php else: ?>
                                                    <strong class="text-primary item-subtotal-modal" data-unit-price="<?php echo $item['price']; ?>">
                                                        <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                                                    </strong>
                                                    <?php if ($item['quantity'] > 1): ?>
                                                        <br><small class="text-muted"><?php echo formatPrice($item['price']); ?> c/u</small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Controles de cantidad -->
                                            <div class="col-8">
                                                <div class="input-group input-group-sm">
                                                    <button class="btn btn-outline-primary btn-sm quantity-btn-modal" type="button" 
                                                            data-action="decrease" data-product-id="<?php echo $productId; ?>"
                                                            style="width: 30px; height: 30px; padding: 0;">
                                                        <i class="fas fa-minus" style="font-size: 0.7rem;"></i>
                                                    </button>
                                                    <input type="number" class="form-control text-center quantity-input-modal" 
                                                           value="<?php echo $item['quantity']; ?>" min="1" max="10" 
                                                           data-product-id="<?php echo $productId; ?>"
                                                           style="width: 50px; height: 30px; font-size: 0.8rem;">
                                                    <button class="btn btn-outline-primary btn-sm quantity-btn-modal" type="button" 
                                                            data-action="increase" data-product-id="<?php echo $productId; ?>"
                                                            style="width: 30px; height: 30px; padding: 0;">
                                                        <i class="fas fa-plus" style="font-size: 0.7rem;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <!-- Botón eliminar -->
                                            <div class="col-4 text-end">
                                                <button class="btn btn-outline-danger btn-sm remove-item-modal" 
                                                        data-product-id="<?php echo $productId; ?>" 
                                                        title="Eliminar"
                                                        style="width: 30px; height: 30px; padding: 0;">
                                                    <i class="fas fa-trash" style="font-size: 0.7rem;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!$cartEmpty): ?>
                <!-- Footer usando order-item-compact -->
                <div class="sidebar-header-compact" style="border-top: 1px solid rgba(30, 64, 175, 0.1); border-bottom: none;">
                    <div class="order-item-compact">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span>Total:</span>
                            <strong class="text-primary cart-total-modal"><?php echo formatPrice($cartTotals['total']); ?></strong>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="<?php echo SITE_URL; ?>/pages/cart.php" class="btn btn-corporate" onclick="closeCartModal()">
                                <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                            </a>
                            <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-outline-primary" onclick="closeCartModal()">
                                <i class="fas fa-credit-card me-2"></i>Checkout
                            </a>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearCartModal()">
                                <i class="fas fa-trash me-1"></i>Vaciar Carrito
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
// JavaScript simple para el modal del carrito
let cartModalOpen = false;

// Función para abrir el modal
function openCartModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    if (cartModalOpen) return;
    
    cartModalOpen = true;
    const modal = document.getElementById('cartModal');
    modal.style.display = 'flex';
    modal.style.alignItems = 'flex-start';
    modal.style.justifyContent = 'flex-end';
    modal.style.padding = '20px';
    
    // Efecto de entrada simple
    setTimeout(() => {
        modal.style.opacity = '1';
        const container = modal.querySelector('.cart-modal-container');
        container.style.transform = 'translateX(0)';
    }, 10);
    
    // Prevenir scroll del body
    document.body.style.overflow = 'hidden';
}

// Función para cerrar el modal
function closeCartModal() {
    cartModalOpen = false;
    const modal = document.getElementById('cartModal');
    const container = modal.querySelector('.cart-modal-container');
    
    // Efecto de salida
    modal.style.opacity = '0';
    container.style.transform = 'translateX(100%)';
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    
    // Restaurar scroll del body
    document.body.style.overflow = 'auto';
}

// Función para formatear precios
function formatPrice(price) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
    }).format(price);
}

// Manejar controles de cantidad en el modal
document.addEventListener('click', function(e) {
    if (e.target.closest('.quantity-btn-modal')) {
        const btn = e.target.closest('.quantity-btn-modal');
        const input = btn.parentElement.querySelector('.quantity-input-modal');
        const productId = btn.dataset.productId;
        const action = btn.dataset.action;
        
        let newValue = parseInt(input.value);
        if (action === 'increase' && newValue < 10) {
            newValue++;
        } else if (action === 'decrease' && newValue > 1) {
            newValue--;
        }
        
        input.value = newValue;
        updateModalItemPrice(productId, newValue);
    }
    
    if (e.target.closest('.remove-item-modal')) {
        const btn = e.target.closest('.remove-item-modal');
        const productId = btn.dataset.productId;
        
        if (confirm('¿Eliminar este producto del carrito?')) {
            removeItemFromModal(productId);
        }
    }
});

// Manejar cambios directos en inputs de cantidad
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('quantity-input-modal')) {
        const input = e.target;
        const productId = input.dataset.productId;
        let newValue = parseInt(input.value);
        
        if (newValue < 1) newValue = 1;
        if (newValue > 10) newValue = 10;
        input.value = newValue;
        
        updateModalItemPrice(productId, newValue);
    }
});

// Actualizar precio de item en el modal
function updateModalItemPrice(productId, quantity) {
    const item = document.querySelector(`[data-product-id="${productId}"]`);
    const priceElement = item.querySelector('.item-subtotal-modal');
    const unitPrice = parseFloat(priceElement.dataset.unitPrice);
    
    if (unitPrice > 0) {
        const newSubtotal = unitPrice * quantity;
        priceElement.textContent = formatPrice(newSubtotal);
        
        // Actualizar texto c/u
        const perUnit = item.querySelector('small');
        if (perUnit) {
            if (quantity > 1) {
                perUnit.style.display = 'block';
                perUnit.innerHTML = `${formatPrice(unitPrice)} c/u`;
            } else {
                perUnit.style.display = 'none';
            }
        }
    }
    
    updateModalTotal();
}

// Actualizar total del modal
function updateModalTotal() {
    let total = 0;
    let itemCount = 0;
    
    document.querySelectorAll('.item-subtotal-modal').forEach(element => {
        const unitPrice = parseFloat(element.dataset.unitPrice) || 0;
        const item = element.closest('[data-product-id]');
        const quantity = parseInt(item.querySelector('.quantity-input-modal').value);
        
        total += unitPrice * quantity;
        itemCount += quantity;
    });
    
    const totalElement = document.querySelector('.cart-total-modal');
    const countElement = document.getElementById('modal-cart-count');
    
    if (totalElement) totalElement.textContent = formatPrice(total);
    if (countElement) countElement.textContent = itemCount;
}

// Eliminar item del modal
function removeItemFromModal(productId) {
    const item = document.querySelector(`[data-product-id="${productId}"]`);
    if (item) {
        item.remove();
        updateModalTotal();
        
        // Si no quedan items, mostrar estado vacío
        const remainingItems = document.querySelectorAll('[data-product-id]');
        if (remainingItems.length === 0) {
            const content = document.getElementById('cart-modal-content');
            content.innerHTML = `
                <div class="empty-state-compact text-center py-4">
                    <div class="empty-icon-compact mb-3">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <h6 class="mb-2">Tu carrito está vacío</h6>
                    <small class="text-muted">Agrega productos para comenzar</small>
                    <div class="mt-3">
                        <a href="${'<?php echo SITE_URL; ?>'}/productos" class="crystal-btn" onclick="closeCartModal()">
                            <i class="fas fa-search me-2"></i>Explorar
                        </a>
                    </div>
                </div>
            `;
        }
    }
}

// Vaciar carrito
function clearCartModal() {
    if (confirm('¿Vaciar todo el carrito?')) {
        const content = document.getElementById('cart-modal-content');
        content.innerHTML = `
            <div class="empty-state-compact text-center py-4">
                <div class="empty-icon-compact mb-3">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h6 class="mb-2">Tu carrito está vacío</h6>
                <small class="text-muted">Agrega productos para comenzar</small>
                <div class="mt-3">
                    <a href="${'<?php echo SITE_URL; ?>'}/productos" class="crystal-btn" onclick="closeCartModal()">
                        <i class="fas fa-search me-2"></i>Explorar
                    </a>
                </div>
            </div>
        `;
    }
}

// Cerrar modal al hacer click fuera
document.addEventListener('click', function(event) {
    const modal = document.getElementById('cartModal');
    if (event.target === modal) {
        closeCartModal();
    }
});

// Cerrar modal con tecla Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && cartModalOpen) {
        closeCartModal();
    }
});

// Prevenir cierre al hacer click dentro del modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('cartModal');
    const container = modal.querySelector('.cart-modal-container');
    
    // Configurar estilos iniciales
    modal.style.opacity = '0';
    modal.style.transition = 'opacity 0.3s ease';
    container.style.transform = 'translateX(100%)';
    container.style.transition = 'transform 0.3s ease';
    
    // Prevenir cierre al hacer click en el contenido
    container.addEventListener('click', function(event) {
        event.stopPropagation();
    });
});
</script>