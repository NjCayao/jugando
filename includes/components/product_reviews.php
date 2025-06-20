<?php
// /includes/components/product_reviews.php
// Widget completo de reseñas para la página de producto
// Requiere: $productId, $productName

if (!isset($productId)) {
    return;
}
?>

<div class="product-reviews-section">
    <!-- Header con estadísticas -->
    <div class="dashboard-section mb-4">
        <div class="section-header-compact">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="section-title-compact mb-0">
                    <i class="fas fa-star me-2"></i>Reseñas de Clientes
                </h3>
                <div class="review-actions">
                    <?php if (isLoggedIn()): ?>
                        <button class="btn btn-primary btn-write-review" onclick="checkAndShowReviewForm()">
                            <i class="fas fa-edit me-2"></i>Escribir Reseña
                        </button>
                    <?php else: ?>
                        <a href="<?php echo SITE_URL; ?>/login?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Inicia sesión para comentar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="section-body-compact">
            <div class="row">
                <div class="col-md-4">
                    <div class="rating-summary text-center">
                        <div class="avg-rating-large">
                            <span id="avgRating">0.0</span>
                        </div>
                        <div class="rating-stars-display">
                            <div id="avgStars"></div>
                        </div>
                        <div class="text-muted">
                            <span id="totalReviews">0</span> reseñas
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="rating-distribution">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="rating-bar-item">
                                <span class="star-label"><?php echo $i; ?> <i class="fas fa-star text-warning"></i></span>
                                <div class="progress flex-grow-1 mx-3">
                                    <div class="progress-bar bg-warning" id="bar-<?php echo $i; ?>" style="width: 0%"></div>
                                </div>
                                <span class="count-label" id="count-<?php echo $i; ?>">0</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Formulario de reseña (oculto por defecto) -->
    <div id="reviewFormWrapper"></div>
    
    <!-- Filtros y ordenamiento -->
    <div class="review-filters mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <label class="text-muted me-2">Ordenar por:</label>
                <select class="form-select form-select-sm d-inline-block w-auto" id="reviewSort">
                    <option value="recent">Más recientes</option>
                    <option value="helpful">Más útiles</option>
                    <option value="rating_high">Mayor calificación</option>
                    <option value="rating_low">Menor calificación</option>
                </select>
            </div>
            <div class="text-muted">
                Mostrando <span id="showingCount">0</span> de <span id="totalCount">0</span> reseñas
            </div>
        </div>
    </div>
    
    <!-- Lista de reseñas -->
    <div id="reviewsList" class="reviews-list">
        <div class="text-center py-5">
            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
            <p class="text-muted mt-2">Cargando reseñas...</p>
        </div>
    </div>
    
    <!-- Paginación -->
    <div id="reviewsPagination" class="d-flex justify-content-center mt-4"></div>
</div>

<style>
.product-reviews-section {
    margin-top: 3rem;
}

.avg-rating-large {
    font-size: 3rem;
    font-weight: bold;
    color: #333;
    line-height: 1;
}

.rating-stars-display {
    margin: 10px 0;
}

.rating-distribution {
    padding: 0 20px;
}

.rating-bar-item {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.star-label {
    width: 40px;
    text-align: right;
    font-size: 14px;
}

.count-label {
    width: 30px;
    text-align: right;
    font-size: 14px;
    color: #6c757d;
}

.review-item {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    position: relative;
}

.review-item.featured {
    border: 2px solid #ffc107;
    background: #fffef5;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
}

.review-author {
    font-weight: 600;
    color: #333;
}

.review-date {
    font-size: 0.875rem;
    color: #6c757d;
}

.review-rating {
    color: #ffc107;
    margin-bottom: 10px;
}

.review-comment {
    line-height: 1.6;
    color: #333;
}

.review-helpful {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 20px;
}

.helpful-text {
    color: #6c757d;
    font-size: 0.875rem;
}

.helpful-buttons {
    display: flex;
    gap: 10px;
}

.btn-helpful {
    padding: 4px 12px;
    font-size: 0.875rem;
    border-radius: 20px;
}

.btn-helpful.active {
    background-color: #28a745;
    color: white;
    border-color: #28a745;
}

.admin-response {
    background: #e8f5e9;
    border-left: 3px solid #28a745;
    padding: 15px;
    margin-top: 15px;
    border-radius: 5px;
}

.admin-response-header {
    font-weight: 600;
    color: #28a745;
    margin-bottom: 5px;
    font-size: 0.875rem;
}

.featured-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ffc107;
    color: #333;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}

.empty-reviews {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.empty-reviews i {
    font-size: 4rem;
    color: #dee2e6;
    margin-bottom: 20px;
}
</style>

<script>
let currentPage = 1;
let currentSort = 'recent';
let productId = <?php echo $productId; ?>;

// Cargar reseñas al iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadReviews(1);
    
    // Event listener para ordenamiento
    document.getElementById('reviewSort').addEventListener('change', function() {
        currentSort = this.value;
        loadReviews(1);
    });
});

