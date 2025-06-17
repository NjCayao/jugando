<?php
// pages/download_error.php - Página de error de descarga
$siteName = getSetting('site_name', 'MiSistema');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Descarga - <?php echo htmlspecialchars($siteName); ?></title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <div class="text-danger mb-4">
                            <i class="fas fa-exclamation-triangle fa-4x"></i>
                        </div>
                        
                        <h2 class="text-danger mb-3">Error de Descarga</h2>
                        
                        <p class="lead mb-4">
                            <?php echo htmlspecialchars($errorMessage ?? 'Error desconocido'); ?>
                        </p>
                        
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>¿Qué puedes hacer?</h6>
                            <ul class="text-start mb-0">
                                <li>Verifica que el enlace sea correcto</li>
                                <li>Asegúrate de haber completado la compra</li>
                                <li>Revisa tu email de confirmación</li>
                                <li>Contacta a soporte si el problema persiste</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <a href="<?php echo SITE_URL; ?>/productos" class="btn btn-primary me-3">
                                <i class="fas fa-arrow-left me-2"></i>Volver a Productos
                            </a>
                            
                            <a href="<?php echo SITE_URL; ?>/contacto" class="btn btn-outline-secondary">
                                <i class="fas fa-life-ring me-2"></i>Contactar Soporte
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>