<?php
// admin/pages/reviews/settings.php
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../config/functions.php';
require_once '../../../config/settings.php';

// Verificar autenticación admin
if (!isAdmin()) {
    redirect(ADMIN_URL . '/login.php');
}

$pageTitle = 'Configuración de Reseñas';
$currentPage = 'reviews';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Actualizar configuraciones
        $settings = [
            'reviews_enabled' => $_POST['reviews_enabled'] ?? '1',
            'reviews_require_approval' => $_POST['reviews_require_approval'] ?? '1',
            'reviews_min_length' => intval($_POST['reviews_min_length'] ?? 20),
            'reviews_max_length' => intval($_POST['reviews_max_length'] ?? 1000),
            'reviews_allow_anonymous' => $_POST['reviews_allow_anonymous'] ?? '0',
            'reviews_show_verified_badge' => $_POST['reviews_show_verified_badge'] ?? '1',
            'reviews_allow_votes' => $_POST['reviews_allow_votes'] ?? '1',
            'reviews_featured_count' => intval($_POST['reviews_featured_count'] ?? 3),
            'reviews_per_page' => intval($_POST['reviews_per_page'] ?? 10),
            'reviews_notification_email' => $_POST['reviews_notification_email'] ?? '1'
        ];
        
        foreach ($settings as $key => $value) {
            Settings::set($key, $value);
        }
        
        // Actualizar plantillas de email si se proporcionaron
        if (isset($_POST['review_approved_subject'])) {
            Settings::set('review_approved_subject', $_POST['review_approved_subject']);
        }
        if (isset($_POST['review_approved_template'])) {
            Settings::set('review_approved_template', $_POST['review_approved_template']);
        }
        if (isset($_POST['review_rejected_subject'])) {
            Settings::set('review_rejected_subject', $_POST['review_rejected_subject']);
        }
        if (isset($_POST['review_rejected_template'])) {
            Settings::set('review_rejected_template', $_POST['review_rejected_template']);
        }
        
        setFlashMessage('success', 'Configuración actualizada correctamente');
        redirect($_SERVER['PHP_SELF']);
        
    } catch (Exception $e) {
        $error = 'Error al guardar la configuración: ' . $e->getMessage();
        logError($error);
    }
}

// Obtener configuraciones actuales
$reviewsEnabled = Settings::get('reviews_enabled', '1');
$requireApproval = Settings::get('reviews_require_approval', '1');
$minLength = Settings::get('reviews_min_length', '20');
$maxLength = Settings::get('reviews_max_length', '1000');
$allowAnonymous = Settings::get('reviews_allow_anonymous', '0');
$showVerifiedBadge = Settings::get('reviews_show_verified_badge', '1');
$allowVotes = Settings::get('reviews_allow_votes', '1');
$featuredCount = Settings::get('reviews_featured_count', '3');
$perPage = Settings::get('reviews_per_page', '10');
$notificationEmail = Settings::get('reviews_notification_email', '1');

// Plantillas de email
$approvedSubject = Settings::get('review_approved_subject', 'Tu reseña ha sido aprobada');
$approvedTemplate = Settings::get('review_approved_template', 'Hola {USER_NAME},\n\nTu reseña para {PRODUCT_NAME} ha sido aprobada y ya es visible en nuestro sitio.\n\nGracias por compartir tu experiencia.\n\nSaludos,\nEl equipo de {SITE_NAME}');
$rejectedSubject = Settings::get('review_rejected_subject', 'Tu reseña no pudo ser aprobada');
$rejectedTemplate = Settings::get('review_rejected_template', 'Hola {USER_NAME},\n\nLamentablemente tu reseña para {PRODUCT_NAME} no cumple con nuestras políticas.\n\nRazón: {REASON}\n\nPuedes escribir una nueva reseña siguiendo nuestras guías.\n\nSaludos,\nEl equipo de {SITE_NAME}');

