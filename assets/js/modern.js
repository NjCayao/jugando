/**
 * =========================================
 * 🚀 FUTURISTIC CYBERPUNK JAVASCRIPT
 * Efectos que ningún programador ha visto
 * =========================================
 */

// === CONFIGURACIÓN CYBERPUNK ===
const SITE_URL = window.SITE_URL || '';
const CYBERPUNK_CONFIG = {
    matrixChars: '01アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホマミムメモヤユヨラリルレロワヲン',
    glitchTexts: ['LOADING...', 'ACCESSING...', 'HACKING...', 'CONNECTING...', 'PROCESSING...'],
    colors: ['#00ffff', '#ff0080', '#00ff41', '#8000ff', '#ffff00']
};

// === CLASE PRINCIPAL CYBERPUNK ===
class CyberpunkSystem {
    constructor() {
        this.matrixInterval = null;
        this.glitchInterval = null;
        this.scanLines = [];
        this.init();
    }

    init() {
        this.createMatrixBackground();
        this.initHeaderEffects();
        this.initGlitchEffects();
        this.initScanLines();
        this.initHologramEffects();
        this.initParticleSystem();
        this.initCyberpunkAnimations();
        this.initCartSystem();
        this.initScrollEffects();
    }

    // === EFECTO MATRIX DE FONDO ===
    createMatrixBackground() {
        const matrixContainer = document.createElement('div');
        matrixContainer.className = 'matrix-bg';
        document.body.appendChild(matrixContainer);

        const createMatrixColumn = () => {
            const column = document.createElement('div');
            column.style.position = 'absolute';
            column.style.left = Math.random() * 100 + '%';
            column.style.top = '-100px';
            column.style.fontSize = (Math.random() * 20 + 10) + 'px';
            column.style.color = CYBERPUNK_CONFIG.colors[Math.floor(Math.random() * CYBERPUNK_CONFIG.colors.length)];
            column.style.fontFamily = 'Fira Code, monospace';
            column.style.textShadow = '0 0 10px currentColor';
            column.style.userSelect = 'none';
            column.style.pointerEvents = 'none';

            let text = '';
            for (let i = 0; i < Math.random() * 20 + 5; i++) {
                text += CYBERPUNK_CONFIG.matrixChars.charAt(Math.floor(Math.random() * CYBERPUNK_CONFIG.matrixChars.length)) + '<br>';
            }
            column.innerHTML = text;

            const speed = Math.random() * 3 + 1;
            column.style.animation = `matrix-fall ${speed}s linear`;

            matrixContainer.appendChild(column);

            setTimeout(() => {
                if (column.parentNode) {
                    column.remove();
                }
            }, speed * 1000);
        };

        // Crear columnas de matrix continuamente
        this.matrixInterval = setInterval(createMatrixColumn, 200);
    }

