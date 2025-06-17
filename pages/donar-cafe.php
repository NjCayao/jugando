<?php
// pages/donar-cafe.php - Página de donación de café
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar modo mantenimiento
if (getSetting('maintenance_mode', '0') == '1' && !isAdmin()) {
    include '../maintenance.php';
    exit;
}

// Obtener producto de referencia
$productId = intval($_GET['producto'] ?? 0);
$product = null;

if ($productId > 0) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
    } catch (Exception $e) {
        logError("Error obteniendo producto para donación: " . $e->getMessage());
    }
}

$siteName = getSetting('site_name', 'MiSistema');
$siteEmail = getSetting('site_email', 'admin@misistema.com');

// Montos predefinidos de donación
$donationAmounts = [
    ['amount' => 3, 'label' => '☕ Un Café', 'description' => 'Un café para el desarrollador'],
    ['amount' => 5, 'label' => '☕☕ Dos Cafés', 'description' => 'Dos cafés para seguir programando'],
    ['amount' => 10, 'label' => '🍕 Pizza', 'description' => 'Una pizza para el equipo'],
    ['amount' => 20, 'label' => '🎁 Apoyo Generoso', 'description' => 'Apoyo generoso al proyecto'],
    ['amount' => 0, 'label' => '💰 Monto Personalizado', 'description' => 'Elige tu monto']
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>☕ Donar un Café al Dev - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Apoya al desarrollador con una pequeña donación. ¡Tu apoyo mantiene este proyecto gratuito!">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .donation-hero {
            background: linear-gradient(135deg, #6f4e37 0%, #8b5a3c 100%);
            color: white;
            padding: 4rem 0;
        }
        
        .coffee-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: steam 2s ease-in-out infinite alternate;
        }
        
        @keyframes steam {
            0% { transform: translateY(0px); }
            100% { transform: translateY(-5px); }
        }
        
        .donation-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            margin-top: -2rem;
            position: relative;
            z-index: 2;
        }
        
        .amount-btn {
            border: 2px solid #6f4e37;
            color: #6f4e37;
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .amount-btn:hover, .amount-btn.selected {
            background: #6f4e37;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(111, 78, 55, 0.3);
        }
        
        .custom-amount {
            border: 2px solid #6f4e37;
            border-radius: 10px;
            padding: 0.75rem;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .payment-methods {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .payment-btn {
            flex: 1;
            max-width: 200px;
            padding: 1rem;
            border-radius: 15px;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .payment-btn.paypal {
            background: #0070ba;
            color: white;
        }
        
        .payment-btn.mercadopago {
            background: #009ee3;
            color: white;
        }
        
        .payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .thank-you-message {
            background: #f8f5f0;
            border-left: 5px solid #6f4e37;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
        }
        
        .supporters-section {
            background: #f8f9fa;
            padding: 3rem 0;
            margin-top: 3rem;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Hero Section -->
    <div class="donation-hero text-center">
        <div class="container">
            <div class="coffee-icon">☕</div>
            <h1 class="display-4 mb-3">¡Invítame un Café!</h1>
            <p class="lead mb-0">
                Si mis proyectos gratuitos te han sido útiles, considera apoyarme con una pequeña donación.
            </p>
            <?php if ($product): ?>
                <p class="mt-2">
                    <small>Donación por: <strong><?php echo htmlspecialchars($product['name']); ?></strong></small>
                </p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Donation Form -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="donation-card">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">
                            <i class="fas fa-heart text-danger me-2"></i>
                            Elige tu Apoyo
                        </h3>
                        
                        <!-- Donation Amounts -->
                        <div class="row" id="donationAmounts">
                            <?php foreach ($donationAmounts as $index => $donation): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="amount-btn text-center" 
                                         onclick="selectAmount(<?php echo $donation['amount']; ?>, this)"
                                         data-amount="<?php echo $donation['amount']; ?>">
                                        <h5 class="mb-1"><?php echo $donation['label']; ?></h5>
                                        <?php if ($donation['amount'] > 0): ?>
                                            <p class="mb-2 fw-bold">$<?php echo $donation['amount']; ?></p>
                                        <?php endif; ?>
                                        <small><?php echo $donation['description']; ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Custom Amount -->
                        <div class="text-center mt-4" id="customAmountSection" style="display: none;">
                            <label for="customAmount" class="form-label">Monto Personalizado (USD)</label>
                            <input type="number" 
                                   class="form-control custom-amount" 
                                   id="customAmount" 
                                   placeholder="0.00" 
                                   min="1" 
                                   max="999" 
                                   step="0.01">
                        </div>
                        
                        <!-- Donor Info -->
                        <div class="mt-4">
                            <h5 class="text-center mb-3">Información del Donante (Opcional)</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="donorName" class="form-label">Nombre</label>
                                        <input type="text" class="form-control" id="donorName" placeholder="Tu nombre">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="donorEmail" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="donorEmail" placeholder="tu@email.com">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group mb-3">
                                <label for="donorMessage" class="form-label">Mensaje (Opcional)</label>
                                <textarea class="form-control" id="donorMessage" rows="3" placeholder="Mensaje de apoyo..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Payment Methods -->
                        <div class="payment-methods">
                            <button class="payment-btn paypal" onclick="donateWithPayPal()" id="paypalBtn" disabled>
                                <i class="fab fa-paypal me-2"></i>PayPal
                            </button>
                            <button class="payment-btn mercadopago" onclick="donateWithMercadoPago()" id="mercadopagoBtn" disabled>
                                <i class="fas fa-credit-card me-2"></i>MercadoPago
                            </button>
                        </div>
                        
                        <div id="selectedAmount" class="text-center mt-3" style="display: none;">
                            <strong>Monto seleccionado: $<span id="amountDisplay">0</span></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Thank You Message -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="thank-you-message">
                    <h5><i class="fas fa-heart text-danger me-2"></i>¿Por qué donar?</h5>
                    <ul class="mb-3">
                        <li>Mantienes los proyectos <strong>100% gratuitos</strong></li>
                        <li>Apoyas el desarrollo de <strong>nuevas funcionalidades</strong></li>
                        <li>Ayudas a cubrir gastos de <strong>hosting y dominio</strong></li>
                        <li>Motivas al desarrollador a seguir <strong>compartiendo código</strong></li>
                    </ul>
                    <p class="mb-0">
                        <strong>¡Cada donación, por pequeña que sea, hace la diferencia!</strong>
                        Tu apoyo permite que estos proyectos sigan siendo accesibles para toda la comunidad.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Support Info -->
    <div class="supporters-section">
        <div class="container text-center">
            <h4 class="mb-3">
                <i class="fas fa-users text-primary me-2"></i>
                Únete a la Comunidad de Supporters
            </h4>
            <p class="text-muted mb-4">
                Más de <strong>500+</strong> desarrolladores ya han apoyado este proyecto
            </p>
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <h3 class="text-primary">15K+</h3>
                        <p>Descargas Gratuitas</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <h3 class="text-success">500+</h3>
                        <p>Supporters</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <h3 class="text-warning">50+</h3>
                        <p>Proyectos Gratuitos</p>
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
        let selectedAmount = 0;
        
        function selectAmount(amount, element) {
            // Remover selección previa
            document.querySelectorAll('.amount-btn').forEach(btn => btn.classList.remove('selected'));
            
            // Seleccionar nueva opción
            element.classList.add('selected');
            
            if (amount === 0) {
                // Monto personalizado
                document.getElementById('customAmountSection').style.display = 'block';
                document.getElementById('selectedAmount').style.display = 'none';
                disablePaymentButtons();
                
                document.getElementById('customAmount').addEventListener('input', function() {
                    selectedAmount = parseFloat(this.value) || 0;
                    if (selectedAmount > 0) {
                        enablePaymentButtons();
                        updateAmountDisplay();
                    } else {
                        disablePaymentButtons();
                    }
                });
            } else {
                // Monto predefinido
                selectedAmount = amount;
                document.getElementById('customAmountSection').style.display = 'none';
                enablePaymentButtons();
                updateAmountDisplay();
            }
        }
        
        function updateAmountDisplay() {
            document.getElementById('amountDisplay').textContent = selectedAmount.toFixed(2);
            document.getElementById('selectedAmount').style.display = 'block';
        }
        
        function enablePaymentButtons() {
            document.getElementById('paypalBtn').disabled = false;
            document.getElementById('mercadopagoBtn').disabled = false;
        }
        
        function disablePaymentButtons() {
            document.getElementById('paypalBtn').disabled = true;
            document.getElementById('mercadopagoBtn').disabled = true;
            document.getElementById('selectedAmount').style.display = 'none';
        }
        
        function donateWithPayPal() {
            if (selectedAmount <= 0) {
                alert('Por favor selecciona un monto');
                return;
            }
            
            // Redirigir a procesador de donación PayPal
            const params = new URLSearchParams({
                amount: selectedAmount,
                method: 'paypal',
                product_id: <?php echo $productId; ?>,
                donor_name: document.getElementById('donorName').value || '',
                donor_email: document.getElementById('donorEmail').value || '',
                donor_message: document.getElementById('donorMessage').value || ''
            });
            
            window.location.href = `${window.SITE_URL}/api/donations/process.php?${params}`;
        }
        
        function donateWithMercadoPago() {
            if (selectedAmount <= 0) {
                alert('Por favor selecciona un monto');
                return;
            }
            
            // Redirigir a procesador de donación MercadoPago
            const params = new URLSearchParams({
                amount: selectedAmount,
                method: 'mercadopago',
                product_id: <?php echo $productId; ?>,
                donor_name: document.getElementById('donorName').value || '',
                donor_email: document.getElementById('donorEmail').value || '',
                donor_message: document.getElementById('donorMessage').value || ''
            });
            
            window.location.href = `${window.SITE_URL}/api/donations/process.php?${params}`;
        }
    </script>
</body>
</html>