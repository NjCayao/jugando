<?php
// api/updates/process_renewal.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../config/settings.php';

if (!isLoggedIn()) {
    redirect('/login');
}

$user = getCurrentUser();
$licenseId = intval($_POST['license_id'] ?? 0);
$selectedPlan = intval($_POST['selected_plan'] ?? 0);

if ($licenseId <= 0 || $selectedPlan <= 0) {
    setFlashMessage('error', 'Datos inválidos');
    redirect('/mis-descargas');
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener licencia y producto
    $stmt = $db->prepare("
        SELECT ul.*, p.name as product_name, p.price as base_price
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        WHERE ul.id = ? AND ul.user_id = ?
    ");
    $stmt->execute([$licenseId, $user['id']]);
    $license = $stmt->fetch();
    
    if (!$license) {
        throw new Exception('Licencia no encontrada');
    }
    
    // Calcular precio
    $discount = floatval(Settings::get('update_renewal_discount', '20'));
    $priceFactors = [
        3 => 0.25,
        6 => 0.45,
        12 => 0.8,
        24 => 1.5
    ];
    
    if (!isset($priceFactors[$selectedPlan])) {
        throw new Exception('Plan no válido');
    }
    
    $baseAmount = $license['base_price'] * $priceFactors[$selectedPlan];
    $finalAmount = $baseAmount * (1 - $discount/100);
    
    // Guardar en sesión para el checkout
    $_SESSION['renewal_checkout'] = [
        'license_id' => $licenseId,
        'product_id' => $license['product_id'],
        'product_name' => $license['product_name'],
        'months' => $selectedPlan,
        'amount' => $finalAmount,
        'base_amount' => $baseAmount,
        'discount' => $discount,
        'current_expiry' => $license['update_expires_at']
    ];
    
    // Redirigir al checkout con parámetros especiales
    redirect('/checkout?renewal=1');
    
} catch (Exception $e) {
    setFlashMessage('error', $e->getMessage());
    redirect('/renew-license?license=' . $licenseId);
}