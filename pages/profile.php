<?php
// pages/profile.php - Página de Mi Perfil
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Verificar que el usuario está logueado
if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

// Obtener datos del usuario
$user = getCurrentUser();
if (!$user) {
    logoutUser();
    redirect('/login');
}

$success = getFlashMessage('success');
$error = getFlashMessage('error');
$errors = [];

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        if (isset($_POST['update_profile'])) {
            // Actualizar perfil
            $firstName = sanitize($_POST['first_name'] ?? '');
            $lastName = sanitize($_POST['last_name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $country = sanitize($_POST['country'] ?? '');
            
            // Validaciones
            if (empty($firstName)) {
                $errors[] = 'El nombre es obligatorio';
            }
            if (empty($lastName)) {
                $errors[] = 'El apellido es obligatorio';
            }
            
            if (empty($errors)) {
                $stmt = $db->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, country = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$firstName, $lastName, $phone, $country, $user['id']])) {
                    // Actualizar sesión
                    $_SESSION[SESSION_NAME]['first_name'] = $firstName;
                    $_SESSION[SESSION_NAME]['last_name'] = $lastName;
                    
                    setFlashMessage('success', 'Perfil actualizado exitosamente');
                    redirect('/perfil');
                } else {
                    $errors[] = 'Error al actualizar el perfil';
                }
            }
            
        } elseif (isset($_POST['change_password'])) {
            // Cambiar contraseña
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validaciones
            if (empty($currentPassword)) {
                $errors[] = 'Ingresa tu contraseña actual';
            }
            if (empty($newPassword)) {
                $errors[] = 'Ingresa la nueva contraseña';
            } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'La nueva contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres';
            }
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Las contraseñas no coinciden';
            }
            
            // Verificar contraseña actual
            if (empty($errors) && !verifyPassword($currentPassword, $user['password'])) {
                $errors[] = 'La contraseña actual es incorrecta';
            }
            
            if (empty($errors)) {
                $hashedPassword = hashPassword($newPassword);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$hashedPassword, $user['id']])) {
                    setFlashMessage('success', 'Contraseña cambiada exitosamente');
                    redirect('/perfil');
                } else {
                    $errors[] = 'Error al cambiar la contraseña';
                }
            }
            
        } elseif (isset($_POST['upload_avatar'])) {
            // Subir avatar (implementar más adelante)
            $errors[] = 'Función de avatar próximamente disponible';
        }
        
    } catch (Exception $e) {
        logError("Error en perfil: " . $e->getMessage());
        $errors[] = 'Error del sistema. Inténtalo más tarde';
    }
}

