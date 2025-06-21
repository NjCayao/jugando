<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?php echo ADMIN_URL; ?>/index.php" class="brand-link">
        <img src="<?php echo SITE_URL; ?>/vendor/adminlte/dist/img/AdminLTELogo.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
        <span class="brand-text font-weight-light"><?php echo getSetting('site_name', 'MiSistema'); ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?php echo SITE_URL; ?>/vendor/adminlte/dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block"><?php echo htmlspecialchars($_SESSION[ADMIN_SESSION_NAME]['username']); ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">

                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && !isset($_GET['page']) ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <!-- SECCIÓN: VENTAS -->
                <li class="nav-header">VENTAS</li>

                <!-- Productos -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/products/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/products/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-box"></i>
                        <p>
                            Productos
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/" class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/products/') !== false && basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                                <i class="fas fa-list nav-icon"></i>
                                <p>Todos los Productos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/?create=1" class="nav-link">
                                <i class="fas fa-plus nav-icon"></i>
                                <p>Agregar Producto</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/products/categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tags nav-icon"></i>
                                <p>Categorías</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Órdenes -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/orders/" class="nav-link">
                        <i class="nav-icon fas fa-shopping-cart"></i>
                        <p>
                            Órdenes
                            <span class="badge badge-info right">Próximo</span>
                        </p>
                    </a>
                </li>

                <!-- Donaciones -->
                <li class="nav-item has-treeview <?php echo strpos($_SERVER['REQUEST_URI'], '/donations/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/donations/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-coffee"></i>
                        <p>
                            Donaciones
                            <i class="right fas fa-angle-left"></i>
                            <?php
                            try {
                                $db = Database::getInstance()->getConnection();
                                $stmt = $db->prepare("SELECT COUNT(*) as pending FROM donations WHERE payment_status = 'pending'");
                                $stmt->execute();
                                $pendingDonations = $stmt->fetch()['pending'];
                                if ($pendingDonations > 0):
                            ?>
                                    <span class="badge badge-warning right"><?php echo $pendingDonations; ?></span>
                            <?php
                                endif;
                            } catch (Exception $e) {
                                // Silenciar error
                            }
                            ?>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/donations/" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todas las Donaciones</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/donations/reports.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Reportes</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- SECCIÓN: USUARIOS Y LICENCIAS -->
                <li class="nav-header">USUARIOS</li>

                <!-- Usuarios -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/users/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/users/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <p>
                            Usuarios
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/index.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todos los Usuarios</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/create.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Nuevo Usuario</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/index.php?status=active" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Usuarios Activos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/users/index.php?verified=unverified" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Sin Verificar</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Licencias -->
                <li class="nav-item has-treeview <?php echo strpos($_SERVER['REQUEST_URI'], '/licenses/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/licenses/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-key"></i>
                        <p>
                            Licencias
                            <i class="right fas fa-angle-left"></i>
                            <?php
                            try {
                                $db = Database::getInstance()->getConnection();
                                $stmt = $db->query("
                                    SELECT COUNT(*) as expiring 
                                    FROM user_licenses 
                                    WHERE is_active = 1 
                                    AND update_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                                ");
                                $expiringCount = $stmt->fetch()['expiring'];
                                if ($expiringCount > 0):
                            ?>
                                    <span class="badge badge-warning right"><?php echo $expiringCount; ?></span>
                            <?php
                                endif;
                            } catch (Exception $e) {
                                // Ignorar error
                            }
                            ?>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/licenses/" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todas las Licencias</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/licenses/expired.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Expiradas
                                    <?php
                                    try {
                                        $stmt = $db->query("
                                            SELECT COUNT(*) as expired 
                                            FROM user_licenses 
                                            WHERE is_active = 1 
                                            AND update_expires_at < NOW()
                                        ");
                                        $expiredCount = $stmt->fetch()['expired'];
                                        if ($expiredCount > 0):
                                    ?>
                                            <span class="badge badge-danger right"><?php echo $expiredCount; ?></span>
                                    <?php
                                        endif;
                                    } catch (Exception $e) {
                                        // Ignorar error
                                    }
                                    ?>
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/licenses/report.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Reportes</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/licenses/bulk-extend.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Extensión Masiva</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Reseñas -->
                <li class="nav-item has-treeview <?php echo strpos($_SERVER['REQUEST_URI'], '/reviews/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/reviews/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-star"></i>
                        <p>
                            Reseñas
                            <i class="right fas fa-angle-left"></i>
                            <?php
                            try {
                                $pendingReviews = $db->query("
                                    SELECT COUNT(*) as pending 
                                    FROM product_reviews 
                                    WHERE is_approved = 0
                                ")->fetch()['pending'];

                                if ($pendingReviews > 0):
                            ?>
                                    <span class="badge badge-warning right"><?php echo $pendingReviews; ?></span>
                            <?php
                                endif;
                            } catch (Exception $e) {
                                // Ignorar error
                            }
                            ?>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/reviews/" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Todas las Reseñas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/reviews/pending.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Pendientes
                                    <?php if (isset($pendingReviews) && $pendingReviews > 0): ?>
                                        <span class="badge badge-warning right"><?php echo $pendingReviews; ?></span>
                                    <?php endif; ?>
                                </p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/reviews/reported.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Reportadas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/reviews/settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Configuración</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- SECCIÓN: SISTEMA -->
                <li class="nav-header">SISTEMA</li>

                <!-- Actualizaciones -->
                <li class="nav-item has-treeview <?php echo strpos($_SERVER['REQUEST_URI'], '/updates/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/updates/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-sync-alt"></i>
                        <p>
                            Actualizaciones
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/updates/" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/updates/notifications.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Notificaciones</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/updates/settings.php" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Configuración</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- Reportes -->
                <li class="nav-item">
                    <a href="<?php echo ADMIN_URL; ?>/pages/reports/" class="nav-link">
                        <i class="nav-icon fas fa-chart-pie"></i>
                        <p>
                            Reportes
                            <span class="badge badge-info right">Próximo</span>
                        </p>
                    </a>
                </li>

                <!-- SECCIÓN: CONTENIDO -->
                <li class="nav-header">CONTENIDO</li>

                <!-- Contenido -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/content/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/content/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <p>
                            Contenido
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/pages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pages.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file nav-icon"></i>
                                <p>Páginas</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/menus.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'menus.php' ? 'active' : ''; ?>">
                                <i class="fas fa-bars nav-icon"></i>
                                <p>Menús</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/content/banners.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'banners.php' ? 'active' : ''; ?>">
                                <i class="fas fa-images nav-icon"></i>
                                <p>Banners</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- SECCIÓN: CONFIGURACIÓN -->
                <li class="nav-header">CONFIGURACIÓN</li>

                <!-- Configuración -->
                <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/config/') !== false ? 'menu-open' : ''; ?>">
                    <a href="#" class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/config/') !== false ? 'active' : ''; ?>">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>
                            Configuración
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/general.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'general.php' ? 'active' : ''; ?>">
                                <i class="fas fa-cog nav-icon"></i>
                                <p>General</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/payments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                                <i class="fas fa-credit-card nav-icon"></i>
                                <p>Pagos</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/email.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'email.php' ? 'active' : ''; ?>">
                                <i class="fas fa-envelope nav-icon"></i>
                                <p>Email</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo ADMIN_URL; ?>/pages/config/seo.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'seo.php' ? 'active' : ''; ?>">
                                <i class="fas fa-search nav-icon"></i>
                                <p>SEO</p>
                            </a>
                        </li>
                    </ul>
                </li>

                <!-- SECCIÓN: HERRAMIENTAS -->
                <li class="nav-header">HERRAMIENTAS</li>

                <!-- Ver Sitio -->
                <li class="nav-item">
                    <a href="<?php echo SITE_URL; ?>" class="nav-link" target="_blank">
                        <i class="nav-icon fas fa-external-link-alt"></i>
                        <p>Ver Sitio Web</p>
                    </a>
                </li>

                <!-- Limpiar Cache -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="clearCache()">
                        <i class="nav-icon fas fa-broom"></i>
                        <p>Limpiar Cache</p>
                    </a>
                </li>

                <!-- Backup -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="generateBackup()">
                        <i class="nav-icon fas fa-download"></i>
                        <p>Generar Backup</p>
                    </a>
                </li>

                <!-- SECCIÓN: DESARROLLO -->
                <li class="nav-header">DESARROLLO</li>

                <!-- Progreso del desarrollo -->
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showProgress()">
                        <i class="nav-icon fas fa-tasks"></i>
                        <p>
                            Progreso del Proyecto
                            <span class="badge badge-success right">83%</span>
                        </p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>

<!-- Scripts adicionales para funcionalidades del sidebar -->
<script>
    function clearCache() {
        if (confirm('¿Limpiar cache del sistema?')) {
            toastr.info('Funcionalidad próximamente disponible');
        }
    }

    function generateBackup() {
        if (confirm('¿Generar backup de la base de datos?')) {
            toastr.info('Funcionalidad próximamente disponible');
        }
    }

    function showProgress() {
        const progressModal = `
        <div class="modal fade" id="progressModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">
                            <i class="fas fa-tasks"></i> Progreso del Proyecto MiSistema
                        </h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="progress-group">
                            <span class="progress-text">Fase 1: Estructura Base</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 2: Dashboard Admin</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 3: Frontend Público</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 4: Sistema de Pagos</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 5: Funcionalidades Avanzadas</span>
                            <span class="float-right"><b>100%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-success" style="width: 100%"></div>
                            </div>
                        </div>
                        
                        <div class="progress-group">
                            <span class="progress-text">Fase 6: Optimización y Lanzamiento</span>
                            <span class="float-right"><b>0%</b></span>
                            <div class="progress progress-sm">
                                <div class="progress-bar bg-secondary" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <hr>
                        <div class="progress-group">
                            <span class="progress-text"><strong>Progreso Total</strong></span>
                            <span class="float-right"><b>83%</b></span>
                            <div class="progress">
                                <div class="progress-bar bg-primary progress-bar-striped" style="width: 83%"></div>
                            </div>
                        </div>
                        
                        <div class="alert alert-success mt-3">
                            <h5><i class="icon fas fa-check"></i> Completado:</h5>
                            <ul>
                                <li>✅ Sistema de Actualizaciones (Fase 5.1)</li>
                                <li>✅ Sistema de Comentarios/Reseñas (Fase 5.2)</li>
                                <li>✅ Sistema de Donaciones (Fase 5.3)</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-info">
                            <h5><i class="icon fas fa-info"></i> Próximo:</h5>
                            Fase 6 - Optimización y Lanzamiento: Testing completo, SEO, rendimiento y documentación.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

        $('#progressModal').remove();
        $('body').append(progressModal);
        $('#progressModal').modal('show');
    }
</script>