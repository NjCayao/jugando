<?php
// verificar_licencias.php
require_once 'config/database.php';

$db = Database::getInstance()->getConnection();

echo "<h2>Verificación de Órdenes y Licencias</h2>";

// Últimas 10 órdenes
$stmt = $db->query("
    SELECT 
        o.id,
        o.order_number,
        o.customer_email,
        o.user_id,
        o.payment_status,
        u.email as user_email,
        COUNT(ul.id) as licencias
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN user_licenses ul ON o.id = ul.order_id
    WHERE o.payment_status = 'completed'
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Orden</th><th>Email Orden</th><th>User ID</th><th>Email Usuario</th><th>Licencias</th><th>Estado</th></tr>";

while ($row = $stmt->fetch()) {
    $estado = "❓";
    if (!$row['user_id']) {
        $estado = "❌ Sin user_id";
    } elseif ($row['licencias'] == 0) {
        $estado = "⚠️ Sin licencias";
    } else {
        $estado = "✅ OK";
    }
    
    echo "<tr>";
    echo "<td>{$row['order_number']}</td>";
    echo "<td>{$row['customer_email']}</td>";
    echo "<td>" . ($row['user_id'] ?: 'NULL') . "</td>";
    echo "<td>{$row['user_email']}</td>";
    echo "<td>{$row['licencias']}</td>";
    echo "<td>$estado</td>";
    echo "</tr>";
}
echo "</table>";
?>