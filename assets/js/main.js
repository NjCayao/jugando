// assets/js/main.js - JAVASCRIPT CORPORATIVO MEJORADO

// === CARGA DE RECURSOS ===
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar efectos corporativos
    initCorporateEffects();
    
    // Header scroll effect con glassmorphism mejorado
    const header = document.querySelector('.main-header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }
    
    // Smooth scroll mejorado para anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Auto-hide alerts con animación
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Inicializar counter animation para estadísticas
    initCounterAnimation();
    
    // Inicializar parallax suave
    initParallaxEffect();
    
    // Inicializar intersection observer para animaciones
    initScrollAnimations();
    
    // Inicializar carrito
    updateCartDisplay();
    initCartEvents();
});

// === EFECTOS CORPORATIVOS ===
function initCorporateEffects() {
    // Efecto shimmer en logo
    const logoText = document.querySelector('.logo-text');
    if (logoText) {
        setInterval(() => {
            logoText.style.animation = 'none';
            setTimeout(() => {
                logoText.style.animation = 'shimmer 2s ease-in-out';
            }, 100);
        }, 5000);
    }
    
    // Efecto pulse en badges
    const badges = document.querySelectorAll('.product-badge.free');
    badges.forEach(badge => {
        badge.style.animation = 'corporate-pulse 2s infinite';
    });
    
    // Pattern animations de fondo
    const body = document.body;
    if (body.querySelector('::before')) {
        // El patrón ya está aplicado en CSS
    }
    
    // Efectos hover corporativos en tarjetas
    const cards = document.querySelectorAll('.category-card, .product-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
}

// === ANIMACIÓN DE CONTADORES ===
function initCounterAnimation() {
    const counters = document.querySelectorAll('.stat-number[data-counter]');
    
    const animateCounter = (counter) => {
        const target = parseInt(counter.getAttribute('data-counter'));
        const duration = 2000; // 2 segundos
        const increment = target / (duration / 16); // 60fps
        let current = 0;
        
        const updateCounter = () => {
            current += increment;
            if (current < target) {
                counter.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
                // Agregar efecto final
                counter.style.animation = 'corporate-pulse 0.5s ease';
            }
        };
        
        updateCounter();
    };
    
    // Usar Intersection Observer para iniciar animaciones cuando sean visibles
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.dataset.animated) {
                animateCounter(entry.target);
                entry.target.dataset.animated = 'true';
            }
        });
    }, { threshold: 0.5 });
    
    counters.forEach(counter => observer.observe(counter));
}

// === EFECTO PARALLAX SUAVE ===
function initParallaxEffect() {
    let ticking = false;
    
    function updateParallax() {
        const scrolled = window.pageYOffset;
        const parallaxElements = document.querySelectorAll('.hero-background, .floating-card');
        
        parallaxElements.forEach((element, index) => {
            const speed = (index + 1) * 0.5;
            const yPos = -(scrolled * speed);
            element.style.transform = `translateY(${yPos}px)`;
        });
        
        ticking = false;
    }
    
    function requestTick() {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    }
    
    window.addEventListener('scroll', requestTick);
}

// === ANIMACIONES DE SCROLL ===
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
                
                // Agregar delay progresivo para elementos hermanos
                const siblings = entry.target.parentNode.children;
                Array.from(siblings).forEach((sibling, index) => {
                    if (sibling.classList.contains('fade-in-up')) {
                        sibling.style.animationDelay = `${index * 0.1}s`;
                    }
                });
            }
        });
    }, observerOptions);
    
    // Observar elementos para animación
    const animatedElements = document.querySelectorAll('.category-card, .product-card, .stat-card, .section-title, .section-subtitle');
    animatedElements.forEach(el => observer.observe(el));
}

// === SMOOTH TRANSITIONS MEJORADAS ===
function enhanceSmoothTransitions() {
    // Mejorar transiciones en navegación
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
    });
    
    // Mejorar transiciones en botones
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
    });
}

