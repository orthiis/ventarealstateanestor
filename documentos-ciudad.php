<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();
$city = $_GET['city'] ?? '';

if (empty($city)) {
    redirect('documentos.php');
}

$pageTitle = __('documents.documents', [], 'Documents') . ' - ' . htmlspecialchars($city);

// Obtener propiedades de esta ciudad con sus documentos E IMAGEN PRINCIPAL
$properties = db()->select("
    SELECT 
        p.id,
        p.reference,
        p.title,
        p.address,
        p.city,
        pi.image_path as main_image,
        COUNT(DISTINCT d.id) as document_count,
        COALESCE(SUM(d.file_size), 0) as total_size,
        MAX(d.created_at) as last_update
    FROM properties p
    LEFT JOIN documents d ON p.id = d.property_id AND d.status = 'active'
    LEFT JOIN property_images pi ON p.id = pi.property_id AND pi.is_main = 1
    WHERE p.city = ?
    GROUP BY p.id, pi.image_path
    ORDER BY p.reference
", [$city]);

// Obtener estadísticas de la ciudad
$cityStats = db()->selectOne("
    SELECT 
        COUNT(DISTINCT property_id) as total_properties,
        COUNT(id) as total_documents,
        COALESCE(SUM(file_size), 0) as total_size
    FROM documents
    WHERE city = ? AND status = 'active'
", [$city]);

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #10b981;
    --danger-color: #ef4444;
    --dark-color: #1f2937;
    --light-bg: #f8fafc;
    --border-radius: 16px;
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.city-view-container {
    padding: 30px;
    background: var(--light-bg);
    min-height: calc(100vh - 80px);
}

/* ============= BREADCRUMB ============= */
.breadcrumb-drive {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    color: #6b7280;
    margin-bottom: 20px;
    flex-wrap: wrap;
    padding: 12px 18px;
    background: #f9fafb;
    border-radius: 10px;
    border: 1px solid #e5e7eb;
}

.breadcrumb-drive a {
    color: var(--primary-color);
    text-decoration: none;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

.breadcrumb-drive a:hover {
    color: var(--secondary-color);
    text-decoration: underline;
}

.breadcrumb-drive i {
    font-size: 12px;
    color: #9ca3af;
}

.breadcrumb-drive span {
    color: var(--dark-color);
    font-weight: 600;
}

/* ============= CITY HEADER ============= */
.city-header {
    background: white;
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.city-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.city-title {
    font-size: 32px;
    font-weight: 800;
    color: var(--dark-color);
    margin: 20px 0 0 0;
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.city-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 32px;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    flex-shrink: 0;
}

.city-title > div {
    flex: 1;
    min-width: 200px;
}

.city-subtitle {
    font-size: 16px;
    color: #6b7280;
    font-weight: 500;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.city-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 25px;
}

.city-stat {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    border: 1px solid #e5e7eb;
    transition: var(--transition);
}

.city-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-color);
}

.city-stat-value {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 5px;
}

.city-stat-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ============= PROPERTIES GRID ============= */
.properties-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

.property-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
}

.property-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: linear-gradient(90deg, var(--success-color), #059669);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
    z-index: 1;
}

.property-card:hover::before {
    transform: scaleX(1);
}

.property-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

/* ============= PROPERTY IMAGE ============= */
.property-card-image {
    width: 100%;
    height: 200px;
    overflow: hidden;
    position: relative;
    background: linear-gradient(135deg, var(--success-color), #059669);
}

.property-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.property-card:hover .property-card-image img {
    transform: scale(1.1);
}

.property-card-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0), rgba(0,0,0,0.4));
    display: flex;
    align-items: center;
    justify-content: center;
}

.property-card-image-overlay i {
    font-size: 64px;
    color: white;
    opacity: 0.5;
}

/* ============= PROPERTY CONTENT ============= */
.property-card-content {
    padding: 25px;
}

.property-header {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
    align-items: start;
}

.property-icon-small {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, var(--success-color), #059669);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.property-info {
    flex: 1;
    min-width: 0;
}

.property-info h3 {
    font-size: 18px;
    font-weight: 200;
    color: var(--dark-color);
    margin: 0 0 8px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.property-reference {
    font-size: 13px;
    color: var(--primary-color);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #eff6ff;
    padding: 4px 10px;
    border-radius: 6px;
}

.property-address {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: start;
    gap: 5px;
    margin-top: 12px;
    line-height: 1.5;
}

.property-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 15px;
}

.property-stat {
    background: #f8fafc;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid #f1f5f9;
    transition: var(--transition);
}

.property-stat:hover {
    background: #f1f5f9;
    border-color: var(--primary-color);
}

.property-stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 3px;
}

.property-stat-label {
    font-size: 11px;
    color: #6b7280;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.property-card-content .mt-3 {
    margin-top: 15px;
    font-size: 12px;
    color: #9ca3af;
    display: flex;
    align-items: center;
    gap: 6px;
    padding-top: 15px;
    border-top: 1px solid #f1f5f9;
}

/* ============= EMPTY STATE ============= */
.empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
}

.empty-icon {
    font-size: 80px;
    color: #d1d5db;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: var(--dark-color);
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6b7280;
    font-size: 16px;
}

