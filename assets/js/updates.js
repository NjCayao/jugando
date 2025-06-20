// assets/js/updates.js - Sistema de Actualizaciones

/**
 * Clase para manejar las actualizaciones de productos
 */
class UpdateManager {
    constructor() {
        this.checkingUpdates = false;
        this.updateCache = {};
    }

    /**
     * Verificar actualizaciones para un producto
     */
    async checkUpdates(productId, showLoader = true) {
        if (this.checkingUpdates) return;
        
        this.checkingUpdates = true;
        
        if (showLoader) {
            this.showLoader();
        }

        try {
            const response = await fetch(`${SITE_URL}/api/updates/check_updates.php?product_id=${productId}`);
            const data = await response.json();

            if (data.success) {
                this.updateCache[productId] = data.data;
                this.displayUpdateInfo(productId, data.data);
            } else {
                this.showError(data.message || 'Error al verificar actualizaciones');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showError('Error de conexión al verificar actualizaciones');
        } finally {
            this.checkingUpdates = false;
            this.hideLoader();
        }
    }

    /**
     * Mostrar información de actualizaciones
     */
    displayUpdateInfo(productId, updateData) {
        const container = document.getElementById(`update-info-${productId}`);
        if (!container) return;

        // Limpiar contenedor
        container.innerHTML = '';

        if (!updateData.has_updates) {
            container.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Tienes la última versión instalada (v${updateData.current_version})
                </div>
            `;
            return;
        }

        // Verificar estado de licencia
        if (updateData.license_info.update_expired) {
            container.innerHTML = `
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Licencia de actualizaciones expirada</h5>
                    <p>Tu licencia de actualizaciones expiró el ${this.formatDate(updateData.license_info.update_expires_at)}</p>
                    ${updateData.license_info.can_renew ? 
                        `<a href="${SITE_URL}/renew-license?product=${productId}" class="btn btn-warning btn-sm">
                            <i class="fas fa-sync-alt me-1"></i>Renovar Licencia
                        </a>` : ''
                    }
                </div>
            `;
        }

        // Mostrar actualizaciones disponibles
        let html = `
            <div class="update-summary mb-3">
                <h5>
                    <i class="fas fa-download me-2 text-primary"></i>
                    ${updateData.update_count} actualización(es) disponible(s)
                </h5>
                <p class="text-muted mb-0">
                    Tu versión: <strong>v${updateData.current_version}</strong> | 
                    Última versión: <strong>v${updateData.latest_version}</strong>
                </p>
            </div>
        `;

        // Lista de actualizaciones
        html += '<div class="update-list">';
        
        updateData.updates.forEach((update, index) => {
            const canDownload = update.can_download && !updateData.license_info.update_expired;
            
            html += `
                <div class="update-item ${!canDownload ? 'disabled' : ''}" data-version-id="${update.id}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="update-details">
                            <h6 class="mb-1">
                                Versión ${update.version}
                                ${update.is_major_update ? '<span class="badge bg-warning ms-2">Actualización Mayor</span>' : ''}
                            </h6>
                            <small class="text-muted">
                                Lanzada el ${update.release_date} | ${update.file_size}
                            </small>
                            
                            ${update.changelog ? `
                                <div class="changelog-preview mt-2">
                                    <a href="#" onclick="UpdateManager.toggleChangelog(${update.id}); return false;">
                                        <i class="fas fa-list me-1"></i>Ver cambios
                                    </a>
                                    <div id="changelog-${update.id}" class="changelog-content mt-2" style="display: none;">
                                        <div class="card card-body bg-light">
                                            ${this.formatChangelog(update.changelog)}
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                        
                        <div class="update-actions">
                            ${canDownload ? `
                                <button class="btn btn-primary btn-sm" onclick="UpdateManager.downloadUpdate(${update.id})">
                                    <i class="fas fa-download me-1"></i>Descargar
                                </button>
                            ` : `
                                <button class="btn btn-secondary btn-sm" disabled>
                                    <i class="fas fa-lock me-1"></i>No disponible
                                </button>
                            `}
                        </div>
                    </div>
                    
                    ${!canDownload && update.reason ? `
                        <div class="alert alert-warning mt-2 mb-0">
                            <small><i class="fas fa-info-circle me-1"></i>${update.reason}</small>
                        </div>
                    ` : ''}
                </div>
            `;
        });
        
        html += '</div>';

        // Información de licencia
        if (updateData.license_info.days_left !== null && updateData.license_info.days_left > 0) {
            html += `
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Tu licencia de actualizaciones es válida por ${updateData.license_info.days_left} días más
                </div>
            `;
        }

        container.innerHTML = html;
    }

    /**
     * Descargar actualización
     */
    static async downloadUpdate(versionId) {
        if (!confirm('¿Deseas descargar esta actualización?')) {
            return;
        }

        // Mostrar progreso
        const btn = event.target;
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Preparando...';

        try {
            // Redirigir a la descarga
            window.location.href = `${SITE_URL}/api/updates/download_update.php?version_id=${versionId}`;
            
            // Restaurar botón después de un tiempo
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }, 3000);
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error al iniciar la descarga');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    }

    /**
     * Mostrar/ocultar changelog
     */
    static toggleChangelog(updateId) {
        const changelogDiv = document.getElementById(`changelog-${updateId}`);
        if (changelogDiv) {
            changelogDiv.style.display = changelogDiv.style.display === 'none' ? 'block' : 'none';
        }
    }

    /**
     * Formatear changelog
     */
    formatChangelog(changelog) {
        // Convertir saltos de línea en <br>
        let formatted = changelog.replace(/\n/g, '<br>');
        
        // Convertir bullets
        formatted = formatted.replace(/^- /gm, '• ');
        formatted = formatted.replace(/^\* /gm, '• ');
        
        return formatted;
    }

    /**
     * Formatear fecha
     */
    formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    /**
     * Mostrar loader
     */
    showLoader() {
        const loaders = document.querySelectorAll('.update-loader');
        loaders.forEach(loader => loader.style.display = 'block');
    }

    /**
     * Ocultar loader
     */
    hideLoader() {
        const loaders = document.querySelectorAll('.update-loader');
        loaders.forEach(loader => loader.style.display = 'none');
    }

    /**
     * Mostrar error
     */
    showError(message) {
        const containers = document.querySelectorAll('[id^="update-info-"]');
        containers.forEach(container => {
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    ${message}
                </div>
            `;
        });
    }

    /**
     * Verificar actualizaciones automáticamente
     */
    autoCheckUpdates() {
        const updateButtons = document.querySelectorAll('[data-check-updates]');
        updateButtons.forEach(btn => {
            const productId = btn.getAttribute('data-check-updates');
            if (productId) {
                // Verificar al hacer clic
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.checkUpdates(productId);
                });
            }
        });

        // Auto-verificar productos marcados
        const autoCheckProducts = document.querySelectorAll('[data-auto-check-updates]');
        autoCheckProducts.forEach(element => {
            const productId = element.getAttribute('data-auto-check-updates');
            if (productId) {
                // Verificar después de cargar la página
                setTimeout(() => {
                    this.checkUpdates(productId, false);
                }, 1000);
            }
        });
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Crear instancia global
    window.updateManager = new UpdateManager();
    
    // Iniciar verificación automática
    updateManager.autoCheckUpdates();
});

// Exponer métodos estáticos al objeto window para uso inline
window.UpdateManager = UpdateManager;