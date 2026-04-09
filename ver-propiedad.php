<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();

// Obtener ID de la propiedad
$propertyId = $_GET['id'] ?? null;

if (!$propertyId) {
    setFlashMessage('error', __('properties.not_found', [], 'Propiedad no encontrada'));
    redirect('propiedades.php');
}

// Obtener datos de la propiedad
$property = db()->selectOne(
    "SELECT p.*, 
     pt.name as property_type_name,
     pt.icon as property_type_icon,
     CONCAT(u.first_name, ' ', u.last_name) as agent_name,
     u.email as agent_email,
     u.phone as agent_phone,
     u.profile_picture as agent_picture,
     o.name as office_name,
     o.address as office_address,
     o.phone as office_phone
     FROM properties p
     LEFT JOIN property_types pt ON p.property_type_id = pt.id
     LEFT JOIN users u ON p.agent_id = u.id
     LEFT JOIN offices o ON p.office_id = o.id
     WHERE p.id = ?",
    [$propertyId]
);

if (!$property) {
    setFlashMessage('error', __('properties.not_found', [], 'Propiedad no encontrada'));
    redirect('propiedades.php');
}

// Verificar permisos de visualización
if ($currentUser['role']['name'] !== 'administrador' && $property['agent_id'] != $currentUser['id']) {
    setFlashMessage('error', __('no_permission', [], 'No tienes permisos para ver esta propiedad'));
    redirect('propiedades.php');
}

$pageTitle = $property['reference'] . ' - ' . $property['title'];

// Obtener imágenes
$images = db()->select(
    "SELECT * FROM property_images WHERE property_id = ? ORDER BY is_main DESC, display_order",
    [$propertyId]
);

// Obtener características
$features = db()->select(
    "SELECT f.* FROM features f
     INNER JOIN property_features pf ON f.id = pf.feature_id
     WHERE pf.property_id = ?
     ORDER BY f.category, f.name",
    [$propertyId]
);

// Agrupar características por categoría
$featuresByCategory = [];
foreach ($features as $feature) {
    $category = $feature['category'] ?? __('others', [], 'Otras');
    $featuresByCategory[$category][] = $feature;
}

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary: #667eea;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
}