// === LOADING BAR CORPORATIVO ===
function showLoadingBar() {
    const loadingBar = document.createElement('div');
    loadingBar.className = 'loading-bar';
    loadingBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        height: 3px;
        background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
        z-index: 9999;
        animation: loading-bar 1s ease-in-out;
    `;
    
    document.body.appendChild(loadingBar);
    
    setTimeout(() => {
        loadingBar.remove();
    }, 1000);
}

// === FUNCIONES DEL CARRITO DE COMPRAS (MANTENIDAS) ===

// Variables globales del carrito
let cartUpdating = false;

/**
 * Agregar producto al carrito
 */
function addToCart(productId, quantity = 1) {
    if (cartUpdating) return;
    
    cartUpdating = true;
    
    // Mostrar loading corporativo en el botón
    const addButton = document.querySelector(`[onclick="addToCart(${productId})"]`);
    let originalText = '';
    if (addButton) {
        originalText = addButton.innerHTML;
        addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        addButton.disabled = true;
        addButton.style.background = 'linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%)';
    }
    
    // Mostrar loading bar
    showLoadingBar();
    
    // Hacer petición AJAX
    fetch('api/cart/add.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar contador del carrito
            updateCartCount(data.cart_count);
            
            // Mostrar notificación de éxito corporativa
            showCartNotification(data.message, 'success');
            
            // Animar el icono del carrito
            animateCartIcon();
            
        } else {
            showCartNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al agregar el producto', 'error');
    })
    .finally(() => {
        cartUpdating = false;
        
        // Restaurar botón
        if (addButton) {
            addButton.innerHTML = originalText || '<i class="fas fa-cart-plus me-2"></i>Agregar al Carrito';
            addButton.disabled = false;
            addButton.style.background = '';
        }
    });
}

function addToWishlist(productId) {
    // TODO: Implementar sistema de wishlist en futuras fases
    showCartNotification('Función de favoritos próximamente disponible', 'info');
}

/**
 * Actualizar cantidad de un producto
 */
function updateCartQuantity(productId, quantity) {
    if (cartUpdating) return;
    
    cartUpdating = true;

    fetch('api/cart/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
            
            if (quantity === 0) {
                // Producto eliminado
                removeCartItemFromDOM(productId);
                showCartNotification(data.message, 'info');
            } else {
                // Cantidad actualizada
                updateCartItemDOM(productId, data);
            }
            
            // Actualizar totales
            updateCartTotals(data.totals);
            
            // Verificar si el carrito está vacío
            if (data.cart_count === 0) {
                showEmptyCart();
            }
            
        } else {
            showCartNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al actualizar el carrito', 'error');
    })
    .finally(() => {
        cartUpdating = false;
    });
}

/**
 * Eliminar producto del carrito
 */
function removeFromCart(productId) {
    if (cartUpdating) return;
    
    cartUpdating = true;

    fetch('api/cart/remove.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `product_id=${productId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.cart_count);
            removeCartItemFromDOM(productId);
            updateCartTotals(data.totals);
            showCartNotification(data.message, 'info');
            
            if (data.cart_empty) {
                showEmptyCart();
            }
        } else {
            showCartNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al eliminar el producto', 'error');
    })
    .finally(() => {
        cartUpdating = false;
    });
}

/**
 * Vaciar todo el carrito
 */
function clearCart() {
    if (!confirm('¿Estás seguro de que quieres vaciar todo el carrito?')) {
        return;
    }
    
    cartUpdating = true;
    
    // Simular eliminación de todos los productos
    const cartItems = document.querySelectorAll('.cart-item');
    cartItems.forEach(item => {
        const productId = item.dataset.productId;
        removeFromCart(productId);
    });
}

/**
 * Cargar contenido del carrito
 */
function loadCartContent() {
    showLoadingBar();
    
    fetch(`api/cart/get.php`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartModal(data);
            updateCartCount(data.items_count);
            
            // Mostrar errores de validación si los hay
            if (!data.validation.valid) {
                data.validation.errors.forEach(error => {
                    showCartNotification(error, 'warning');
                });
            }
        } else {
            showCartNotification('Error al cargar el carrito', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showCartNotification('Error al cargar el carrito', 'error');
    });
}