/* ============= RESPONSIVE ============= */
@media (max-width: 768px) {
    .city-view-container {
        padding: 15px;
    }
    
    .city-header {
        padding: 20px;
    }
    
    .city-title {
        font-size: 24px;
    }
    
    .city-icon {
        width: 60px;
        height: 60px;
        font-size: 28px;
    }
    
    .properties-grid {
        grid-template-columns: 1fr;
    }
    
    .city-stats-row {
        grid-template-columns: 1fr;
    }
    
    .property-card-image {
        height: 180px;
    }
}
</style>

<div class="city-view-container">
    
    <!-- HEADER -->
    <div class="city-header">
        <div class="breadcrumb-drive">
            <a href="documentos.php"><i class="fas fa-folder-open"></i> <?php echo __('documents.file_cabinets', [], 'File Cabinets'); ?></a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($city); ?></span>
        </div>
        
        <div class="city-title">
            <div class="city-icon">
                <i class="fas fa-archive"></i>
            </div>
            <div>
                <?php echo htmlspecialchars($city); ?>
                <div class="city-subtitle">
                    <i class="fas fa-folder"></i>
                    <?php echo __('documents.city_cabinet', [], 'City File Cabinet'); ?>
                </div>
            </div>
        </div>
        
        <div class="city-stats-row">
            <div class="city-stat">
                <div class="city-stat-value"><?php echo $cityStats['total_properties']; ?></div>
                <div class="city-stat-label"><?php echo __('documents.properties', [], 'Properties'); ?></div>
            </div>
            <div class="city-stat">
                <div class="city-stat-value"><?php echo $cityStats['total_documents']; ?></div>
                <div class="city-stat-label"><?php echo __('documents.documents', [], 'Documents'); ?></div>
            </div>
            <div class="city-stat">
                <div class="city-stat-value"><?php echo number_format($cityStats['total_size'] / (1024 * 1024), 2); ?> MB</div>
                <div class="city-stat-label"><?php echo __('documents.storage', [], 'Storage'); ?></div>
            </div>
        </div>
    </div>

    <!-- PROPERTIES GRID -->
    <div class="properties-grid">
        <?php if (empty($properties)): ?>
            <div class="empty-state">
                <i class="fas fa-building empty-icon"></i>
                <h3><?php echo __('documents.no_properties', [], 'No properties in this city'); ?></h3>
                <p class="text-muted"><?php echo __('documents.no_properties_registered', [], 'This city has no registered properties yet'); ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($properties as $property): 
                // Procesar ruta de imagen
                $hasImage = false;
                $webImagePath = '';
                
                if (!empty($property['main_image'])) {
                    $imagePath = $property['main_image'];
                    
                    // Extraer solo la parte después de 'uploads/'
                    if (strpos($imagePath, 'uploads/') !== false) {
                        $webImagePath = substr($imagePath, strpos($imagePath, 'uploads/'));
                    } else {
                        $webImagePath = $imagePath;
                    }
                    
                    // Limpiar barras iniciales
                    $webImagePath = ltrim($webImagePath, '/');
                    
                    // Verificar si existe
                    if (file_exists($webImagePath)) {
                        $hasImage = true;
                    }
                }
            ?>
            <div class="property-card" onclick="openProperty(<?php echo $property['id']; ?>)">
                
                <!-- IMAGEN DE LA PROPIEDAD -->
                <div class="property-card-image">
                    <?php if ($hasImage): ?>
                        <img src="<?php echo htmlspecialchars($webImagePath); ?>" 
                             alt="<?php echo htmlspecialchars($property['title']); ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="property-card-image-overlay" style="display: none;">
                            <i class="fas fa-building"></i>
                        </div>
                    <?php else: ?>
                        <div class="property-card-image-overlay">
                            <i class="fas fa-building"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- CONTENIDO -->
                <div class="property-card-content">
                    <div class="property-header">
                        <div class="property-icon-small">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="property-info">
                            <h3><?php echo htmlspecialchars($property['title']); ?></h3>
                            <div class="property-reference">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($property['reference']); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="property-address">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($property['address']); ?></span>
                    </div>
                    
                    <div class="property-stats">
                        <div class="property-stat">
                            <div class="property-stat-value"><?php echo $property['document_count']; ?></div>
                            <div class="property-stat-label"><?php echo __('documents.documents', [], 'Documents'); ?></div>
                        </div>
                        <div class="property-stat">
                            <div class="property-stat-value"><?php echo number_format($property['total_size'] / (1024 * 1024), 2); ?> MB</div>
                            <div class="property-stat-label"><?php echo __('documents.size', [], 'Size'); ?></div>
                        </div>
                    </div>
                    
                    <?php if ($property['last_update']): ?>
                    <div class="mt-3">
                        <i class="fas fa-clock"></i>
                        <?php echo __('documents.updated', [], 'Updated'); ?>: <?php echo date('d/m/Y', strtotime($property['last_update'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
function openProperty(propertyId) {
    window.location.href = `documentos-propiedad.php?id=${propertyId}`;
}
</script>

<?php include 'footer.php'; ?>