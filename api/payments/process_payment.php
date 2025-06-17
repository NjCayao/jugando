<?php
// api/payments/process_payment.php - Procesador principal de pagos COMPLETO
header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Incluir configuraciones
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';
require_once __DIR__ . '/../../config/cart.php';
require_once __DIR__ . '/../../config/payments.php';

try {
    // Verificar que el carrito no esté vacío
    if (Cart::isEmpty()) {
        throw new Exception('El carrito está vacío');
    }
    
    // Validar carrito
    $validation = Cart::validate();
    if (!$validation['valid']) {
        throw new Exception('Error en el carrito: ' . implode(', ', $validation['errors']));
    }
    
    // Preparar datos del checkout
    $checkoutData = Cart::prepareCheckoutData();
    if (!$checkoutData['valid']) {
        throw new Exception('Error preparando checkout: ' . implode(', ', $checkoutData['errors']));
    }
    
    // Obtener datos del cliente
    $customerData = [
        'first_name' => sanitize($_POST['first_name'] ?? ''),
        'last_name' => sanitize($_POST['last_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'country' => sanitize($_POST['country'] ?? ''),
        'create_account' => isset($_POST['create_account']) ? 1 : 0,
        'user_id' => $_SESSION[SESSION_NAME]['user_id'] ?? null
    ];
    
    // Validaciones básicas
    if (empty($customerData['first_name']) || empty($customerData['last_name']) || empty($customerData['email'])) {
        throw new Exception('Datos del cliente incompletos');
    }
    
    if (!isValidEmail($customerData['email'])) {
        throw new Exception('Email inválido');
    }
    
    // Obtener método de pago
    $paymentMethod = sanitize($_POST['payment_method'] ?? '');
    if (empty($paymentMethod)) {
        throw new Exception('Método de pago requerido');
    }
    
    // Verificar términos y condiciones
    if (!isset($_POST['accept_terms'])) {
        throw new Exception('Debes aceptar los términos y condiciones');
    }
    
    // Crear usuario si no existe y se solicitó crear cuenta
    if (!$customerData['user_id'] && $customerData['create_account']) {
        $customerData['user_id'] = PaymentProcessor::createGuestUser($customerData);
    }
    
    // Crear orden en la base de datos
    $orderResult = PaymentProcessor::createOrder($customerData, $checkoutData, $paymentMethod);
    if (!$orderResult['success']) {
        throw new Exception('Error creando la orden: ' . $orderResult['message']);
    }
    
    // Log de la transacción
    logError("Orden creada: {$orderResult['order_number']} - Cliente: {$customerData['email']} - Método: {$paymentMethod}", 'payments.log');
    
    // Procesar según el método de pago
    switch ($paymentMethod) {
        case 'free':
            // Productos gratuitos - completar inmediatamente
            $result = PaymentProcessor::processFreeOrder($orderResult, $customerData);
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Orden procesada exitosamente',
                    'order_number' => $result['order_number'],
                    'redirect_url' => $result['redirect_url']
                ]);
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'stripe':
            // Procesar con Stripe
            $stripeResult = processStripePayment($orderResult, $customerData, $checkoutData);
            echo json_encode($stripeResult);
            break;
            
        case 'paypal':
            // Procesar con PayPal
            $paypalResult = processPayPalPayment($orderResult, $customerData, $checkoutData);
            echo json_encode($paypalResult);
            break;
            
        case 'mercadopago':
            // Procesar con MercadoPago
            $mercadopagoResult = processMercadoPagoPayment($orderResult, $customerData, $checkoutData);
            echo json_encode($mercadopagoResult);
            break;
            
        default:
            throw new Exception('Método de pago no soportado');
    }
    
} catch (Exception $e) {
    logError("Error en process_payment: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Procesar pago con Stripe
 */
function processStripePayment($orderResult, $customerData, $checkoutData) {
    try {
        $config = PaymentProcessor::getGatewayConfig('stripe');
        
        if (!$config['enabled'] || empty($config['secret_key'])) {
            throw new Exception('Stripe no está configurado correctamente');
        }
        
        // Calcular precio final con comisiones
        $finalAmount = PaymentProcessor::calculateFinalPrice($checkoutData['totals']['total'], 'stripe');
        
        // Simular creación de Payment Intent (en producción usar SDK real)
        $paymentIntentId = 'pi_' . uniqid() . '_test';
        $clientSecret = $paymentIntentId . '_secret_test';
        
        // Actualizar orden con payment intent ID
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE orders SET payment_id = ? WHERE id = ?");
        $stmt->execute([$paymentIntentId, $orderResult['order_id']]);
        
        return [
            'success' => true,
            'payment_method' => 'stripe',
            'client_secret' => $clientSecret,
            'publishable_key' => $config['publishable_key'],
            'order_number' => $orderResult['order_number'],
            'amount' => $finalAmount
        ];
        
    } catch (Exception $e) {
        PaymentProcessor::failPayment($orderResult['order_id'], $e->getMessage());
        throw new Exception('Error procesando pago con Stripe: ' . $e->getMessage());
    }
}

/**
 * Procesar pago con PayPal
 */
function processPayPalPayment($orderResult, $customerData, $checkoutData) {
    try {
        $config = PaymentProcessor::getGatewayConfig('paypal');
        
        if (!$config['enabled'] || empty($config['client_id'])) {
            throw new Exception('PayPal no está configurado correctamente');
        }
        
        // Calcular precio final con comisiones
        $finalAmount = PaymentProcessor::calculateFinalPrice($checkoutData['totals']['total'], 'paypal');
        
        // Obtener token de acceso de PayPal
        $tokenResponse = getPayPalAccessToken($config);
        if (!$tokenResponse['success']) {
            throw new Exception('Error obteniendo token de PayPal: ' . $tokenResponse['message']);
        }
        
        // Crear orden en PayPal
        $baseUrl = $config['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $orderResult['order_number'],
                'description' => "Orden #{$orderResult['order_number']} - " . Settings::get('site_name'),
                'amount' => [
                    'currency_code' => Settings::get('currency', 'USD'),
                    'value' => number_format($finalAmount, 2, '.', '')
                ]
            ]],
            'application_context' => [
                'return_url' => SITE_URL . '/api/payments/paypal_return.php',
                'cancel_url' => SITE_URL . '/pages/failed.php?reason=cancelled&order=' . $orderResult['order_number']
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v2/checkout/orders');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $tokenResponse['access_token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            logError("PayPal API Error: HTTP $httpCode - Response: $response");
            throw new Exception('Error creando orden en PayPal');
        }
        
        $paypalOrder = json_decode($response, true);
        
        if (!$paypalOrder || !isset($paypalOrder['id'])) {
            throw new Exception('Respuesta inválida de PayPal');
        }
        
        // Actualizar orden con PayPal order ID
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE orders SET payment_id = ? WHERE id = ?");
        $stmt->execute([$paypalOrder['id'], $orderResult['order_id']]);
        
        // Obtener URL de aprobación
        $approvalUrl = '';
        foreach ($paypalOrder['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        if (empty($approvalUrl)) {
            throw new Exception('URL de aprobación no encontrada en respuesta de PayPal');
        }
        
        return [
            'success' => true,
            'payment_method' => 'paypal',
            'paypal_order_id' => $paypalOrder['id'],
            'approval_url' => $approvalUrl,
            'order_number' => $orderResult['order_number']
        ];
        
    } catch (Exception $e) {
        PaymentProcessor::failPayment($orderResult['order_id'], $e->getMessage());
        throw new Exception('Error procesando pago con PayPal: ' . $e->getMessage());
    }
}

/**
 * Procesar pago con MercadoPago
 */
function processMercadoPagoPayment($orderResult, $customerData, $checkoutData) {
    try {
        $config = PaymentProcessor::getGatewayConfig('mercadopago');
        
        if (!$config['enabled'] || empty($config['access_token'])) {
            throw new Exception('MercadoPago no está configurado correctamente');
        }
        
        // Calcular precio final con comisiones
        $finalAmount = PaymentProcessor::calculateFinalPrice($checkoutData['totals']['total'], 'mercadopago');
        
        // Crear preferencia de MercadoPago
        $baseUrl = $config['sandbox'] ? 'https://api.mercadopago.com' : 'https://api.mercadopago.com';
        
        $preferenceData = [
            'items' => [[
                'title' => "Orden #{$orderResult['order_number']}",
                'description' => 'Compra en ' . Settings::get('site_name'),
                'quantity' => 1,
                'currency_id' => Settings::get('currency', 'USD'),
                'unit_price' => floatval($finalAmount)
            ]],
            'payer' => [
                'email' => $customerData['email'],
                'name' => $customerData['first_name'],
                'surname' => $customerData['last_name']
            ],
            'back_urls' => [
                'success' => SITE_URL . '/api/payments/mercadopago_return.php',
                'failure' => SITE_URL . '/pages/failed.php?reason=declined&order=' . $orderResult['order_number'],
                'pending' => SITE_URL . '/pages/pending.php?order=' . $orderResult['order_number'] . '&method=mercadopago'
            ],
            'auto_return' => 'approved',
            'external_reference' => $orderResult['order_number'],
            'notification_url' => SITE_URL . '/api/payments/mercadopago_webhook.php'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/checkout/preferences');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['access_token']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            logError("MercadoPago API Error: HTTP $httpCode - Response: $response");
            throw new Exception('Error creando preferencia en MercadoPago');
        }
        
        $preference = json_decode($response, true);
        
        if (!$preference || !isset($preference['id'])) {
            throw new Exception('Respuesta inválida de MercadoPago');
        }
        
        // Actualizar orden con preference ID
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE orders SET payment_id = ? WHERE id = ?");
        $stmt->execute([$preference['id'], $orderResult['order_id']]);
        
        return [
            'success' => true,
            'payment_method' => 'mercadopago',
            'preference_id' => $preference['id'],
            'init_point' => $preference['init_point'],
            'sandbox_init_point' => $preference['sandbox_init_point'] ?? '',
            'order_number' => $orderResult['order_number']
        ];
        
    } catch (Exception $e) {
        PaymentProcessor::failPayment($orderResult['order_id'], $e->getMessage());
        throw new Exception('Error procesando pago con MercadoPago: ' . $e->getMessage());
    }
}

/**
 * Obtener token de acceso de PayPal
 */
function getPayPalAccessToken($config) {
    try {
        $baseUrl = $config['sandbox'] ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $config['client_id'] . ':' . $config['client_secret']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en_US'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true, 
                'access_token' => $data['access_token']
            ];
        } else {
            logError("PayPal token error: HTTP $httpCode - Response: $response");
            return [
                'success' => false, 
                'message' => 'Error obteniendo token de PayPal'
            ];
        }
        
    } catch (Exception $e) {
        logError("PayPal token exception: " . $e->getMessage());
        return [
            'success' => false, 
            'message' => $e->getMessage()
        ];
    }
}
?>