/**
 * Actualizar contador del carrito con animación corporativa
 */
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('#cart-count, #modal-cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        
        if (count > 0) {
            element.style.display = 'inline';
            element.style.animation = 'corporate-pulse 0.5s ease';
        } else {
            element.style.display = 'none';
        }
    });
}

/**
 * Actualizar display del carrito al cargar la página
 */
function updateCartDisplay() {
    fetch('api/cart/get.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateCartCount(data.items_count);
        }
    })
    .catch(error => {
        console.error('Error loading cart:', error);
    });
}

/**
 * Inicializar eventos del carrito
 */
function initCartEvents() {
    // Evento para abrir modal del carrito
    document.addEventListener('click', function(e) {
        if (e.target.closest('.cart-icon a')) {
            e.preventDefault();
            loadCartContent();
            const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
            cartModal.show();
        }
    });
    
    // Eventos para controles de cantidad
    document.addEventListener('click', function(e) {
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
            updateCartQuantity(productId, currentQuantity);
        }
    });
    
    // Evento para inputs de cantidad
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('quantity-input')) {
            const productId = e.target.dataset.productId;
            let quantity = parseInt(e.target.value);
            
            if (quantity < 1) quantity = 1;
            if (quantity > 10) quantity = 10;
            
            e.target.value = quantity;
            updateCartQuantity(productId, quantity);
        }
    });
    
    // Evento para eliminar items
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            const button = e.target.closest('.remove-item');
            const productId = button.dataset.productId;
            removeFromCart(productId);
        }
    });
}

/**
 * Actualizar modal del carrito
 */
function updateCartModal(data) {
    const modalBody = document.getElementById('cart-modal-body');
    
    if (data.cart_empty) {
        showEmptyCart();
        return;
    }
    
    // Generar HTML de los items con estilos corporativos
    let itemsHTML = '<div class="cart-items">';
    
    data.items.forEach(item => {
        itemsHTML += `
            <div class="cart-item" data-product-id="${item.id}" style="border-left: 3px solid #1E40AF; margin-bottom: 1rem; padding-left: 1rem;">
                <div class="row align-items-center">
                    <div class="col-3">
                        <div class="product-image">
                            ${item.image_url ? 
                                `<img src="${item.image_url}" alt="${item.name}" class="img-fluid rounded" style="max-height: 60px; object-fit: cover; border: 2px solid #DBEAFE;">` :
                                `<div class="no-image bg-light rounded d-flex align-items-center justify-content-center" style="height: 60px; border: 2px solid #DBEAFE;">
                                    <i class="fas fa-image text-muted"></i>
                                </div>`
                            }
                        </div>
                    </div>
                    <div class="col-6">
                        <h6 class="mb-1" style="color: #1E40AF; font-weight: 700;">
                            <a href="${item.product_url}" class="text-decoration-none" style="color: #1E40AF;" data-bs-dismiss="modal">
                                ${item.name}
                            </a>
                        </h6>
                        ${item.category_name ? `<small class="text-muted">${item.category_name}</small>` : ''}
                        <div class="price-info mt-1">
                            ${item.is_free ? 
                                '<span class="text-success fw-bold">GRATIS</span>' :
                                `<span class="fw-bold" style="color: #1E40AF;">${item.price}</span>
                                ${item.quantity > 1 ? `<small class="text-muted">x${item.quantity} = <span class="item-subtotal">${item.subtotal}</span></small>` : ''}`
                            }
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="quantity-controls d-flex align-items-center justify-content-between">
                            <div class="input-group input-group-sm" style="max-width: 120px;">
                                <button class="btn btn-outline-primary quantity-btn" type="button" 
                                        data-action="decrease" data-product-id="${item.id}" style="border-color: #1E40AF;">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="form-control text-center quantity-input" 
                                       value="${item.quantity}" min="1" max="10" 
                                       data-product-id="${item.id}" style="border-color: #1E40AF;">
                                <button class="btn btn-outline-primary quantity-btn" type="button" 
                                        data-action="increase" data-product-id="${item.id}" style="border-color: #1E40AF;">
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
                <hr style="border-color: #DBEAFE;">
            </div>
        `;
    });
    
    itemsHTML += '</div>';
    
    // Agregar totales con estilo corporativo
    const totals = data.totals;
    itemsHTML += `
        <div class="cart-totals p-3 rounded" style="background: linear-gradient(135deg, #DBEAFE 0%, #f8fafc 100%); border: 2px solid #1E40AF;">
            ${totals.subtotal_raw > 0 ? `
                <div class="row mb-2">
                    <div class="col-6"><span style="color: #1E40AF; font-weight: 600;">Subtotal:</span></div>
                    <div class="col-6 text-end"><span class="cart-subtotal" style="color: #1E40AF; font-weight: 700;">${totals.subtotal}</span></div>
                </div>
                ${totals.tax_raw > 0 ? `
                    <div class="row mb-2">
                        <div class="col-6"><span style="color: #1E40AF; font-weight: 600;">Impuestos (${totals.tax_rate}%):</span></div>
                        <div class="col-6 text-end"><span class="cart-tax" style="color: #1E40AF; font-weight: 700;">${totals.tax}</span></div>
                    </div>
                ` : ''}
                <hr class="my-2" style="border-color: #1E40AF;">
            ` : ''}
            <div class="row">
                <div class="col-6"><strong style="color: #1E40AF;">Total:</strong></div>
                <div class="col-6 text-end"><strong class="cart-total" style="color: #1E40AF; font-size: 1.2rem;">${totals.total}</strong></div>
            </div>
        </div>
    `;
    
    modalBody.innerHTML = itemsHTML;
    
    // Actualizar footer del modal
    updateCartModalFooter(true);
}

