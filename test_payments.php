<?php
// test_payments.php - Página para probar el sistema de pagos CORREGIDA
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/settings.php';
require_once __DIR__ . '/config/cart.php';
require_once __DIR__ . '/config/payments.php';

// Solo en desarrollo
if (strpos(SITE_URL, 'localhost') === false) {
    die('Esta página solo está disponible en desarrollo');
}

$message = '';
$error = '';

// Obtener productos disponibles
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT id, name, price, is_free, is_active FROM products WHERE is_active = 1 ORDER BY id LIMIT 10");
    $availableProducts = $stmt->fetchAll();
    
    // Debug: Log de productos encontrados
    logError("Debug: Productos encontrados en BD: " . count($availableProducts));
    foreach ($availableProducts as $p) {
        logError("Debug: Producto ID: {$p['id']}, Nombre: {$p['name']}, Precio: {$p['price']}, Gratis: {$p['is_free']}");
    }
    
} catch (Exception $e) {
    $error = "Error obteniendo productos: " . $e->getMessage();
    $availableProducts = [];
    logError("Error obteniendo productos: " . $e->getMessage());
}

// Procesar acción
$action = $_POST['action'] ?? '';

if ($action === 'test_config') {
    // Probar configuración de pasarelas
    $mercadopagoConfig = PaymentProcessor::getGatewayConfig('mercadopago');
    $paypalConfig = PaymentProcessor::getGatewayConfig('paypal');
    
    $message = '<div class="alert alert-info">';
    $message .= '<h5>Estado de Configuración:</h5>';
    $message .= '<p><strong>MercadoPago:</strong> ' . ($mercadopagoConfig['enabled'] ? '✅ Habilitado' : '❌ Deshabilitado') . '</p>';
    $message .= '<p><strong>PayPal:</strong> ' . ($paypalConfig['enabled'] ? '✅ Habilitado' : '❌ Deshabilitado') . '</p>';
    $message .= '</div>';
}

elseif ($action === 'add_test_products') {
    // Agregar productos específicos al carrito
    $addedCount = 0;
    $errors = [];
    
    foreach ($availableProducts as $product) {
        if ($addedCount >= 2) break; // Máximo 2 productos
        
        $result = Cart::addItem($product['id'], 1);
        if ($result['success']) {
            $addedCount++;
        } else {
            $errors[] = $result['message'];
        }
    }
    
    if ($addedCount > 0) {
        $message = '<div class="alert alert-success">Se agregaron ' . $addedCount . ' productos al carrito</div>';
    } else {
        $error = '<div class="alert alert-danger">No se pudieron agregar productos: ' . implode(', ', $errors) . '</div>';
    }
}

elseif ($action === 'add_single_product') {
    $productId = intval($_POST['product_id'] ?? 0);
    
    // Debug info
    logError("Debug: Intentando agregar producto ID: $productId, POST data: " . print_r($_POST, true));
    
    if ($productId > 0) {
        $result = Cart::addItem($productId, 1);
        
        logError("Debug: Resultado de Cart::addItem: " . print_r($result, true));
        
        if ($result['success']) {
            $message = '<div class="alert alert-success">✅ Producto ID ' . $productId . ' agregado al carrito - Total items: ' . $result['cart_count'] . '</div>';
        } else {
            $error = '<div class="alert alert-danger">Error agregando producto ID ' . $productId . ': ' . $result['message'] . '</div>';
        }
    } else {
        $error = '<div class="alert alert-danger">ID de producto inválido (recibido: ' . $productId . ')</div>';
        logError("Debug: Product ID inválido. POST: " . print_r($_POST, true));
    }
}

elseif ($action === 'clear_cart') {
    Cart::clear();
    $message = '<div class="alert alert-warning">Carrito vaciado</div>';
}

