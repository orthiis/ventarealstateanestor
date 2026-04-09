<?php
// en-construccion.php - Página En Construcción
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Verificar si el usuario está logueado
requireLogin();

// Obtener usuario actual
$currentUser = getCurrentUser();

// Título de la página
$pageTitle = __('construction.page_title', [], 'Página En Construcción');

// Incluir header y sidebar
include 'header.php';
include 'sidebar.php';
?>

<style>
.construction-container {
    min-height: calc(100vh - 70px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

.construction-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><rect width="60" height="60" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2"/></svg>');
    animation: pattern-move 20s linear infinite;
}

@keyframes pattern-move {
    0% { transform: translateX(0) translateY(0); }
    100% { transform: translateX(60px) translateY(60px); }
}

.construction-content {
    position: relative;
    z-index: 2;
    max-width: 900px;
    width: 100%;
    text-align: center;
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Animación del casco */
.construction-helmet {
    width: 180px;
    height: 180px;
    margin: 0 auto 30px;
    position: relative;
    animation: helmet-bounce 2s ease-in-out infinite;
}

@keyframes helmet-bounce {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-15px) rotate(5deg); }
}

.construction-helmet i {
    font-size: 150px;
    color: #fbbf24;
    filter: drop-shadow(0 10px 30px rgba(0,0,0,0.3));
}

.construction-title {
    font-size: 48px;
    font-weight: 900;
    color: white;
    margin: 20px 0;
    text-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.construction-subtitle {
    font-size: 20px;
    color: rgba(255,255,255,0.95);
    margin-bottom: 40px;
    line-height: 1.6;
    font-weight: 500;
}

.construction-card {
    background: white;
    border-radius: 24px;
    padding: 50px;
    box-shadow: 0 25px 70px rgba(0,0,0,0.3);
    margin-top: 40px;
    position: relative;
    overflow: hidden;
}

.construction-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, transparent, #667eea, transparent);
    animation: progress-line 2s ease-in-out infinite;
}

@keyframes progress-line {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* Barra de progreso */
.construction-progress {
    margin-bottom: 40px;
}

.construction-progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    font-weight: 600;
    color: #374151;
    font-size: 15px;
}

.construction-progress-bar {
    height: 12px;
    background: #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.construction-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    width: 0%;
    animation: progress-fill 3s ease-out forwards;
    position: relative;
    overflow: hidden;
}

@keyframes progress-fill {
    to { width: 83%; }
}

.construction-progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255,255,255,0.4),
        transparent
    );
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Features en desarrollo */
.construction-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 40px 0;
}

.construction-feature {
    background: #f9fafb;
    padding: 25px;
    border-radius: 16px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.construction-feature::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(102,126,234,0.1), transparent);
    transition: left 0.5s ease;
}

.construction-feature:hover::before {
    left: 100%;
}

.construction-feature:hover {
    border-color: #667eea;
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(102,126,234,0.3);
}

.construction-feature-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    color: white;
    font-size: 28px;
}

.construction-feature-title {
    font-weight: 700;
    font-size: 16px;
    color: #1f2937;
    margin-bottom: 8px;
}

.construction-feature-desc {
    font-size: 13px;
    color: #6b7280;
    line-height: 1.5;
}

.construction-feature-status {
    display: inline-block;
    margin-top: 10px;
    padding: 4px 12px;
    background: #ede9fe;
    color: #7c3aed;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Timeline de desarrollo */
.construction-timeline {
    text-align: left;
    margin: 40px 0;
    padding: 30px;
    background: #f9fafb;
    border-radius: 16px;
}

.construction-timeline-title {
    font-size: 20px;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 25px;
    text-align: center;
}

.construction-timeline-item {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    position: relative;
}

.construction-timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 19px;
    top: 40px;
    bottom: -20px;
    width: 2px;
    background: #e5e7eb;
}

.construction-timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    z-index: 1;
}

.construction-timeline-icon.completed {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.construction-timeline-icon.in-progress {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    animation: pulse-icon 2s ease-in-out infinite;
}

@keyframes pulse-icon {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(102,126,234,0.7); }
    50% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(102,126,234,0); }
}

.construction-timeline-icon.pending {
    background: #e5e7eb;
    color: #9ca3af;
}

.construction-timeline-content {
    flex: 1;
    padding-top: 8px;
}

.construction-timeline-label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.construction-timeline-text {
    font-size: 13px;
    color: #6b7280;
}

/* Botones de acción */
.construction-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 40px;
}

.construction-btn {
    padding: 14px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 15px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: none;
    cursor: pointer;
}

.construction-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
}

.construction-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102,126,234,0.6);
}

.construction-btn-secondary {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.construction-btn-secondary:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
}

/* Footer info */
.construction-footer {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #e5e7eb;
}

