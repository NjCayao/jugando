<?php
// /includes/components/review_form.php
// Componente para mostrar formulario de reseña
// Requiere: $productId, $canReview (de verify_purchase)

if (!isset($productId)) {
    return;
}

$minLength = Settings::get('reviews_min_length', '20');
$maxLength = Settings::get('reviews_max_length', '1000');
?>

<div id="reviewFormContainer" class="review-form-container" style="display: none;">
    <div class="dashboard-section">
        <div class="section-header-compact">
            <h4 class="section-title-compact mb-0">
                <i class="fas fa-star me-2"></i>Escribe tu Reseña
            </h4>
        </div>
        <div class="section-body-compact">
            <form id="reviewForm" class="review-form">
                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                
                <!-- Rating Stars -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Tu Calificación</label>
                    <div class="rating-input">
                        <div class="stars-container">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star-rating" data-rating="<?php echo $i; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="0" required>
                        <div class="rating-text mt-2">
                            <span id="ratingText" class="text-muted">Haz clic en las estrellas para calificar</span>
                        </div>
                    </div>
                </div>
                
                <!-- Comment -->
                <div class="mb-4">
                    <label for="reviewComment" class="form-label fw-bold">Tu Comentario</label>
                    <textarea 
                        class="form-control" 
                        id="reviewComment" 
                        name="comment" 
                        rows="5" 
                        placeholder="Comparte tu experiencia con este producto..."
                        minlength="<?php echo $minLength; ?>"
                        maxlength="<?php echo $maxLength; ?>"
                        required
                    ></textarea>
                    <div class="form-text">
                        <span id="charCount">0</span> / <?php echo $maxLength; ?> caracteres (mínimo <?php echo $minLength; ?>)
                    </div>
                </div>
                
                <!-- Show Name -->
                <div class="mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="showName" name="show_name" value="1" checked>
                        <label class="form-check-label" for="showName">
                            Mostrar mi nombre en la reseña
                        </label>
                    </div>
                    <small class="text-muted">Si no marcas esta opción, tu reseña aparecerá como "Usuario Anónimo"</small>
                </div>
                
                <!-- Guidelines -->
                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading">
                        <i class="fas fa-info-circle me-2"></i>Guías para escribir una buena reseña:
                    </h6>
                    <ul class="mb-0 small">
                        <li>Sé honesto y objetivo en tu opinión</li>
                        <li>Describe tu experiencia con el producto</li>
                        <li>Menciona aspectos positivos y negativos</li>
                        <li>Evita lenguaje ofensivo o spam</li>
                    </ul>
                </div>
                
                <!-- Submit -->
                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-secondary" onclick="hideReviewForm()">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitReviewBtn">
                        <i class="fas fa-paper-plane me-2"></i>Enviar Reseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.review-form-container {
    margin: 2rem 0;
}

.rating-input {
    user-select: none;
}

.stars-container {
    font-size: 2rem;
    color: #ddd;
    cursor: pointer;
    display: inline-block;
}

.star-rating {
    transition: color 0.2s;
    margin-right: 5px;
}

.star-rating:hover {
    color: #ffc107;
}

.star-rating.active {
    color: #ffc107;
}

.star-rating.hover {
    color: #ffc107;
}

#reviewComment {
    resize: vertical;
    min-height: 120px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('.star-rating');
    const ratingInput = document.getElementById('ratingInput');
    const ratingText = document.getElementById('ratingText');
    const reviewComment = document.getElementById('reviewComment');
    const charCount = document.getElementById('charCount');
    const form = document.getElementById('reviewForm');
    
    const ratingTexts = {
        1: 'Muy malo',
        2: 'Malo', 
        3: 'Regular',
        4: 'Bueno',
        5: 'Excelente'
    };
    
    // Rating stars interaction
    stars.forEach((star, index) => {
        star.addEventListener('click', function() {
            const rating = index + 1;
            ratingInput.value = rating;
            updateStars(rating);
            ratingText.textContent = ratingTexts[rating];
            ratingText.classList.remove('text-muted');
            ratingText.classList.add('text-warning', 'fw-bold');
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = index + 1;
            updateStarsHover(rating);
        });
    });
    
    document.querySelector('.stars-container').addEventListener('mouseleave', function() {
        updateStars(parseInt(ratingInput.value));
    });
    
    function updateStars(rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
            star.classList.remove('hover');
        });
    }
    
    function updateStarsHover(rating) {
        stars.forEach((star, index) => {
            if (index < rating) {
                star.classList.add('hover');
            } else {
                star.classList.remove('hover');
            }
        });
    }
    
    // Character counter
    reviewComment.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length;
        
        if (length < <?php echo $minLength; ?>) {
            charCount.classList.add('text-danger');
            charCount.classList.remove('text-success');
        } else if (length > <?php echo $maxLength; ?> - 50) {
            charCount.classList.add('text-warning');
            charCount.classList.remove('text-success', 'text-danger');
        } else {
            charCount.classList.add('text-success');
            charCount.classList.remove('text-danger', 'text-warning');
        }
    });
    
    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (ratingInput.value == 0) {
            showCartNotification('Por favor selecciona una calificación', 'error');
            return;
        }
        
        const submitBtn = document.getElementById('submitReviewBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
        
        try {
            const formData = new FormData(form);
            const response = await fetch(window.SITE_URL + '/api/reviews/add_review.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showCartNotification(data.message, 'success');
                form.reset();
                hideReviewForm();
                // Recargar reseñas
                if (typeof loadReviews === 'function') {
                    loadReviews(1);
                }
                // Actualizar botón de reseña
                const reviewBtn = document.querySelector('.btn-write-review');
                if (reviewBtn) {
                    reviewBtn.disabled = true;
                    reviewBtn.innerHTML = '<i class="fas fa-check me-2"></i>Reseña Enviada';
                }
            } else {
                showCartNotification(data.message || 'Error al enviar la reseña', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showCartNotification('Error al enviar la reseña', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });
});

function hideReviewForm() {
    document.getElementById('reviewFormContainer').style.display = 'none';
    document.querySelector('.btn-write-review')?.scrollIntoView({ behavior: 'smooth' });
}
</script>