    // === EFECTOS DE HEADER ===
    initHeaderEffects() {
        const header = document.querySelector('.main-header');
        if (!header) return;

        // Efecto de scroll cyberpunk
        window.addEventListener('scroll', () => {
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
                this.createScanLine(header);
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Efectos de hover en navegación
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navLinks.forEach(link => {
            link.addEventListener('mouseenter', () => {
                this.createGlitchEffect(link);
            });
        });
    }

    // === EFECTOS DE GLITCH ===
    initGlitchEffects() {
        const glitchElements = document.querySelectorAll('.hero-title, .section-title');
        
        glitchElements.forEach(element => {
            // Agregar atributo data-text para el efecto
            element.setAttribute('data-text', element.textContent);
            
            element.addEventListener('mouseenter', () => {
                this.createTextGlitch(element);
            });
        });
    }

    createTextGlitch(element) {
        const originalText = element.textContent;
        let glitchCount = 0;
        const maxGlitches = 10;

        const glitchInterval = setInterval(() => {
            if (glitchCount >= maxGlitches) {
                element.textContent = originalText;
                clearInterval(glitchInterval);
                return;
            }

            let glitchedText = '';
            for (let i = 0; i < originalText.length; i++) {
                if (Math.random() < 0.1) {
                    glitchedText += CYBERPUNK_CONFIG.matrixChars.charAt(Math.floor(Math.random() * CYBERPUNK_CONFIG.matrixChars.length));
                } else {
                    glitchedText += originalText[i];
                }
            }
            
            element.textContent = glitchedText;
            glitchCount++;
        }, 50);
    }

    createGlitchEffect(element) {
        const glitch = document.createElement('div');
        glitch.style.position = 'absolute';
        glitch.style.top = '0';
        glitch.style.left = '0';
        glitch.style.width = '100%';
        glitch.style.height = '100%';
        glitch.style.background = `linear-gradient(90deg, transparent, ${CYBERPUNK_CONFIG.colors[Math.floor(Math.random() * CYBERPUNK_CONFIG.colors.length)]}40, transparent)`;
        glitch.style.pointerEvents = 'none';
        glitch.style.animation = 'glitch-sweep 0.3s ease-out';

        element.style.position = 'relative';
        element.appendChild(glitch);

        setTimeout(() => {
            if (glitch.parentNode) {
                glitch.remove();
            }
        }, 300);
    }

    // === LÍNEAS DE ESCANEO ===
    initScanLines() {
        const addScanLineCSS = () => {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes glitch-sweep {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
                
                @keyframes scan-line-move {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }
            `;
            document.head.appendChild(style);
        };
        addScanLineCSS();
    }

    createScanLine(container) {
        if (container.querySelector('.scan-line-effect')) return;

        const scanLine = document.createElement('div');
        scanLine.className = 'scan-line-effect';
        scanLine.style.position = 'absolute';
        scanLine.style.top = '0';
        scanLine.style.left = '0';
        scanLine.style.width = '100%';
        scanLine.style.height = '2px';
        scanLine.style.background = 'linear-gradient(90deg, transparent, #00ffff, transparent)';
        scanLine.style.animation = 'scan-line-move 2s ease-in-out';
        scanLine.style.pointerEvents = 'none';

        container.style.position = 'relative';
        container.appendChild(scanLine);

        setTimeout(() => {
            if (scanLine.parentNode) {
                scanLine.remove();
            }
        }, 2000);
    }

    // === EFECTOS HOLOGRÁFICOS ===
    initHologramEffects() {
        const cards = document.querySelectorAll('.category-card, .product-card, .floating-card');
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                this.createHologramScan(card);
            });

            card.addEventListener('mousemove', (e) => {
                this.createMouseTrail(e);
            });
        });
    }

    createHologramScan(element) {
        const scan = document.createElement('div');
        scan.style.position = 'absolute';
        scan.style.top = '0';
        scan.style.left = '0';
        scan.style.width = '100%';
        scan.style.height = '4px';
        scan.style.background = 'linear-gradient(90deg, transparent, #00ffff, #ff0080, #00ffff, transparent)';
        scan.style.animation = 'hologram-scan 1s ease-in-out';
        scan.style.pointerEvents = 'none';
        scan.style.zIndex = '10';

        element.style.position = 'relative';
        element.appendChild(scan);

        setTimeout(() => {
            if (scan.parentNode) {
                scan.remove();
            }
        }, 1000);
    }

    createMouseTrail(e) {
        const trail = document.createElement('div');
        trail.style.position = 'fixed';
        trail.style.left = e.clientX - 5 + 'px';
        trail.style.top = e.clientY - 5 + 'px';
        trail.style.width = '10px';
        trail.style.height = '10px';
        trail.style.background = CYBERPUNK_CONFIG.colors[Math.floor(Math.random() * CYBERPUNK_CONFIG.colors.length)];
        trail.style.borderRadius = '50%';
        trail.style.pointerEvents = 'none';
        trail.style.zIndex = '9999';
        trail.style.animation = 'trail-fade 0.5s ease-out forwards';
        trail.style.boxShadow = '0 0 10px currentColor';

        document.body.appendChild(trail);

        setTimeout(() => {
            if (trail.parentNode) {
                trail.remove();
            }
        }, 500);
    }

    // === SISTEMA DE PARTÍCULAS ===
    initParticleSystem() {
        const addParticleCSS = () => {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes trail-fade {
                    0% { opacity: 1; transform: scale(1); }
                    100% { opacity: 0; transform: scale(0); }
                }
                
                @keyframes hologram-scan {
                    0% { transform: translateY(0); opacity: 1; }
                    100% { transform: translateY(100px); opacity: 0; }
                }
                
                @keyframes particle-float {
                    0% { transform: translateY(0) rotate(0deg); opacity: 1; }
                    100% { transform: translateY(-100px) rotate(360deg); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        };
        addParticleCSS();
    }

    // === ANIMACIONES CYBERPUNK ===
    initCyberpunkAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.8s ease forwards';
                    this.createDataStream(entry.target);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in-up').forEach(el => {
            observer.observe(el);
        });

        // Animaciones de contadores con efectos
        this.initCounterAnimations();
    }

    createDataStream(element) {
        for (let i = 0; i < 5; i++) {
            setTimeout(() => {
                const particle = document.createElement('div');
                particle.style.position = 'absolute';
                particle.style.width = '2px';
                particle.style.height = '2px';
                particle.style.background = CYBERPUNK_CONFIG.colors[Math.floor(Math.random() * CYBERPUNK_CONFIG.colors.length)];
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = '100%';
                particle.style.animation = 'particle-float 2s ease-out forwards';
                particle.style.pointerEvents = 'none';
                particle.style.boxShadow = '0 0 5px currentColor';

                element.style.position = 'relative';
                element.appendChild(particle);

                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.remove();
                    }
                }, 2000);
            }, i * 100);
        }
    }

    initCounterAnimations() {
        const counters = document.querySelectorAll('[data-counter]');
        
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    const target = parseInt(element.dataset.counter) || parseInt(element.textContent);
                    
                    this.animateCounterWithEffects(element, target);
                    counterObserver.unobserve(element);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(counter => counterObserver.observe(counter));
    }

    animateCounterWithEffects(element, target, duration = 2000) {
        const start = 0;
        const increment = target / (duration / 50);
        let current = start;

        const counterInterval = setInterval(() => {
            current += increment;
            
            if (current >= target) {
                element.textContent = target;
                clearInterval(counterInterval);
                this.createCounterGlow(element);
            } else {
                element.textContent = Math.floor(current);
                
                // Efecto de glitch ocasional
                if (Math.random() < 0.1) {
                    element.style.textShadow = `0 0 20px ${CYBERPUNK_CONFIG.colors[Math.floor(Math.random() * CYBERPUNK_CONFIG.colors.length)]}`;
                    setTimeout(() => {
                        element.style.textShadow = '0 0 10px #00ffff';
                    }, 100);
                }
            }
        }, 50);
    }

    createCounterGlow(element) {
        element.style.animation = 'counter-pulse 1s ease-in-out';
        setTimeout(() => {
            element.style.animation = '';
        }, 1000);
    }

    // === SISTEMA DE CARRITO CYBERPUNK ===
    initCartSystem() {
        this.cartUpdating = false;
        this.updateCartDisplay();
        this.initCartEvents();
    }

    async updateCartDisplay() {
        try {
            const response = await fetch('/api/cart/get.php');
            const data = await response.json();
            
            if (data.success) {
                this.updateCartCount(data.items_count);
            }
        } catch (error) {
            console.error('Error loading cart:', error);
        }
    }

    updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('#cart-count, #modal-cart-count');
        cartCountElements.forEach(element => {
            element.textContent = count;
            element.style.display = count > 0 ? 'inline' : 'none';
            
            if (count > 0) {
                this.createPulseEffect(element);
            }
        });
    }

    createPulseEffect(element) {
        element.style.animation = 'cyber-pulse 0.5s ease';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }

    async addToCart(productId, quantity = 1) {
        if (this.cartUpdating) return;
        
        this.cartUpdating = true;
        
        // Efecto de loading cyberpunk
        const addButton = document.querySelector(`[onclick="addToCart(${productId})"]`);
        let originalText = '';
        if (addButton) {
            originalText = addButton.innerHTML;
            addButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> PROCESANDO...';
            addButton.disabled = true;
            this.createLoadingEffect(addButton);
        }
        
        try {
            const response = await fetch('api/cart/add.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=${quantity}`
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.cart_count);
                this.showCyberpunkNotification(data.message, 'success');
                this.animateCartIcon();
            } else {
                this.showCyberpunkNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showCyberpunkNotification('ERROR DE CONEXIÓN', 'error');
        } finally {
            this.cartUpdating = false;
            
            if (addButton) {
                addButton.innerHTML = originalText || '<i class="fas fa-cart-plus me-2"></i>AGREGAR';
                addButton.disabled = false;
            }
        }
    }

    createLoadingEffect(element) {
        const loadingOverlay = document.createElement('div');
        loadingOverlay.style.position = 'absolute';
        loadingOverlay.style.top = '0';
        loadingOverlay.style.left = '0';
        loadingOverlay.style.width = '100%';
        loadingOverlay.style.height = '100%';
        loadingOverlay.style.background = 'linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.3), transparent)';
        loadingOverlay.style.animation = 'loading-scan 1s linear infinite';
        loadingOverlay.style.pointerEvents = 'none';

        element.style.position = 'relative';
        element.appendChild(loadingOverlay);

        setTimeout(() => {
            if (loadingOverlay.parentNode) {
                loadingOverlay.remove();
            }
        }, 2000);
    }

    animateCartIcon() {
        const cartIcon = document.querySelector('.cart-icon');
        if (cartIcon) {
            cartIcon.style.animation = 'cyber-bounce 0.8s ease';
            this.createEnergyBurst(cartIcon);
            setTimeout(() => {
                cartIcon.style.animation = '';
            }, 800);
        }
    }

    createEnergyBurst(element) {
        for (let i = 0; i < 8; i++) {
            const burst = document.createElement('div');
            burst.style.position = 'absolute';
            burst.style.width = '4px';
            burst.style.height = '4px';
            burst.style.background = CYBERPUNK_CONFIG.colors[Math.floor(Math.random() * CYBERPUNK_CONFIG.colors.length)];
            burst.style.borderRadius = '50%';
            burst.style.left = '50%';
            burst.style.top = '50%';
            burst.style.transform = `translate(-50%, -50%) rotate(${i * 45}deg) translateY(-20px)`;
            burst.style.animation = 'energy-burst 0.6s ease-out forwards';
            burst.style.pointerEvents = 'none';
            burst.style.boxShadow = '0 0 10px currentColor';

            element.style.position = 'relative';
            element.appendChild(burst);

            setTimeout(() => {
                if (burst.parentNode) {
                    burst.remove();
                }
            }, 600);
        }
    }

    showCyberpunkNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `cyberpunk-notification notification-${type}`;
        
        const colors = {
            success: '#00ff41',
            error: '#ff0080',
            warning: '#ffff00',
            info: '#00ffff'
        };

        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };

        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            min-width: 350px;
            background: rgba(0, 0, 0, 0.95);
            border: 2px solid ${colors[type]};
            color: ${colors[type]};
            padding: 1rem 1.5rem;
            font-family: 'Fira Code', monospace;
            text-transform: uppercase;
            letter-spacing: 1px;
            clip-path: polygon(10% 0%, 90% 0%, 100% 10%, 100% 90%, 90% 100%, 10% 100%, 0% 90%, 0% 10%);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 20px ${colors[type]}40;
        `;
        
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-${icons[type]}" style="font-size: 1.5rem; animation: cyber-pulse 1s infinite;"></i>
                <div>
                    <div style="font-weight: 700; margin-bottom: 0.25rem;">[SISTEMA]</div>
                    <div style="font-size: 0.9rem;">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: 1px solid ${colors[type]}; color: ${colors[type]}; padding: 0.25rem; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animación de entrada
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);
        
        // Efecto de escaneo
        this.createScanLine(notification);
        
        // Auto-remover
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500);
        }, 5000);
    }

    initCartEvents() {
        // Eventos del carrito con efectos cyberpunk
        document.addEventListener('click', (e) => {
            if (e.target.closest('.cart-icon a')) {
                e.preventDefault();
                this.loadCartContent();
                const cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
                cartModal.show();
            }
        });

        // Otros eventos del carrito manteniendo funcionalidad original
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
                this.updateCartQuantity(productId, currentQuantity);
            }
        });
    }

    async loadCartContent() {
        try {
            const response = await fetch(`/api/cart/get.php`);
            const data = await response.json();
            
            if (data.success) {
                this.updateCartModal(data);
                this.updateCartCount(data.items_count);
            } else {
                this.showCyberpunkNotification('ERROR AL CARGAR CARRITO', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showCyberpunkNotification('ERROR DE CONEXIÓN', 'error');
        }
    }

    updateCartModal(data) {
        // Implementación del modal del carrito con efectos cyberpunk
        const modalBody = document.getElementById('cart-modal-body');
        
        if (data.cart_empty) {
            this.showEmptyCart();
            return;
        }
        
        // Generar HTML con estilos cyberpunk
        let itemsHTML = '<div class="cart-items">';
        
        data.items.forEach(item => {
            itemsHTML += `
                <div class="cart-item cyberpunk-item" data-product-id="${item.id}" style="border: 1px solid #00ffff; margin-bottom: 1rem; padding: 1rem; background: rgba(0,0,0,0.8);">
                    <div class="row align-items-center">
                        <div class="col-3">
                            <div class="product-image">
                                ${item.image_url ? 
                                    `<img src="${item.image_url}" alt="${item.name}" class="img-fluid rounded" style="max-height: 60px; object-fit: cover; filter: hue-rotate(180deg);">` :
                                    `<div class="no-image bg-dark rounded d-flex align-items-center justify-content-center" style="height: 60px; color: #00ffff;">
                                        <i class="fas fa-image"></i>
                                    </div>`
                                }
                            </div>
                        </div>
                        <div class="col-6">
                            <h6 class="mb-1" style="color: #00ffff; font-family: 'Orbitron', sans-serif;">
                                ${item.name}
                            </h6>
                            ${item.category_name ? `<small style="color: #00ff41;">${item.category_name}</small>` : ''}
                            <div class="price-info mt-1">
                                ${item.is_free ? 
                                    '<span style="color: #00ff41; font-weight: bold;">GRATIS</span>' :
                                    `<span style="color: #ffff00; font-weight: bold;">${item.price}</span>`
                                }
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="quantity-controls">
                                <div class="input-group input-group-sm">
                                    <button class="btn btn-outline-info quantity-btn" type="button" 
                                            data-action="decrease" data-product-id="${item.id}">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" class="form-control text-center quantity-input" 
                                           value="${item.quantity}" min="1" max="10" 
                                           data-product-id="${item.id}" style="background: rgba(0,0,0,0.8); color: #00ffff; border: 1px solid #00ffff;">
                                    <button class="btn btn-outline-info quantity-btn" type="button" 
                                            data-action="increase" data-product-id="${item.id}">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-outline-danger mt-2 remove-item" 
                                        data-product-id="${item.id}">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        itemsHTML += '</div>';
        
        modalBody.innerHTML = itemsHTML;
    }

