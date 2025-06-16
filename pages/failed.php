<?php
// pages/failed.php - Página de pago fallido
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/cart.php';

// Verificar modo mantenimiento
if (Settings::get('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

$reason = $_GET['reason'] ?? 'unknown';
$orderNumber = $_GET['order'] ?? '';
$message = $_GET['message'] ?? '';

// Definir mensajes según la razón del fallo
$errorTypes = [
    'cancelled' => [
        'icon' => 'fas fa-times-circle',
        'color' => 'warning',
        'title' => 'Pago Cancelado',
        'description' => 'Has cancelado el proceso de pago. Tu carrito sigue disponible.',
        'canRetry' => true
    ],
    'declined' => [
        'icon' => 'fas fa-credit-card',
        'color' => 'danger',
        'title' => 'Tarjeta Rechazada',
        'description' => 'Tu tarjeta fue rechazada. Verifica los datos o intenta con otra tarjeta.',
        'canRetry' => true
    ],
    'expired' => [
        'icon' => 'fas fa-clock',
        'color' => 'warning',
        'title' => 'Sesión Expirada',
        'description' => 'La sesión de pago ha expirado. Debes reiniciar el proceso.',
        'canRetry' => true
    ],
    'insufficient_funds' => [
        'icon' => 'fas fa-wallet',
        'color' => 'danger',
        'title' => 'Fondos Insuficientes',
        'description' => 'No hay suficientes fondos en tu cuenta o tarjeta.',
        'canRetry' => true
    ],
    'network_error' => [
        'icon' => 'fas fa-wifi',
        'color' => 'danger',
        'title' => 'Error de Conexión',
        'description' => 'Hubo un problema de conexión. Intenta nuevamente.',
        'canRetry' => true
    ],
    'gateway_error' => [
        'icon' => 'fas fa-server',
        'color' => 'danger',
        'title' => 'Error del Procesador',
        'description' => 'El procesador de pagos tiene problemas temporales.',
        'canRetry' => true
    ],
    'fraud_detected' => [
        'icon' => 'fas fa-shield-alt',
        'color' => 'danger',
        'title' => 'Transacción Bloqueada',
        'description' => 'La transacción fue bloqueada por seguridad. Contacta a tu banco.',
        'canRetry' => false
    ],
    'invalid_data' => [
        'icon' => 'fas fa-exclamation-triangle',
        'color' => 'warning',
        'title' => 'Datos Inválidos',
        'description' => 'Los datos proporcionados son incorrectos. Revisa la información.',
        'canRetry' => true
    ],
    'unknown' => [
        'icon' => 'fas fa-question-circle',
        'color' => 'secondary',
        'title' => 'Error Desconocido',
        'description' => 'Ocurrió un error inesperado durante el procesamiento.',
        'canRetry' => true
    ]
];

$currentError = $errorTypes[$reason] ?? $errorTypes['unknown'];

// Obtener información de la orden si está disponible
$order = null;
if ($orderNumber) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as items_count
            FROM orders o 
            WHERE o.order_number = ?
        ");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
    } catch (Exception $e) {
        logError("Error obteniendo orden en failed.php: " . $e->getMessage());
    }
}

// Verificar si hay productos en el carrito
$hasCartItems = !Cart::isEmpty();
$cartTotals = Cart::getTotals();