.property-view-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* ============ HEADER ============ */
.property-header {
    background: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.property-title {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 10px 0;
}

.property-reference {
    font-size: 14px;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.property-price {
    font-size: 36px;
    font-weight: 700;
    color: var(--primary);
    margin: 15px 0;
}

.property-price small {
    font-size: 16px;
    color: #718096;
    font-weight: 500;
}

/* ============ GALLERY ============ */
.gallery-section {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.main-image-container {
    position: relative;
    height: 500px;
    overflow: hidden;
    cursor: pointer;
}

.main-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.main-image:hover {
    transform: scale(1.05);
}

.image-counter {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    backdrop-filter: blur(10px);
}

.fullscreen-btn {
    position: absolute;
    bottom: 20px;
    right: 20px;
    background: rgba(0,0,0,0.7);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    backdrop-filter: blur(10px);
}

.fullscreen-btn:hover {
    background: rgba(0,0,0,0.9);
    transform: scale(1.05);
}

.thumbnails-container {
    padding: 20px;
    display: flex;
    gap: 15px;
    overflow-x: auto;
    background: #f9fafb;
}

.thumbnails-container::-webkit-scrollbar {
    height: 8px;
}

.thumbnails-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.thumbnails-container::-webkit-scrollbar-thumb {
    background: #cbd5e0;
    border-radius: 10px;
}

.thumbnail {
    flex-shrink: 0;
    width: 120px;
    height: 80px;
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.3s;
}

.thumbnail.active {
    border-color: var(--primary);
    transform: scale(1.05);
}

.thumbnail:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* ============ LIGHTBOX ============ */
.lightbox {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.95);
    z-index: 9999;
    display: none;
    justify-content: center;
    align-items: center;
}

.lightbox.active {
    display: flex;
}

.lightbox-content {
    position: relative;
    max-width: 90%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.lightbox-image {
    max-width: 100%;
    max-height: 80vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

.lightbox-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.9);
    border: none;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lightbox-nav:hover {
    background: white;
    transform: translateY(-50%) scale(1.1);
}

.lightbox-prev {
    left: 30px;
}

.lightbox-next {
    right: 30px;
}

.lightbox-close {
    position: absolute;
    top: 30px;
    right: 30px;
    background: rgba(255,255,255,0.9);
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    transition: all 0.3s;
}

.lightbox-close:hover {
    background: white;
    transform: scale(1.1) rotate(90deg);
}

.lightbox-thumbnails {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    overflow-x: auto;
    max-width: 90vw;
    padding: 10px;
}

.lightbox-thumbnail {
    flex-shrink: 0;
    width: 80px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    border: 3px solid transparent;
    transition: all 0.3s;
    opacity: 0.6;
}

.lightbox-thumbnail.active {
    border-color: white;
    opacity: 1;
    transform: scale(1.1);
}

.lightbox-thumbnail:hover {
    opacity: 1;
}

.lightbox-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* ============ INFO CARDS ============ */
.info-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f3f4f6;
}

.info-card-header i {
    font-size: 24px;
    color: var(--primary);
}

.info-card-header h5 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #2d3748;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-label {
    font-size: 13px;
    color: #718096;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 16px;
    color: #2d3748;
    font-weight: 600;
}

.info-value.highlight {
    color: var(--primary);
    font-size: 18px;
}

/* ============ STATS ============ */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-box {
    background: white;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.stat-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 15px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.stat-box.purple .stat-icon { background: #f3e8ff; color: #8b5cf6; }
.stat-box.blue .stat-icon { background: #dbeafe; color: #3b82f6; }
.stat-box.green .stat-icon { background: #d1fae5; color: #10b981; }
.stat-box.orange .stat-icon { background: #fed7aa; color: #f59e0b; }

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #718096;
    font-weight: 600;
}

/* ============ FEATURES ============ */
.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.feature-category {
    background: #f9fafb;
    border-radius: 12px;
    padding: 20px;
}

.feature-category h6 {
    font-size: 16px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e7eb;
}

.feature-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #4b5563;
    font-size: 14px;
}

.feature-item i {
    color: var(--success);
    font-size: 16px;
}

/* ============ MAP ============ */
#propertyMap {
    height: 400px;
    border-radius: 12px;
    overflow: hidden;
}

/* ============ AGENT CARD ============ */
.agent-card {
    display: flex;
    gap: 20px;
    align-items: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 25px;
    color: white;
}

.agent-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.agent-info h6 {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 10px 0;
}

.agent-contact {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
}

.agent-contact a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.agent-contact a:hover {
    opacity: 0.8;
}

/* ============ BADGES ============ */
.badge-modern {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.badge-available { background: #d1fae5; color: #065f46; }
.badge-sold { background: #e5e7eb; color: #374151; }
.badge-rented { background: #dbeafe; color: #1e40af; }
.badge-reserved { background: #fef3c7; color: #92400e; }
.badge-draft { background: #f3f4f6; color: #6b7280; }
.badge-retired { background: #fee2e2; color: #991b1b; }

/* ============ BUTTONS ============ */
.btn-modern {
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
    color: white;
}

.btn-primary-modern:hover {
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    transform: translateY(-2px);
    color: white;
}

.btn-secondary-modern {
    background: white;
    border: 2px solid #e2e8f0;
    color: #4a5568;
}

.btn-secondary-modern:hover {
    border-color: #cbd5e0;
    background: #f7fafc;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    .property-view-container {
        padding: 15px;
    }
    
    .main-image-container {
        height: 300px;
    }
    
    .property-title {
        font-size: 24px;
    }
    
    .property-price {
        font-size: 28px;
    }
    
    .lightbox-nav {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    
    .lightbox-prev {
        left: 10px;
    }
    
    .lightbox-next {
        right: 10px;
    }
    
    .agent-card {
        flex-direction: column;
        text-align: center;
    }
}

/* ============ ANIMATIONS ============ */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-card, .stat-box {
    animation: fadeIn 0.5s ease;
}
</style>

<div class="property-view-container">
    
    <!-- Header -->
    <div class="property-header">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="flex-grow-1">
                <div class="property-reference">
                    <i class="fas fa-hashtag"></i> <?php echo $property['reference']; ?>
                </div>
                <h1 class="property-title"><?php echo htmlspecialchars($property['title']); ?></h1>
                <div class="property-price">
                    <?php echo $property['currency']; ?> <?php echo number_format($property['price'], 2); ?>
                    <small>
                        / <?php echo __('properties.operation_' . $property['operation_type'], [], ucfirst($property['operation_type'])); ?>
                    </small>
                </div>
            </div>
            <div class="d-flex flex-column gap-2 align-items-end">
                <span class="badge-modern badge-<?php 
                    echo $property['status'] === 'available' ? 'available' : 
                        ($property['status'] === 'sold' ? 'sold' : 
                        ($property['status'] === 'rented' ? 'rented' : 
                        ($property['status'] === 'reserved' ? 'reserved' : 
                        ($property['status'] === 'draft' ? 'draft' : 'retired')))); 
                ?>">
                    <i class="fas fa-circle" style="font-size: 8px;"></i>
                    <?php echo __('properties.status_' . $property['status'], [], ucfirst($property['status'])); ?>
                </span>
                <div class="d-flex gap-2">
                    <a href="editar-propiedad.php?id=<?php echo $propertyId; ?>" class="btn-modern btn-primary-modern">
                        <i class="fas fa-edit"></i> <?php echo __('edit', [], 'Editar'); ?>
                    </a>
                    <a href="propiedades.php" class="btn-modern btn-secondary-modern">
                        <i class="fas fa-arrow-left"></i> <?php echo __('back', [], 'Volver'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="dashboard.php"><?php echo __('home', [], 'Inicio'); ?></a></li>
                <li class="breadcrumb-item"><a href="propiedades.php"><?php echo __('properties.title', [], 'Propiedades'); ?></a></li>
                <li class="breadcrumb-item active"><?php echo $property['reference']; ?></li>
            </ol>
        </nav>
    </div>

    <!-- Gallery -->
    <?php if (!empty($images)): ?>
    <div class="gallery-section">
        <div class="main-image-container" onclick="openLightbox(0)">
            <img src="<?php echo $images[0]['image_url']; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" class="main-image" id="mainImage">
            <div class="image-counter">
                <i class="fas fa-images"></i> <span id="currentImageNumber">1</span> / <?php echo count($images); ?>
            </div>
            <button class="fullscreen-btn">
                <i class="fas fa-expand"></i> <?php echo __('properties.view_fullscreen', [], 'View in full screen'); ?>
            </button>
        </div>
        
        <div class="thumbnails-container">
            <?php foreach ($images as $index => $image): ?>
            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeMainImage(<?php echo $index; ?>)">
                <img src="<?php echo $image['image_url']; ?>" alt="Imagen <?php echo $index + 1; ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Row -->
    <div class="stats-row">
        <?php if ($property['bedrooms']): ?>
        <div class="stat-box purple">
            <div class="stat-icon">
                <i class="fas fa-bed"></i>
            </div>
            <div class="stat-value"><?php echo $property['bedrooms']; ?></div>
            <div class="stat-label"><?php echo __('properties.bedrooms', [], 'Habitaciones'); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($property['bathrooms']): ?>
        <div class="stat-box blue">
            <div class="stat-icon">
                <i class="fas fa-bath"></i>
            </div>
            <div class="stat-value"><?php echo $property['bathrooms']; ?></div>
            <div class="stat-label"><?php echo __('properties.bathrooms', [], 'Baños'); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($property['built_area']): ?>
        <div class="stat-box green">
            <div class="stat-icon">
                <i class="fas fa-ruler-combined"></i>
            </div>
            <div class="stat-value"><?php echo number_format($property['built_area'], 0); ?> m²</div>
            <div class="stat-label"><?php echo __('properties.built_area', [], 'Sup. Construida'); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if ($property['garage_spaces']): ?>
        <div class="stat-box orange">
            <div class="stat-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="stat-value"><?php echo $property['garage_spaces']; ?></div>
            <div class="stat-label"><?php echo __('properties.garage_spaces', [], 'Plazas Garaje'); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Basic Information -->
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-info-circle"></i>
            <h5><?php echo __('properties.basic_info', [], 'Información Básica'); ?></h5>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.property_type', [], 'Tipo de Propiedad'); ?></div>
                <div class="info-value">
                    <i class="fas <?php echo $property['property_type_icon'] ?? 'fa-home'; ?> me-2"></i>
                    <?php echo $property['property_type_name']; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.operation', [], 'Operación'); ?></div>
                <div class="info-value"><?php echo __('properties.operation_' . $property['operation_type'], [], ucfirst($property['operation_type'])); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('price', [], 'Precio'); ?></div>
                <div class="info-value highlight">
                    <?php echo $property['currency']; ?> <?php echo number_format($property['price'], 2); ?>
                </div>
            </div>
            
            <?php if ($property['year_built']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.year_built', [], 'Año de Construcción'); ?></div>
                <div class="info-value"><?php echo $property['year_built']; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['conservation_status']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.conservation_status', [], 'Estado'); ?></div>
                <div class="info-value"><?php echo $property['conservation_status']; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['orientation']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.orientation', [], 'Orientación'); ?></div>
                <div class="info-value"><?php echo __('properties.' . $property['orientation'], [], ucfirst($property['orientation'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Description -->
    <?php if ($property['description']): ?>
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-align-left"></i>
            <h5><?php echo __('description', [], 'Descripción'); ?></h5>
        </div>
        <div class="property-description">
            <?php echo $property['description']; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Details -->
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-list-ul"></i>
            <h5><?php echo __('properties.details', [], 'Detalles'); ?></h5>
        </div>
        
        <div class="info-grid">
            <?php if ($property['useful_area']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.useful_area', [], 'Superficie Útil'); ?></div>
                <div class="info-value"><?php echo number_format($property['useful_area'], 2); ?> m²</div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['built_area']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.built_area', [], 'Superficie Construida'); ?></div>
                <div class="info-value"><?php echo number_format($property['built_area'], 2); ?> m²</div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['plot_area']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.plot_area', [], 'Superficie Parcela'); ?></div>
                <div class="info-value"><?php echo number_format($property['plot_area'], 2); ?> m²</div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['floor_number']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.floor', [], 'Planta'); ?></div>
                <div class="info-value"><?php echo $property['floor_number']; ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['total_floors']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.building_height', [], 'Altura Edificio'); ?></div>
                <div class="info-value"><?php echo $property['total_floors']; ?> <?php echo __('properties.floors', [], 'plantas'); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['energy_certificate']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.energy_certificate', [], 'Certificado Energético'); ?></div>
                <div class="info-value"><?php echo $property['energy_certificate']; ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.garage', [], 'Garaje'); ?></div>
                <div class="info-value">
                    <?php if ($property['garage']): ?>
                        <i class="fas fa-check-circle text-success me-2"></i><?php echo __('yes', [], 'Sí'); ?>
                        <?php if ($property['garage_spaces']): ?>
                            (<?php echo $property['garage_spaces']; ?> <?php echo __('properties.spaces', [], 'plazas'); ?>)
                        <?php endif; ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-danger me-2"></i><?php echo __('no', [], 'No'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.elevator', [], 'Ascensor'); ?></div>
                <div class="info-value">
                    <?php if ($property['elevator']): ?>
                        <i class="fas fa-check-circle text-success me-2"></i><?php echo __('yes', [], 'Sí'); ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-danger me-2"></i><?php echo __('no', [], 'No'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.furnished', [], 'Amueblado'); ?></div>
                <div class="info-value">
                    <?php if ($property['furnished']): ?>
                        <i class="fas fa-check-circle text-success me-2"></i><?php echo __('yes', [], 'Sí'); ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-danger me-2"></i><?php echo __('no', [], 'No'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Features -->
    <?php if (!empty($featuresByCategory)): ?>
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-star"></i>
            <h5><?php echo __('properties.features', [], 'Características'); ?></h5>
        </div>
        
        <div class="features-grid">
            <?php foreach ($featuresByCategory as $category => $categoryFeatures): ?>
            <div class="feature-category">
                <h6><?php echo $category; ?></h6>
                <div class="feature-list">
                    <?php foreach ($categoryFeatures as $feature): ?>
                    <div class="feature-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $feature['name']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Location -->
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-map-marker-alt"></i>
            <h5><?php echo __('properties.location', [], 'Ubicación'); ?></h5>
        </div>
        
        <div class="info-grid mb-4">
            <div class="info-item">
                <div class="info-label"><?php echo __('address', [], 'Dirección'); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($property['address']); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('city', [], 'Ciudad'); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($property['city']); ?></div>
            </div>
            
            <?php if ($property['zone']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('zone', [], 'Zona'); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($property['zone']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($property['postal_code']): ?>
            <div class="info-item">
                <div class="info-label"><?php echo __('postal_code', [], 'Código Postal'); ?></div>
                <div class="info-value"><?php echo htmlspecialchars($property['postal_code']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($property['latitude'] && $property['longitude']): ?>
        <div id="propertyMap"></div>
        <?php endif; ?>
    </div>

    <!-- Videos & Virtual Tours -->
    <?php if ($property['video_url'] || $property['virtual_tour_url']): ?>
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-video"></i>
            <h5><?php echo __('properties.videos_tours', [], 'Videos y Tours Virtuales'); ?></h5>
        </div>
        
        <div class="row g-3">
            <?php if ($property['video_url']): ?>
            <div class="col-md-6">
                <div class="info-label mb-2"><?php echo __('properties.video', [], 'Video'); ?></div>
                <a href="<?php echo $property['video_url']; ?>" target="_blank" class="btn-modern btn-primary-modern">
                    <i class="fab fa-youtube"></i> <?php echo __('properties.watch_video', [], 'Ver Video'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($property['virtual_tour_url']): ?>
            <div class="col-md-6">
                <div class="info-label mb-2"><?php echo __('properties.virtual_tour', [], 'Tour Virtual'); ?></div>
                <a href="<?php echo $property['virtual_tour_url']; ?>" target="_blank" class="btn-modern btn-primary-modern">
                    <i class="fas fa-street-view"></i> <?php echo __('properties.start_tour', [], 'Iniciar Tour 360°'); ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Agent Information -->
    <?php if ($property['agent_name']): ?>
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-user-tie"></i>
            <h5><?php echo __('properties.agent_info', [], 'Información del Agente'); ?></h5>
        </div>
        
        <div class="agent-card">
            <img src="<?php echo $property['agent_picture'] ?: 'assets/images/default-avatar.png'; ?>" 
                 alt="<?php echo htmlspecialchars($property['agent_name']); ?>" 
                 class="agent-avatar">
            <div class="agent-info flex-grow-1">
                <h6><?php echo htmlspecialchars($property['agent_name']); ?></h6>
                <p class="mb-0"><?php echo __('properties.responsible_agent', [], 'Agente Responsable'); ?></p>
                
                <div class="agent-contact">
                    <?php if ($property['agent_email']): ?>
                    <a href="mailto:<?php echo $property['agent_email']; ?>">
                        <i class="fas fa-envelope"></i>
                        <?php echo $property['agent_email']; ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($property['agent_phone']): ?>
                    <a href="tel:<?php echo $property['agent_phone']; ?>">
                        <i class="fas fa-phone"></i>
                        <?php echo $property['agent_phone']; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if ($property['office_name']): ?>
        <div class="mt-3 p-3 bg-light rounded">
            <h6 class="mb-2"><i class="fas fa-building me-2"></i><?php echo __('office', [], 'Oficina'); ?>: <?php echo htmlspecialchars($property['office_name']); ?></h6>
            <?php if ($property['office_address']): ?>
            <p class="mb-1 text-muted"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($property['office_address']); ?></p>
            <?php endif; ?>
            <?php if ($property['office_phone']): ?>
            <p class="mb-0 text-muted"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($property['office_phone']); ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Publication Settings -->
    <div class="info-card">
        <div class="info-card-header">
            <i class="fas fa-globe"></i>
            <h5><?php echo __('properties.publication', [], 'Publicación'); ?></h5>
        </div>
        
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.publish_on_website', [], 'Publicar en Web'); ?></div>
                <div class="info-value">
                    <?php if ($property['publish_on_website']): ?>
                        <i class="fas fa-check-circle text-success me-2"></i><?php echo __('yes', [], 'Sí'); ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-danger me-2"></i><?php echo __('no', [], 'No'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.publish_on_homepage', [], 'Publicar en Portada'); ?></div>
                <div class="info-value">
                    <?php if ($property['publish_on_homepage']): ?>
                        <i class="fas fa-check-circle text-success me-2"></i><?php echo __('yes', [], 'Sí'); ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-danger me-2"></i><?php echo __('no', [], 'No'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('properties.featured', [], 'Destacada'); ?></div>
                <div class="info-value">
                    <?php if ($property['featured']): ?>
                        <i class="fas fa-check-circle text-success me-2"></i><?php echo __('yes', [], 'Sí'); ?>
                    <?php else: ?>
                        <i class="fas fa-times-circle text-danger me-2"></i><?php echo __('no', [], 'No'); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('created_at', [], 'Fecha de Creación'); ?></div>
                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($property['created_at'])); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label"><?php echo __('updated_at', [], 'Última Actualización'); ?></div>
                <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($property['updated_at'])); ?></div>
            </div>
        </div>
        
        <?php if ($property['internal_notes']): ?>
        <div class="mt-3 p-3 bg-light rounded">
            <h6 class="mb-2"><i class="fas fa-sticky-note me-2"></i><?php echo __('properties.internal_notes', [], 'Notas Internas'); ?></h6>
            <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($property['internal_notes'])); ?></p>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" onclick="closeLightbox()">
        <i class="fas fa-times"></i>
    </button>
    
    <button class="lightbox-nav lightbox-prev" onclick="changeLightboxImage(-1)">
        <i class="fas fa-chevron-left"></i>
    </button>
    
    <div class="lightbox-content">
        <img src="" alt="" class="lightbox-image" id="lightboxImage">
        
        <div class="lightbox-thumbnails" id="lightboxThumbnails">
            <?php foreach ($images as $index => $image): ?>
            <div class="lightbox-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" onclick="setLightboxImage(<?php echo $index; ?>)">
                <img src="<?php echo $image['image_url']; ?>" alt="Imagen <?php echo $index + 1; ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <button class="lightbox-nav lightbox-next" onclick="changeLightboxImage(1)">
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<script>
// ============ GALLERY ============
const images = <?php echo json_encode(array_column($images, 'image_url')); ?>;
let currentImageIndex = 0;

function changeMainImage(index) {
    currentImageIndex = index;
    document.getElementById('mainImage').src = images[index];
    document.getElementById('currentImageNumber').textContent = index + 1;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === index);
    });
}

// ============ LIGHTBOX ============
let lightboxIndex = 0;

function openLightbox(index) {
    lightboxIndex = index;
    const lightbox = document.getElementById('lightbox');
    const lightboxImage = document.getElementById('lightboxImage');
    
    lightboxImage.src = images[index];
    lightbox.classList.add('active');
    
    updateLightboxThumbnails();
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeLightbox() {
    const lightbox = document.getElementById('lightbox');
    lightbox.classList.remove('active');
    document.body.style.overflow = '';
}

function changeLightboxImage(direction) {
    lightboxIndex += direction;
    
    if (lightboxIndex < 0) {
        lightboxIndex = images.length - 1;
    } else if (lightboxIndex >= images.length) {
        lightboxIndex = 0;
    }
    
    document.getElementById('lightboxImage').src = images[lightboxIndex];
    updateLightboxThumbnails();
}

function setLightboxImage(index) {
    lightboxIndex = index;
    document.getElementById('lightboxImage').src = images[index];
    updateLightboxThumbnails();
}

function updateLightboxThumbnails() {
    document.querySelectorAll('.lightbox-thumbnail').forEach((thumb, i) => {
        thumb.classList.toggle('active', i === lightboxIndex);
    });
}

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    const lightbox = document.getElementById('lightbox');
    if (lightbox.classList.contains('active')) {
        if (e.key === 'Escape') {
            closeLightbox();
        } else if (e.key === 'ArrowLeft') {
            changeLightboxImage(-1);
        } else if (e.key === 'ArrowRight') {
            changeLightboxImage(1);
        }
    }
});

// Close lightbox on background click
document.getElementById('lightbox').addEventListener('click', (e) => {
    if (e.target.id === 'lightbox') {
        closeLightbox();
    }
});

// ============ MAP ============
<?php if ($property['latitude'] && $property['longitude']): ?>
const leafletCSS = document.createElement('link');
leafletCSS.rel = 'stylesheet';
leafletCSS.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
document.head.appendChild(leafletCSS);

const leafletJS = document.createElement('script');
leafletJS.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
leafletJS.onload = function() {
    const map = L.map('propertyMap').setView([<?php echo $property['latitude']; ?>, <?php echo $property['longitude']; ?>], 15);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    L.marker([<?php echo $property['latitude']; ?>, <?php echo $property['longitude']; ?>]).addTo(map)
        .bindPopup('<strong><?php echo htmlspecialchars($property['title']); ?></strong><br><?php echo htmlspecialchars($property['address']); ?>')
        .openPopup();
};
document.head.appendChild(leafletJS);
<?php endif; ?>

console.log('✅ Ver propiedad cargado correctamente');
</script>

<?php include 'footer.php'; ?>