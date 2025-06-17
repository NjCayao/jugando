// assets/js/modules/cart.js - MÓDULO DEL CARRITO INDEPENDIENTE

/**
 * Módulo del Carrito de Compras
 */
class CartModule {
    constructor() {
        this.updating = false;
        // POR ESTA:
    this.apiBase = window.SITE_URL + '/api/cart/';
    this.init();
    }

    /**
     * Inicializar módulo
     */
    init() {
        this.updateCartDisplay();
        this.initEventListeners();
        console.log('🛒 Cart Module initialized');
    }

    /**
     * Inicializar event listeners
     */
    initEventListeners() {
        // Event listener para abrir modal del carrito
        // document.addEventListener('click', (e) => {
        //     if (e.target.closest('.cart-icon a')) {
        //         e.preventDefault();
        //         this.loadCartContent();
        //         const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
        //         cartModal.show();
        //     }
        // });
        document.addEventListener('click', (e) => {
        if (e.target.closest('.quantity-btn')) {
            const button = e.target.closest('.quantity-btn');
            const action = button.dataset.action;
            const productId = button.dataset.productId;
            const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
            
            let currentQuantity = parseInt(input.value);
            
            if (action === 'increase' && currentQuantity < 10) {
                currentQuantity++;
            } else if (action === 'decrease' && currentQuantity > 1) {
                currentQuantity--;
            }
            
            input.value = currentQuantity;
            this.updateQuantity(productId, currentQuantity);
        }
        if (e.target.closest('.quantity-btn-modal')) {
            const button = e.target.closest('.quantity-btn-modal');
            const action = button.dataset.action;
            const productId = button.dataset.productId;
            const input = document.querySelector(`.quantity-input-modal[data-product-id="${productId}"]`);
            
            let currentQuantity = parseInt(input.value);
            
            if (action === 'increase' && currentQuantity < 10) {
                currentQuantity++;
            } else if (action === 'decrease' && currentQuantity > 1) {
                currentQuantity--;
            }
            
            input.value = currentQuantity;
            // Usar función del modal en lugar de la del módulo
            updateModalItemPrice(productId, currentQuantity);
        }

        // AGREGAR: Event listener para eliminar del modal
        if (e.target.closest('.remove-item-modal')) {
            const button = e.target.closest('.remove-item-modal');
            const productId = button.dataset.productId;
            
            if (confirm('¿Eliminar este producto del carrito?')) {
                removeItemFromModal(productId);
            }
        }
    });

        // Event listeners para controles de cantidad
        document.addEventListener('click', (e) => {
            if (e.target.closest('.quantity-btn')) {
                const button = e.target.closest('.quantity-btn');
                const action = button.dataset.action;
                const productId = button.dataset.productId;
                const input = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                
                let currentQuantity = parseInt(input.value);
                
                if (action === 'increase' && currentQuantity < 10) {
                    currentQuantity++;
                } else if (action === 'decrease' && currentQuantity > 1) {
                    currentQuantity--;
                }
                
                input.value = currentQuantity;
                this.updateQuantity(productId, currentQuantity);
            }
        });

        // Event listener para inputs de cantidad
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('quantity-input')) {
                const productId = e.target.dataset.productId;
                let quantity = parseInt(e.target.value);
                
                if (quantity < 1) quantity = 1;
                if (quantity > 10) quantity = 10;
                
                e.target.value = quantity;
                this.updateQuantity(productId, quantity);
            }
        });

        // Event listener para eliminar items
        document.addEventListener('click', (e) => {
            if (e.target.closest('.remove-item')) {
                const button = e.target.closest('.remove-item');
                const productId = button.dataset.productId;
                this.removeItem(productId);
            }
        });
    }

    /**
     * Agregar producto al carrito
     */
    async addItem(productId, quantity = 1) {
        if (this.updating) return;
        
        this.updating = true;
        
        // Mostrar loading en el botón
        const addButton = document.querySelector(`[onclick*="addToCart(${productId})"]`);
        let originalText = '';
        if (addButton) {
            originalText = addButton.innerHTML;
            addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            addButton.disabled = true;
        }

        try {
            const response = await fetch(this.apiBase + 'add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.cart_count);
                this.showNotification(data.message, 'success');
                this.animateCartIcon();
            } else {
                this.showNotification(data.message, 'error');
            }

        } catch (error) {
            console.error('Error adding to cart:', error);
            this.showNotification('Error al agregar el producto', 'error');
        } finally {
            this.updating = false;
            
            // Restaurar botón
            if (addButton) {
                addButton.innerHTML = originalText || '<i class="fas fa-cart-plus me-2"></i>Agregar al Carrito';
                addButton.disabled = false;
            }
        }
    }

    /**
     * Actualizar cantidad
     */
    async updateQuantity(productId, quantity) {
        if (this.updating) return;
        
        this.updating = true;

        try {
            const response = await fetch(this.apiBase + 'update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.cart_count);
                
                if (quantity === 0) {
                    this.removeCartItemFromDOM(productId);
                    this.showNotification(data.message, 'info');
                } else {
                    this.updateCartItemDOM(productId, data);
                }
                
                this.updateCartTotals(data.totals);
                
                if (data.cart_count === 0) {
                    this.showEmptyCart();
                }
                
            } else {
                this.showNotification(data.message, 'error');
            }

        } catch (error) {
            console.error('Error updating cart:', error);
            this.showNotification('Error al actualizar el carrito', 'error');
        } finally {
            this.updating = false;
        }
    }

    /**
     * Eliminar producto del carrito
     */
    async removeItem(productId) {
        if (this.updating) return;
        
        this.updating = true;

        try {
            const response = await fetch(this.apiBase + 'remove.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}`
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.cart_count);
                this.removeCartItemFromDOM(productId);
                this.updateCartTotals(data.totals);
                this.showNotification(data.message, 'info');
                
                if (data.cart_empty) {
                    this.showEmptyCart();
                }
            } else {
                this.showNotification(data.message, 'error');
            }

        } catch (error) {
            console.error('Error removing from cart:', error);
            this.showNotification('Error al eliminar el producto', 'error');
        } finally {
            this.updating = false;
        }
    }

    /**
     * Vaciar carrito
     */
    async clearCart() {
        if (!confirm('¿Estás seguro de que quieres vaciar todo el carrito?')) {
            return;
        }
        
        this.updating = true;
        
        try {
            const response = await fetch(this.apiBase + 'clear.php', {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(0);
                this.showEmptyCart();
                this.showNotification(data.message, 'info');
            } else {
                this.showNotification(data.message, 'error');
            }

        } catch (error) {
            console.error('Error clearing cart:', error);
            this.showNotification('Error al vaciar el carrito', 'error');
        } finally {
            this.updating = false;
        }
    }

    /**
     * Cargar contenido del carrito
     */
    async loadCartContent() {
        try {
            const response = await fetch(this.apiBase + 'get.php');
            const data = await response.json();

            if (data.success) {
                this.updateCartModal(data);
                this.updateCartCount(data.items_count);
                
                if (!data.validation.valid) {
                    data.validation.errors.forEach(error => {
                        this.showNotification(error, 'warning');
                    });
                }
            } else {
                this.showNotification('Error al cargar el carrito', 'error');
            }

        } catch (error) {
            console.error('Error loading cart:', error);
            this.showNotification('Error al cargar el carrito', 'error');
        }
    }

    /**
     * Actualizar contador del carrito
     */
    updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('#cart-count, #modal-cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            
            if (count > 0) {
                element.style.display = 'inline';
                element.style.animation = 'pulse 0.5s ease';
            } else {
                element.style.display = 'none';
            }
        });
    }

    /**
     * Actualizar display del carrito al cargar la página
     */
    async updateCartDisplay() {
        try {
            const response = await fetch(this.apiBase + 'get.php');
            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.items_count);
            }

        } catch (error) {
            console.error('Error loading cart display:', error);
        }
    }

    /**
     * Actualizar modal del carrito
     */
    updateCartModal(data) {
        const modalBody = document.getElementById('cart-modal-body');
        
        if (data.cart_empty) {
            this.showEmptyCart();
            return;
        }
        
        // Generar HTML de los items
        let itemsHTML = '<div class="cart-items">';
        
        data.items.forEach(item => {
            itemsHTML += `
                <div class="cart-item" data-product-id="${item.id}">
                    <div class="row align-items-center">
                        <div class="col-3">
                            <div class="product-image">
                                ${item.image_url ? 
                                    `<img src="${item.image_url}" alt="${item.name}" class="img-fluid rounded" style="max-height: 60px; object-fit: cover;">` :
                                    `<div class="no-image bg-light rounded d-flex align-items-center justify-content-center" style="height: 60px;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>`
                                }
                            </div>
                        </div>
                        <div class="col-6">
                            <h6 class="mb-1">
                                <a href="${item.product_url}" class="text-decoration-none text-dark" data-bs-dismiss="modal">
                                    ${item.name}
                                </a>
                            </h6>
                            ${item.category_name ? `<small class="text-muted">${item.category_name}</small>` : ''}
                            <div class="price-info mt-1">
                                ${item.is_free ? 
                                    '<span class="text-success fw-bold">GRATIS</span>' :
                                    `<span class="text-primary fw-bold">${item.price}</span>
                                    ${item.quantity > 1 ? `<small class="text-muted">x${item.quantity} = <span class="item-subtotal">${item.subtotal}</span></small>` : ''}`
                                }
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="quantity-controls d-flex align-items-center justify-content-between">
                                <div class="input-group input-group-sm" style="max-width: 120px;">
                                    <button class="btn btn-outline-secondary quantity-btn" type="button" 
                                            data-action="decrease" data-product-id="${item.id}">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center quantity-input" 
                                           value="${item.quantity}" min="1" max="10" 
                                           data-product-id="${item.id}">
                                    <button class="btn btn-outline-secondary quantity-btn" type="button" 
                                            data-action="increase" data-product-id="${item.id}">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-outline-danger ms-2 remove-item" 
                                        data-product-id="${item.id}" title="Eliminar del carrito">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <hr>
                </div>
            `;
        });
        
        itemsHTML += '</div>';
        
        // Agregar totales
        const totals = data.totals;
        itemsHTML += `
            <div class="cart-totals bg-light p-3 rounded">
                ${totals.subtotal_raw > 0 ? `
                    <div class="row mb-2">
                        <div class="col-6">Subtotal:</div>
                        <div class="col-6 text-end"><span class="cart-subtotal">${totals.subtotal}</span></div>
                    </div>
                    ${totals.tax_raw > 0 ? `
                        <div class="row mb-2">
                            <div class="col-6">Impuestos (${totals.tax_rate}%):</div>
                            <div class="col-6 text-end"><span class="cart-tax">${totals.tax}</span></div>
                        </div>
                    ` : ''}
                    <hr class="my-2">
                ` : ''}
                <div class="row">
                    <div class="col-6"><strong>Total:</strong></div>
                    <div class="col-6 text-end"><strong class="text-primary cart-total">${totals.total}</strong></div>
                </div>
            </div>
        `;
        
        modalBody.innerHTML = itemsHTML;
        this.updateCartModalFooter(true);
    }

    /**
     * Mostrar carrito vacío
     */
    showEmptyCart() {
        const modalBody = document.getElementById('cart-modal-body');
        modalBody.innerHTML = `
            <div class="empty-cart text-center py-5">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">Tu carrito está vacío</h5>
                <p class="text-muted mb-4">Explora nuestros productos y agrega algunos al carrito</p>
                <a href="/productos" class="btn btn-primary" data-bs-dismiss="modal">
                    <i class="fas fa-search me-2"></i>Explorar Productos
                </a>
            </div>
        `;
        
        this.updateCartModalFooter(false);
    }

    /**
     * Actualizar footer del modal
     */
    updateCartModalFooter(hasItems) {
        const modal = document.getElementById('cartModal');
        if (!modal) return;
        
        let footer = modal.querySelector('.modal-footer');
        
        if (!hasItems) {
            if (footer) footer.remove();
            return;
        }
        
        if (!footer) {
            footer = document.createElement('div');
            footer.className = 'modal-footer';
            modal.querySelector('.modal-content').appendChild(footer);
        }
        
        footer.innerHTML = `
            <div class="w-100">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="/pages/cart.php" class="btn btn-outline-primary w-100" data-bs-dismiss="modal">
                            <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="/pages/checkout.php" class="btn btn-success w-100" data-bs-dismiss="modal">
                            <i class="fas fa-credit-card me-2"></i>Proceder al Pago
                        </a>
                    </div>
                </div>
                <div class="text-center mt-2">
                    <button type="button" class="btn btn-link btn-sm text-muted" onclick="cart.clearCart()">
                        <i class="fas fa-trash me-1"></i>Vaciar Carrito
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Eliminar item del DOM
     */
    removeCartItemFromDOM(productId) {
        const item = document.querySelector(`[data-product-id="${productId}"]`);
        if (item) {
            item.style.transition = 'all 0.4s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateX(-100%)';
            setTimeout(() => {
                item.remove();
            }, 400);
        }
    }

    /**
     * Actualizar item en el DOM
     */
    updateCartItemDOM(productId, data) {
        const item = document.querySelector(`[data-product-id="${productId}"]`);
        if (item) {
            const subtotalSpan = item.querySelector('.item-subtotal');
            if (subtotalSpan && data.item_subtotal) {
                subtotalSpan.textContent = data.item_subtotal;
                subtotalSpan.style.animation = 'pulse 0.5s ease';
            }
        }
    }

    /**
     * Actualizar totales en el DOM
     */
    updateCartTotals(totals) {
        const elements = {
            '.cart-subtotal': totals.subtotal,
            '.cart-tax': totals.tax,
            '.cart-total': totals.total
        };
        
        Object.entries(elements).forEach(([selector, value]) => {
            const element = document.querySelector(selector);
            if (element) {
                element.textContent = value;
                element.style.animation = 'pulse 0.5s ease';
            }
        });
    }

    /**
     * Animar icono del carrito
     */
    animateCartIcon() {
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            cartIcon.style.animation = 'pulse 0.8s ease';
            setTimeout(() => {
                cartIcon.style.animation = '';
            }, 800);
        }
    }

    /**
     * Mostrar notificación
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible cart-notification`;
        notification.style.cssText = `
            position: fixed;
            top: 180px;
            right: 30px;
            z-index: 9999;
            min-width: 350px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s ease;
        `;
        
        const iconMap = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        
        notification.innerHTML = `
            <i class="fas fa-${iconMap[type]} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Auto-remover
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 400);
        }, 4000);
    }
}

// Inicializar módulo del carrito globalmente
let cart;

document.addEventListener('DOMContentLoaded', function() {
    cart = new CartModule();
});

// Funciones globales para mantener compatibilidad
function addToCart(productId, quantity = 1) {
    if (cart) {
        cart.addItem(productId, quantity);
    }
}

function addToWishlist(productId) {
    cart?.showNotification('Función de favoritos próximamente disponible', 'info');
}

function buyNow(productId) {
    // Agregar al carrito y redirigir al checkout
    if (cart) {
        cart.addItem(productId, 1).then(() => {
            window.location.href = '/pages/checkout.php';
        });
    }
}

function downloadFree(productId) {
    // Para productos gratuitos, redirigir al enlace correcto
    window.location.href = window.SITE_URL + `/download/${productId}`;
}