$success = getFlashMessage('success');
$error = getFlashMessage('error');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $pageTitle; ?> | <?php echo getSetting('site_name', 'MiSistema'); ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/dist/css/adminlte.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <?php include '../../includes/navbar.php'; ?>

        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Configuración de Reseñas</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="<?php echo ADMIN_URL; ?>/pages/reviews/">Reseñas</a></li>
                                <li class="breadcrumb-item active">Configuración</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <!-- Mensajes -->
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-check"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                            <i class="icon fas fa-ban"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="settingsForm">
                        <div class="row">
                            <!-- Configuración General -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-cog"></i> Configuración General
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label>Sistema de Reseñas</label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="reviews_enabled" 
                                                       name="reviews_enabled" value="1" <?php echo $reviewsEnabled == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="reviews_enabled">
                                                    Activar sistema de reseñas
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">
                                                Desactivar ocultará todas las reseñas del sitio
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label>Moderación</label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="reviews_require_approval" 
                                                       name="reviews_require_approval" value="1" <?php echo $requireApproval == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="reviews_require_approval">
                                                    Requerir aprobación antes de publicar
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">
                                                Las reseñas necesitarán aprobación manual del administrador
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label>Notificaciones</label>
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="reviews_notification_email" 
                                                       name="reviews_notification_email" value="1" <?php echo $notificationEmail == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="reviews_notification_email">
                                                    Notificar al admin sobre nuevas reseñas
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label>Opciones de visualización</label>
                                            
                                            <div class="custom-control custom-switch mb-2">
                                                <input type="checkbox" class="custom-control-input" id="reviews_show_verified_badge" 
                                                       name="reviews_show_verified_badge" value="1" <?php echo $showVerifiedBadge == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="reviews_show_verified_badge">
                                                    Mostrar badge de "Compra Verificada"
                                                </label>
                                            </div>

                                            <div class="custom-control custom-switch mb-2">
                                                <input type="checkbox" class="custom-control-input" id="reviews_allow_votes" 
                                                       name="reviews_allow_votes" value="1" <?php echo $allowVotes == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="reviews_allow_votes">
                                                    Permitir votos de utilidad en reseñas
                                                </label>
                                            </div>

                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="reviews_allow_anonymous" 
                                                       name="reviews_allow_anonymous" value="1" <?php echo $allowAnonymous == '1' ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="reviews_allow_anonymous">
                                                    Permitir nombres anónimos
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Límites y Restricciones -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">
                                            <i class="fas fa-sliders-h"></i> Límites y Restricciones
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="reviews_min_length">Longitud mínima del comentario</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="reviews_min_length" 
                                                       name="reviews_min_length" value="<?php echo $minLength; ?>" 
                                                       min="10" max="500" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">caracteres</span>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                Mínimo de caracteres requeridos para un comentario
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label for="reviews_max_length">Longitud máxima del comentario</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="reviews_max_length" 
                                                       name="reviews_max_length" value="<?php echo $maxLength; ?>" 
                                                       min="100" max="5000" required>
                                                <div class="input-group-append">
                                                    <span class="input-group-text">caracteres</span>
                                                </div>
                                            </div>
                                            <small class="form-text text-muted">
                                                Máximo de caracteres permitidos en un comentario
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label for="reviews_featured_count">Reseñas destacadas a mostrar</label>
                                            <input type="number" class="form-control" id="reviews_featured_count" 
                                                   name="reviews_featured_count" value="<?php echo $featuredCount; ?>" 
                                                   min="0" max="10" required>
                                            <small class="form-text text-muted">
                                                Número de reseñas destacadas que se mostrarán primero
                                            </small>
                                        </div>

                                        <div class="form-group">
                                            <label for="reviews_per_page">Reseñas por página</label>
                                            <input type="number" class="form-control" id="reviews_per_page" 
                                                   name="reviews_per_page" value="<?php echo $perPage; ?>" 
                                                   min="5" max="50" required>
                                            <small class="form-text text-muted">
                                                Cantidad de reseñas a mostrar por página
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Plantillas de Email -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-envelope"></i> Plantillas de Email
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Email de Aprobación</h5>
                                        <div class="form-group">
                                            <label for="review_approved_subject">Asunto</label>
                                            <input type="text" class="form-control" id="review_approved_subject" 
                                                   name="review_approved_subject" value="<?php echo htmlspecialchars($approvedSubject); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="review_approved_template">Plantilla</label>
                                            <textarea class="form-control" id="review_approved_template" 
                                                      name="review_approved_template" rows="8"><?php echo htmlspecialchars($approvedTemplate); ?></textarea>
                                            <small class="form-text text-muted">
                                                Variables disponibles: {USER_NAME}, {PRODUCT_NAME}, {SITE_NAME}
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>Email de Rechazo</h5>
                                        <div class="form-group">
                                            <label for="review_rejected_subject">Asunto</label>
                                            <input type="text" class="form-control" id="review_rejected_subject" 
                                                   name="review_rejected_subject" value="<?php echo htmlspecialchars($rejectedSubject); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="review_rejected_template">Plantilla</label>
                                            <textarea class="form-control" id="review_rejected_template" 
                                                      name="review_rejected_template" rows="8"><?php echo htmlspecialchars($rejectedTemplate); ?></textarea>
                                            <small class="form-text text-muted">
                                                Variables disponibles: {USER_NAME}, {PRODUCT_NAME}, {SITE_NAME}, {REASON}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Guardar -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Configuración
                                </button>
                                <a href="<?php echo ADMIN_URL; ?>/pages/reviews/" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- Footer -->
        <?php include '../../includes/footer.php'; ?>
    </div>

    <!-- Scripts -->
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/dist/js/adminlte.min.js"></script>
    <script src="<?php echo ADMINLTE_URL; ?>/plugins/toastr/toastr.min.js"></script>

    <script>
    // Validación del formulario
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const minLength = parseInt(document.getElementById('reviews_min_length').value);
        const maxLength = parseInt(document.getElementById('reviews_max_length').value);
        
        if (minLength >= maxLength) {
            e.preventDefault();
            toastr.error('La longitud mínima debe ser menor que la máxima');
            return false;
        }
        
        if (minLength < 10) {
            e.preventDefault();
            toastr.error('La longitud mínima no puede ser menor a 10 caracteres');
            return false;
        }
        
        if (maxLength > 5000) {
            e.preventDefault();
            toastr.error('La longitud máxima no puede ser mayor a 5000 caracteres');
            return false;
        }
    });

    // Auto-ocultar alertas
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
    </script>
</body>
</html>