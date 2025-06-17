<?php
// api/donations/mercadopago_process.php - Procesador específico para MercadoPago

function processMercadoPagoDonation($donationId, $transactionId, $amount, $donorData, $product) {
    try {
        // Obtener configuración de MercadoPago
        $accessToken = Settings::get('mercadopago_access_token', '');
        $sandbox = Settings::get('mercadopago_sandbox', '1') == '1';
        
        if (empty($accessToken)) {
            throw new Exception('MercadoPago no configurado correctamente');
        }
        
        // URLs de retorno
        $baseUrl = SITE_URL;
        $successUrl = $baseUrl . "/pages/donation-success.php?transaction_id=" . $transactionId;
        $failureUrl = $baseUrl . "/pages/donation-failed.php?transaction_id=" . $transactionId;
        $pendingUrl = $baseUrl . "/pages/donation-success.php?transaction_id=" . $transactionId . "&status=pending";
        
        // URL del webhook
        $webhookUrl = $baseUrl . "/api/donations/mercadopago_webhook.php";
        
        // Preparar datos de la preferencia
        $siteName = Settings::get('site_name', 'MiSistema');
        $description = "Donación de café - " . $siteName;
        
        if ($product) {
            $description .= " (Por: " . $product['name'] . ")";
        }
        
        $preferenceData = [
            'items' => [
                [
                    'id' => $transactionId,
                    'title' => $description,
                    'quantity' => 1,
                    'unit_price' => floatval($amount),
                    'currency_id' => 'USD'
                ]
            ],
            'payer' => [
                'name' => $donorData['name'] ?: 'Donante Anónimo',
                'email' => $donorData['email'] ?: 'donante@ejemplo.com'
            ],
            'back_urls' => [
                'success' => $successUrl,
                'failure' => $failureUrl,
                'pending' => $pendingUrl
            ],
            'auto_return' => 'approved',
            'external_reference' => $transactionId,
            'notification_url' => $webhookUrl,
            'statement_descriptor' => substr($siteName, 0, 22), // Máximo 22 caracteres
            'expires' => true,
            'expiration_date_from' => date('c'),
            'expiration_date_to' => date('c', strtotime('+1 hour'))
        ];
        
        // Agregar mensaje si existe
        if (!empty($donorData['message'])) {
            $preferenceData['additional_info'] = substr($donorData['message'], 0, 600);
        }
        
        // URL de la API según el ambiente
        $apiUrl = $sandbox ? 
            'https://api.mercadopago.com/checkout/preferences' : 
            'https://api.mercadopago.com/checkout/preferences';
        
        // Realizar petición a MercadoPago
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($preferenceData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => !$sandbox // No verificar SSL en sandbox
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('Error de conexión con MercadoPago: ' . $curlError);
        }
        
        if ($httpCode !== 201) {
            logError("Error HTTP de MercadoPago: $httpCode - Respuesta: $response");
            throw new Exception('Error al crear preferencia en MercadoPago');
        }
        
        $preferenceResponse = json_decode($response, true);
        
        if (!$preferenceResponse || !isset($preferenceResponse['init_point'])) {
            logError("Respuesta inválida de MercadoPago: $response");
            throw new Exception('Respuesta inválida de MercadoPago');
        }
        
        // Actualizar donación con datos de MercadoPago
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE donations 
            SET external_id = ?, gateway_response = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $preferenceResponse['id'],
            json_encode($preferenceResponse),
            $donationId
        ]);
        
        // Log exitoso
        logActivity("Donación MercadoPago creada - ID: $transactionId, Monto: $amount");
        
        // Redirigir al checkout de MercadoPago
        $checkoutUrl = $sandbox ? $preferenceResponse['sandbox_init_point'] : $preferenceResponse['init_point'];
        redirect($checkoutUrl);
        
    } catch (Exception $e) {
        logError("Error en procesamiento MercadoPago: " . $e->getMessage());
        throw $e;
    }
}
?>