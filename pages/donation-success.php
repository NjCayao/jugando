<?php
// pages/donation-success.php - Página de éxito en donación
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

$transactionId = sanitize($_GET['transaction_id'] ?? '');
$status = sanitize($_GET['status'] ?? 'completed');
$donation = null;

// Obtener datos de la donación
if (!empty($transactionId)) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM donations WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        $donation = $stmt->fetch();
    } catch (Exception $e) {
        logError("Error obteniendo donación en success: " . $e->getMessage());
    }
}

$siteName = Settings::get('site_name', 'MiSistema');
$isPending = $status === 'pending';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isPending ? 'Donación Pendiente' : '¡Donación Exitosa!'; ?> - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="robots" content="noindex, nofollow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .success-hero {
            background: <?php echo $isPending ? 'linear-gradient(135deg, #f39c12 0%, #e67e22 100%)' : 'linear-gradient(135deg, #27ae60 0%, #2ecc71 100%)'; ?>;
            color: white;
            padding: 5rem 0;
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        
        .donation-details {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: -3rem;
            position: relative;
            z-index: 2;
        }
        
        .detail-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .share-buttons {
            margin-top: 2rem;
        }
        
        .share-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .share-btn.twitter {
            background: #1da1f2;
            color: white;
        }
        
        .share-btn.facebook {
            background: #4267b2;
            color: white;
        }
        
        .share-btn.whatsapp {
            background: #25d366;
            color: white;
        }
        
        .next-steps {
            background: #f8f9fa;
            padding: 3rem 0;
            margin-top: 3rem;
        }
        
        .step-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 1rem 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .step-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Success Hero -->
    <div class="success-hero">
        <div class="container">
            <div class="success-icon">
                <?php if ($isPending): ?>
                    <i class="fas fa-clock text-warning"></i>
                <?php else: ?>
                    <i class="fas fa-heart text-white"></i>
                <?php endif; ?>
            </div>
            
            <?php if ($isPending): ?>
                <h1 class="display-4 mb-3">¡Donación en Proceso!</h1>
                <p class="lead">
                    Tu donación está siendo procesada. Te notificaremos cuando esté confirmada.
                </p>
            <?php else: ?>
                <h1 class="display-4 mb-3">¡Muchas Gracias!</h1>
                <p class="lead">
                    Tu donación ha sido confirmada exitosamente. ¡Eres increíble!
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Donation Details -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="donation-details">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">
                            <i class="fas fa-receipt text-primary me-2"></i>
                            Detalles de tu Donación
                        </h3>
                        
                        <?php if ($donation): ?>
                            <div class="detail-item">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <strong>Monto Donado:</strong>
                                    </div>
                                    <div class="col-sm-8">
                                        <span class="h5 text-success">
                                            $<?php echo number_format($donation['amount'], 2); ?> USD
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <strong>ID de Transacción:</strong>
                                    </div>
                                    <div class="col-sm-8">
                                        <code><?php echo htmlspecialchars($donation['transaction_id']); ?></code>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <strong>Método de Pago:</strong>
                                    </div>
                                    <div class="col-sm-8">
                                        <?php
                                        switch ($donation['payment_method']) {
                                            case 'mercadopago':
                                                echo '<i class="fas fa-credit-card text-primary me-1"></i>MercadoPago';
                                                break;
                                            case 'paypal':
                                                echo '<i class="fab fa-paypal text-primary me-1"></i>PayPal';
                                                break;
                                            default:
                                                echo ucfirst($donation['payment_method']);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <strong>Fecha:</strong>
                                    </div>
                                    <div class="col-sm-8">
                                        <?php echo date('d/m/Y H:i', strtotime($donation['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($donation['product_name']): ?>
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <strong>Producto Relacionado:</strong>
                                        </div>
                                        <div class="col-sm-8">
                                            <?php echo htmlspecialchars($donation['product_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($donation['donor_message']): ?>
                                <div class="detail-item">
                                    <div class="row">
                                        <div class="col-sm-4">
                                            <strong>Tu Mensaje:</strong>
                                        </div>
                                        <div class="col-sm-8">
                                            <em>"<?php echo htmlspecialchars($donation['donor_message']); ?>"</em>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center">
                                <p class="text-muted">No se pudieron cargar los detalles de la donación.</p>
                                <p>Si tienes alguna duda, contacta a soporte con el ID de transacción.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Share Buttons -->
                        <?php if (!$isPending): ?>
                            <div class="share-buttons text-center">
                                <h5 class="mb-3">¡Comparte tu Buena Acción!</h5>
                                
                                <?php
                                $shareText = urlencode("¡Acabo de apoyar un proyecto increíble! #OpenSource #Support");
                                $shareUrl = urlencode(SITE_URL);
                                ?>
                                
                                <a href="https://twitter.com/intent/tweet?text=<?php echo $shareText; ?>&url=<?php echo $shareUrl; ?>" 
                                   target="_blank" class="share-btn twitter">
                                    <i class="fab fa-twitter me-1"></i> Twitter
                                </a>
                                
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $shareUrl; ?>" 
                                   target="_blank" class="share-btn facebook">
                                    <i class="fab fa-facebook me-1"></i> Facebook
                                </a>
                                
                                <a href="https://wa.me/?text=<?php echo $shareText; ?>%20<?php echo $shareUrl; ?>" 
                                   target="_blank" class="share-btn whatsapp">
                                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Next Steps -->
    <div class="next-steps">
        <div class="container">
            <h3 class="text-center mb-5">
                <?php echo $isPending ? '¿Qué sigue?' : '¿Qué pasa ahora?'; ?>
            </h3>
            
            <div class="row">
                <?php if ($isPending): ?>
                    <div class="col-md-4">
                        <div class="step-card text-center">
                            <i class="fas fa-search fa-3x text-info mb-3"></i>
                            <h5>Verificación</h5>
                            <p>Estamos verificando tu pago con la pasarela de pagos.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="step-card text-center">
                            <i class="fas fa-envelope fa-3x text-warning mb-3"></i>
                            <h5>Notificación</h5>
                            <p>Te enviaremos un email cuando la donación esté confirmada.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="step-card text-center">
                            <i class="fas fa-heart fa-3x text-danger mb-3"></i>
                            <h5>¡Gracias!</h5>
                            <p>Tu apoyo significa mucho para nosotros.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="col-md-4">
                        <div class="step-card text-center">
                            <i class="fas fa-code fa-3x text-primary mb-3"></i>
                            <h5>Desarrollo Continuo</h5>
                            <p>Tu donación nos permite seguir desarrollando y mejorando nuestros proyectos.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="step-card text-center">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <h5>Comunidad</h5>
                            <p>Únete a nuestra comunidad para estar al día con las últimas actualizaciones.</p>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="step-card text-center">
                            <i class="fas fa-download fa-3x text-info mb-3"></i>
                            <h5>Acceso Completo</h5>
                            <p>Disfruta de acceso completo a todos nuestros recursos gratuitos.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-home me-2"></i>
                    Volver al Inicio
                </a>
                
                <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-outline-primary btn-lg ms-2">
                    <i class="fas fa-box me-2"></i>
                    Ver Productos
                </a>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    
    <!-- Confetti Effect for Success -->
    <?php if (!$isPending): ?>
    <script>
        // Simple confetti effect
        function createConfetti() {
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57', '#ff9ff3'];
            
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: fixed;
                        top: -10px;
                        left: ${Math.random() * 100}%;
                        width: 10px;
                        height: 10px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        animation: fall 3s linear forwards;
                        z-index: 9999;
                        pointer-events: none;
                    `;
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 3000);
                }, i * 50);
            }
        }
        
        // Add CSS animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                }
            }
        `;
        document.head.appendChild(style);
        
        // Trigger confetti on load
        window.addEventListener('load', () => {
            setTimeout(createConfetti, 500);
        });
    </script>
    <?php endif; ?>
</body>
</html>