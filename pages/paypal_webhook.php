<?php
// api/donations/paypal_webhook.php - Webhook para confirmaciones de PayPal
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../config/functions.php';
require_once '../../config/settings.php';

// Solo permitir métodos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Log de webhook recibido
logActivity("Webhook PayPal recibido - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

try {
    // Obtener configuración
    $clientId = Settings::get('paypal_client_id', '');
    $clientSecret = Settings::get('paypal_client_secret', '');
    $webhookId = Settings::get('paypal_webhook_id', '');
    $sandbox = Settings::get('paypal_sandbox', '1') == '1';
    
    if (empty($clientId) || empty($clientSecret)) {
        throw new Exception('PayPal no configurado');
    }
    
    // Leer el cuerpo de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos de webhook inválidos');
    }
    
    // Log del contenido del webhook
    logActivity("Webhook PayPal - Tipo: " . ($data['event_type'] ?? 'unknown') . " - Datos: " . $input);
    
    // Verificar webhook signature si está configurado
    if (!empty($webhookId)) {
        $verified = verifyPayPalWebhook($input, $_SERVER, $webhookId, $clientId, $clientSecret, $sandbox);
        if (!$verified) {
            throw new Exception('Firma de webhook PayPal inválida');
        }
    }
    
    // Solo procesar eventos de checkout completed
    if (!isset($data['event_type']) || $data['event_type'] !== 'CHECKOUT.ORDER.APPROVED') {
        http_response_code(200);
        exit('OK - No es evento de checkout completado');
    }
    
    if (!isset($data['resource']['id'])) {
        throw new Exception('ID de orden no encontrado en webhook');
    }
    
    $orderId = $data['resource']['id'];
    
    // Obtener detalles de la orden desde la API de PayPal
    $orderDetails = getPayPalOrderDetails($orderId, $clientId, $clientSecret, $sandbox);
    
    if (!$orderDetails) {
        throw new Exception('No se pudieron obtener detalles de la orden PayPal');
    }
    
    // Obtener external_reference para encontrar nuestra donación
    $externalReference = null;
    if (isset($orderDetails['purchase_units'][0]['reference_id'])) {
        $externalReference = $orderDetails['purchase_units'][0]['reference_id'];
    }
    
    if (empty($externalReference)) {
        throw new Exception('Reference ID no encontrado en orden PayPal');
    }
    
    // Buscar la donación en nuestra BD
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM donations WHERE transaction_id = ? AND payment_method = 'paypal'");
    $stmt->execute([$externalReference]);
    $donation = $stmt->fetch();
    
    if (!$donation) {
        throw new Exception("Donación no encontrada: $externalReference");
    }
    
    // Capturar el pago si está aprobado
    if ($orderDetails['status'] === 'APPROVED') {
        $captureResult = capturePayPalOrder($orderId, $clientId, $clientSecret, $sandbox);
        
        if ($captureResult && isset($captureResult['status'])) {
            $orderDetails['capture_result'] = $captureResult;
            $orderDetails['status'] = $captureResult['status'];
        }
    }
    
    // Determinar estado según el resultado
    $newStatus = 'pending';
    $completedAt = null;
    
    switch ($orderDetails['status']) {
        case 'COMPLETED':
            $newStatus = 'completed';
            $completedAt = date('Y-m-d H:i:s');
            break;
        case 'APPROVED':
        case 'PENDING':
            $newStatus = 'pending';
            break;
        case 'VOIDED':
        case 'CANCELLED':
            $newStatus = 'failed';
            break;
    }
    
    // Obtener monto final
    $finalAmount = $donation['amount'];
    if (isset($orderDetails['purchase_units'][0]['amount']['value'])) {
        $finalAmount = floatval($orderDetails['purchase_units'][0]['amount']['value']);
    }
    
    // Actualizar donación
    $stmt = $db->prepare("
        UPDATE donations 
        SET payment_status = ?, 
            completed_at = ?, 
            webhook_received = 1,
            webhook_data = ?,
            final_amount = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $newStatus,
        $completedAt,
        json_encode($orderDetails),
        $finalAmount,
        $donation['id']
    ]);
    
    // Si el pago fue completado, enviar emails
    if ($newStatus === 'completed') {
        try {
            sendDonationEmails($donation, $orderDetails);
        } catch (Exception $emailError) {
            logError("Error enviando emails de donación PayPal: " . $emailError->getMessage());
        }
    }
    
    // Log exitoso
    logActivity("Donación PayPal actualizada - ID: {$donation['transaction_id']}, Estado: $newStatus");
    
    // Respuesta exitosa a PayPal
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    // Log del error
    logError("Error en webhook PayPal: " . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}