.construction-contact {
    background: #ede9fe;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.construction-contact-title {
    font-weight: 700;
    color: #5b21b6;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.construction-contact-text {
    color: #6d28d9;
    font-size: 14px;
}

.construction-info {
    color: #6b7280;
    font-size: 13px;
}

/* Responsive */
@media (max-width: 768px) {
    .construction-helmet {
        width: 120px;
        height: 120px;
    }
    
    .construction-helmet i {
        font-size: 100px;
    }
    
    .construction-title {
        font-size: 36px;
    }
    
    .construction-subtitle {
        font-size: 16px;
    }
    
    .construction-card {
        padding: 30px 20px;
    }
    
    .construction-features {
        grid-template-columns: 1fr;
    }
    
    .construction-actions {
        flex-direction: column;
    }
    
    .construction-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Herramientas flotantes */
.construction-tools {
    position: absolute;
    font-size: 40px;
    color: rgba(255,255,255,0.1);
    animation: tool-float 20s linear infinite;
}

@keyframes tool-float {
    0% {
        transform: translateY(100vh) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 1;
    }
    90% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100vh) rotate(360deg);
        opacity: 0;
    }
}
</style>

<div class="construction-container">
    <!-- Herramientas flotantes -->
    <i class="fas fa-wrench construction-tools" style="left: 10%; animation-delay: 0s;"></i>
    <i class="fas fa-hammer construction-tools" style="left: 25%; animation-delay: 3s;"></i>
    <i class="fas fa-screwdriver construction-tools" style="left: 40%; animation-delay: 6s;"></i>
    <i class="fas fa-hard-hat construction-tools" style="left: 55%; animation-delay: 9s;"></i>
    <i class="fas fa-ruler construction-tools" style="left: 70%; animation-delay: 12s;"></i>
    <i class="fas fa-paint-roller construction-tools" style="left: 85%; animation-delay: 15s;"></i>

    <div class="construction-content">
        <!-- Icono del casco -->
        <div class="construction-helmet">
            <i class="fas fa-hard-hat"></i>
        </div>

        <!-- Título y subtítulo -->
        <h1 class="construction-title">
            <?php echo __('construction.title', [], '🚧 En Construcción'); ?>
        </h1>
        <p class="construction-subtitle">
            <?php echo __('construction.subtitle', [], 'Estamos trabajando arduamente para traerte nuevas funcionalidades. Esta página estará lista muy pronto.'); ?>
        </p>

        <!-- Card principal -->
        <div class="construction-card">
            <!-- Barra de progreso -->
            <div class="construction-progress">
                <div class="construction-progress-label">
                    <span><?php echo __('construction.progress', [], 'Progreso de Desarrollo'); ?></span>
                    <span style="color: #667eea;">83%</span>
                </div>
                <div class="construction-progress-bar">
                    <div class="construction-progress-fill"></div>
                </div>
            </div>

            <!-- Características en desarrollo -->
            <div class="construction-features">
                <div class="construction-feature">
                    <div class="construction-feature-icon">
                        <i class="fas fa-code"></i>
                    </div>
                    <div class="construction-feature-title">
                        <?php echo __('construction.feature1_title', [], 'Backend Optimizado'); ?>
                    </div>
                    <div class="construction-feature-desc">
                        <?php echo __('construction.feature1_desc', [], 'Desarrollando una arquitectura robusta y escalable'); ?>
                    </div>
                    <span class="construction-feature-status">
                        <?php echo __('construction.in_progress', [], 'En Progreso'); ?>
                    </span>
                </div>

                <div class="construction-feature">
                    <div class="construction-feature-icon">
                        <i class="fas fa-paint-brush"></i>
                    </div>
                    <div class="construction-feature-title">
                        <?php echo __('construction.feature2_title', [], 'Diseño UI/UX'); ?>
                    </div>
                    <div class="construction-feature-desc">
                        <?php echo __('construction.feature2_desc', [], 'Creando una interfaz intuitiva y moderna'); ?>
                    </div>
                    <span class="construction-feature-status">
                        <?php echo __('construction.in_progress', [], 'En Progreso'); ?>
                    </span>
                </div>

                <div class="construction-feature">
                    <div class="construction-feature-icon">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="construction-feature-title">
                        <?php echo __('construction.feature3_title', [], 'Base de Datos'); ?>
                    </div>
                    <div class="construction-feature-desc">
                        <?php echo __('construction.feature3_desc', [], 'Estructurando información de manera eficiente'); ?>
                    </div>
                    <span class="construction-feature-status">
                        <?php echo __('construction.in_progress', [], 'En Progreso'); ?>
                    </span>
                </div>
            </div>

            <!-- Timeline de desarrollo -->
            <div class="construction-timeline">
                <h3 class="construction-timeline-title">
                    <?php echo __('construction.timeline_title', [], '📋 Hoja de Ruta de Desarrollo'); ?>
                </h3>

                <div class="construction-timeline-item">
                    <div class="construction-timeline-icon completed">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="construction-timeline-content">
                        <div class="construction-timeline-label">
                            <?php echo __('construction.phase1', [], 'Fase 1: Planificación'); ?>
                        </div>
                        <div class="construction-timeline-text">
                            <?php echo __('construction.phase1_desc', [], 'Análisis de requisitos y diseño inicial - Completado'); ?>
                        </div>
                    </div>
                </div>

                <div class="construction-timeline-item">
                    <div class="construction-timeline-icon in-progress">
                        <i class="fas fa-cog fa-spin"></i>
                    </div>
                    <div class="construction-timeline-content">
                        <div class="construction-timeline-label">
                            <?php echo __('construction.phase2', [], 'Fase 2: Desarrollo'); ?>
                        </div>
                        <div class="construction-timeline-text">
                            <?php echo __('construction.phase2_desc', [], 'Codificación y pruebas unitarias - En Progreso'); ?>
                        </div>
                    </div>
                </div>

                <div class="construction-timeline-item">
                    <div class="construction-timeline-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="construction-timeline-content">
                        <div class="construction-timeline-label">
                            <?php echo __('construction.phase3', [], 'Fase 3: Pruebas'); ?>
                        </div>
                        <div class="construction-timeline-text">
                            <?php echo __('construction.phase3_desc', [], 'Testing exhaustivo y correcciones - Próximamente'); ?>
                        </div>
                    </div>
                </div>

                <div class="construction-timeline-item">
                    <div class="construction-timeline-icon pending">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="construction-timeline-content">
                        <div class="construction-timeline-label">
                            <?php echo __('construction.phase4', [], 'Fase 4: Lanzamiento'); ?>
                        </div>
                        <div class="construction-timeline-text">
                            <?php echo __('construction.phase4_desc', [], 'Implementación y puesta en producción - Planificado'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="construction-actions">
                <a href="dashboard.php" class="construction-btn construction-btn-primary">
                    <i class="fas fa-home"></i>
                    <?php echo __('construction.go_dashboard', [], 'Volver al Dashboard'); ?>
                </a>
                <button onclick="window.history.back()" class="construction-btn construction-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo __('construction.go_back', [], 'Página Anterior'); ?>
                </button>
            </div>

            <!-- Footer -->
            <div class="construction-footer">
                <div class="construction-contact">
                    <div class="construction-contact-title">
                        <i class="fas fa-bell"></i>
                        <?php echo __('construction.notify_title', [], '¿Quieres que te notifiquemos?'); ?>
                    </div>
                    <p class="construction-contact-text">
                        <?php echo __('construction.notify_text', [], 'Te informaremos cuando esta función esté disponible. Mantente atento a las actualizaciones.'); ?>
                    </p>
                </div>

                <p class="construction-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo __('construction.info', [], 'Mientras tanto, puedes explorar otras funcionalidades del sistema que ya están disponibles.'); ?>
                </p>

                <p class="construction-info" style="margin-top: 15px; font-weight: 600; color: #667eea;">
                    <?php echo SITE_NAME; ?> - <?php echo __('construction.estimated', [], 'Lanzamiento estimado:'); ?> 
                    <span style="color: #374151;">Q1 2026</span>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Registrar visita a página en construcción
    if (typeof gtag !== 'undefined') {
        gtag('event', 'page_view', {
            'page_title': 'Under Construction',
            'page_location': window.location.href,
            'page_path': window.location.pathname
        });
    }

    // Animar porcentaje de progreso
    const progressFill = document.querySelector('.construction-progress-fill');
    if (progressFill) {
        setTimeout(function() {
            progressFill.style.width = '83%';
        }, 500);
    }

    // Efecto de hover en features
    const features = document.querySelectorAll('.construction-feature');
    features.forEach(function(feature, index) {
        feature.style.animationDelay = (index * 0.1) + 's';
    });

    // Animar herramientas flotantes
    const tools = document.querySelectorAll('.construction-tools');
    tools.forEach(function(tool, index) {
        tool.style.left = (Math.random() * 90) + '%';
        tool.style.animationDelay = (index * 3) + 's';
        tool.style.animationDuration = (15 + Math.random() * 10) + 's';
    });
});

// Función para recargar la página (simulando verificar si ya está lista)
function checkPageStatus() {
    console.log('Verificando estado de la página...');
    // Aquí podrías hacer una petición AJAX para verificar si la página ya está lista
}

// Verificar cada 5 minutos si la página está lista
setInterval(checkPageStatus, 300000);
</script>

<?php include 'footer.php'; ?>