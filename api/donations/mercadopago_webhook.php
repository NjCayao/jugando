<?php
// api/donations/mercadopago_webhook.php - Webhook para confirmaciones de MercadoPago
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
logActivity("Webhook MercadoPago recibido - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

try {
    // Obtener configuración
    $accessToken = Settings::get('mercadopago_access_token', '');
    $webhookSecret = Settings::get('mercadopago_webhook_secret', '');
    $sandbox = Settings::get('mercadopago_sandbox', '1') == '1';
    
    if (empty($accessToken)) {
        throw new Exception('MercadoPago no configurado');
    }
    
    // Leer el cuerpo de la petición
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos de webhook inválidos');
    }
    
    // Verificar webhook secret si está configurado
    if (!empty($webhookSecret)) {
        $expectedSignature = hash_hmac('sha256', $input, $webhookSecret);
        $receivedSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        
        if (!hash_equals($expectedSignature, $receivedSignature)) {
            throw new Exception('Firma de webhook inválida');
        }
    }
    
    // Log del contenido del webhook
    logActivity("Webhook MercadoPago - Tipo: " . ($data['type'] ?? 'unknown') . " - Datos: " . $input);
    
    // Solo procesar notificaciones de pago
    if (!isset($data['type']) || $data['type'] !== 'payment') {
        http_response_code(200);
        exit('OK - No es notificación de pago');
    }
    
    if (!isset($data['data']['id'])) {
        throw new Exception('ID de pago no encontrado en webhook');
    }
    
    $paymentId = $data['data']['id'];
    
    // Obtener detalles del pago desde la API de MercadoPago
    $apiUrl = $sandbox ? 
        'https://api.mercadopago.com/v1/payments/' . $paymentId :
        'https://api.mercadopago.com/v1/payments/' . $paymentId;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => !$sandbox
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error obteniendo pago de MercadoPago: HTTP $httpCode");
    }
    
    $paymentData = json_decode($response, true);
    if (!$paymentData) {
        throw new Exception('Respuesta inválida de la API de MercadoPago');
    }
    
    // Obtener external_reference para encontrar nuestra donación
    $externalReference = $paymentData['external_reference'] ?? '';
    if (empty($externalReference)) {
        throw new Exception('External reference no encontrado en pago');
    }
    
    // Buscar la donación en nuestra BD
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM donations WHERE transaction_id = ? AND payment_method = 'mercadopago'");
    $stmt->execute([$externalReference]);
    $donation = $stmt->fetch();
    
    if (!$donation) {
        throw new Exception("Donación no encontrada: $externalReference");
    }
    
    // Actualizar estado según el estado del pago
    $newStatus = 'pending';
    $completedAt = null;
    
    switch ($paymentData['status']) {
        case 'approved':
            $newStatus = 'completed';
            $completedAt = date('Y-m-d H:i:s');
            break;
        case 'pending':
        case 'in_process':
            $newStatus = 'pending';
            break;
        case 'rejected':
        case 'cancelled':
            $newStatus = 'failed';
            break;
        case 'refunded':
            $newStatus = 'refunded';
            break;
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
    
    $finalAmount = isset($paymentData['transaction_amount']) ? 
        floatval($paymentData['transaction_amount']) : 
        $donation['amount'];
    
    $stmt->execute([
        $newStatus,
        $completedAt,
        json_encode($paymentData),
        $finalAmount,
        $donation['id']
    ]);
    
    // Si el pago fue aprobado, enviar emails
    if ($newStatus === 'completed') {
        try {
            sendDonationEmails($donation, $paymentData);
        } catch (Exception $emailError) {
            logError("Error enviando emails de donación: " . $emailError->getMessage());
        }
    }
    
    // Log exitoso
    logActivity("Donación MercadoPago actualizada - ID: {$donation['transaction_id']}, Estado: $newStatus");
    
    // Respuesta exitosa a MercadoPago
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    // Log del error
    logError("Error en webhook MercadoPago: " . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}

function sendDonationEmails($donation, $paymentData) {
    $siteName = Settings::get('site_name', 'MiSistema');
    $adminEmail = Settings::get('admin_notification_email', '');
    
    // Email al donante si proporcionó email
    if (!empty($donation['donor_email'])) {
        $subject = "¡Gracias por tu donación! - $siteName";
        $message = "
        <h2>¡Muchas gracias por tu donación!</h2>
        <p>Hola " . ($donation['donor_name'] ?: 'Donante') . ",</p>
        <p>Tu donación de $" . number_format($donation['amount'], 2) . " USD ha sido confirmada.</p>
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
        <p><strong>Método:</strong> MercadoPago</p>
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