async function loadReviews(page) {
    currentPage = page;
    
    try {
        const response = await fetch(`${window.SITE_URL}/api/reviews/get_reviews.php?product_id=${productId}&page=${page}&sort=${currentSort}`);
        const data = await response.json();
        
        if (data.success) {
            // Actualizar estadísticas
            updateStats(data.stats);
            
            // Mostrar reseñas
            displayReviews(data.reviews);
            
            // Actualizar paginación
            updatePagination(data.pagination);
            
            // Actualizar contadores
            document.getElementById('showingCount').textContent = data.reviews.length;
            document.getElementById('totalCount').textContent = data.pagination.total_reviews;
        }
    } catch (error) {
        console.error('Error cargando reseñas:', error);
        document.getElementById('reviewsList').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error al cargar las reseñas. Por favor, intenta de nuevo.
            </div>
        `;
    }
}

function updateStats(stats) {
    // Rating promedio
    document.getElementById('avgRating').textContent = stats.avg_rating || '0.0';
    document.getElementById('totalReviews').textContent = stats.total_reviews;
    
    // Estrellas promedio
    const avgStars = document.getElementById('avgStars');
    avgStars.innerHTML = '';
    const fullStars = Math.floor(stats.avg_rating);
    const hasHalfStar = (stats.avg_rating % 1) >= 0.5;
    
    for (let i = 0; i < fullStars; i++) {
        avgStars.innerHTML += '<i class="fas fa-star text-warning"></i>';
    }
    if (hasHalfStar) {
        avgStars.innerHTML += '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    for (let i = fullStars + (hasHalfStar ? 1 : 0); i < 5; i++) {
        avgStars.innerHTML += '<i class="far fa-star text-warning"></i>';
    }
    
    // Distribución
    const total = stats.total_reviews || 1;
    for (let i = 5; i >= 1; i--) {
        const count = stats.distribution[i] || 0;
        const percentage = (count / total) * 100;
        document.getElementById(`bar-${i}`).style.width = percentage + '%';
        document.getElementById(`count-${i}`).textContent = count;
    }
}

function displayReviews(reviews) {
    const container = document.getElementById('reviewsList');
    
    if (reviews.length === 0) {
        container.innerHTML = `
            <div class="empty-reviews">
                <i class="fas fa-comments"></i>
                <h5>No hay reseñas aún</h5>
                <p class="text-muted">Sé el primero en compartir tu experiencia con este producto</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = reviews.map(review => `
        <div class="review-item ${review.is_featured ? 'featured' : ''}">
            ${review.is_featured ? '<span class="featured-badge">DESTACADA</span>' : ''}
            
            <div class="review-header">
                <div>
                    <div class="review-author">${review.author}</div>
                    <div class="review-date">${review.time_ago}</div>
                </div>
                <div class="review-rating">
                    ${generateStars(review.rating)}
                </div>
            </div>
            
            <div class="review-comment">
                ${escapeHtml(review.comment)}
            </div>
            
            ${review.admin_response ? `
                <div class="admin-response">
                    <div class="admin-response-header">
                        <i class="fas fa-reply me-1"></i>
                        Respuesta de ${review.admin_response.author}
                    </div>
                    <div>${escapeHtml(review.admin_response.text)}</div>
                </div>
            ` : ''}
            
            <div class="review-helpful">
                <span class="helpful-text">¿Te resultó útil esta reseña?</span>
                <div class="helpful-buttons">
                    <button class="btn btn-sm btn-outline-success btn-helpful ${review.user_vote === 'helpful' ? 'active' : ''}" 
                            onclick="voteReview(${review.id}, 'helpful')"
                            data-review-id="${review.id}">
                        <i class="fas fa-thumbs-up me-1"></i>
                        Sí (${review.helpful_count})
                    </button>
                    <button class="btn btn-sm btn-outline-secondary btn-helpful ${review.user_vote === 'not_helpful' ? 'active' : ''}" 
                            onclick="voteReview(${review.id}, 'not_helpful')"
                            data-review-id="${review.id}">
                        <i class="fas fa-thumbs-down me-1"></i>
                        No (${review.not_helpful_count})
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="fas fa-star"></i>';
        } else {
            stars += '<i class="far fa-star"></i>';
        }
    }
    return stars;
}

function updatePagination(pagination) {
    const container = document.getElementById('reviewsPagination');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<nav><ul class="pagination">';
    
    // Anterior
    if (pagination.current_page > 1) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="loadReviews(${pagination.current_page - 1}); return false;">
                <i class="fas fa-chevron-left"></i>
            </a>
        </li>`;
    }
    
    // Páginas
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else if (i === 1 || i === pagination.total_pages || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="loadReviews(${i}); return false;">${i}</a>
            </li>`;
        } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    
    // Siguiente
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item">
            <a class="page-link" href="#" onclick="loadReviews(${pagination.current_page + 1}); return false;">
                <i class="fas fa-chevron-right"></i>
            </a>
        </li>`;
    }
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

async function checkAndShowReviewForm() {
    try {
        const response = await fetch(`${window.SITE_URL}/api/reviews/verify_purchase.php?product_id=${productId}`);
        const data = await response.json();
        
        if (!data.success) {
            showCartNotification(data.message || 'Error al verificar compra', 'error');
            return;
        }
        
        if (!data.can_review) {
            showCartNotification(data.message, 'warning');
            return;
        }
        
        // Cargar y mostrar formulario
        showReviewForm();
        
    } catch (error) {
        console.error('Error:', error);
        showCartNotification('Error al verificar tu compra', 'error');
    }
}

function showReviewForm() {
    const wrapper = document.getElementById('reviewFormWrapper');
    
    // Cargar formulario si no existe
    if (!wrapper.innerHTML) {
        fetch(`${window.SITE_URL}/includes/components/review_form.php?product_id=${productId}`)
            .then(response => response.text())
            .then(html => {
                wrapper.innerHTML = html;
                document.getElementById('reviewFormContainer').style.display = 'block';
                wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
    } else {
        document.getElementById('reviewFormContainer').style.display = 'block';
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function voteReview(reviewId, voteType) {
    try {
        const formData = new FormData();
        formData.append('review_id', reviewId);
        formData.append('vote_type', voteType);
        
        const response = await fetch(`${window.SITE_URL}/api/reviews/vote_helpful.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Recargar reseñas para actualizar conteos
            loadReviews(currentPage);
        }
    } catch (error) {
        console.error('Error votando:', error);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>