/**
 * Mostrar carrito vacío con estilo corporativo
 */
function showEmptyCart() {
    const modalBody = document.getElementById('cart-modal-body');
    modalBody.innerHTML = `
        <div class="empty-cart text-center py-5" style="background: linear-gradient(135deg, #f8fafc 0%, #DBEAFE 100%); border-radius: 12px; border: 2px solid #1E40AF;">
            <i class="fas fa-shopping-cart fa-3x mb-3" style="color: #1E40AF;"></i>
            <h5 style="color: #1E40AF; font-weight: 700;">Tu carrito está vacío</h5>
            <p class="text-muted mb-4">Explora nuestros productos y agrega algunos al carrito</p>
            <a href="/productos" class="btn btn-corporate" data-bs-dismiss="modal">
                <i class="fas fa-search me-2"></i>Explorar Productos
            </a>
        </div>
    `;
    
    updateCartModalFooter(false);
}

/**
 * Actualizar footer del modal con estilo corporativo
 */
function updateCartModalFooter(hasItems) {
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
        footer.style.borderTop = '2px solid #1E40AF';
        modal.querySelector('.modal-content').appendChild(footer);
    }
    
    footer.innerHTML = `
        <div class="w-100">
            <div class="row g-2">
                <div class="col-6">
                    <a href="/pages/cart.php" class="btn btn-outline-primary w-100" data-bs-dismiss="modal" style="border-color: #1E40AF; color: #1E40AF;">
                        <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                    </a>
                </div>
                <div class="col-6">
                    <a href="/pages/checkout.php" class="btn btn-corporate w-100" data-bs-dismiss="modal">
                        <i class="fas fa-credit-card me-2"></i>Proceder al Pago
                    </a>
                </div>
            </div>
            <div class="text-center mt-2">
                <button type="button" class="btn btn-link btn-sm text-muted" onclick="clearCart()">
                    <i class="fas fa-trash me-1"></i>Vaciar Carrito
                </button>
            </div>
        </div>
    `;
}

/**
 * Eliminar item del DOM con animación corporativa
 */
