<?php
// Crear este archivo como: debug_checkout.php
// Colocarlo en la ra��z del proyecto para hacer pruebas

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'config/functions.php';
require_once 'config/settings.php';
require_once 'config/payments.php';

session_start();

// Colores para output
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[1;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "<pre style='background: #000; color: #fff; padding: 20px; font-size: 14px;'>";
echo "{$blue}=== TEST DE DIAGN�0�7STICO DEL SISTEMA DE CHECKOUT ==={$reset}\n\n";

// 1. Verificar sesi��n
echo "{$yellow}1. VERIFICANDO SESI�0�7N:{$reset}\n";
echo "Session ID: " . session_id() . "\n";
echo "Usuario logueado: " . (isLoggedIn() ? "S�0�1 - ID: " . $_SESSION[SESSION_NAME]['user_id'] : "NO") . "\n\n";

// 2. Simular datos de checkout
echo "{$yellow}2. DATOS DE PRUEBA:{$reset}\n";
$testCustomerData = [
    'first_name' => 'Test',
    'last_name' => 'Usuario',
    'email' => 'test_' . time() . '@example.com',
    'phone' => '999999999',
    'country' => 'PE',
    'create_account' => '1',
    'user_id' => null
];

echo "Email de prueba: {$testCustomerData['email']}\n";
echo "Crear cuenta: " . ($testCustomerData['create_account'] === '1' ? 'S�0�1' : 'NO') . "\n\n";

// 3. Probar creaci��n de usuario
echo "{$yellow}3. PROBANDO CREACI�0�7N DE USUARIO:{$reset}\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar si el email ya existe
    $stmt = $db->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$testCustomerData['email']]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "{$red}�7�1 Usuario ya existe con ID: {$existingUser['id']}{$reset}\n";
        $userId = $existingUser['id'];
    } else {
        echo "�7�7 Email no existe, procediendo a crear usuario...\n";
        
        // Intentar crear usuario
        $userId = PaymentProcessor::createGuestUser($testCustomerData);
        
        if ($userId) {
            echo "{$green}�7�7 Usuario creado con ID: $userId{$reset}\n";
            
            // Verificar que realmente existe
            $stmt = $db->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $newUser = $stmt->fetch();
            
            if ($newUser) {
                echo "{$green}�7�7 Usuario verificado en BD:{$reset}\n";
                echo "  - ID: {$newUser['id']}\n";
                echo "  - Email: {$newUser['email']}\n";
                echo "  - Nombre: {$newUser['first_name']} {$newUser['last_name']}\n";
            } else {
                echo "{$red}�7�1 ERROR: Usuario creado pero no se encuentra en BD{$reset}\n";
            }
        } else {
            echo "{$red}�7�1 ERROR: No se pudo crear usuario{$reset}\n";
        }
    }
} catch (Exception $e) {
    echo "{$red}�7�1 ERROR: " . $e->getMessage() . "{$reset}\n";
    $userId = null;
}

echo "\n";

// 4. Verificar tabla de ��rdenes
echo "{$yellow}4. VERIFICANDO ESTRUCTURA DE TABLA ORDERS:{$reset}\n";

try {
    // Verificar foreign keys
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = 'orders' 
        AND TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $constraints = $stmt->fetchAll();
    
    foreach ($constraints as $constraint) {
        echo "Foreign Key: {$constraint['CONSTRAINT_NAME']}\n";
        echo "  - Columna: {$constraint['COLUMN_NAME']}\n";
        echo "  - Referencia: {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // Verificar si user_id puede ser NULL
    $stmt = $db->query("
        SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_NAME = 'orders' 
        AND COLUMN_NAME = 'user_id'
        AND TABLE_SCHEMA = DATABASE()
    ");
    
    $column = $stmt->fetch();
    echo "\nColumna user_id:\n";
    echo "  - Puede ser NULL: " . ($column['IS_NULLABLE'] === 'YES' ? 'S�0�1' : 'NO') . "\n";
    echo "  - Valor por defecto: " . ($column['COLUMN_DEFAULT'] ?? 'ninguno') . "\n";
    
} catch (Exception $e) {
    echo "{$red}�7�1 ERROR verificando estructura: " . $e->getMessage() . "{$reset}\n";
}

echo "\n";

// 5. Intentar crear orden de prueba
echo "{$yellow}5. INTENTANDO CREAR ORDEN DE PRUEBA:{$reset}\n";

try {
    // Primero con user_id NULL
    echo "Probando insertar orden con user_id = NULL...\n";
    
    $testOrderNumber = 'TEST-' . time();
    
    $stmt = $db->prepare("
        INSERT INTO orders (
            user_id, order_number, total_amount, subtotal, 
            customer_email, customer_name, payment_method, payment_status,
            created_at
        ) VALUES (
            NULL, ?, 10.00, 10.00, ?, ?, 'test', 'pending', NOW()
        )
    ");
    
    $result = $stmt->execute([
        $testOrderNumber,
        $testCustomerData['email'],
        $testCustomerData['first_name'] . ' ' . $testCustomerData['last_name']
    ]);
    
    if ($result) {
        echo "{$green}�7�7 Orden creada con user_id NULL exitosamente{$reset}\n";
        $orderId = $db->lastInsertId();
        
        // Eliminar orden de prueba
        $db->exec("DELETE FROM orders WHERE id = $orderId");
        echo "�7�7 Orden de prueba eliminada\n";
    } else {
        echo "{$red}�7�1 ERROR: No se puede crear orden con user_id NULL{$reset}\n";
    }
    
} catch (Exception $e) {
    echo "{$red}�7�1 ERROR creando orden: " . $e->getMessage() . "{$reset}\n";
}

echo "\n";

// 6. Verificar visibilidad del checkbox
echo "{$yellow}6. VERIFICANDO CHECKBOX DE CREAR CUENTA:{$reset}\n";
echo "Para verificar en el navegador, abre la consola y ejecuta:\n";
echo "{$blue}";
echo "// Verificar si existe el checkbox\n";
echo "document.getElementById('create_account')\n\n";
echo "// Ver su estado\n";
echo "document.getElementById('create_account').checked\n\n";
echo "// Ver si est�� oculto por CSS\n";
echo "window.getComputedStyle(document.getElementById('create_account').parentElement.parentElement).display\n";
echo "{$reset}\n";

// 7. Logs recientes
echo "{$yellow}7. �0�3LTIMAS L�0�1NEAS DE LOGS:{$reset}\n";

$logFiles = ['orders.log', 'users.log', 'errors.log'];
foreach ($logFiles as $logFile) {
    $logPath = __DIR__ . '/logs/' . $logFile;
    if (file_exists($logPath)) {
        echo "\n{$blue}=== $logFile ==={$reset}\n";
        $lines = file($logPath);
        $lastLines = array_slice($lines, -5);
        foreach ($lastLines as $line) {
            echo trim($line) . "\n";
        }
    }
}

echo "\n{$yellow}=== FIN DEL DIAGN�0�7STICO ==={$reset}\n";
echo "</pre>";

// Script SQL para verificar/corregir la estructura
echo "<h3>Script SQL para ejecutar si user_id no puede ser NULL:</h3>";
echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
echo "-- Verificar la restricci��n actual
SHOW CREATE TABLE orders;

-- Si user_id no puede ser NULL, ejecutar:
ALTER TABLE orders MODIFY COLUMN user_id INT(11) DEFAULT NULL;

-- Verificar que el cambio se aplic��
DESCRIBE orders;
</pre>";
?>