function verifyPayPalWebhook($body, $headers, $webhookId, $clientId, $clientSecret, $sandbox) {
    try {
        // Obtener token de acceso
        $accessToken = getPayPalAccessToken($clientId, $clientSecret, $sandbox);
        if (!$accessToken) {
            return false;
        }
        
        // Preparar datos para verificación
        $verificationData = [
            'auth_algo' => $headers['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
            'cert_id' => $headers['HTTP_PAYPAL_CERT_ID'] ?? '',
            'headers' => [
                'PAYPAL-AUTH-ALGO' => $headers['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
                'PAYPAL-AUTH-VERSION' => $headers['HTTP_PAYPAL_AUTH_VERSION'] ?? '',
                'PAYPAL-CERT-ID' => $headers['HTTP_PAYPAL_CERT_ID'] ?? '',
                'PAYPAL-TRANSMISSION-ID' => $headers['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
                'PAYPAL-TRANSMISSION-SIG' => $headers['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
                'PAYPAL-TRANSMISSION-TIME' => $headers['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? ''
            ],
            'transmission_id' => $headers['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($body, true)
        ];
        
        $baseUrl = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $verifyUrl = $baseUrl . '/v1/notifications/verify-webhook-signature';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $verifyUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($verificationData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => !$sandbox
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return isset($result['verification_status']) && $result['verification_status'] === 'SUCCESS';
        }
        
        return false;
    } catch (Exception $e) {
        logError("Error verificando webhook PayPal: " . $e->getMessage());
        return false;
    }
}

function getPayPalAccessToken($clientId, $clientSecret, $sandbox) {
    $baseUrl = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
    $tokenUrl = $baseUrl . '/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $tokenUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => !$sandbox
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    return null;
}

function getPayPalOrderDetails($orderId, $clientId, $clientSecret, $sandbox) {
    $accessToken = getPayPalAccessToken($clientId, $clientSecret, $sandbox);
    if (!$accessToken) {
        return null;
    }
    
    $baseUrl = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
    $orderUrl = $baseUrl . '/v2/checkout/orders/' . $orderId;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $orderUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => !$sandbox
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

function capturePayPalOrder($orderId, $clientId, $clientSecret, $sandbox) {
    $accessToken = getPayPalAccessToken($clientId, $clientSecret, $sandbox);
    if (!$accessToken) {
        return null;
    }
    
    $baseUrl = $sandbox ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
    $captureUrl = $baseUrl . '/v2/checkout/orders/' . $orderId . '/capture';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $captureUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => !$sandbox
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201) {
        return json_decode($response, true);
    }
    
    return null;
}

function sendDonationEmails($donation, $orderDetails) {
    $siteName = Settings::get('site_name', 'MiSistema');
    $adminEmail = Settings::get('admin_notification_email', '');
    
    // Email al donante si proporcionó email
    if (!empty($donation['donor_email'])) {
        $subject = "¡Gracias por tu donación! - $siteName";
        $message = "
        <h2>¡Muchas gracias por tu donación!</h2>
        <p>Hola " . ($donation['donor_name'] ?: 'Donante') . ",</p>
        <p>Tu donación de $" . number_format($donation['amount'], 2) . " USD ha sido confirmada via PayPal.</p>
        <p><strong>ID de transacción:</strong> {$donation['transaction_id']}</p>
        <p>Tu apoyo nos ayuda a mantener este proyecto gratuito y seguir desarrollando software de calidad.</p>
        <p>¡Mil gracias por tu generosidad!</p>
        <p>Saludos,<br>El equipo de $siteName</p>
        ";
        
        // Aquí implementarías el envío real del email
        // sendEmail($donation['donor_email'], $subject, $message);
    }
    
    // Email al admin
    if (!empty($adminEmail)) {
        $subject = "Nueva donación recibida - $siteName";
        $message = "
        <h2>Nueva donación confirmada</h2>
        <p><strong>Monto:</strong> $" . number_format($donation['amount'], 2) . " USD</p>
        <p><strong>Donante:</strong> " . ($donation['donor_name'] ?: 'Anónimo') . "</p>
        <p><strong>Email:</strong> " . ($donation['donor_email'] ?: 'No proporcionado') . "</p>
        <p><strong>Método:</strong> PayPal</p>
        <p><strong>ID:</strong> {$donation['transaction_id']}</p>
        ";
        
        if (!empty($donation['donor_message'])) {
            $message .= "<p><strong>Mensaje:</strong> " . htmlspecialchars($donation['donor_message']) . "</p>";
        }
        
        if (!empty($donation['product_name'])) {
            $message .= "<p><strong>Producto relacionado:</strong> {$donation['product_name']}</p>";
        }
        
        // Aquí implementarías el envío real del email
        // sendEmail($adminEmail, $subject, $message);
    }
}
?>