function removeCartItemFromDOM(productId) {
    const item = document.querySelector(`[data-product-id="${productId}"]`);
    if (item) {
        item.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
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
function updateCartItemDOM(productId, data) {
    const item = document.querySelector(`[data-product-id="${productId}"]`);
    if (item) {
        const subtotalSpan = item.querySelector('.item-subtotal');
        if (subtotalSpan && data.item_subtotal) {
            subtotalSpan.textContent = data.item_subtotal;
            subtotalSpan.style.animation = 'corporate-pulse 0.5s ease';
        }
    }
}

/**
 * Actualizar totales en el DOM
 */
function updateCartTotals(totals) {
    const elements = {
        '.cart-subtotal': totals.subtotal,
        '.cart-tax': totals.tax,
        '.cart-total': totals.total
    };
    
    Object.entries(elements).forEach(([selector, value]) => {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = value;
            element.style.animation = 'corporate-pulse 0.5s ease';
        }
    });
}

/**
 * Animar icono del carrito con estilo corporativo
 */
function animateCartIcon() {
    const cartIcon = document.querySelector('.cart-icon');
    if (cartIcon) {
        cartIcon.style.animation = 'corporate-pulse 0.8s ease';
        setTimeout(() => {
            cartIcon.style.animation = '';
        }, 800);
    }
}

/**
 * Mostrar notificación del carrito con diseño corporativo
 */
function showCartNotification(message, type = 'info') {
    // Crear elemento de notificación corporativa
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible cart-notification`;
    notification.style.cssText = `
        position: fixed;
        top: 120px;
        right: 30px;
        z-index: 9999;
        min-width: 350px;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(15px);
        border: 2px solid #1E40AF;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(30, 64, 175, 0.2);
        color: #1E40AF;
        font-weight: 600;
    `;
    
    const iconMap = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    
    notification.innerHTML = `
        <i class="fas fa-${iconMap[type]} me-2" style="color: #1E40AF;"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" style="filter: hue-rotate(210deg);"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Animar entrada
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-remover después de 4 segundos
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

// === EFECTOS ADICIONALES CORPORATIVOS ===

// Back to top functionality con estilo corporativo
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Show/hide back to top button con animación
window.addEventListener('scroll', function() {
    const backToTop = document.querySelector('.back-to-top');
    if (backToTop) {
        if (window.scrollY > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    }
});

// Newsletter form handling corporativo
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[name="email"]').value;
            
            // Mostrar loading
            showLoadingBar();
            
            // TODO: Implementar envío real más adelante
            setTimeout(() => {
                showCartNotification('¡Gracias por suscribirte! Te enviaremos nuestras novedades.', 'success');
                this.reset();
            }, 1000);
        });
    }
});

// CSS para animaciones corporativas
const corporateCSS = `
@keyframes corporate-pulse {
    0%, 100% { 
        box-shadow: 0 0 0 0 rgba(30, 64, 175, 0.4);
        transform: scale(1);
    }
    50% { 
        box-shadow: 0 0 0 10px rgba(30, 64, 175, 0);
        transform: scale(1.05);
    }
}

@keyframes loading-bar {
    0% { width: 0%; }
    100% { width: 100%; }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

.cart-notification {
    box-shadow: 0 10px 25px rgba(30, 64, 175, 0.2) !important;
    border: 2px solid #1E40AF !important;
}

.btn-corporate {
    background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%) !important;
    border: none !important;
    color: white !important;
    padding: 12px 24px !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    position: relative !important;
    overflow: hidden !important;
}

.btn-corporate::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.btn-corporate:hover::before {
    left: 100%;
}

.btn-corporate:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 10px 25px rgba(30, 64, 175, 0.3) !important;
    color: white !important;
}

.loading-bar {
    position: fixed;
    top: 0;
    left: 0;
    height: 3px;
    background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
    z-index: 9999;
    animation: loading-bar 1s ease-in-out;
}

.fade-in-up {
    animation: fadeInUp 0.8s ease forwards;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
`;

// Agregar CSS corporativo al head
if (!document.getElementById('corporate-styles')) {
    const style = document.createElement('style');
    style.id = 'corporate-styles';
    style.textContent = corporateCSS;
    document.head.appendChild(style);
}

// === INICIALIZACIÓN FINAL ===
// Llamar funciones adicionales cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    enhanceSmoothTransitions();
    
    // Configurar tooltips corporativos de Bootstrap si están disponibles
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Configurar popovers corporativos de Bootstrap si están disponibles
    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
});