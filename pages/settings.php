<?php
// pages/settings.php - Página de Configuración
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

// Obtener configuraciones del usuario (crear tabla si no existe)
try {
    $db = Database::getInstance()->getConnection();
    
    // Crear tabla de configuraciones de usuario si no existe
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_setting (user_id, setting_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Función para obtener configuración del usuario
    function getUserSetting($userId, $key, $default = '') {
        global $db;
        $stmt = $db->prepare("SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?");
        $stmt->execute([$userId, $key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    }
    
    // Función para guardar configuración del usuario
    function setUserSetting($userId, $key, $value) {
        global $db;
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        return $stmt->execute([$userId, $key, $value]);
    }
    
} catch (Exception $e) {
    logError("Error configurando tabla user_settings: " . $e->getMessage());
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_settings'])) {
            // Guardar configuraciones
            $settings = [
                'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
                'newsletter_subscribe' => isset($_POST['newsletter_subscribe']) ? '1' : '0',
                'order_notifications' => isset($_POST['order_notifications']) ? '1' : '0',
                'promo_notifications' => isset($_POST['promo_notifications']) ? '1' : '0',
                'language' => sanitize($_POST['language'] ?? 'es'),
                'timezone' => sanitize($_POST['timezone'] ?? 'America/Lima'),
                'currency' => sanitize($_POST['currency'] ?? 'USD'),
                'items_per_page' => intval($_POST['items_per_page'] ?? 12),
                'default_view' => sanitize($_POST['default_view'] ?? 'grid'),
                'auto_download' => isset($_POST['auto_download']) ? '1' : '0',
                'download_notifications' => isset($_POST['download_notifications']) ? '1' : '0',
                'theme' => sanitize($_POST['theme'] ?? 'light')
            ];
            
            $saved = 0;
            foreach ($settings as $key => $value) {
                if (setUserSetting($user['id'], $key, $value)) {
                    $saved++;
                }
            }
            
            if ($saved > 0) {
                setFlashMessage('success', 'Configuraciones guardadas exitosamente');
                redirect('/configuracion');
            } else {
                $errors[] = 'No se pudieron guardar las configuraciones';
            }
            
        } elseif (isset($_POST['delete_account'])) {
            // Confirmar eliminación de cuenta
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $confirmText = sanitize($_POST['confirm_text'] ?? '');
            
            if (empty($confirmPassword)) {
                $errors[] = 'Ingresa tu contraseña para confirmar';
            } elseif (!verifyPassword($confirmPassword, $user['password'])) {
                $errors[] = 'Contraseña incorrecta';
            } elseif (strtolower($confirmText) !== 'eliminar mi cuenta') {
                $errors[] = 'Debes escribir exactamente "eliminar mi cuenta"';
            } else {
                // Proceder con eliminación (marcar como inactivo en lugar de eliminar)
                $stmt = $db->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$user['id']])) {
                    // Log de eliminación
                    logError("Usuario eliminó su cuenta: {$user['email']} (ID: {$user['id']})");
                    
                    // Cerrar sesión
                    logoutUser();
                    
                    setFlashMessage('success', 'Tu cuenta ha sido desactivada exitosamente');
                    redirect('/');
                } else {
                    $errors[] = 'Error al eliminar la cuenta';
                }
            }
        }
        
    } catch (Exception $e) {
        logError("Error en configuraciones: " . $e->getMessage());
        $errors[] = 'Error del sistema. Inténtalo más tarde';
    }
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Configura tus preferencias y opciones de cuenta">
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
                    <li class="breadcrumb-item active text-white">Configuración</li>
                </ol>
            </nav>
            
            <div class="dashboard-welcome">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="dashboard-avatar me-3">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div>
                                <h1 class="h3 mb-2 text-white">Configuración</h1>
                                <p class="mb-0 text-white-50">
                                    Personaliza tu experiencia y gestiona tus preferencias
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="<?php echo SITE_URL; ?>/dashboard" class="btn btn-light">
                            <i class="fas fa-arrow-left me-2"></i>Volver
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
        
        <!-- Tabs de Configuración -->
        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                    <i class="fas fa-bell me-2"></i>Notificaciones
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                    <i class="fas fa-cog me-2"></i>Preferencias
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy" type="button" role="tab">
                    <i class="fas fa-shield-alt me-2"></i>Privacidad
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                    <i class="fas fa-user-cog me-2"></i>Cuenta
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="settingsTabContent">
            <!-- Tab Notificaciones -->
            <div class="tab-pane fade show active" id="notifications" role="tabpanel">
                <form method="POST" id="settingsForm">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <div class="dashboard-section">
                        <div class="section-header-compact">
                            <h5 class="section-title-compact mb-0">
                                <i class="fas fa-envelope me-2"></i>Notificaciones por Email
                            </h5>
                        </div>
                        <div class="section-body-compact">
                            <div class="user-product-item mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="product-title-compact mb-1">Notificaciones Generales</h6>
                                        <p class="product-meta-compact mb-0">Recibir emails sobre actualizaciones de cuenta y sistema</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?php echo getUserSetting($user['id'], 'email_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="user-product-item mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="product-title-compact mb-1">Notificaciones de Órdenes</h6>
                                        <p class="product-meta-compact mb-0">Recibir confirmaciones de compras y cambios de estado</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="order_notifications" id="order_notifications" <?php echo getUserSetting($user['id'], 'order_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="user-product-item mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="product-title-compact mb-1">Newsletter</h6>
                                        <p class="product-meta-compact mb-0">Recibir noticias, ofertas y productos nuevos</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="newsletter_subscribe" id="newsletter_subscribe" <?php echo getUserSetting($user['id'], 'newsletter_subscribe', '0') === '1' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="user-product-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="product-title-compact mb-1">Promociones</h6>
                                        <p class="product-meta-compact mb-0">Recibir ofertas especiales y descuentos</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="promo_notifications" id="promo_notifications" <?php echo getUserSetting($user['id'], 'promo_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Tab Preferencias -->
            <div class="tab-pane fade" id="preferences" role="tabpanel">
                <div class="dashboard-section">
                    <div class="section-header-compact">
                        <h5 class="section-title-compact mb-0">
                            <i class="fas fa-palette me-2"></i>Preferencias de Visualización
                        </h5>
                    </div>
                    <div class="section-body-compact">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Idioma</label>
                                <select class="form-select" name="language" form="settingsForm">
                                    <option value="es" <?php echo getUserSetting($user['id'], 'language', 'es') === 'es' ? 'selected' : ''; ?>>🇪🇸 Español</option>
                                    <option value="en" <?php echo getUserSetting($user['id'], 'language', 'es') === 'en' ? 'selected' : ''; ?>>🇺🇸 English</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Zona Horaria</label>
                                <select class="form-select" name="timezone" form="settingsForm">
                                    <option value="America/Lima" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'America/Lima' ? 'selected' : ''; ?>>🇵🇪 Lima (UTC-5)</option>
                                    <option value="America/Mexico_City" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'America/Mexico_City' ? 'selected' : ''; ?>>🇲🇽 Ciudad de México (UTC-6)</option>
                                    <option value="America/Buenos_Aires" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'America/Buenos_Aires' ? 'selected' : ''; ?>>🇦🇷 Buenos Aires (UTC-3)</option>
                                    <option value="Europe/Madrid" <?php echo getUserSetting($user['id'], 'timezone', 'America/Lima') === 'Europe/Madrid' ? 'selected' : ''; ?>>🇪🇸 Madrid (UTC+1)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Moneda</label>
                                <select class="form-select" name="currency" form="settingsForm">
                                    <option value="USD" <?php echo getUserSetting($user['id'], 'currency', 'USD') === 'USD' ? 'selected' : ''; ?>>💵 USD ($)</option>
                                    <option value="EUR" <?php echo getUserSetting($user['id'], 'currency', 'USD') === 'EUR' ? 'selected' : ''; ?>>💶 EUR (€)</option>
                                    <option value="PEN" <?php echo getUserSetting($user['id'], 'currency', 'USD') === 'PEN' ? 'selected' : ''; ?>>💰 PEN (S/)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Productos por página</label>
                                <select class="form-select" name="items_per_page" form="settingsForm">
                                    <option value="8" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '8' ? 'selected' : ''; ?>>8 productos</option>
                                    <option value="12" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '12' ? 'selected' : ''; ?>>12 productos</option>
                                    <option value="24" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '24' ? 'selected' : ''; ?>>24 productos</option>
                                    <option value="48" <?php echo getUserSetting($user['id'], 'items_per_page', '12') === '48' ? 'selected' : ''; ?>>48 productos</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Vista predeterminada</label>
                                <select class="form-select" name="default_view" form="settingsForm">
                                    <option value="grid" <?php echo getUserSetting($user['id'], 'default_view', 'grid') === 'grid' ? 'selected' : ''; ?>>🔲 Cuadrícula</option>
                                    <option value="list" <?php echo getUserSetting($user['id'], 'default_view', 'grid') === 'list' ? 'selected' : ''; ?>>📋 Lista</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tema</label>
                                <select class="form-select" name="theme" form="settingsForm">
                                    <option value="light" <?php echo getUserSetting($user['id'], 'theme', 'light') === 'light' ? 'selected' : ''; ?>>☀️ Claro</option>
                                    <option value="dark" <?php echo getUserSetting($user['id'], 'theme', 'light') === 'dark' ? 'selected' : ''; ?>>🌙 Oscuro</option>
                                    <option value="auto" <?php echo getUserSetting($user['id'], 'theme', 'light') === 'auto' ? 'selected' : ''; ?>>🔄 Automático</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-section">
                    <div class="section-header-compact">
                        <h5 class="section-title-compact mb-0">
                            <i class="fas fa-download me-2"></i>Preferencias de Descarga
                        </h5>
                    </div>
                    <div class="section-body-compact">
                        <div class="user-product-item mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="product-title-compact mb-1">Descarga Automática</h6>
                                    <p class="product-meta-compact mb-0">Descargar automáticamente después de la compra</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_download" id="auto_download" form="settingsForm" <?php echo getUserSetting($user['id'], 'auto_download', '0') === '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        
                        <div class="user-product-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="product-title-compact mb-1">Notificaciones de Descarga</h6>
                                    <p class="product-meta-compact mb-0">Notificar cuando estén disponibles nuevas versiones</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="download_notifications" id="download_notifications" form="settingsForm" <?php echo getUserSetting($user['id'], 'download_notifications', '1') === '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Privacidad -->
            <div class="tab-pane fade" id="privacy" role="tabpanel">
                <div class="dashboard-section">
                    <div class="section-header-compact">
                        <h5 class="section-title-compact mb-0">
                            <i class="fas fa-user-shield me-2"></i>Configuraciones de Privacidad
                        </h5>
                    </div>
                    <div class="section-body-compact">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Información:</strong> Respetamos tu privacidad. Tus datos nunca serán compartidos con terceros sin tu consentimiento.
                        </div>
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="crystal-inner h-100">
                                    <div class="crystal-content p-4">
                                        <h6 class="crystal-title-small">📊 Datos que recopilamos:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Información de perfil</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Historial de compras</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Preferencias de configuración</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Logs de actividad</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="crystal-inner h-100">
                                    <div class="crystal-content p-4">
                                        <h6 class="crystal-title-small">🛡️ Tus derechos:</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-eye text-primary me-2"></i>Acceder a tu información</li>
                                            <li class="mb-2"><i class="fas fa-edit text-primary me-2"></i>Corregir datos incorrectos</li>
                                            <li class="mb-2"><i class="fas fa-trash text-primary me-2"></i>Solicitar eliminación</li>
                                            <li class="mb-2"><i class="fas fa-download text-primary me-2"></i>Exportar tus datos</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="<?php echo SITE_URL; ?>/poltica-de-privacidad" class="btn btn-outline-primary me-2">
                                <i class="fas fa-file-alt me-2"></i>Política de Privacidad
                            </a>
                            <button type="button" class="btn btn-outline-secondary" onclick="exportUserData()">
                                <i class="fas fa-download me-2"></i>Exportar Mis Datos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tab Cuenta -->
            <div class="tab-pane fade" id="account" role="tabpanel">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="dashboard-section h-100">
                            <div class="section-header-compact">
                                <h5 class="section-title-compact mb-0">
                                    <i class="fas fa-tools me-2"></i>Acciones de Cuenta
                                </h5>
                            </div>
                            <div class="section-body-compact">
                                <div class="d-grid gap-3">
                                    <a href="<?php echo SITE_URL; ?>/perfil" class="btn btn-outline-primary">
                                        <i class="fas fa-user-edit me-2"></i>Editar Perfil
                                    </a>
                                    <button type="button" class="btn btn-outline-warning" onclick="resetSettings()">
                                        <i class="fas fa-undo me-2"></i>Restablecer Configuraciones
                                    </button>
                                    <a href="<?php echo SITE_URL; ?>/logout" class="btn btn-outline-secondary">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="sidebar-card-compact h-100">
                            <div class="sidebar-header-compact">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Información de Cuenta
                                </h5>
                            </div>
                            <div class="sidebar-body-compact">
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                    <span class="text-muted">ID de Usuario:</span>
                                    <span class="fw-bold">#<?php echo $user['id']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                    <span class="text-muted">Registro:</span>
                                    <span class="fw-bold"><?php echo formatDate($user['created_at']); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                                    <span class="text-muted">Último acceso:</span>
                                    <span class="fw-bold"><?php echo $user['last_login'] ? timeAgo($user['last_login']) : 'Primer acceso'; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted">Estado:</span>
                                    <span>
                                        <?php if ($user['is_verified']): ?>
                                            <span class="badge bg-success">Verificada</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Sin verificar</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Zona de Peligro -->
                <div class="alert alert-danger mt-4">
                    <div class="text-center">
                        <h5 class="alert-heading">
                            <i class="fas fa-exclamation-triangle me-2"></i>Zona de Peligro
                        </h5>
                        <hr>
                        <h6 class="text-danger">Eliminar Cuenta</h6>
                        <p class="mb-3">
                            Esta acción desactivará permanentemente tu cuenta. No podrás acceder a tus compras ni descargas.
                        </p>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash me-2"></i>Eliminar Mi Cuenta
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón Guardar -->
        <div class="text-center mt-5">
            <button type="submit" form="settingsForm" class="btn btn-primary btn-lg px-5">
                <i class="fas fa-save me-2"></i>Guardar Configuraciones
            </button>
        </div>
    </div>
    
    <!-- Modal Eliminar Cuenta -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación de Cuenta
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="delete_account" value="1">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>¡Atención!</strong> Esta acción no se puede deshacer. Tu cuenta será desactivada permanentemente.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirma tu contraseña:</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Escribe "eliminar mi cuenta" para confirmar:</label>
                            <input type="text" class="form-control" name="confirm_text" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Eliminar Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <script>
        function exportUserData() {
            alert('Función de exportación próximamente disponible');
            // TODO: Implementar exportación de datos
        }
        
        function resetSettings() {
            if (confirm('¿Estás seguro de que quieres restablecer todas las configuraciones a sus valores predeterminados?')) {
                // TODO: Implementar reset de configuraciones
                alert('Configuraciones restablecidas');
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Guardar configuraciones automáticamente al cambiar
        document.querySelectorAll('input[type="checkbox"], select').forEach(element => {
            element.addEventListener('change', function() {
                // Opcional: guardar automáticamente
                // document.getElementById('settingsForm').submit();
            });
        });
    </script>
</body>
</html>