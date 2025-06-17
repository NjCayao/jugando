<?php
// includes/cart_modal.php - Modal simple y funcional
require_once __DIR__ . '/../config/cart.php';
?>

<!-- Modal del Carrito - Diseño Simple y Elegante -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="cartModalLabel">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Mi Carrito
                    <span class="badge bg-light text-primary ms-2" id="modal-cart-count">0</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Body -->
            <div class="modal-body p-0" id="cart-modal-body">
                <!-- Contenido dinámico se carga aquí -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando carrito...</p>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="modal-footer d-none" id="cart-modal-footer">
                <div class="w-100">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-danger w-100" onclick="clearCartModal()">
                                <i class="fas fa-trash me-2"></i>Vaciar
                            </button>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo SITE_URL; ?>/pages/cart.php" class="btn btn-outline-primary w-100" data-bs-dismiss="modal">
                                <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                            </a>
                        </div>
                        <div class="col-md-4">
                            <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-success w-100" data-bs-dismiss="modal">
                                <i class="fas fa-credit-card me-2"></i>Checkout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos para el modal del carrito */
.cart-item-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    margin-bottom: 1rem;
    padding: 1rem;
    transition: all 0.3s ease;
}

.cart-item-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.cart-item-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
}

.cart-item-no-image {
    width: 80px;
    height: 80px;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
}

.quantity-control {
    max-width: 120px;
}

.quantity-control .btn {
    width: 35px;
    height: 35px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.quantity-control input {
    text-align: center;
    height: 35px;
    border-left: 0;
    border-right: 0;
}

.cart-totals {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.empty-cart-state {
    padding: 3rem 1rem;
    text-align: center;
    color: #6c757d;
}

.empty-cart-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}
</style>

<script>
// JavaScript para el modal del carrito - Versión simplificada y funcional
document.addEventListener('DOMContentLoaded', function() {
    
    // Función global para abrir el modal
    window.openCartModal = function(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Mostrar modal
        const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
        cartModal.show();
        
        // Cargar contenido
        loadCartModalContent();
    };
    
    // Event listener cuando se abre el modal
    document.getElementById('cartModal').addEventListener('shown.bs.modal', function() {
        console.log('🛒 Modal del carrito abierto');
        loadCartModalContent();
    });
    
});

// Función para cargar contenido del modal
async function loadCartModalContent() {
    const modalBody = document.getElementById('cart-modal-body');
    const modalFooter = document.getElementById('cart-modal-footer');
    const modalCount = document.getElementById('modal-cart-count');
    
    try {
        // Mostrar loading
        modalBody.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-3 text-muted">Cargando carrito...</p>
            </div>
        `;
        
        // Obtener datos del carrito
        const response = await fetch(window.SITE_URL + '/api/cart/get.php');
        
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        
        const text = await response.text();
        console.log('Respuesta del servidor:', text); // Para debug
        
        const data = JSON.parse(text);
        
        if (data.success) {
            // Actualizar contador
            modalCount.textContent = data.items_count;
            
            // Actualizar contenido
            if (data.cart_empty) {
                showEmptyCartModal();
                modalFooter.classList.add('d-none');
            } else {
                showCartItemsModal(data);
                modalFooter.classList.remove('d-none');
            }
        } else {
            throw new Error(data.message || 'Error cargando carrito');
        }
        
    } catch (error) {
        console.error('Error cargando carrito:', error);
        modalBody.innerHTML = `
            <div class="alert alert-danger m-3">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Error</h6>
                <p class="mb-0">No se pudo cargar el carrito. Por favor, recarga la página.</p>
                <button class="btn btn-sm btn-outline-danger mt-2" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>Recargar Página
                </button>
            </div>
        `;
        modalFooter.classList.add('d-none');
    }
}

// Mostrar carrito vacío
function showEmptyCartModal() {
    const modalBody = document.getElementById('cart-modal-body');
    modalBody.innerHTML = `
        <div class="empty-cart-state">
            <div class="empty-cart-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h5>Tu carrito está vacío</h5>
            <p class="mb-4">¡Explora nuestros productos y encuentra algo increíble!</p>
            <a href="${window.SITE_URL}/productos" class="btn btn-primary" data-bs-dismiss="modal">
                <i class="fas fa-search me-2"></i>Explorar Productos
            </a>
        </div>
    `;
}

// Mostrar items del carrito
function showCartItemsModal(data) {
    const modalBody = document.getElementById('cart-modal-body');
    
    let html = '<div class="p-3">';
    
    // Items
    data.items.forEach(item => {
        html += `
            <div class="cart-item-card" data-product-id="${item.id}">
                <div class="row align-items-center">
                    <!-- Imagen -->
                    <div class="col-auto">
                        ${item.image_url ? 
                            `<img src="${item.image_url}" alt="${item.name}" class="cart-item-image">` :
                            `<div class="cart-item-no-image"><i class="fas fa-image"></i></div>`
                        }
                    </div>
                    
                    <!-- Info -->
                    <div class="col">
                        <h6 class="mb-1">
                            <a href="${item.product_url}" class="text-decoration-none text-dark" data-bs-dismiss="modal">
                                ${item.name}
                            </a>
                        </h6>
                        ${item.category_name ? `<small class="text-muted"><i class="fas fa-tag me-1"></i>${item.category_name}</small><br>` : ''}
                        <strong class="text-success">
                            ${item.is_free ? 'GRATIS' : item.subtotal}
                        </strong>
                        ${item.quantity > 1 && !item.is_free ? `<small class="text-muted d-block">${item.price} c/u</small>` : ''}
                    </div>
                    
                    <!-- Cantidad -->
                    <div class="col-auto">
                        <div class="quantity-control">
                            <div class="input-group">
                                <button class="btn btn-outline-primary" type="button" onclick="updateCartQuantityModal(${item.id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control" value="${item.quantity}" min="1" max="10" 
                                       onchange="updateCartQuantityModal(${item.id}, this.value)">
                                <button class="btn btn-outline-primary" type="button" onclick="updateCartQuantityModal(${item.id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Eliminar -->
                    <div class="col-auto">
                        <button class="btn btn-outline-danger btn-sm" onclick="removeCartItemModal(${item.id})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    // Totales
    html += `
        <div class="cart-totals">
            <h6 class="mb-3"><i class="fas fa-calculator me-2"></i>Resumen</h6>
            ${data.totals.subtotal_raw > 0 ? `
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span>${data.totals.subtotal}</span>
                </div>
            ` : ''}
            ${data.totals.tax_raw > 0 ? `
                <div class="d-flex justify-content-between mb-2">
                    <span>Impuestos:</span>
                    <span>${data.totals.tax}</span>
                </div>
            ` : ''}
            <hr>
            <div class="d-flex justify-content-between">
                <strong>Total:</strong>
                <strong class="text-success fs-5">${data.totals.total}</strong>
            </div>
        </div>
    `;
    
    html += '</div>';
    modalBody.innerHTML = html;
}

// Función para actualizar cantidad desde el modal
async function updateCartQuantityModal(productId, quantity) {
    quantity = Math.max(1, Math.min(10, parseInt(quantity)));
    
    if (window.cart) {
        // Usar el módulo cart existente
        await cart.updateQuantity(productId, quantity);
        // Recargar modal
        setTimeout(() => loadCartModalContent(), 500);
    } else {
        // Fallback manual
        try {
            const response = await fetch(window.SITE_URL + '/api/cart/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&quantity=${quantity}`
            });
            
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success) {
                loadCartModalContent();
                // Actualizar contador global
                document.getElementById('cart-count').textContent = data.cart_count;
            }
        } catch (error) {
            console.error('Error updating quantity:', error);
            alert('Error al actualizar cantidad');
        }
    }
}