    showEmptyCart() {
        const modalBody = document.getElementById('cart-modal-body');
        modalBody.innerHTML = `
            <div class="empty-cart text-center py-5" style="color: #00ffff;">
                <i class="fas fa-shopping-cart fa-3x mb-3" style="animation: cyber-pulse 2s infinite;"></i>
                <h5 style="color: #ff0080; font-family: 'Orbitron', sans-serif; text-transform: uppercase;">CARRITO VACÍO</h5>
                <p style="color: #00ff41; font-family: 'Fira Code', monospace;">SISTEMA LISTO PARA NUEVOS PRODUCTOS</p>
                <a href="/productos" class="btn btn-primary" data-bs-dismiss="modal" style="border: 2px solid #00ffff; background: transparent; color: #00ffff;">
                    <i class="fas fa-search me-2"></i>EXPLORAR PRODUCTOS
                </a>
            </div>
        `;
    }

    async updateCartQuantity(productId, quantity) {
        if (this.cartUpdating) return;
        
        this.cartUpdating = true;

        try {
            const response = await fetch('api/cart/update.php', {
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
                    this.showCyberpunkNotification('PRODUCTO ELIMINADO', 'info');
                }
                
                if (data.cart_count === 0) {
                    this.showEmptyCart();
                }
            } else {
                this.showCyberpunkNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showCyberpunkNotification('ERROR DE ACTUALIZACIÓN', 'error');
        } finally {
            this.cartUpdating = false;
        }
    }

    removeCartItemFromDOM(productId) {
        const item = document.querySelector(`[data-product-id="${productId}"]`);
        if (item) {
            item.style.transition = 'all 0.5s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateX(100px)';
            setTimeout(() => {
                item.remove();
            }, 500);
        }
    }

    // === EFECTOS DE SCROLL ===
    initScrollEffects() {
        let ticking = false;

        const updateScrollEffects = () => {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.5;

            // Parallax cyberpunk
            const heroSection = document.querySelector('.hero-section, .hero-carousel-section');
            if (heroSection) {
                heroSection.style.transform = `translateY(${rate}px)`;
            }

            // Efectos en elementos
            const cards = document.querySelectorAll('.category-card, .product-card');
            cards.forEach((card, index) => {
                const rect = card.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) {
                    const offset = (window.innerHeight - rect.top) / window.innerHeight;
                    card.style.transform = `translateY(${offset * 20}px)`;
                }
            });

            ticking = false;
        };

        const requestScrollUpdate = () => {
            if (!ticking) {
                requestAnimationFrame(updateScrollEffects);
                ticking = true;
            }
        };

        window.addEventListener('scroll', requestScrollUpdate);

        // Back to top cyberpunk
        const backToTop = document.querySelector('.back-to-top');
        if (backToTop) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) {
                    backToTop.classList.add('show');
                } else {
                    backToTop.classList.remove('show');
                }
            });

            backToTop.addEventListener('click', () => {
                this.showCyberpunkNotification('REGRESANDO AL INICIO', 'info');
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }
    }

    // === DESTRUCTOR ===
    destroy() {
        if (this.matrixInterval) {
            clearInterval(this.matrixInterval);
        }
        if (this.glitchInterval) {
            clearInterval(this.glitchInterval);
        }
    }
}