// Refrescar datos del usuario
$user = getCurrentUser();

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Edita tu perfil y configuraciones de cuenta">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Page Header -->
    <div class="dashboard-header-compact">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard" class="text-white-50">Dashboard</a></li>
                    <li class="breadcrumb-item active text-white">Mi Perfil</li>
                </ol>
            </nav>
            
            <div class="dashboard-welcome">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="dashboard-avatar me-3">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <h1 class="h3 mb-2 text-white">Mi Perfil</h1>
                                <p class="mb-0 text-white-50">
                                    Gestiona tu información personal y configuraciones de cuenta
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Volver al Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="container my-5">
        <!-- Mostrar mensajes -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Columna Principal -->
            <div class="col-lg-8">
                <!-- Información Personal -->
                <div class="dashboard-section">
                    <div class="section-header-compact">
                        <h5 class="section-title-compact mb-0">
                            <i class="fas fa-user me-2"></i>Información Personal
                        </h5>
                    </div>
                    <div class="section-body-compact">
                        <form method="POST" id="profileForm">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                               placeholder="Nombre" required>
                                        <label for="first_name">Nombre</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                               placeholder="Apellido" required>
                                        <label for="last_name">Apellido</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               placeholder="Email" disabled>
                                        <label for="email">Email (no se puede cambiar)</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                               placeholder="Teléfono">
                                        <label for="phone">Teléfono</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-floating">
                                        <select class="form-select" id="country" name="country">
                                            <option value="">Seleccionar país</option>
                                            <option value="PE" <?php echo ($user['country'] ?? '') === 'PE' ? 'selected' : ''; ?>>Perú</option>
                                            <option value="AR" <?php echo ($user['country'] ?? '') === 'AR' ? 'selected' : ''; ?>>Argentina</option>
                                            <option value="CL" <?php echo ($user['country'] ?? '') === 'CL' ? 'selected' : ''; ?>>Chile</option>
                                            <option value="CO" <?php echo ($user['country'] ?? '') === 'CO' ? 'selected' : ''; ?>>Colombia</option>
                                            <option value="EC" <?php echo ($user['country'] ?? '') === 'EC' ? 'selected' : ''; ?>>Ecuador</option>
                                            <option value="MX" <?php echo ($user['country'] ?? '') === 'MX' ? 'selected' : ''; ?>>México</option>
                                            <option value="ES" <?php echo ($user['country'] ?? '') === 'ES' ? 'selected' : ''; ?>>España</option>
                                            <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>Estados Unidos</option>
                                            <option value="OTHER" <?php echo ($user['country'] ?? '') === 'OTHER' ? 'selected' : ''; ?>>Otro</option>
                                        </select>
                                        <label for="country">País</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i>Actualizar Perfil
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cambiar Contraseña -->
                <div class="dashboard-section">
                    <div class="section-header-compact">
                        <h5 class="section-title-compact mb-0">
                            <i class="fas fa-lock me-2"></i>Cambiar Contraseña
                        </h5>
                    </div>
                    <div class="section-body-compact">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="current_password" name="current_password" 
                                               placeholder="Contraseña actual" required>
                                        <label for="current_password">Contraseña Actual</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               placeholder="Nueva contraseña" required>
                                        <label for="new_password">Nueva Contraseña</label>
                                    </div>
                                    <div class="mt-2" style="height: 4px; background: #e9ecef; border-radius: 2px; overflow: hidden;">
                                        <div id="strengthBar" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                                    </div>
                                    <small id="passwordHelp" class="form-text text-muted">
                                        Mínimo <?php echo PASSWORD_MIN_LENGTH; ?> caracteres
                                    </small>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                               placeholder="Confirmar contraseña" required>
                                        <label for="confirm_password">Confirmar Contraseña</label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-warning btn-lg">
                                        <i class="fas fa-key me-2"></i>Cambiar Contraseña
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Avatar -->
                <div class="sidebar-card-compact">
                    <div class="sidebar-header-compact">
                        <h5 class="mb-0">
                            <i class="fas fa-camera me-2"></i>Foto de Perfil
                        </h5>
                    </div>
                    <div class="sidebar-body-compact text-center">
                        <div class="position-relative d-inline-block mb-3">
                            <div class="dashboard-avatar" style="width: 100px; height: 100px; font-size: 2.5rem; margin: 0 auto;">
                                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                            </div>
                            <div class="position-absolute bottom-0 end-0" style="cursor: pointer;" onclick="document.getElementById('avatar-upload').click()">
                                <div class="btn btn-primary btn-sm rounded-circle" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                        </div>
                        <h6><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                        
                        <form method="POST" enctype="multipart/form-data" style="display: none;">
                            <input type="hidden" name="upload_avatar" value="1">
                            <input type="file" id="avatar-upload" name="avatar" accept="image/*" onchange="this.form.submit()">
                        </form>
                    </div>
                </div>
                
                <!-- Información de Cuenta -->
                <div class="sidebar-card-compact">
                    <div class="sidebar-header-compact">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Información de Cuenta
                        </h5>
                    </div>
                    <div class="sidebar-body-compact">
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted">Estado de Cuenta:</span>
                            <span>
                                <?php if ($user['is_verified']): ?>
                                    <span class="badge bg-success">Verificada</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Sin Verificar</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <span class="text-muted">Miembro desde:</span>
                            <span class="fw-bold"><?php echo formatDate($user['created_at'], 'F Y'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted">Último acceso:</span>
                            <span class="fw-bold">
                                <?php if ($user['last_login']): ?>
                                    <?php echo timeAgo($user['last_login']); ?>
                                <?php else: ?>
                                    Primer acceso
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Actividad Reciente -->
                <div class="sidebar-card-compact">
                    <div class="sidebar-header-compact">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Actividad Reciente
                        </h5>
                    </div>
                    <div class="sidebar-body-compact">
                        <div class="user-product-item mb-3">
                            <div class="fw-bold">Perfil actualizado</div>
                            <div class="text-muted small">Hace 2 horas</div>
                        </div>
                        <div class="user-product-item mb-3">
                            <div class="fw-bold">Inicio de sesión</div>
                            <div class="text-muted small">Hoy</div>
                        </div>
                        <div class="user-product-item">
                            <div class="fw-bold">Cuenta creada</div>
                            <div class="text-muted small"><?php echo formatDate($user['created_at']); ?></div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthBar = document.getElementById('strengthBar');
            const passwordHelp = document.getElementById('passwordHelp');
            
            // Validador de fuerza de contraseña
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                // Actualizar barra de fuerza
                strengthBar.style.width = (strength.score * 25) + '%';
                strengthBar.style.background = strength.color;
                
                // Actualizar texto de ayuda
                passwordHelp.textContent = strength.message;
                passwordHelp.className = `form-text text-${strength.textColor}`;
            });
            
            // Validar confirmación de contraseña
            confirmPasswordInput.addEventListener('input', function() {
                const password = newPasswordInput.value;
                const confirm = this.value;
                
                if (confirm && password !== confirm) {
                    this.setCustomValidity('Las contraseñas no coinciden');
                    this.classList.add('is-invalid');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                    if (confirm) this.classList.add('is-valid');
                }
            });
            
            // Auto-dismiss alerts
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
        
        function calculatePasswordStrength(password) {
            let score = 0;
            let message = 'Muy débil';
            let color = '#dc3545';
            let textColor = 'danger';
            
            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) score++;
            if (password.match(/[a-z]/)) score++;
            if (password.match(/[A-Z]/)) score++;
            if (password.match(/[0-9]/)) score++;
            if (password.match(/[^a-zA-Z0-9]/)) score++;
            
            switch (score) {
                case 0:
                case 1:
                    message = 'Muy débil';
                    color = '#dc3545';
                    textColor = 'danger';
                    break;
                case 2:
                    message = 'Débil';
                    color = '#ffc107';
                    textColor = 'warning';
                    break;
                case 3:
                    message = 'Buena';
                    color = '#28a745';
                    textColor = 'success';
                    break;
                case 4:
                case 5:
                    message = 'Muy fuerte';
                    color = '#007bff';
                    textColor = 'primary';
                    break;
            }
            
            return { score, message, color, textColor };
        }
    </script>
</body>
</html>