<?php
// pages/forgot-password.php - Solicitar recuperación de contraseña
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Redirigir si ya está logueado
if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}

$errors = [];
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    
    // Validaciones
    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Ingresa un email válido';
    }
    
    if (empty($errors)) {
        try {
            $db = Database::getInstance()->getConnection();
            
            // Verificar que el usuario existe y está activo
            $stmt = $db->prepare("
                SELECT id, first_name, email, is_verified, is_active 
                FROM users 
                WHERE email = ? AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Por seguridad, no revelar si el email existe o no
                $success = 'Si el email existe en nuestro sistema, recibirás las instrucciones para recuperar tu contraseña.';
            } elseif (!$user['is_verified']) {
                $errors[] = 'Tu cuenta no está verificada. Revisa tu email para verificar tu cuenta primero.';
            } else {
                // Generar token de recuperación
                $resetToken = generateResetToken();
                $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $stmt = $db->prepare("
                    UPDATE users 
                    SET reset_token = ?, reset_token_expires = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$resetToken, $resetExpires, $user['id']])) {
                    // Enviar email de recuperación
                    $emailSent = EmailSystem::sendPasswordResetEmail($email, $user['first_name'], $resetToken);
                    
                    if ($emailSent) {
                        $success = 'Te hemos enviado las instrucciones para recuperar tu contraseña. Revisa tu email.';
                        logError("Solicitud de recuperación de contraseña para: $email", 'password_resets.log');
                    } else {
                        $errors[] = 'Error al enviar el email. Inténtalo más tarde.';
                        logError("Error enviando email de recuperación para: $email", 'password_reset_errors.log');
                    }
                } else {
                    $errors[] = 'Error del sistema. Inténtalo más tarde.';
                }
            }
            
        } catch (Exception $e) {
            logError("Error en forgot password: " . $e->getMessage());
            $errors[] = 'Error del sistema. Inténtalo más tarde.';
        }
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Recupera el acceso a tu cuenta">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="hero-cards-section min-vh-100 d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8">
                    <div class="contact-card">
                        <!-- Header -->
                        <div class="contact-card-header">
                            <div class="contact-icon mx-auto mb-3">
                                <i class="fas fa-key"></i>
                            </div>
                            <h2 class="mb-2">Recuperar Contraseña</h2>
                            <p class="mb-0 opacity-75">Te ayudaremos a recuperar el acceso a tu cuenta</p>
                        </div>
                        
                        <!-- Body -->
                        <div class="contact-card-body">
                            <!-- Mostrar mensajes -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                                
                                <div class="text-center">
                                    <p class="text-muted mb-3">¿No recibiste el email?</p>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
                                            <i class="fas fa-redo me-2"></i>Intentar Nuevamente
                                        </button>
                                        <a href="/pages/login.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left me-2"></i>Volver al Login
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Información del proceso -->
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-1"></i>¿Cómo funciona?</h6>
                                    <ul class="mb-0">
                                        <li>Ingresa tu email registrado</li>
                                        <li>Te enviaremos un enlace seguro</li>
                                        <li>Haz clic en el enlace y crea una nueva contraseña</li>
                                        <li>El enlace expira en 1 hora por seguridad</li>
                                    </ul>
                                </div>
                                
                                <!-- Formulario -->
                                <form method="POST" id="forgotForm" novalidate>
                                    <!-- Email -->
                                    <div class="form-floating mb-4">
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                               placeholder="Email" required autofocus>
                                        <label for="email">
                                            <i class="fas fa-envelope me-2"></i>Email de tu cuenta
                                        </label>
                                    </div>
                                    
                                    <!-- Botón -->
                                    <div class="d-grid gap-2 mb-3">
                                        <button type="submit" class="btn-send">
                                            <i class="fas fa-paper-plane me-2"></i>Enviar Instrucciones
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Footer Links -->
                            <div class="text-center mt-4 pt-4 border-top">
                                <p class="mb-2">
                                    ¿Recordaste tu contraseña? <a href="/pages/login.php" class="text-decoration-none">Iniciar Sesión</a>
                                </p>
                                <div>
                                    <a href="<?php echo SITE_URL; ?>" class="text-muted text-decoration-none">
                                        <i class="fas fa-home me-1"></i>Volver al inicio
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            const emailInput = document.getElementById('email');
            
            if (form && emailInput) {
                // Validación en tiempo real
                emailInput.addEventListener('blur', validateInput);
                emailInput.addEventListener('input', validateInput);
                
                function validateInput() {
                    if (emailInput.validity.valid) {
                        emailInput.classList.remove('is-invalid');
                        emailInput.classList.add('is-valid');
                    } else {
                        emailInput.classList.remove('is-valid');
                        emailInput.classList.add('is-invalid');
                    }
                }
                
                // Envío del formulario
                form.addEventListener('submit', function(e) {
                    const isValid = form.checkValidity();
                    
                    if (!isValid) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                });
                
                // Detectar Enter
                emailInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>