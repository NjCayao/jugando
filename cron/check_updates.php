<?php
// cron/check_updates.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/email.php';

// Verificar que se ejecuta desde CLI o con token
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'TU_TOKEN_SEGURO')) {
    die('Acceso no autorizado');
}

$log = [];
$log[] = "[" . date('Y-m-d H:i:s') . "] Iniciando verificación de actualizaciones...";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Verificar nuevas versiones para notificar
    $stmt = $db->query("
        SELECT DISTINCT ul.user_id, ul.product_id, ul.id as license_id,
               u.email, u.first_name, u.last_name,
               p.name as product_name,
               pv_current.version as current_version,
               pv_latest.version as latest_version,
               pv_latest.id as latest_version_id,
               pv_latest.release_notes,
               ul.update_expires_at
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        INNER JOIN product_versions pv_current ON p.id = pv_current.product_id AND pv_current.is_current = 1
        INNER JOIN product_versions pv_latest ON p.id = pv_latest.product_id
        WHERE ul.is_active = 1
        AND ul.update_expires_at > NOW()
        AND pv_latest.created_at > IFNULL(ul.last_update_check, '2000-01-01')
        AND pv_latest.created_at > pv_current.created_at
        AND NOT EXISTS (
            SELECT 1 FROM update_notifications un 
            WHERE un.user_id = ul.user_id 
            AND un.product_id = ul.product_id 
            AND un.version_id = pv_latest.id
        )
        ORDER BY ul.user_id, pv_latest.created_at DESC
    ");
    
    $newVersionNotifications = 0;
    $processedUsers = [];
    
    while ($row = $stmt->fetch()) {
        $userId = $row['user_id'];
        $productId = $row['product_id'];
        $versionId = $row['latest_version_id'];
        
        // Evitar duplicados
        $key = "$userId-$productId-$versionId";
        if (in_array($key, $processedUsers)) continue;
        $processedUsers[] = $key;
        
        // Crear notificación
        $notifStmt = $db->prepare("
            INSERT INTO update_notifications (user_id, product_id, version_id, notification_type)
            VALUES (?, ?, ?, 'new_version')
        ");
        $notifStmt->execute([$userId, $productId, $versionId]);
        
        // Enviar email
        $downloadUrl = SITE_URL . "/api/updates/download_update.php?version_id=" . $versionId;
        
        EmailSystem::sendTemplateEmail($row['email'], 'update_available', [
            '{USER_NAME}' => $row['first_name'],
            '{PRODUCT_NAME}' => $row['product_name'],
            '{VERSION}' => $row['latest_version'],
            '{CURRENT_VERSION}' => $row['current_version'],
            '{RELEASE_NOTES}' => $row['release_notes'] ?: 'Mejoras y correcciones',
            '{DOWNLOAD_URL}' => $downloadUrl,
            '{UPDATE_EXPIRES}' => formatDate($row['update_expires_at'])
        ]);
        
        $newVersionNotifications++;
        $log[] = "Notificación enviada: Usuario {$row['email']} - {$row['product_name']} v{$row['latest_version']}";
    }
    
    // 2. Notificar licencias por expirar
    $daysBeforeNotify = intval(Settings::get('update_notification_days_before', '30'));
    
    $stmt = $db->query("
        SELECT ul.*, u.email, u.first_name, p.name as product_name, p.slug as product_slug
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        WHERE ul.is_active = 1
        AND ul.update_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL $daysBeforeNotify DAY)
        AND NOT EXISTS (
            SELECT 1 FROM update_notifications un 
            WHERE un.user_id = ul.user_id 
            AND un.product_id = ul.product_id 
            AND un.notification_type = 'update_expiring'
            AND un.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
    ");
    
    $expiringNotifications = 0;
    
    while ($row = $stmt->fetch()) {
        // Crear notificación
        $notifStmt = $db->prepare("
            INSERT INTO update_notifications (user_id, product_id, version_id, notification_type)
            VALUES (?, ?, NULL, 'update_expiring')
        ");
        $notifStmt->execute([$row['user_id'], $row['product_id']]);
        
        // Calcular días restantes
        $daysLeft = ceil((strtotime($row['update_expires_at']) - time()) / 86400);
        $discount = Settings::get('update_renewal_discount', '20');
        $renewalUrl = SITE_URL . "/renew-license?product=" . $row['product_id'];
        
        EmailSystem::sendTemplateEmail($row['email'], 'update_expiring', [
            '{USER_NAME}' => $row['first_name'],
            '{PRODUCT_NAME}' => $row['product_name'],
            '{DAYS_LEFT}' => $daysLeft,
            '{EXPIRY_DATE}' => formatDate($row['update_expires_at']),
            '{DISCOUNT}' => $discount,
            '{RENEWAL_URL}' => $renewalUrl
        ]);
        
        $expiringNotifications++;
        $log[] = "Notificación de expiración: Usuario {$row['email']} - {$row['product_name']} - {$daysLeft} días";
    }
    
    // 3. Notificar licencias recién expiradas
    $stmt = $db->query("
        SELECT ul.*, u.email, u.first_name, p.name as product_name
        FROM user_licenses ul
        INNER JOIN users u ON ul.user_id = u.id
        INNER JOIN products p ON ul.product_id = p.id
        WHERE ul.is_active = 1
        AND ul.update_expires_at < NOW()
        AND ul.update_expires_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND NOT EXISTS (
            SELECT 1 FROM update_notifications un 
            WHERE un.user_id = ul.user_id 
            AND un.product_id = ul.product_id 
            AND un.notification_type = 'update_expired'
        )
    ");
    
    $expiredNotifications = 0;
    
    while ($row = $stmt->fetch()) {
        // Crear notificación
        $notifStmt = $db->prepare("
            INSERT INTO update_notifications (user_id, product_id, version_id, notification_type)
            VALUES (?, ?, NULL, 'update_expired')
        ");
        $notifStmt->execute([$row['user_id'], $row['product_id']]);
        
        $renewalUrl = SITE_URL . "/renew-license?product=" . $row['product_id'];
        
        EmailSystem::sendTemplateEmail($row['email'], 'update_expired', [
            '{USER_NAME}' => $row['first_name'],
            '{PRODUCT_NAME}' => $row['product_name'],
            '{RENEWAL_URL}' => $renewalUrl
        ]);
        
        $expiredNotifications++;
        $log[] = "Notificación de expiración: Usuario {$row['email']} - {$row['product_name']}";
    }
    
    // 4. Actualizar última verificación en todas las licencias activas
    $db->exec("UPDATE user_licenses SET last_update_check = NOW() WHERE is_active = 1");
    
    // 5. Marcar notificaciones como enviadas
    $db->exec("UPDATE update_notifications SET is_sent = 1, sent_at = NOW() WHERE is_sent = 0");
    
    // Resumen
    $log[] = "----------------------------------------";
    $log[] = "Resumen:";
    $log[] = "- Notificaciones de nueva versión: $newVersionNotifications";
    $log[] = "- Notificaciones de expiración próxima: $expiringNotifications";
    $log[] = "- Notificaciones de licencia expirada: $expiredNotifications";
    $log[] = "Total: " . ($newVersionNotifications + $expiringNotifications + $expiredNotifications);
    $log[] = "[" . date('Y-m-d H:i:s') . "] Proceso completado";
    
} catch (Exception $e) {
    $log[] = "[ERROR] " . $e->getMessage();
    logError("Error en cron check_updates: " . $e->getMessage());
}

// Guardar log
$logContent = implode(PHP_EOL, $log) . PHP_EOL . PHP_EOL;
file_put_contents(__DIR__ . '/../logs/cron_updates.log', $logContent, FILE_APPEND);

// Si se ejecuta desde navegador, mostrar resultado
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    echo $logContent;
}