// === FUNCIONES GLOBALES PARA COMPATIBILIDAD ===
let cyberpunkSystem;

document.addEventListener('DOMContentLoaded', () => {
    cyberpunkSystem = new CyberpunkSystem();
    
    // Configurar variable global para JavaScript
    window.SITE_URL = window.SITE_URL || '';
    
    // Smooth scroll para enlaces
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// Funciones globales para mantener compatibilidad
window.addToCart = (productId, quantity = 1) => {
    if (cyberpunkSystem) {
        cyberpunkSystem.addToCart(productId, quantity);
    }
};

window.addToWishlist = (productId) => {
    if (cyberpunkSystem) {
        cyberpunkSystem.showCyberpunkNotification('FUNCIÓN DE FAVORITOS EN DESARROLLO', 'info');
    }
};

// CSS adicional para efectos cyberpunk
const cyberpunkCSS = `
@keyframes cyber-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
}

@keyframes cyber-bounce {
    0%, 20%, 60%, 100% { transform: translateY(0); }
    40% { transform: translateY(-15px); }
    80% { transform: translateY(-8px); }
}

@keyframes loading-scan {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

@keyframes energy-burst {
    0% { opacity: 1; transform: translate(-50%, -50%) rotate(var(--angle)) translateY(-20px) scale(1); }
    100% { opacity: 0; transform: translate(-50%, -50%) rotate(var(--angle)) translateY(-50px) scale(0); }
}

@keyframes counter-pulse {
    0% { transform: scale(1); filter: brightness(1); }
    50% { transform: scale(1.1); filter: brightness(1.5); }
    100% { transform: scale(1); filter: brightness(1); }
}

.cyberpunk-item {
    position: relative;
    overflow: hidden;
}

.cyberpunk-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 255, 255, 0.1), transparent);
    animation: loading-scan 3s infinite;
    pointer-events: none;
}
`;

// Agregar CSS cyberpunk
if (!document.getElementById('cyberpunk-styles')) {
    const style = document.createElement('style');
    style.id = 'cyberpunk-styles';
    style.textContent = cyberpunkCSS;
    document.head.appendChild(style);
}