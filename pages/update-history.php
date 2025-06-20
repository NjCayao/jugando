<?php
// pages/update-history.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/settings.php';

// Verificar autenticación
if (!isLoggedIn()) {
    redirect('/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user = getCurrentUser();
$success = getFlashMessage('success');
$error = getFlashMessage('error');

// Parámetros
$productId = intval($_GET['product'] ?? 0);
$page = intval($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener productos del usuario con licencias activas
    $stmt = $db->prepare("
        SELECT DISTINCT p.id, p.name, p.slug
        FROM user_licenses ul
        INNER JOIN products p ON ul.product_id = p.id
        WHERE ul.user_id = ? AND ul.is_active = 1
        ORDER BY p.name
    ");
    $stmt->execute([$user['id']]);
    $userProducts = $stmt->fetchAll();
    
    // Construir query para historial
    $whereConditions = ["ud.user_id = ?"];
    $params = [$user['id']];
    
    if ($productId > 0) {
        $whereConditions[] = "p.id = ?";
        $params[] = $productId;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Obtener historial de actualizaciones
    $stmt = $db->prepare("
        SELECT ud.*, p.name as product_name, p.slug as product_slug,
               pv.version, pv.changelog, pv.release_notes, pv.is_major_update,
               pv_prev.version as previous_version,
               ul.update_expires_at
        FROM update_downloads ud
        INNER JOIN user_licenses ul ON ud.license_id = ul.id
        INNER JOIN products p ON ul.product_id = p.id
        INNER JOIN product_versions pv ON ud.version_id = pv.id
        LEFT JOIN product_versions pv_prev ON pv_prev.product_id = p.id 
            AND pv_prev.version = ud.previous_version
        WHERE $whereClause
        ORDER BY ud.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $updates = $stmt->fetchAll();
    
    // Contar total
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM update_downloads ud
        INNER JOIN user_licenses ul ON ud.license_id = ul.id
        INNER JOIN products p ON ul.product_id = p.id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalUpdates = $countStmt->fetch()['total'];
    $totalPages = ceil($totalUpdates / $perPage);
    
    // Estadísticas
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT ud.id) as total_updates,
            COUNT(DISTINCT ud.version_id) as unique_versions,
            COUNT(DISTINCT ul.product_id) as products_updated,
            COUNT(CASE WHEN ud.download_status = 'completed' THEN 1 END) as successful_updates
        FROM update_downloads ud
        INNER JOIN user_licenses ul ON ud.license_id = ul.id
        WHERE ud.user_id = ?
    ");
    $statsStmt->execute([$user['id']]);
    $stats = $statsStmt->fetch();
    
} catch (Exception $e) {
    logError("Error en historial de actualizaciones: " . $e->getMessage());
    $updates = [];
    $userProducts = [];
    $stats = ['total_updates' => 0, 'unique_versions' => 0, 'products_updated' => 0, 'successful_updates' => 0];
}

$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Actualizaciones - <?php echo htmlspecialchars($siteName); ?></title>
    
    <meta name="description" content="Historial de actualizaciones de tus productos">
    <meta name="robots" content="noindex, follow">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <style>
        .update-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .update-timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .update-item {
            position: relative;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .update-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .update-dot {
            position: absolute;
            left: -26px;
            top: 2rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid #007bff;
        }
        
        .update-dot.success {
            border-color: #28a745;
        }
        
        .update-dot.failed {
            border-color: #dc3545;
        }
        
        .update-dot.major {
            width: 20px;
            height: 20px;
            left: -28px;
            border-width: 4px;
            border-color: #ffc107;
        }
        
        .version-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .version-badge.old {
            background: #f8d7da;
            color: #721c24;
        }
        
        .version-badge.new {
            background: #d4edda;
            color: #155724;
        }
        
        .version-badge.major {
            background: #fff3cd;
            color: #856404;
        }
        
        .changelog-box {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 0 5px 5px 0;
        }
        
        .filter-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .filter-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .filter-badge.active {
            background: #007bff;
            color: white;
        }
        
        .stats-mini {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .stat-mini {
            text-align: center;
        }
        
        .stat-mini-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #007bff;
        }
        
        .stat-mini-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <!-- Hero Section -->
    <section class="hero-gradient py-5">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb bg-transparent p-0">
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/dashboard" class="text-white-50">Dashboard</a></li>
                    <li class="breadcrumb-item active text-white">Historial de Actualizaciones</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold text-white mb-3">
                        <i class="fas fa-history me-3"></i>Historial de Actualizaciones
                    </h1>
                    <p class="lead text-white-50">
                        Registro completo de todas las actualizaciones descargadas
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="<?php echo SITE_URL; ?>/mis-descargas" class="btn btn-light btn-lg">
                        <i class="fas fa-download me-2"></i>Mis Descargas
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <div class="container my-5">
        <!-- Mensajes -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Estadísticas -->
        <div class="stats-mini">
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['total_updates']); ?></div>
                <div class="stat-mini-label">Total Actualizaciones</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['unique_versions']); ?></div>
                <div class="stat-mini-label">Versiones Únicas</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['products_updated']); ?></div>
                <div class="stat-mini-label">Productos Actualizados</div>
            </div>
            <div class="stat-mini">
                <div class="stat-mini-value"><?php echo number_format($stats['successful_updates']); ?></div>
                <div class="stat-mini-label">Exitosas</div>
            </div>
        </div>
        
        <!-- Filtros -->
        <?php if (!empty($userProducts)): ?>
            <div class="mb-4">
                <h5 class="mb-3">Filtrar por producto:</h5>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo SITE_URL; ?>/update-history" 
                       class="filter-badge <?php echo $productId == 0 ? 'active' : 'btn-outline-primary'; ?>">
                        Todos los productos
                    </a>
                    <?php foreach ($userProducts as $product): ?>
                        <a href="?product=<?php echo $product['id']; ?>" 
                           class="filter-badge <?php echo $productId == $product['id'] ? 'active' : 'btn-outline-primary'; ?>">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Timeline de actualizaciones -->
        <?php if (empty($updates)): ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-4x text-muted mb-3"></i>
                <h4>No hay actualizaciones en tu historial</h4>
                <p class="text-muted">Cuando descargues actualizaciones de tus productos, aparecerán aquí</p>
                <a href="<?php echo SITE_URL; ?>/mis-descargas" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>Ver mis productos
                </a>
            </div>
        <?php else: ?>
            <div class="update-timeline">
                <?php foreach ($updates as $update): ?>
                    <div class="update-item">
                        <div class="update-dot <?php echo $update['download_status']; ?> <?php echo $update['is_major_update'] ? 'major' : ''; ?>"></div>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-2">
                                    <a href="<?php echo SITE_URL; ?>/producto/<?php echo $update['product_slug']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($update['product_name']); ?>
                                    </a>
                                </h5>
                                
                                <div class="d-flex gap-2 mb-3">
                                    <?php if ($update['previous_version']): ?>
                                        <span class="version-badge old">
                                            v<?php echo htmlspecialchars($update['previous_version']); ?>
                                        </span>
                                        <i class="fas fa-arrow-right align-self-center text-muted"></i>
                                    <?php endif; ?>
                                    
                                    <span class="version-badge <?php echo $update['is_major_update'] ? 'major' : 'new'; ?>">
                                        v<?php echo htmlspecialchars($update['version']); ?>
                                        <?php if ($update['is_major_update']): ?>
                                            <i class="fas fa-star ms-1"></i>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <?php if ($update['changelog'] || $update['release_notes']): ?>
                                    <div class="changelog-box">
                                        <h6 class="mb-2">
                                            <i class="fas fa-list-ul me-2"></i>Cambios en esta versión:
                                        </h6>
                                        <div class="changelog-content">
                                            <?php echo nl2br(htmlspecialchars($update['release_notes'] ?: $update['changelog'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 text-md-end">
                                <div class="mb-2">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo formatDateTime($update['created_at'], 'd/m/Y H:i'); ?>
                                </div>
                                
                                <?php if ($update['download_status'] == 'completed'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i>Descarga exitosa
                                    </span>
                                <?php elseif ($update['download_status'] == 'failed'): ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times me-1"></i>Error en descarga
                                    </span>
                                    <?php if ($update['error_message']): ?>
                                        <div class="mt-2">
                                            <small class="text-danger">
                                                <?php echo htmlspecialchars($update['error_message']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-spinner me-1"></i>En progreso
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($update['ip_address']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            IP: <?php echo htmlspecialchars($update['ip_address']); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Paginación" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>