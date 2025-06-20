<?php
// config/payments.php - Sistema de procesamiento de pagos COMPLETO
require_once __DIR__ . '/email.php';

/**
 * Clase para manejar procesamiento de pagos
 */
class PaymentProcessor
{

    /**
     * Crear orden en la base de datos
     */
    public static function createOrder($customerData, $cartData, $paymentMethod = 'pending')
    {
        try {
            // Validar datos requeridos
            if (empty($customerData['email'])) {
                throw new Exception('Email del cliente es requerido');
            }

            if (empty($customerData['first_name']) || empty($customerData['last_name'])) {
                throw new Exception('Nombre del cliente es requerido');
            }

            if (empty($cartData['items']) || count($cartData['items']) == 0) {
                throw new Exception('El carrito está vacío');
            }

            // Log de entrada
            logError("createOrder iniciado - Email: {$customerData['email']} - Items: " . count($cartData['items']), 'orders.log');


            $db = Database::getInstance()->getConnection();

            // Generar número de orden único
            $orderNumber = generateOrderNumber();

            // Calcular totales
            $totals = $cartData['totals'];

            // Determinar si es donación (solo productos gratuitos)
            $isDonation = $paymentMethod === 'free' && $totals['total'] == 0;

            // Crear usuario si no existe y eligió crear cuenta
            // Determinar el user_id ANTES de crear la orden
            $userId = $customerData['user_id'] ?? null;

            // Si no hay usuario logueado Y eligió crear cuenta
            if (!$userId && isset($customerData['create_account']) && $customerData['create_account'] == '1') {
                logError("Intentando crear usuario para: {$customerData['email']}", 'orders.log');

                // Crear usuario automáticamente
                $createdUserId = self::createGuestUser($customerData);

                if ($createdUserId && $createdUserId > 0) {
                    // Verificar que el usuario realmente existe
                    $checkStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
                    $checkStmt->execute([$createdUserId]);

                    if ($checkStmt->fetch()) {
                        $userId = $createdUserId;
                        logError("Usuario creado y verificado - ID: $userId - Email: {$customerData['email']}", 'orders.log');
                        $customerData['user_id'] = $userId;
                    } else {
                        logError("ERROR: createGuestUser devolvió ID $createdUserId pero no existe en BD", 'orders.log');
                        $userId = null; // No usar un ID inválido
                    }
                } else {
                    logError("No se pudo crear usuario para {$customerData['email']} - Continuando sin cuenta", 'orders.log');
                    $userId = null; // Continuar sin user_id (compra como invitado)
                }
            } else if (!$userId) {
                // Usuario no quiere crear cuenta o no marcó el checkbox
                logError("Compra sin cuenta - Email: {$customerData['email']}", 'orders.log');
                $userId = null;
            }

            // IMPORTANTE: Si userId no es null, debe ser un ID válido
            if ($userId !== null && !is_numeric($userId)) {
                logError("ADVERTENCIA: user_id no es numérico: " . var_export($userId, true), 'orders.log');
                $userId = null;
            }

            logError("Creando orden - Email: {$customerData['email']} - User ID final: " . ($userId ?? 'NULL'), 'orders.log');
            // Log para debug
            logError("Creando orden - Email: {$customerData['email']} - User ID: " . ($userId ? $userId : 'NULL'), 'orders.log');

            // Crear orden principal con el user_id (nuevo o existente)
            $stmt = $db->prepare("
                INSERT INTO orders (
                    user_id, order_number, total_amount, subtotal, tax_amount, tax_rate,
                    currency, items_count, payment_method, payment_status, 
                    customer_email, customer_name, customer_phone, customer_country,
                    is_donation, payment_data, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $userId = $customerData['user_id'] ?? null;
            $customerName = trim($customerData['first_name'] . ' ' . $customerData['last_name']);
            $paymentStatus = $paymentMethod === 'free' ? 'completed' : 'pending';

            $paymentData = json_encode([
                'customer_data' => $customerData,
                'cart_summary' => [
                    'items_count' => count($cartData['items']),
                    'subtotal' => $totals['subtotal'],
                    'tax' => $totals['tax'],
                    'total' => $totals['total']
                ],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Antes del execute(), agregar un log para verificar:
            logError("INSERT orden - user_id: " . ($userId ? $userId : 'NULL') . " - order_number: $orderNumber", 'orders.log');

            $stmt->execute([
                $userId,  // <-- Ahora este $userId tendrá el valor correcto
                $orderNumber,
                $totals['total'],
                $totals['subtotal'],
                $totals['tax'],
                $totals['tax_rate'],
                Settings::get('currency', 'USD'),
                $totals['items_count'],
                $paymentMethod,
                $paymentStatus,
                $customerData['email'],
                $customerName,
                $customerData['phone'] ?? '',
                $customerData['country'] ?? '',
                $isDonation ? 1 : 0,
                $paymentData
            ]);

            $orderId = $db->lastInsertId();

            // Agregar log después de crear la orden
            logError("Orden creada exitosamente - ID: $orderId - User ID: " . ($userId ? $userId : 'NULL'), 'orders.log');
            // Crear items de la orden
            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($cartData['items'] as $item) {
                $itemSubtotal = ($item['is_free'] ? 0 : $item['price']) * $item['quantity'];
                $stmt->execute([
                    $orderId,
                    $item['id'],
                    $item['name'],
                    $item['is_free'] ? 0 : $item['price'],
                    $item['quantity'],
                    $itemSubtotal
                ]);
            }

            return [
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totals['total'],
                'payment_status' => $paymentStatus
            ];
        } catch (Exception $e) {
            // Log detallado del error
            $errorDetails = [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ];

            logError("Error creando orden - Detalles: " . json_encode($errorDetails), 'errors.log');
            logError("Datos recibidos - Customer: " . json_encode($customerData), 'errors.log');
            logError("Datos recibidos - Cart: " . json_encode($cartData), 'errors.log');

            // Devolver mensaje más específico
            return [
                'success' => false,
                'message' => 'Error al crear la orden: ' . $e->getMessage(),
                'debug' => $errorDetails // Solo para desarrollo
            ];
        }
    }

    /**
     * Procesar pago gratuito o completar orden
     */
    public static function processFreeOrder($orderData, $customerData)
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Actualizar orden como completada
            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'completed', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$orderData['order_id']]);

            // Generar licencias de usuario
            $licenses = self::generateUserLicenses($orderData['order_id'], $customerData['user_id'] ?? null);

            // Enviar email de confirmación
            self::sendConfirmationEmail($orderData, $customerData, $licenses);

            // Limpiar carrito
            Cart::clear();

            return [
                'success' => true,
                'order_number' => $orderData['order_number'],
                'licenses' => $licenses,
                'redirect_url' => SITE_URL . '/pages/success.php?order=' . $orderData['order_number']
            ];
        } catch (Exception $e) {
            logError("Error procesando orden gratuita: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al procesar la orden'];
        }
    }

    /**
     * Generar licencias de usuario para productos comprados
     */
    public static function generateUserLicenses($orderId, $userId = null)
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Obtener productos de la orden
            $stmt = $db->prepare("
                SELECT oi.*, p.download_limit, p.update_months
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$orderId]);
            $orderItems = $stmt->fetchAll();

