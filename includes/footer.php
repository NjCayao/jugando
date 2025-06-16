<?php
// includes/footer.php - FOOTER LIMPIO Y ORDENADO
$siteName = getSetting('site_name', 'WebSystems Pro');
$siteDescription = getSetting('site_description', 'Sistemas Web Profesionales para su Empresa');

// Obtener categorías principales para el footer
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("
        SELECT * FROM categories 
        WHERE is_active = 1 
        ORDER BY name ASC 
        LIMIT 4
    ");
    $footerCategories = $stmt->fetchAll();
} catch (Exception $e) {
    $footerCategories = [];
}
?>

<footer class="clean-footer">
    <!-- Main Footer Content -->
    <div class="footer-content">
        <div class="container">
            <div class="row g-5">
                <!-- Company Info -->
                <div class="col-lg-4">
                    <div class="footer-brand">
                        <h4 class="brand-name"><?php echo htmlspecialchars($siteName); ?></h4>
                        <p class="brand-tagline">Professional Development Solutions</p>
                    </div>
                    <p class="footer-description">
                        <?php echo htmlspecialchars($siteDescription); ?>. 
                        Soluciones tecnológicas innovadoras para tu negocio.
                    </p>
                    
                    <!-- Contact -->
                    <div class="footer-contact">
                        <?php if (getSetting('site_email')): ?>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo getSetting('site_email'); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (getSetting('contact_phone')): ?>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span><?php echo getSetting('contact_phone'); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Enlaces</h5>
                    <ul class="footer-links">
                        <li><a href="<?php echo SITE_URL; ?>">Inicio</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/productos">Productos</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/sobre-nosotros">Nosotros</a></li>
                        <li><a href="<?php echo SITE_URL; ?>/contacto">Contacto</a></li>
                    </ul>
                </div>
                
                <!-- Categories -->
                <div class="col-lg-2 col-md-6">
                    <h5 class="footer-title">Categorías</h5>
                    <ul class="footer-links">
                        <?php if (empty($footerCategories)): ?>
                            <li><a href="<?php echo SITE_URL; ?>/categoria/sistemas-web">Sistemas Web</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/categoria/aplicaciones">Aplicaciones</a></li>
                            <li><a href="<?php echo SITE_URL; ?>/categoria/plantillas">Plantillas</a></li>
                        <?php else: ?>
                            <?php foreach ($footerCategories as $category): ?>
                                <li>
                                    <a href="<?php echo SITE_URL; ?>/categoria/<?php echo $category['slug']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Newsletter & Social -->
                <div class="col-lg-4">
                    <h5 class="footer-title">Mantente Conectado</h5>
                    <p class="newsletter-text">
                        Recibe actualizaciones y ofertas especiales.
                    </p>
                    
                    <!-- Newsletter Form -->
                    <form class="newsletter-form" action="<?php echo SITE_URL; ?>/api/newsletter" method="POST">
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Tu email" name="email" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                    
                    <!-- Social Media -->
                    <div class="footer-social">
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
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="copyright">
                        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($siteName); ?>. Todos los derechos reservados.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-legal">
                        <a href="<?php echo SITE_URL; ?>/politica-privacidad">Privacidad</a>
                        <span>|</span>
                        <a href="<?php echo SITE_URL; ?>/terminos-condiciones">Términos</a>
                        <?php if (isAdmin()): ?>
                            <span>|</span>
                            <a href="<?php echo ADMIN_URL; ?>">Admin</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<button class="back-to-top" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</button>

<script>
// Newsletter form
document.addEventListener('DOMContentLoaded', function() {
    const newsletterForm = document.querySelector('.newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[name="email"]').value;
            showCartNotification('¡Gracias por suscribirte!', 'success');
            this.reset();
        });
    }
});
</script>