// Función para eliminar item desde el modal
async function removeCartItemModal(productId) {
    if (!confirm('¿Eliminar este producto del carrito?')) return;
    
    if (window.cart) {
        // Usar el módulo cart existente
        await cart.removeItem(productId);
        // Recargar modal
        setTimeout(() => loadCartModalContent(), 500);
    } else {
        // Fallback manual
        try {
            const response = await fetch(window.SITE_URL + '/api/cart/remove.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}`
            });
            
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success) {
                loadCartModalContent();
                // Actualizar contador global
                document.getElementById('cart-count').textContent = data.cart_count;
            }
        } catch (error) {
            console.error('Error removing item:', error);
            alert('Error al eliminar producto');
        }
    }
}

// Función para vaciar carrito desde el modal
async function clearCartModal() {
    if (!confirm('¿Vaciar todo el carrito?')) return;
    
    if (window.cart) {
        await cart.clearCart();
        setTimeout(() => loadCartModalContent(), 500);
    } else {
        try {
            const response = await fetch(window.SITE_URL + '/api/cart/clear.php', {
                method: 'POST'
            });
            
            const text = await response.text();
            const data = JSON.parse(text);
            
            if (data.success) {
                loadCartModalContent();
                document.getElementById('cart-count').textContent = '0';
            }
        } catch (error) {
            console.error('Error clearing cart:', error);
            alert('Error al vaciar carrito');
        }
    }
}

// Función helper para formatear precios
function formatPrice(price) {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
    }).format(price);
}
</script>