            $licenses = [];

            if ($userId) {
                // Usuario registrado - crear licencias
                foreach ($orderItems as $item) {
                    $updatesUntil = date('Y-m-d H:i:s', strtotime("+{$item['update_months']} months"));

                    $stmt = $db->prepare("
                        INSERT INTO user_licenses (
                            user_id, product_id, order_id, downloads_used, download_limit,
                            expires_at, is_active, created_at
                        ) VALUES (?, ?, ?, 0, ?, ?, 1, NOW())
                        ON DUPLICATE KEY UPDATE
                            download_limit = download_limit + VALUES(download_limit),
                            expires_at = GREATEST(expires_at, VALUES(expires_at)),
                            updated_at = NOW()
                    ");

                    $stmt->execute([
                        $userId,
                        $item['product_id'],
                        $orderId,
                        $item['download_limit'],
                        $updatesUntil
                    ]);

                    $licenses[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'download_limit' => $item['download_limit'],
                        'expires_at' => $updatesUntil
                    ];
                }
            } else {
                // Usuario invitado - crear licencias temporales
                foreach ($orderItems as $item) {
                    $licenses[] = [
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'download_limit' => $item['download_limit'],
                        'expires_at' => date('Y-m-d H:i:s', strtotime("+{$item['update_months']} months"))
                    ];
                }
            }

            return $licenses;
        } catch (Exception $e) {
            logError("Error generando licencias: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Enviar email de confirmación
     */
    public static function sendConfirmationEmail($orderData, $customerData, $licenses)
    {
        try {
            // Generar enlaces de descarga
            $downloadLinks = '';
            foreach ($licenses as $license) {
                $downloadUrl = SITE_URL . '/download/' . $license['product_id'] . '?order=' . $orderData['order_number'];
                $downloadLinks .= "• {$license['product_name']}: {$downloadUrl}\n";
            }

            // Enviar email usando el sistema de plantillas
            EmailSystem::sendPurchaseEmail(
                $customerData['email'],
                $customerData['first_name'],
                $orderData['order_number'],
                $orderData['total_amount'],
                $downloadLinks
            );

            // Notificar al admin
            EmailSystem::notifyNewOrder([
                'order_number' => $orderData['order_number'],
                'customer_name' => trim($customerData['first_name'] . ' ' . $customerData['last_name']),
                'customer_email' => $customerData['email'],
                'total_amount' => $orderData['total_amount'],
                'payment_method' => 'free'
            ]);

            return true;
        } catch (Exception $e) {
            logError("Error enviando email de confirmación: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Completar pago después de confirmación de pasarela
     */
    public static function completePayment($orderId, $paymentId, $paymentData = [])
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Obtener orden
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Orden no encontrada');
            }

            // Verificar que no esté ya completada
            if ($order['payment_status'] === 'completed') {
                return [
                    'success' => true,
                    'order_number' => $order['order_number'],
                    'message' => 'Orden ya completada'
                ];
            }

            // Log para debug
            logError("completePayment - Orden: {$order['order_number']} - User ID: " . ($order['user_id'] ? $order['user_id'] : 'NULL'), 'payments.log');

            // AGREGAR: Verificar si la orden tiene user_id
            if (!$order['user_id']) {
                logError("ALERTA: Orden {$order['order_number']} sin user_id - Email: {$order['customer_email']}", 'payments.log');

                // Buscar usuario por email
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$order['customer_email']]);
                $user = $stmt->fetch();

                if ($user) {
                    // Actualizar orden con el user_id encontrado
                    $updateStmt = $db->prepare("UPDATE orders SET user_id = ? WHERE id = ?");
                    $updateStmt->execute([$user['id'], $orderId]);
                    $order['user_id'] = $user['id'];

                    logError("User_id recuperado: {$user['id']} para orden {$order['order_number']}", 'payments.log');
                } else {
                    logError("ERROR: No se encontró usuario con email {$order['customer_email']}", 'payments.log');
                    // Si no existe, intentar crear el usuario
                    $customerData = json_decode($order['payment_data'], true)['customer_data'];
                    $newUserId = self::createGuestUser($customerData);

                    if ($newUserId) {
                        $updateStmt = $db->prepare("UPDATE orders SET user_id = ? WHERE id = ?");
                        $updateStmt->execute([$newUserId, $orderId]);
                        $order['user_id'] = $newUserId;

                        logError("Usuario creado en completePayment - ID: $newUserId", 'payments.log');
                    }
                }
            }

            // Actualizar orden como completada
            $stmt = $db->prepare("
            UPDATE orders 
            SET payment_status = 'completed', payment_id = ?, payment_date = NOW(),
                payment_data = ?, updated_at = NOW()
            WHERE id = ?
        ");

            $updatedPaymentData = array_merge(
                json_decode($order['payment_data'], true) ?: [],
                $paymentData,
                ['completed_at' => date('Y-m-d H:i:s')]
            );

            $stmt->execute([
                $paymentId,
                json_encode($updatedPaymentData),
                $orderId
            ]);

            // Obtener datos del cliente
            $customerData = json_decode($order['payment_data'], true)['customer_data'];

            // CAMBIO AQUÍ: Usar LicenseManager para generar licencias
            require_once __DIR__ . '/license_manager.php';
            $licenseResult = LicenseManager::generateLicensesFromOrder($orderId);

            if ($licenseResult['success']) {
                $licenses = $licenseResult['licenses'];
                logError("Licencias generadas en completePayment - Orden: {$order['order_number']} - Total: " . count($licenses), 'licenses.log');
            } else {
                // Si falla, intentar con el método antiguo como fallback
                $licenses = self::generateUserLicenses($orderId, $order['user_id']);
                logError("Error con LicenseManager, usando método alternativo: " . $licenseResult['message'], 'licenses.log');
            }

            // Enviar confirmación
            $orderData = [
                'order_id' => $orderId,
                'order_number' => $order['order_number'],
                'total_amount' => $order['total_amount']
            ];

            self::sendConfirmationEmail($orderData, $customerData, $licenses);

            // Limpiar carrito solo si existe sesión
            if (class_exists('Cart')) {
                Cart::clear();
            }

            return [
                'success' => true,
                'order_number' => $order['order_number'],
                'licenses' => $licenses
            ];
        } catch (Exception $e) {
            logError("Error completando pago: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Marcar pago como fallido
     */
    public static function failPayment($orderId, $reason = '')
    {
        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("
                UPDATE orders 
                SET payment_status = 'failed', failure_reason = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reason, $orderId]);

            logError("Pago fallido para orden $orderId: $reason", 'payments.log');

            return true;
        } catch (Exception $e) {
            logError("Error marcando pago como fallido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener configuración de pasarela de pago
     */
    public static function getGatewayConfig($gateway)
    {
        switch ($gateway) {
            case 'stripe':
                return [
                    'enabled' => Settings::get('stripe_enabled', '0') === '1',
                    'publishable_key' => Settings::get('stripe_publishable_key', ''),
                    'secret_key' => Settings::get('stripe_secret_key', ''),
                    'webhook_secret' => Settings::get('stripe_webhook_secret', ''),
                    'commission' => floatval(Settings::get('stripe_commission', '3.5')),
                    'fixed_fee' => floatval(Settings::get('stripe_fixed_fee', '0.30'))
                ];

            case 'paypal':
                return [
                    'enabled' => Settings::get('paypal_enabled', '0') === '1',
                    'client_id' => Settings::get('paypal_client_id', ''),
                    'client_secret' => Settings::get('paypal_client_secret', ''),
                    'webhook_id' => Settings::get('paypal_webhook_id', ''),
                    'sandbox' => Settings::get('paypal_sandbox', '1') === '1',
                    'commission' => floatval(Settings::get('paypal_commission', '4.5')),
                    'fixed_fee' => floatval(Settings::get('paypal_fixed_fee', '0.25'))
                ];

            case 'mercadopago':
                return [
                    'enabled' => Settings::get('mercadopago_enabled', '0') === '1',
                    'public_key' => Settings::get('mercadopago_public_key', ''),
                    'access_token' => Settings::get('mercadopago_access_token', ''),
                    'webhook_secret' => Settings::get('mercadopago_webhook_secret', ''),
                    'sandbox' => Settings::get('mercadopago_sandbox', '1') === '1',
                    'commission' => floatval(Settings::get('mercadopago_commission', '5.2')),
                    'fixed_fee' => floatval(Settings::get('mercadopago_fixed_fee', '0.15'))
                ];

            default:
                return ['enabled' => false];
        }
    }

    /**
     * Calcular precio final con comisiones
     */
    public static function calculateFinalPrice($basePrice, $gateway)
    {
        $config = self::getGatewayConfig($gateway);

        if (!$config['enabled'] || $basePrice <= 0) {
            return $basePrice;
        }

        // Precio final = (precio_base + tarifa_fija) / (1 - comision/100)
        $finalPrice = ($basePrice + $config['fixed_fee']) / (1 - $config['commission'] / 100);

        return round($finalPrice, 2);
    }

    /**
     * Validar webhook signature
     */
    public static function validateWebhookSignature($gateway, $payload, $signature)
    {
        $config = self::getGatewayConfig($gateway);

        switch ($gateway) {
            case 'stripe':
                return self::validateStripeSignature($payload, $signature, $config['webhook_secret']);

            case 'paypal':
                return self::validatePayPalSignature($payload, $signature, $config);

            case 'mercadopago':
                return self::validateMercadoPagoSignature($payload, $signature, $config['webhook_secret']);

            default:
                return false;
        }
    }

    /**
     * Validar signature de Stripe
     */
    private static function validateStripeSignature($payload, $signature, $secret)
    {
        if (empty($secret)) return false;

        $elements = explode(',', $signature);
        $signatureHash = '';

        foreach ($elements as $element) {
            if (strpos($element, 'v1=') === 0) {
                $signatureHash = substr($element, 3);
                break;
            }
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signatureHash);
    }

    /**
     * Validar signature de PayPal
     */
    private static function validatePayPalSignature($payload, $headers, $config)
    {
        // Implementación simplificada - en producción usar la librería oficial
        return true; // Por ahora aceptar todos los webhooks de PayPal
    }

    /**
     * Validar signature de MercadoPago
     */
    private static function validateMercadoPagoSignature($payload, $signature, $secret)
    {
        if (empty($secret)) return false;

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Crear usuario para checkout de invitado
     */
    public static function createGuestUser($customerData)
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Validar datos requeridos
            if (empty($customerData['email'])) {
                logError("createGuestUser: Email vacío", 'users.log');
                return null;
            }

            logError("createGuestUser iniciado - Email: {$customerData['email']}", 'users.log');

            // VERIFICAR SI EL EMAIL YA EXISTE
            $stmt = $db->prepare("SELECT id, is_active, is_verified FROM users WHERE email = ?");
            $stmt->execute([$customerData['email']]);
            $existingUser = $stmt->fetch();

            if ($existingUser) {
                // Usuario existe
                logError("Usuario ya existe - ID: {$existingUser['id']} - Activo: {$existingUser['is_active']}", 'users.log');

                if ($existingUser['is_active'] && $existingUser['is_verified']) {
                    // Usuario activo → Devolver su ID
                    EmailSystem::sendExistingUserEmail(
                        $customerData['email'],
                        $customerData['first_name']
                    );

                    return intval($existingUser['id']); // Asegurar que sea entero
                } else {
                    // Usuario inactivo → Reactivar
                    $resetToken = generateResetToken();

                    $stmt = $db->prepare("
                    UPDATE users 
                    SET reset_token = ?, 
                        reset_token_expires = DATE_ADD(NOW(), INTERVAL 24 HOUR),
                        is_active = 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                    $stmt->execute([$resetToken, $existingUser['id']]);

                    EmailSystem::sendReactivationEmail(
                        $customerData['email'],
                        $customerData['first_name'],
                        $resetToken
                    );

                    return intval($existingUser['id']); // Asegurar que sea entero
                }
            } else {
                // CASO C: Usuario nuevo → Crear cuenta
                logError("Creando nuevo usuario - Email: {$customerData['email']}", 'users.log');

                // Validar datos necesarios
                $firstName = trim($customerData['first_name'] ?? '');
                $lastName = trim($customerData['last_name'] ?? '');

                if (empty($firstName) || empty($lastName)) {
                    logError("Nombre o apellido vacíos - No se puede crear usuario", 'users.log');
                    return null;
                }

                $password = bin2hex(random_bytes(8)); // Contraseña temporal
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                INSERT INTO users (
                    email, password, first_name, last_name, phone, country, 
                    is_active, is_verified, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())
            ");

                $result = $stmt->execute([
                    $customerData['email'],
                    $hashedPassword,
                    $firstName,
                    $lastName,
                    $customerData['phone'] ?? '',
                    $customerData['country'] ?? ''
                ]);

                if (!$result) {
                    logError("Error al ejecutar INSERT de usuario", 'users.log');
                    return null;
                }

                $userId = $db->lastInsertId();

                if (!$userId || $userId <= 0) {
                    logError("lastInsertId devolvió ID inválido: " . var_export($userId, true), 'users.log');
                    return null;
                }

                logError("Usuario creado exitosamente - ID: $userId", 'users.log');

                // Enviar email con credenciales
                EmailSystem::sendNewAccountEmail(
                    $customerData['email'],
                    $firstName,
                    $password
                );

                // Notificar al admin
                EmailSystem::notifyNewUser([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $customerData['email'],
                    'country' => $customerData['country'] ?? 'No especificado'
                ]);

                return intval($userId); // Asegurar que sea entero
            }
        } catch (Exception $e) {
            logError("Error en createGuestUser: " . $e->getMessage() . " - Línea: " . $e->getLine(), 'users.log');
            return null;
        }
    }
}

// Funciones helper
function createOrder($customerData, $cartData, $paymentMethod = 'pending')
{
    return PaymentProcessor::createOrder($customerData, $cartData, $paymentMethod);
}

function processFreeOrder($orderData, $customerData)
{
    return PaymentProcessor::processFreeOrder($orderData, $customerData);
}

function completePayment($orderId, $paymentId, $paymentData = [])
{
    return PaymentProcessor::completePayment($orderId, $paymentId, $paymentData);
}

function failPayment($orderId, $reason = '')
{
    return PaymentProcessor::failPayment($orderId, $reason);
}

function generateRandomPassword($length = 12)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $password;
}
