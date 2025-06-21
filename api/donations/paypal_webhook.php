<?php
// api/donations/paypal_webhook.php - Webhook para confirmaciones de PayPal en donaciones
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
logActivity("Webhook PayPal Donación recibido - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

try {
    // Obtener configuración
    $clientId = Settings::get('paypal_client_id', '');
    $clientSecret = Settings::get('paypal_client_secret', '');
    $webhookId = Settings::get('paypal_webhook_id', '');
    $sandbox = Settings::get('paypal_sandbox', '1') == '1';
    
    if (empty($clientId) || empty($clientSecret)) {
        throw new Exception('PayPal no configurado');
    }
    
    // Leer headers y body
    $headers = getallheaders();
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Datos de webhook inválidos');
    }
    
    // Log del contenido del webhook
    logActivity("Webhook PayPal Donación - Tipo: " . ($data['event_type'] ?? 'unknown') . " - Datos: " . $input);
    
    // Procesar según el tipo de evento
    switch ($data['event_type']) {
        case 'CHECKOUT.ORDER.COMPLETED':
        case 'PAYMENT.CAPTURE.COMPLETED':
            processDonationCompleted($data);
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.FAILED':
            processDonationFailed($data);
            break;
            
        default:
            logActivity("Evento PayPal no manejado: " . $data['event_type']);
            break;
    }
    
    // Respuesta exitosa
    http_response_code(200);
    echo 'OK';
    
} catch (Exception $e) {
    logError("Error en webhook PayPal Donación: " . $e->getMessage());
    http_response_code(500);
    echo 'Error: ' . $e->getMessage();
}

function processDonationCompleted($eventData) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Obtener ID de la orden
        $orderId = $eventData['resource']['supplementary_data']['related_ids']['order_id'] ?? 
                   $eventData['resource']['id'] ?? '';
        
        if (empty($orderId)) {
            throw new Exception('PayPal Order ID no encontrado');
        }
        
        // Buscar donación por external_id
        $stmt = $db->prepare("
            SELECT * FROM donations 
            WHERE external_id = ? AND payment_method = 'paypal'
        ");
        $stmt->execute([$orderId]);
        $donation = $stmt->fetch();
        
        if (!$donation) {
            // Intentar buscar por diferentes campos según el evento
            $captureId = $eventData['resource']['id'] ?? '';
            if ($captureId) {
                $stmt = $db->prepare("
                    SELECT * FROM donations 
                    WHERE JSON_EXTRACT(webhook_data, '$.capture_id') = ?
                ");
                $stmt->execute([$captureId]);
                $donation = $stmt->fetch();
            }
        }
        
        if (!$donation) {
            throw new Exception("Donación no encontrada para PayPal Order: $orderId");
        }
        
        // Actualizar donación
        $stmt = $db->prepare("
            UPDATE donations 
            SET payment_status = 'completed',
                completed_at = NOW(),
                webhook_received = 1,
                webhook_data = ?,
                final_amount = ?
            WHERE id = ?
        ");
        
        $amount = isset($eventData['resource']['amount']['value']) ? 
                  floatval($eventData['resource']['amount']['value']) : 
                  $donation['amount'];
        
        $stmt->execute([
            json_encode($eventData),
            $amount,
            $donation['id']
        ]);
        
        // Enviar emails de confirmación
        sendDonationConfirmationEmails($donation, $eventData);
        
        logActivity("Donación PayPal completada - ID: {$donation['transaction_id']}, Monto: $amount");
        
    } catch (Exception $e) {
        logError("Error procesando donación PayPal completada: " . $e->getMessage());
        throw $e;
    }
}

function processDonationFailed($eventData) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $orderId = $eventData['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
        
        if (empty($orderId)) {
            return; // No hay nada que hacer sin order ID
        }
        
        // Buscar y actualizar donación
        $stmt = $db->prepare("
            UPDATE donations 
            SET payment_status = 'failed',
                webhook_received = 1,
                webhook_data = ?
            WHERE external_id = ? AND payment_method = 'paypal'
        ");
        
        $stmt->execute([
            json_encode($eventData),
            $orderId
        ]);
        
        logActivity("Donación PayPal fallida - Order ID: $orderId");
        
    } catch (Exception $e) {
        logError("Error procesando donación PayPal fallida: " . $e->getMessage());
    }
}

function sendDonationConfirmationEmails($donation, $paymentData) {
    try {
        require_once __DIR__ . '/../../config/email.php';
        
        $siteName = Settings::get('site_name', 'MiSistema');
        $adminEmail = Settings::get('admin_notification_email', '');
        
        // Email al donante si proporcionó email
        if (!empty($donation['donor_email'])) {
            EmailSystem::sendDonationEmail(
                $donation['donor_email'],
                $donation['donor_name'] ?: 'Donante',
                $donation['amount']
            );
        }
        
        // Email al admin
        if (!empty($adminEmail) && Settings::get('email_notifications_enabled', '1') == '1') {
            $subject = "Nueva donación recibida - $siteName";
            $body = "
            <h2>Nueva Donación Confirmada</h2>
            <p><strong>Monto:</strong> $" . number_format($donation['amount'], 2) . " USD</p>
            <p><strong>Donante:</strong> " . ($donation['donor_name'] ?: 'Anónimo') . "</p>
            <p><strong>Email:</strong> " . ($donation['donor_email'] ?: 'No proporcionado') . "</p>
            <p><strong>Método:</strong> PayPal</p>
            <p><strong>ID:</strong> {$donation['transaction_id']}</p>
            ";
            
            if (!empty($donation['donor_message'])) {
                $body .= "<p><strong>Mensaje:</strong> " . htmlspecialchars($donation['donor_message']) . "</p>";
            }
            
            if (!empty($donation['product_name'])) {
                $body .= "<p><strong>Producto relacionado:</strong> {$donation['product_name']}</p>";
            }
            
            EmailSystem::sendEmail($adminEmail, $subject, $body, true);
        }
        
    } catch (Exception $e) {
        logError("Error enviando emails de donación: " . $e->getMessage());
    }
}
?>