// Obtener datos actuales
$cartItems = Cart::getItems();
$cartTotals = Cart::getTotals();
$mercadopagoConfig = PaymentProcessor::getGatewayConfig('mercadopago');
$paypalConfig = PaymentProcessor::getGatewayConfig('paypal');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Sistema de Pagos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1 class="mb-4">🧪 Test de Sistema de Pagos</h1>
        
        <?php echo $message; ?>
        <?php echo $error; ?>
        
        <!-- Productos Disponibles -->
        <?php if (empty($availableProducts)): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> No hay productos disponibles</h5>
                <p>Necesitas tener productos activos en tu base de datos para probar el sistema.</p>
                <p>Ve al <a href="admin/pages/products/">panel de admin</a> y crea algunos productos primero.</p>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-box"></i> Productos Disponibles (<?php echo count($availableProducts); ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($availableProducts as $product): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="card-text">
                                            <strong>Precio:</strong> 
                                            <?php echo $product['is_free'] ? '<span class="badge bg-success">GRATIS</span>' : formatPrice($product['price']); ?>
                                        </p>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="add_single_product">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-plus"></i> Agregar al Carrito
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="add_test_products">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Agregar Primeros 2 Productos
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Estado del Sistema -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cog"></i> Configuración de Pasarelas</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>MercadoPago:</strong>
                            <?php if ($mercadopagoConfig['enabled']): ?>
                                <span class="badge bg-success">✅ Habilitado</span>
                                <br><small class="text-muted">Public Key: <?php echo substr($mercadopagoConfig['public_key'], 0, 20); ?>...</small>
                            <?php else: ?>
                                <span class="badge bg-danger">❌ Deshabilitado</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong>PayPal:</strong>
                            <?php if ($paypalConfig['enabled']): ?>
                                <span class="badge bg-success">✅ Habilitado</span>
                                <br><small class="text-muted">Client ID: <?php echo substr($paypalConfig['client_id'], 0, 20); ?>...</small>
                            <?php else: ?>
                                <span class="badge bg-danger">❌ Deshabilitado</span>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="test_config">
                            <button type="submit" class="btn btn-info btn-sm">
                                <i class="fas fa-sync"></i> Actualizar Estado
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shopping-cart"></i> Estado del Carrito</h5>
                    </div>
                    <div class="card-body">
                        <?php if (Cart::isEmpty()): ?>
                            <p class="text-muted">Carrito vacío</p>
                            <?php if (!empty($availableProducts)): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="add_test_products">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Agregar Productos de Prueba
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><strong>Items:</strong> <?php echo $cartTotals['items_count']; ?></p>
                            <p><strong>Total:</strong> <?php echo formatPrice($cartTotals['total']); ?></p>
                            
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="clear_cart">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="fas fa-trash"></i> Vaciar Carrito
                                </button>
                            </form>
                            
                            <a href="pages/checkout.php" class="btn btn-success btn-sm ms-2">
                                <i class="fas fa-credit-card"></i> Ir a Checkout
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Productos en el Carrito -->
        <?php if (!Cart::isEmpty()): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Productos en el Carrito</h5>
            </div>
            <div class="card-body">
                <?php foreach ($cartItems as $item): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                            <small class="text-muted">
                                Cantidad: <?php echo $item['quantity']; ?> | 
                                Precio: <?php echo $item['is_free'] ? 'GRATIS' : formatPrice($item['price']); ?>
                            </small>
                        </div>
                        <div>
                            <?php echo $item['is_free'] ? 'GRATIS' : formatPrice($item['price'] * $item['quantity']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <strong>Total:</strong>
                    <strong><?php echo formatPrice($cartTotals['total']); ?></strong>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Debug Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-bug"></i> Información de Debug</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Productos en BD:</strong> <?php echo count($availableProducts); ?></p>
                        <p><strong>Sesión activa:</strong> <?php echo session_id() ? '✅ Sí' : '❌ No'; ?></p>
                        <p><strong>Cart clase:</strong> <?php echo class_exists('Cart') ? '✅ Disponible' : '❌ No encontrada'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Base de datos:</strong> <?php 
                            try {
                                $db = Database::getInstance()->getConnection();
                                echo '✅ Conectada';
                            } catch (Exception $e) {
                                echo '❌ Error: ' . $e->getMessage();
                            }
                        ?></p>
                        <p><strong>Configuraciones:</strong> <?php echo class_exists('Settings') ? '✅ Disponible' : '❌ No encontrada'; ?></p>
                    </div>
                </div>
                
                <?php if (!empty($_POST)): ?>
                    <hr>
                    <p><strong>Último POST:</strong></p>
                    <pre class="bg-light p-2 small"><?php echo htmlspecialchars(print_r($_POST, true)); ?></pre>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Instrucciones -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle"></i> Instrucciones de Prueba</h5>
            </div>
            <div class="card-body">
                <h6>📋 Pasos para probar el sistema:</h6>
                <ol>
                    <li><strong>Configurar credenciales:</strong> Ejecuta el script SQL <code>setup_test_credentials.sql</code> en tu base de datos</li>
                    <li><strong>Verificar productos:</strong> Asegúrate de tener productos activos (ve al admin si no hay)</li>
                    <li><strong>Agregar productos:</strong> Usa los botones para agregar productos al carrito</li>
                    <li><strong>Ir a checkout:</strong> Haz clic en "Ir a Checkout" para probar el flujo de pago</li>
                    <li><strong>Probar pagos:</strong> Usa las credenciales de ejemplo para simular pagos</li>
                </ol>
                
                <hr>
                
                <h6>🧪 Datos de Prueba MercadoPago:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tarjetas de prueba:</strong></p>
                        <ul>
                            <li>Visa: 4509 9535 6623 3704</li>
                            <li>Mastercard: 5031 7557 3453 0604</li>
                            <li>CVV: 123 | Fecha: 11/25</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Nombre en tarjeta para resultados:</strong></p>
                        <ul>
                            <li><strong>APRO</strong> - Aprobado</li>
                            <li><strong>CONT</strong> - Pendiente</li>
                            <li><strong>OTHE</strong> - Rechazado</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Enlaces Útiles -->
        <div class="mt-4">
            <h6>🔗 Enlaces Útiles:</h6>
            <div class="btn-group" role="group">
                <a href="admin/pages/config/payments.php" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> Configurar Pagos (Admin)
                </a>
                <a href="pages/cart.php" class="btn btn-outline-secondary">
                    <i class="fas fa-shopping-cart"></i> Ver Carrito
                </a>
                <a href="pages/products.php" class="btn btn-outline-info">
                    <i class="fas fa-box"></i> Ver Productos
                </a>
                <a href="admin/" class="btn btn-outline-success">
                    <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>