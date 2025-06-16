<?php
// pages/page.php - Páginas dinámicas del CMS
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener slug de la página
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header("HTTP/1.0 404 Not Found");
    include '../404.php';
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener página
    $stmt = $db->prepare("SELECT * FROM pages WHERE slug = ? AND is_active = 1");
    $stmt->execute([$slug]);
    $page = $stmt->fetch();
    
    if (!$page) {
        header("HTTP/1.0 404 Not Found");
        include '../404.php';
        exit;
    }
    
    // Incrementar contador de vistas
    $stmt = $db->prepare("UPDATE pages SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
    $stmt->execute([$page['id']]);
    
} catch (Exception $e) {
    logError("Error en página dinámica: " . $e->getMessage());
    header("HTTP/1.0 500 Internal Server Error");
    include '../500.php';
    exit;
}

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = $page['meta_title'] ?: $page['title'];
$pageDescription = $page['meta_description'] ?: substr(strip_tags($page['content']), 0, 160);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($siteName); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/<?php echo $page['slug']; ?>">
    
    <!-- Favicon -->
    <?php if (Settings::get('site_favicon')): ?>
        <link rel="icon" type="image/png" href="<?php echo UPLOADS_URL; ?>/logos/<?php echo Settings::get('site_favicon'); ?>">
    <?php endif; ?>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="bg-gradient-luxury text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h1 class="mb-3"><?php echo htmlspecialchars($page['title']); ?></h1>
                    
                    <!-- Page Meta -->
                    <div class="d-flex justify-content-center gap-4 mb-3">
                        <small class="opacity-75">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo date('d/m/Y', strtotime($page['created_at'])); ?>
                        </small>
                        <small class="opacity-75">
                            <i class="fas fa-edit me-1"></i>
                            <?php echo date('d/m/Y', strtotime($page['updated_at'])); ?>
                        </small>
                    </div>
                    
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                            <li class="breadcrumb-item active text-white"><?php echo htmlspecialchars($page['title']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container main-page-container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="page-content-wrapper">
                        <!-- Tabla de Contenidos (si es necesaria) -->
                        <?php 
                        $content = $page['content'];
                        $hasHeadings = preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/i', $content, $headings);
                        if ($hasHeadings && count($headings[0]) > 3):
                        ?>
                            <div class="category-card mb-3">
                                <div class="category-content p-3">
                                    <h6 class="category-title">
                                        <i class="fas fa-list me-2"></i>Tabla de Contenidos
                                    </h6>
                                    <ul class="list-unstyled">
                                        <?php
                                        foreach ($headings[0] as $index => $heading) {
                                            $level = $headings[1][$index];
                                            $text = strip_tags($headings[2][$index]);
                                            $id = 'heading-' . $index;
                                            // Agregar ID al heading en el contenido
                                            $content = str_replace($heading, str_replace('>', ' id="' . $id . '">', $heading), $content);
                                            
                                            echo '<li class="mb-1"><a href="#' . $id . '" class="text-decoration-none">' . htmlspecialchars($text) . '</a></li>';
                                        }
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Contenido de la Página -->
                        <div class="category-card mb-3">
                            <div class="category-content p-4">
                                <div class="user-content">
                                    <?php echo $content; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Compartir en Redes Sociales -->
                        <div class="category-card mb-3">
                            <div class="category-content p-3">
                                <h6 class="category-title mb-3">
                                    <i class="fas fa-share-alt me-2"></i>Compartir
                                </h6>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/' . $page['slug']); ?>" 
                                       target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fab fa-facebook-f me-1"></i>Facebook
                                    </a>
                                    
                                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . '/' . $page['slug']); ?>&text=<?php echo urlencode($page['title']); ?>" 
                                       target="_blank" class="btn btn-info btn-sm">
                                        <i class="fab fa-twitter me-1"></i>Twitter
                                    </a>
                                    
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(SITE_URL . '/' . $page['slug']); ?>" 
                                       target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fab fa-linkedin-in me-1"></i>LinkedIn
                                    </a>
                                    
                                    <a href="https://wa.me/?text=<?php echo urlencode($page['title'] . ' - ' . SITE_URL . '/' . $page['slug']); ?>" 
                                       target="_blank" class="btn btn-success btn-sm">
                                        <i class="fab fa-whatsapp me-1"></i>WhatsApp
                                    </a>
                                    
                                    <a href="mailto:?subject=<?php echo urlencode($page['title']); ?>&body=<?php echo urlencode('Te comparto esta página: ' . SITE_URL . '/' . $page['slug']); ?>" 
                                       class="btn btn-secondary btn-sm">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Navegación de Páginas -->
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary">
                                <i class="fas fa-box me-2"></i>Ver Productos
                            </a>
                            <a href="<?php echo SITE_URL; ?>/contacto" class="btn btn-outline-success">
                                <i class="fas fa-envelope me-2"></i>Contactanos
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        // Smooth scroll para los enlaces de la tabla de contenidos
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
        
        // Resaltar la sección actual en la tabla de contenidos
        window.addEventListener('scroll', function() {
            const headings = document.querySelectorAll('[id^="heading-"]');
            const tocLinks = document.querySelectorAll('a[href^="#heading-"]');
            
            let current = '';
            headings.forEach(heading => {
                const rect = heading.getBoundingClientRect();
                if (rect.top <= 100) {
                    current = heading.id;
                }
            });
            
            tocLinks.forEach(link => {
                link.classList.remove('text-primary', 'fw-bold');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('text-primary', 'fw-bold');
                }
            });
        });
    </script>
</body>
</html>