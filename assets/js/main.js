// assets/js/main.js - JAVASCRIPT CORPORATIVO PRINCIPAL (SIN CARRITO)

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
                showNotification('¡Gracias por suscribirte! Te enviaremos nuestras novedades.', 'success');
                this.reset();
            }, 1000);
        });
    }
});

// === UTILIDADES GENERALES ===

/**
 * Mostrar notificación general
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible`;
    notification.style.cssText = `
        position: fixed;
        top: 120px;
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

// === CSS CORPORATIVO ===
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

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
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

.back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #1E40AF 0%, #3B82F6 100%);
    color: white;
    border: none;
    border-radius: 50%;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    transform: translateY(100px);
    opacity: 0;
    z-index: 1000;
}

.back-to-top.show {
    transform: translateY(0);
    opacity: 1;
}

.back-to-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(30, 64, 175, 0.3);
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
document.addEventListener('DOMContentLoaded', function() {
    enhanceSmoothTransitions();
});