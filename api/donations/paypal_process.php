<?php
// api/donations/paypal_process.php - Procesador específico para PayPal

function processPayPalDonation($donationId, $transactionId, $amount, $donorData, $product) {
    try {
        // Obtener configuración de PayPal
        $clientId = Settings::get('paypal_client_id', '');
        $clientSecret = Settings::get('paypal_client_secret', '');
        $sandbox = Settings::get('paypal_sandbox', '1') == '1';
        
        if (empty($clientId) || empty($clientSecret)) {
            throw new Exception('PayPal no configurado correctamente');
        }
        
        // URLs según el ambiente
        $baseApiUrl = $sandbox ? 
            'https://api.sandbox.paypal.com' : 
            'https://api.paypal.com';
        
        // URLs de retorno
        $baseUrl = SITE_URL;
        $successUrl = $baseUrl . "/pages/donation-success.php?transaction_id=" . $transactionId;
        $cancelUrl = $baseUrl . "/pages/donation-failed.php?transaction_id=" . $transactionId;
        
        // 1. Obtener token de acceso
        $tokenUrl = $baseApiUrl . '/v1/oauth2/token';
        
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
        
        $tokenResponse = curl_exec($ch);
        $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($tokenHttpCode !== 200) {
            throw new Exception('Error obteniendo token de PayPal');
        }
        
        $tokenData = json_decode($tokenResponse, true);
        if (!isset($tokenData['access_token'])) {
            throw new Exception('Token de PayPal inválido');
        }
        
        $accessToken = $tokenData['access_token'];
        
        // 2. Crear orden de pago
        $siteName = Settings::get('site_name', 'MiSistema');
        $description = "Donación de café - " . $siteName;
        
        if ($product) {
            $description .= " (Por: " . $product['name'] . ")";
        }
        
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $transactionId,
                    'description' => $description,
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => number_format($amount, 2, '.', '')
                    ]
                ]
            ],
            'application_context' => [
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => $siteName,
                'landing_page' => 'BILLING',
                'user_action' => 'PAY_NOW'
            ]
        ];
        
        $ordersUrl = $baseApiUrl . '/v2/checkout/orders';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $ordersUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . $transactionId
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => !$sandbox
        ]);
        
        $orderResponse = curl_exec($ch);
        $orderHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($orderHttpCode !== 201) {
            logError("Error HTTP de PayPal: $orderHttpCode - Respuesta: $orderResponse");
            throw new Exception('Error al crear orden en PayPal');
        }
        
        $orderData = json_decode($orderResponse, true);
        
        if (!$orderData || !isset($orderData['id'])) {
            logError("Respuesta inválida de PayPal: $orderResponse");
            throw new Exception('Respuesta inválida de PayPal');
        }
        
        // Buscar URL de aprobación
        $approvalUrl = null;
        if (isset($orderData['links'])) {
            foreach ($orderData['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approvalUrl = $link['href'];
                    break;
                }
            }
        }
        
        if (!$approvalUrl) {
            throw new Exception('URL de aprobación no encontrada en respuesta de PayPal');
        }
        
        // Actualizar donación con datos de PayPal
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE donations 
            SET external_id = ?, gateway_response = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $orderData['id'],
            json_encode($orderData),
            $donationId
        ]);
        
        // Log exitoso
        logActivity("Donación PayPal creada - ID: $transactionId, Monto: $amount");
        
        // Redirigir al checkout de PayPal
        redirect($approvalUrl);
        
    } catch (Exception $e) {
        logError("Error en procesamiento PayPal: " . $e->getMessage());
        throw $e;
    }
}
?>