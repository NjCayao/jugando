<?php
// pages/donation-failed.php - Página de error en donación
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

$transactionId = sanitize($_GET['transaction_id'] ?? '');
$error = sanitize($_GET['error'] ?? 'Error desconocido en el procesamiento');
$donation = null;

// Obtener datos de la donación si existe
if (!empty($transactionId)) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM donations WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        $donation = $stmt->fetch();
    } catch (Exception $e) {
        logError("Error obteniendo donación en failed: " . $e->getMessage());
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
$supportEmail = Settings::get('site_email', 'soporte@misistema.com');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error en Donación - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="robots" content="noindex, nofollow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .error-hero {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 5rem 0;
            text-align: center;
        }
        
        .error-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .error-details {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: -3rem;
            position: relative;
            z-index: 2;
        }
        
        .error-message {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 2rem 0;
            color: #742a2a;
        }
        
        .retry-section {
            background: #f8f9fa;
            padding: 3rem 0;
            margin-top: 3rem;
        }
        
        .retry-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .retry-card:hover {
            transform: translateY(-5px);
        }
        
        .contact-info {
            background: #e8f4fd;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }
        
        .btn-retry {
            background: #27ae60;
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-retry:hover {
            background: #2ecc71;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Error Hero -->
    <div class="error-hero">
        <div class="container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="display-4 mb-3">¡Ups! Algo salió mal</h1>
            <p class="lead">
                No pudimos procesar tu donación. No te preocupes, no se ha realizado ningún cargo.
            </p>
        </div>
    </div>
    
    <!-- Error Details -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="error-details">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">
                            <i class="fas fa-info-circle text-danger me-2"></i>
                            Detalles del Error
                        </h3>
                        
                        <div class="error-message">
                            <h5><i class="fas fa-exclamation-circle me-2"></i>¿Qué pasó?</h5>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                        
                        <?php if ($donation): ?>
                            <div class="row">
                                <div class="col-sm-6">
                                    <strong>ID de Transacción:</strong><br>
                                    <code><?php echo htmlspecialchars($donation['transaction_id']); ?></code>
                                </div>
                                <div class="col-sm-6">
                                    <strong>Monto Intentado:</strong><br>
                                    $<?php echo number_format($donation['amount'], 2); ?> USD
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="contact-info">
                            <h5><i class="fas fa-headset text-primary me-2"></i>¿Necesitas Ayuda?</h5>
                            <p class="mb-2">
                                Si el problema persiste, no dudes en contactarnos:
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-envelope me-2"></i>
                                <a href="mailto:<?php echo $supportEmail; ?>"><?php echo $supportEmail; ?></a>
                            </p>
                            <?php if ($donation): ?>
                                <small class="text-muted">
                                    Menciona el ID de transacción: <?php echo htmlspecialchars($donation['transaction_id']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Retry Section -->
    <div class="retry-section">
        <div class="container">
            <h3 class="text-center mb-5">¿Qué puedes hacer?</h3>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="retry-card">
                        <i class="fas fa-redo fa-3x text-success mb-3"></i>
                        <h5>Intentar de Nuevo</h5>
                        <p>Puedes volver a intentar la donación. A veces es solo un problema temporal.</p>
                        <a href="<?php echo SITE_URL; ?>/donar-cafe<?php echo $donation && $donation['product_id'] ? '?producto=' . $donation['product_id'] : ''; ?>" 
                           class="btn btn-retry">
                            <i class="fas fa-heart me-1"></i> Reintentar Donación
                        </a>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="retry-card">
                        <i class="fas fa-credit-card fa-3x text-info mb-3"></i>
                        <h5>Cambiar Método</h5>
                        <p>Prueba con un método de pago diferente si tienes problemas con el actual.</p>
                        <a href="<?php echo SITE_URL; ?>/donar-cafe<?php echo $donation && $donation['product_id'] ? '?producto=' . $donation['product_id'] : ''; ?>" 
                           class="btn btn-outline-info">
                            <i class="fas fa-exchange-alt me-1"></i> Otro Método
                        </a>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="retry-card">
                        <i class="fas fa-question-circle fa-3x text-warning mb-3"></i>
                        <h5>Obtener Ayuda</h5>
                        <p>Contáctanos si necesitas asistencia personalizada con tu donación.</p>
                        <a href="mailto:<?php echo $supportEmail; ?><?php echo $donation ? '?subject=Error en donación ' . urlencode($donation['transaction_id']) : ''; ?>" 
                           class="btn btn-outline-warning">
                            <i class="fas fa-envelope me-1"></i> Contactar Soporte
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Common Issues -->
    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-lightbulb text-warning me-2"></i>
                            Problemas Comunes y Soluciones
                        </h5>
                        
                        <div class="accordion" id="troubleshootAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#issue1">
                                        Tarjeta rechazada o insuficientes fondos
                                    </button>
                                </h2>
                                <div id="issue1" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Verifica que tengas fondos suficientes</li>
                                            <li>Asegúrate de que la tarjeta esté activa</li>
                                            <li>Contacta a tu banco si persiste el problema</li>
                                            <li>Prueba con una tarjeta diferente</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#issue2">
                                        Error de conexión o timeout
                                    </button>
                                </h2>
                                <div id="issue2" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Verifica tu conexión a internet</li>
                                            <li>Intenta refrescar la página</li>
                                            <li>Desactiva temporalmente bloqueadores de anuncios</li>
                                            <li>Prueba desde otro navegador</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#issue3">
                                        Problemas con PayPal o MercadoPago
                                    </button>
                                </h2>
                                <div id="issue3" class="accordion-collapse collapse" data-bs-parent="#troubleshootAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Verifica que tu cuenta esté verificada</li>
                                            <li>Asegúrate de estar logueado en tu cuenta</li>
                                            <li>Revisa los límites de tu cuenta</li>
                                            <li>Contacta el soporte de la plataforma de pago</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Alternative Support -->
    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="card border-primary">
                    <div class="card-body">
                        <h5 class="card-title text-primary">
                            <i class="fas fa-heart me-2"></i>
                            ¡Tu Apoyo Sigue Siendo Valioso!
                        </h5>
                        <p class="card-text">
                            Aunque la donación no se pudo procesar, valoramos mucho tu intención de apoyarnos. 
                            Mientras tanto, puedes ayudarnos de otras formas:
                        </p>
                        <div class="d-grid gap-2 d-md-block">
                            <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary">
                                <i class="fas fa-download me-1"></i> Descargar Gratis
                            </a>
                            <a href="#" onclick="shareProject()" class="btn btn-outline-success">
                                <i class="fas fa-share me-1"></i> Compartir Proyecto
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
        function shareProject() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($siteName); ?>',
                    text: 'Conoce este increíble proyecto de software gratuito',
                    url: '<?php echo SITE_URL; ?>'
                });
            } else {
                // Fallback para navegadores que no soportan Web Share API
                const url = '<?php echo SITE_URL; ?>';
                const text = 'Conoce este increíble proyecto: ' + url;
                
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        alert('¡Enlace copiado al portapapeles!');
                    });
                } else {
                    prompt('Copia este enlace para compartir:', text);
                }
            }
        }
        
        // Auto-retry logic (opcional)
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const autoRetry = urlParams.get('auto_retry');
            
            if (autoRetry === '1') {
                // Mostrar mensaje de auto-retry
                const alert = document.createElement('div');
                alert.className = 'alert alert-info alert-dismissible fade show';
                alert.innerHTML = `
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Reintento automático:</strong> Estamos intentando procesar tu donación nuevamente.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                const container = document.querySelector('.container');
                container.insertBefore(alert, container.firstChild);
                
                // Redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = '<?php echo SITE_URL; ?>/donar-cafe<?php echo $donation && $donation['product_id'] ? '?producto=' . $donation['product_id'] : ''; ?>';
                }, 3000);
            }
        });
    </script>
</body>
</html>