$siteName = Settings::get('site_name', 'MiSistema');
$pageTitle = 'Error en el Pago';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="robots" content="noindex, nofollow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Failed Page -->
    <div class="hero-cards-section min-vh-75 d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="crystal-inner">
                        <div class="crystal-content p-5 text-center">
                            <!-- Error Icon -->
                            <div class="category-icon mx-auto mb-4 text-<?php echo $currentError['color']; ?>">
                                <i class="<?php echo $currentError['icon']; ?>" style="font-size: 3rem;"></i>
                            </div>
                            
                            <!-- Title & Description -->
                            <h1 class="crystal-title mb-3"><?php echo $currentError['title']; ?></h1>
                            <p class="crystal-description mb-4"><?php echo $currentError['description']; ?></p>
                            
                            <?php if ($message): ?>
                                <div class="alert alert-info">
                                    <strong>Detalles del error:</strong> <?php echo htmlspecialchars($message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Order Info (if available) -->
                            <?php if ($order): ?>
                                <div class="category-card mb-4">
                                    <div class="category-content p-6">
                                        <h5 class="category-title mb-3">
                                            <i class="fas fa-receipt me-2"></i>
                                            Información de la Orden
                                        </h5>
                                        <div class="row text-start">
                                            <div class="col-md-6">
                                                <strong>Orden:</strong> #<?php echo htmlspecialchars($order['order_number']); ?><br>
                                                <strong>Cliente:</strong> <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                                <strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Total:</strong> <?php echo formatPrice($order['total_amount']); ?><br>
                                                <strong>Método:</strong> <?php echo ucfirst($order['payment_method']); ?><br>
                                                <strong>Fecha:</strong> <?php echo formatDateTime($order['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Cart Summary (if items available) -->
                            <?php if ($hasCartItems): ?>
                                <div class="alert alert-warning">
                                    <h5 class="alert-heading">
                                        <i class="fas fa-shopping-cart me-2"></i>
                                        Tu Carrito Sigue Disponible
                                    </h5>
                                    <p class="mb-0">
                                        Tienes <?php echo $cartTotals['items_count']; ?> producto(s) en tu carrito 
                                        por un total de <strong><?php echo formatPrice($cartTotals['total']); ?></strong>
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Troubleshooting Suggestions -->
                            <div class="category-card mb-4">
                                <div class="category-content p-6">
                                    <h5 class="category-title text-info mb-3">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        Sugerencias para Resolver el Problema
                                    </h5>
                                    <div class="text-start">
                                        <ul>
                                            <?php
                                            $suggestions = [
                                                'cancelled' => [
                                                    'Puedes reintentar el pago cuando estés listo',
                                                    'Verifica que tengas todos los datos necesarios'
                                                ],
                                                'declined' => [
                                                    'Verifica que los datos de la tarjeta sean correctos',
                                                    'Contacta a tu banco para verificar el estado de la tarjeta',
                                                    'Intenta con una tarjeta diferente'
                                                ],
                                                'expired' => [
                                                    'Reinicia el proceso de pago',
                                                    'Completa el pago más rápidamente'
                                                ],
                                                'insufficient_funds' => [
                                                    'Verifica el saldo de tu cuenta',
                                                    'Intenta con una tarjeta diferente',
                                                    'Contacta a tu banco'
                                                ],
                                                'network_error' => [
                                                    'Verifica tu conexión a internet',
                                                    'Intenta nuevamente en unos minutos',
                                                    'Prueba desde otro dispositivo'
                                                ],
                                                'gateway_error' => [
                                                    'Intenta nuevamente en unos minutos',
                                                    'Prueba con un método de pago diferente'
                                                ],
                                                'fraud_detected' => [
                                                    'Contacta a tu banco inmediatamente',
                                                    'Verifica si hay bloqueos en tu tarjeta',
                                                    'Intenta desde tu ubicación habitual'
                                                ],
                                                'invalid_data' => [
                                                    'Revisa que todos los campos estén completos',
                                                    'Verifica que los datos sean correctos',
                                                    'Intenta escribir los datos manualmente'
                                                ]
                                            ];
                                            
                                            $currentSuggestions = $suggestions[$reason] ?? $suggestions['unknown'] ?? [
                                                'Intenta nuevamente más tarde',
                                                'Contacta a nuestro equipo de soporte',
                                                'Prueba con un método de pago diferente'
                                            ];
                                            
                                            foreach ($currentSuggestions as $suggestion):
                                            ?>
                                                <li><?php echo $suggestion; ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="mb-4">
                                <?php if ($currentError['canRetry']): ?>
                                    <?php if ($hasCartItems): ?>
                                        <a href="<?php echo SITE_URL; ?>/pages/checkout.php" class="btn btn-corporate me-3">
                                            <i class="fas fa-redo me-2"></i>Reintentar Pago
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo SITE_URL; ?>/pages/cart.php" class="btn btn-warning me-3">
                                        <i class="fas fa-shopping-cart me-2"></i>Ver Carrito
                                    </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Explorar Productos
                                </a>
                            </div>
                            
                            <!-- Alternative Payment Methods -->
                            <div class="category-card mb-4">
                                <div class="category-content p-6">
                                    <h5 class="category-title mb-3">Métodos de Pago Alternativos</h5>
                                    <p class="category-description mb-3">Si sigues teniendo problemas, prueba con:</p>
                                    <div class="row">
                                        <!-- <div class="col-md-4">
                                            <div class="text-center p-3">
                                                <div class="category-icon mx-auto mb-2">
                                                    <i class="fab fa-cc-stripe"></i>
                                                </div>
                                                <small>Tarjetas de Crédito/Débito</small>
                                            </div>
                                        </div> -->
                                        <div class="col-md-6">
                                            <div class="text-center p-3">
                                                <div class="category-icon mx-auto mb-2">
                                                    <i class="fab fa-paypal"></i>
                                                </div>
                                                <small>PayPal</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-center p-3">
                                                <div class="category-icon mx-auto mb-2">
                                                    <i class="fas fa-mobile-alt"></i>
                                                </div>
                                                <small>MercadoPago / Yape</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Support Section -->
                            <div class="border-top pt-4">
                                <h6 class="mb-3">¿Necesitas Ayuda Personalizada?</h6>
                                <p class="text-muted mb-3">
                                    Nuestro equipo de soporte está disponible para ayudarte con cualquier problema de pago.
                                </p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="<?php echo SITE_URL; ?>/contacto" class="btn btn-outline-primary w-100 mb-2">
                                            <i class="fas fa-envelope me-2"></i>Contactar Soporte
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="https://wa.me/<?php echo Settings::get('whatsapp_number', ''); ?>" 
                                           target="_blank" class="btn btn-outline-success w-100 mb-2">
                                            <i class="fab fa-whatsapp me-2"></i>Chat WhatsApp
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Security Notice -->
                            <div class="alert alert-info mt-4">
                                <h6><i class="fas fa-shield-alt me-2"></i>Seguridad Garantizada</h6>
                                <small>
                                    Todos nuestros pagos están protegidos con encriptación SSL de 256 bits. 
                                    Nunca almacenamos datos de tarjetas de crédito en nuestros servidores.
                                </small>
                            </div>
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
            // Auto-limpiar URL después de 30 segundos
            setTimeout(() => {
                if (window.location.search) {
                    const url = window.location.pathname;
                    window.history.replaceState({}, document.title, url);
                }
            }, 30000);
            
            // Log error para analytics (opcional)
            if (typeof gtag !== 'undefined') {
                gtag('event', 'payment_failed', {
                    'reason': '<?php echo $reason; ?>',
                    'order_number': '<?php echo $orderNumber; ?>'
                });
            }
        });
    </script>
</body>
</html>