<?php
// pages/login.php - Sistema de login de usuarios
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

// Procesar mensajes de flash
$success = getFlashMessage('success') ?: $success;
$error = getFlashMessage('error');
if ($error) {
    $errors[] = $error;
}

// Mensajes especiales por parámetros GET
if (isset($_GET['verified']) && $_GET['verified'] == '1') {
    $success = '¡Cuenta verificada exitosamente! Ya puedes iniciar sesión.';
}

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success = 'Registro completado. Inicia sesión con tus credenciales.';
}

if (isset($_GET['reset']) && $_GET['reset'] == '1') {
    $success = 'Contraseña actualizada exitosamente. Inicia sesión con tu nueva contraseña.';
}

if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $errors[] = 'Tu sesión ha expirado. Inicia sesión nuevamente.';
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']);
    
    // Validaciones básicas
    if (empty($email)) {
        $errors[] = 'El email es obligatorio';
    } elseif (!isValidEmail($email)) {
        $errors[] = 'Ingresa un email válido';
    }
    
    if (empty($password)) {
        $errors[] = 'La contraseña es obligatoria';
    }
    
    // Intentar login si no hay errores
    if (empty($errors)) {
        $loginResult = loginUser($email, $password);
        
        if ($loginResult['success']) {
            // Login exitoso
            $user = $loginResult['user'];
            
            // Configurar "recordarme" si está seleccionado
            if ($remember) {
                // Crear cookie que dure 30 días
                $cookieValue = base64_encode($user['id'] . '|' . $user['email'] . '|' . time());
                setcookie('remember_user', $cookieValue, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            }
            
            // Redirigir según el parámetro redirect o al dashboard
            $redirectTo = $_GET['redirect'] ?? '/pages/dashboard.php';
            $redirectTo = cleanUrl($redirectTo);
            
            // Validar que la redirección sea segura (dentro del sitio)
            if (strpos($redirectTo, 'http') === 0 && strpos($redirectTo, SITE_URL) !== 0) {
                $redirectTo = '/pages/dashboard.php';
            }
            
            setFlashMessage('success', "¡Bienvenido de vuelta, {$user['first_name']}!");
            redirect($redirectTo);
        } else {
            // Error en login
            $errors[] = $loginResult['message'];
            
            // Log de intento de login fallido
            logError("Login fallido para email: $email - Motivo: {$loginResult['message']}", 'login_attempts.log');
        }
    }
}

// Verificar cookie "recordarme" si no hay sesión activa
if (!isLoggedIn() && isset($_COOKIE['remember_user'])) {
    try {
        $cookieData = base64_decode($_COOKIE['remember_user']);
        $parts = explode('|', $cookieData);
        
        if (count($parts) === 3) {
            $userId = $parts[0];
            $cookieEmail = $parts[1];
            $cookieTime = $parts[2];
            
            // Verificar que la cookie no sea muy antigua (máximo 30 días)
            if (time() - $cookieTime < (30 * 24 * 60 * 60)) {
                // Verificar que el usuario sigue existiendo y activo
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("
                    SELECT id, email, first_name, last_name, is_verified, is_active 
                    FROM users 
                    WHERE id = ? AND email = ? AND is_active = 1 AND is_verified = 1
                ");
                $stmt->execute([$userId, $cookieEmail]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Auto-login
                    $_SESSION[SESSION_NAME] = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'login_time' => time(),
                        'auto_login' => true
                    ];
                    
                    // Actualizar último login
                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Renovar cookie
                    $newCookieValue = base64_encode($user['id'] . '|' . $user['email'] . '|' . time());
                    setcookie('remember_user', $newCookieValue, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                    
                    redirect('/pages/dashboard.php');
                }
            }
        }
        
        // Si llegamos aquí, la cookie no es válida
        setcookie('remember_user', '', time() - 3600, '/');
        
    } catch (Exception $e) {
        // Error procesando cookie, eliminarla
        setcookie('remember_user', '', time() - 3600, '/');
        logError("Error procesando cookie remember_user: " . $e->getMessage());
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Inicia sesión en tu cuenta para acceder a tus productos">
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
                                <i class="fas fa-sign-in-alt"></i>
                            </div>
                            <h2 class="mb-2">Iniciar Sesión</h2>
                            <p class="mb-0 opacity-75">Accede a tu cuenta y explora nuestros productos</p>
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
                            <?php endif; ?>
                            
                            <!-- Credenciales de demo (solo en desarrollo) -->
                            <?php if (strpos(SITE_URL, 'localhost') !== false): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-1"></i>Credenciales de prueba:</h6>
                                    <div class="mt-2">
                                        <strong>Email:</strong> demo@misistema.com<br>
                                        <strong>Contraseña:</strong> 123456
                                    </div>
                                    <small class="text-muted d-block mt-2">Solo visible en desarrollo</small>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Formulario de Login -->
                            <form method="POST" id="loginForm" novalidate>
                                <!-- Mantener parámetro redirect si existe -->
                                <?php if (isset($_GET['redirect'])): ?>
                                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                                <?php endif; ?>
                                
                                <!-- Email -->
                                <div class="form-floating mb-3">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                           placeholder="Email" required autofocus>
                                    <label for="email">
                                        <i class="fas fa-envelope me-2"></i>Email
                                    </label>
                                </div>
                                
                                <!-- Contraseña -->
                                <div class="form-floating mb-3 position-relative">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Contraseña" required>
                                    <label for="password">
                                        <i class="fas fa-lock me-2"></i>Contraseña
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary position-absolute top-50 end-0 translate-middle-y me-2" 
                                            style="z-index: 10; border: none; background: none;" onclick="togglePassword()">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                                
                                <!-- Opciones -->
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                        <label class="form-check-label" for="remember_me">
                                            Recordarme
                                        </label>
                                    </div>
                                    <a href="<?php echo SITE_URL; ?>/pages/forgot-password.php" class="text-decoration-none">
                                        ¿Olvidaste tu contraseña?
                                    </a>
                                </div>
                                
                                <!-- Botón de Login -->
                                <button type="submit" class="btn-send w-100 mb-3">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </button>
                            </form>
                            
                            <!-- Footer Links -->
                            <div class="text-center pt-3 border-top">
                                <p class="mb-2">
                                    ¿No tienes cuenta? <a href="<?php echo SITE_URL; ?>/pages/register.php" class="text-decoration-none">Crear Cuenta</a>
                                </p>
                                <div>
                                    <a href="<?php echo SITE_URL; ?>" class="text-muted text-decoration-none">
                                        <i class="fas fa-arrow-left me-1"></i>Volver al inicio
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
            const form = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            // Validación en tiempo real
            const inputs = [emailInput, passwordInput];
            inputs.forEach(input => {
                input.addEventListener('blur', validateInput);
                input.addEventListener('input', validateInput);
            });
            
            function validateInput() {
                if (this.validity.valid) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
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
            
            // Auto-completar credenciales demo en desarrollo
            <?php if (strpos(SITE_URL, 'localhost') !== false): ?>
                document.querySelector('.alert-info').addEventListener('click', function() {
                    emailInput.value = 'demo@misistema.com';
                    passwordInput.value = '123456';
                    emailInput.classList.add('is-valid');
                    passwordInput.classList.add('is-valid');
                });
            <?php endif; ?>
            
            // Detectar Enter en campos
            inputs.forEach(input => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        form.submit();
                    }
                });
            });
        });
        
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-focus en email al cargar
        document.getElementById('email').focus();
    </script>
</body>
</html>