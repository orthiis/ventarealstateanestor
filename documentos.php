<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = __('documents.file_manager', [], 'Document Manager');
$currentUser = getCurrentUser();

// Obtener estadísticas generales
$stats = [
    'total_cities' => (int)db()->selectValue("
        SELECT COUNT(DISTINCT city) 
        FROM documents 
        WHERE city IS NOT NULL AND status = 'active'
    "),
    'total_properties' => (int)db()->selectValue("
        SELECT COUNT(DISTINCT property_id) 
        FROM documents 
        WHERE property_id IS NOT NULL AND status = 'active'
    "),
    'total_documents' => (int)db()->selectValue("
        SELECT COUNT(*) 
        FROM documents 
        WHERE status = 'active'
    "),
    'total_size' => (float)db()->selectValue("
        SELECT COALESCE(SUM(file_size), 0) 
        FROM documents 
        WHERE status = 'active'
    ") / (1024 * 1024) // Convertir a MB
];

// Obtener todas las ciudades con sus estadísticas
$cities = db()->select("
    SELECT 
        d.city,
        COUNT(DISTINCT d.property_id) as property_count,
        COUNT(d.id) as document_count,
        COALESCE(SUM(d.file_size), 0) as total_size
    FROM documents d
    WHERE d.city IS NOT NULL 
    AND d.status = 'active'
    GROUP BY d.city
    ORDER BY d.city ASC
");

// Obtener carpetas/categorías
$folders = db()->select("
    SELECT * FROM document_folders 
    WHERE is_active = 1 
    ORDER BY display_order
");

include 'header.php';
include 'sidebar.php';
?>

<style>
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #10b981;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
    --info-color: #3b82f6;
    --dark-color: #1f2937;
    --light-bg: #f8fafc;
    --border-radius: 16px;
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ============= CONTAINER ============= */
.documents-drive-container {
    padding: 30px;
    background: var(--light-bg);
    min-height: calc(100vh - 80px);
}

/* ============= HEADER ============= */
.drive-header {
    background: white;
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.drive-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.drive-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.drive-title {
    font-size: 32px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 15px;
}

.drive-title i {
    font-size: 36px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.drive-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.btn-drive {
    padding: 12px 24px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-drive-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-drive-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

.btn-drive-outline {
    background: white;
    color: var(--primary-color);
    border: 2px solid var(--primary-color);
}

.btn-drive-outline:hover {
    background: var(--primary-color);
    color: white;
    transform: translateY(-2px);
}

/* ============= STATS CARDS ============= */
.drive-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card-drive {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    border-left: 4px solid var(--primary-color);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card-drive::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border-radius: 50%;
    transform: translate(30px, -30px);
}

.stat-card-drive:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
}

.stat-card-drive .stat-value {
    font-size: 36px;
    font-weight: 800;
    color: var(--dark-color);
    margin: 10px 0;
    position: relative;
    z-index: 1;
}

.stat-card-drive .stat-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 1;
}

.stat-card-drive .stat-icon {
    font-size: 32px;
    opacity: 0.15;
    position: absolute;
    right: 20px;
    top: 20px;
}

/* ============= SEARCH BAR ============= */
.drive-search-bar {
    background: white;
    padding: 25px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
}

.search-input-group {
    position: relative;
    max-width: 100%;
}

.search-input-drive {
    width: 100%;
    padding: 16px 55px 16px 20px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f9fafb;
}

.search-input-drive:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

.search-icon {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    font-size: 20px;
    pointer-events: none;
}

/* ============= SEARCH RESULTS ============= */
.search-results {
    display: none;
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    margin-top: 10px;
    max-height: 400px;
    overflow-y: auto;
    position: relative;
    z-index: 100;
}

.search-results.active {
    display: block;
}

.search-result-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 15px;
}

.search-result-item:hover {
    background: #f9fafb;
}

.search-result-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.search-result-info {
    flex: 1;
}

.search-result-name {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 3px;
}

.search-result-meta {
    font-size: 12px;
    color: #6b7280;
}

/* ============= ARCHIVEROS (CITIES CARDS) ============= */
.archiveros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.archivero-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    box-shadow: var(--shadow-md);
    cursor: pointer;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.archivero-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
}

.archivero-card:hover::before {
    transform: scaleX(1);
}

.archivero-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

.archivero-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.archivero-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    flex-shrink: 0;
}

.archivero-info {
    flex: 1;
    min-width: 0;
}

.archivero-info h3 {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark-color);
    margin: 0 0 5px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.archivero-info .city-name {
    color: #6b7280;
    font-size: 14px;
}

.archivero-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.archivero-stat {
    background: #f8fafc;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    transition: var(--transition);
}

.archivero-stat:hover {
    background: #f1f5f9;
}

.archivero-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.archivero-stat-label {
    font-size: 12px;
    color: #6b7280;
    font-weight: 500;
}

/* ============= MODAL UPLOAD ============= */
.modal-drive {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}

.modal-drive.active {
    display: flex;
}

.modal-content-drive {
    background: white;
    border-radius: var(--border-radius);
    padding: 40px;
    max-width: 700px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header-drive {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e5e7eb;
}

.modal-header-drive h2 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark-color);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    color: #9ca3af;
    cursor: pointer;
    transition: var(--transition);
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    background: #f3f4f6;
    color: var(--danger-color);
    transform: rotate(90deg);
}

/* ============= DROPZONE ============= */
.dropzone-area {
    border: 3px dashed #d1d5db;
    border-radius: var(--border-radius);
    padding: 60px 20px;
    text-align: center;
    background: #f8fafc;
    transition: var(--transition);
    cursor: pointer;
    margin-bottom: 25px;
}

.dropzone-area.dragover {
    border-color: var(--primary-color);
    background: #eff6ff;
    transform: scale(1.02);
}

.dropzone-icon {
    font-size: 64px;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.dropzone-text {
    font-size: 18px;
    color: var(--dark-color);
    font-weight: 600;
    margin-bottom: 8px;
}

.dropzone-hint {
    font-size: 14px;
    color: #6b7280;
}

.files-preview {
    margin-top: 20px;
    max-height: 300px;
    overflow-y: auto;
}

.file-preview-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background: #f9fafb;
    border-radius: 10px;
    margin-bottom: 10px;
    transition: var(--transition);
}

.file-preview-item:hover {
    background: #f1f5f9;
}

.file-icon-preview {
    font-size: 32px;
    color: var(--primary-color);
}

.file-info-preview {
    flex: 1;
    min-width: 0;
}

.file-name-preview {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-size-preview {
    font-size: 13px;
    color: #6b7280;
}

.file-remove-btn {
    background: var(--danger-color);
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
}

.file-remove-btn:hover {
    background: #dc2626;
    transform: scale(1.1);
}

/* ============= FORM STYLES ============= */
.form-group-drive {
    margin-bottom: 20px;
}

.form-label-drive {
    display: block;
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control-drive {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 15px;
    transition: var(--transition);
    background: #f9fafb;
}

.form-control-drive:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    background: white;
}

.form-control-drive:disabled {
    background: #e5e7eb;
    cursor: not-allowed;
}

/* ============= MODAL FOOTER (BOTONES) ============= */
.modal-footer-drive {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

/* ============= LOADING ============= */
.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.loading-overlay.active {
    display: flex;
}

.loading-spinner {
    text-align: center;
    color: white;
}

.spinner {
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    width: 60px;
    height: 60px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ============= EMPTY STATE ============= */
.empty-state-drive {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
}

.empty-icon-drive {
    font-size: 80px;
    color: #d1d5db;
    margin-bottom: 20px;
}

.empty-state-drive h3 {
    color: var(--dark-color);
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 10px;
}

.empty-state-drive p {
    color: #6b7280;
    margin-bottom: 25px;
}

/* ============= RESPONSIVE ============= */
@media (max-width: 768px) {
    .documents-drive-container {
        padding: 15px;
    }
    
    .drive-header {
        padding: 20px;
    }
    
    .drive-header-top {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .drive-title {
        font-size: 24px;
    }
    
    .drive-actions {
        width: 100%;
    }
    
    .btn-drive {
        width: 100%;
        justify-content: center;
    }
    
    .archiveros-grid {
        grid-template-columns: 1fr;
    }
    
    .drive-stats {
        grid-template-columns: 1fr;
    }
    
    .modal-content-drive {
        padding: 25px;
        width: 95%;
    }
    
    .modal-footer-drive {
        flex-direction: column-reverse;
    }
    
    .modal-footer-drive .btn-drive {
        width: 100%;
    }
}
</style>

<div class="documents-drive-container">
    
    <!-- HEADER -->
    <div class="drive-header">
        <div class="drive-header-top">
            <h1 class="drive-title">
                <i class="fas fa-folder-open"></i>
                <?php echo __('documents.file_manager', [], 'Document Manager'); ?>
            </h1>
            <div class="drive-actions">
                <button class="btn-drive btn-drive-primary" onclick="openUploadModal()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <?php echo __('documents.upload_files', [], 'Upload Files'); ?>
                </button>
                <button class="btn-drive btn-drive-outline" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i>
                    <?php echo __('documents.refresh', [], 'Refresh'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- STATISTICS -->
    <div class="drive-stats">
        <div class="stat-card-drive" style="border-left-color: #667eea;">
            <i class="fas fa-city stat-icon" style="color: #667eea;"></i>
            <div class="stat-label"><?php echo __('documents.cities', [], 'Cities'); ?></div>
            <div class="stat-value"><?php echo $stats['total_cities']; ?></div>
        </div>
        <div class="stat-card-drive" style="border-left-color: #10b981;">
            <i class="fas fa-building stat-icon" style="color: #10b981;"></i>
            <div class="stat-label"><?php echo __('documents.properties', [], 'Properties'); ?></div>
            <div class="stat-value"><?php echo $stats['total_properties']; ?></div>
        </div>
        <div class="stat-card-drive" style="border-left-color: #f59e0b;">
            <i class="fas fa-file stat-icon" style="color: #f59e0b;"></i>
            <div class="stat-label"><?php echo __('documents.documents', [], 'Documents'); ?></div>
            <div class="stat-value"><?php echo $stats['total_documents']; ?></div>
        </div>
        <div class="stat-card-drive" style="border-left-color: #3b82f6;">
            <i class="fas fa-hdd stat-icon" style="color: #3b82f6;"></i>
            <div class="stat-label"><?php echo __('documents.storage', [], 'Storage'); ?></div>
            <div class="stat-value"><?php echo number_format($stats['total_size'], 1); ?> MB</div>
        </div>
    </div>

    <!-- SEARCH BAR -->
    <div class="drive-search-bar">
        <div class="search-input-group">
            <input type="text" 
               class="search-input-drive" 
               id="globalSearch" 
               placeholder="<?php echo __('documents.search_placeholder', [], 'Search documents, properties, cities...'); ?>"
               onkeyup="performSearch()">
            <i class="fas fa-search search-icon"></i>
        </div>
    </div>

    <!-- ARCHIVEROS (CITIES) -->
    <div class="archiveros-grid" id="archiverosGrid">
        <?php if (empty($cities)): ?>
            <div class="col-12 text-center py-5">
                <i class="fas fa-folder-open" style="font-size: 64px; color: #d1d5db;"></i>
                <p class="text-muted mt-3"><?php echo __('documents.no_documents_yet', [], 'No documents organized by city yet'); ?></p>
                <button class="btn-drive btn-drive-primary mt-3" onclick="openUploadModal()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <?php echo __('documents.upload_first_document', [], 'Upload First Document'); ?>
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($cities as $city): ?>
            <div class="archivero-card" onclick="openCity('<?php echo htmlspecialchars($city['city']); ?>')">
                <div class="archivero-header">
                    <div class="archivero-icon">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="archivero-info">
                        <h3><?php echo htmlspecialchars($city['city']); ?></h3>
                        <span class="city-name"><?php echo __('documents.file_cabinet', [], 'File Cabinet'); ?></span>
                    </div>
                </div>
                <div class="archivero-stats">
                    <div class="archivero-stat">
                        <div class="archivero-stat-value"><?php echo $city['property_count']; ?></div>
                        <div class="archivero-stat-label"><?php echo __('documents.properties', [], 'Properties'); ?></div>
                    </div>
                    <div class="archivero-stat">
                        <div class="archivero-stat-value"><?php echo $city['document_count']; ?></div>
                        <div class="archivero-stat-label"><?php echo __('documents.documents', [], 'Documents'); ?></div>
                    </div>
                </div>
                <div class="mt-3" style="font-size: 13px; color: #6b7280;">
                    <i class="fas fa-database"></i>
                    <?php echo __('documents.size', [], 'Size'); ?>: <?php echo number_format($city['total_size'] / (1024 * 1024), 2); ?> MB
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL UPLOAD -->
<div class="modal-drive" id="uploadModal">
    <div class="modal-content-drive">
        <div class="modal-header-drive">
            <h2><i class="fas fa-cloud-upload-alt"></i> <?php echo __('documents.upload_documents', [], 'Upload Documents'); ?></h2>
            <button class="modal-close" onclick="closeUploadModal()">&times;</button>
        </div>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <!-- DROPZONE -->
            <div class="dropzone-area" id="dropzone">
                <i class="fas fa-cloud-upload-alt dropzone-icon"></i>
                <div class="dropzone-text"><?php echo __('documents.drag_files_here', [], 'Drag files here'); ?></div>
                <div class="dropzone-hint"><?php echo __('documents.or_click_to_select', [], 'or click to select'); ?></div>
                <input type="file" 
                       id="fileInput" 
                       name="files[]" 
                       multiple 
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" 
                       style="display: none;">
            </div>
            
            <div class="files-preview" id="filesPreview"></div>
            
            <!-- FORM FIELDS -->
            <div class="form-group-drive">
                <label class="form-label-drive"><?php echo __('documents.city', [], 'City'); ?> *</label>
                <select class="form-control-drive" name="city" id="citySelect" required onchange="loadPropertiesByCity()">
                    <option value=""><?php echo __('documents.select_city', [], 'Select city...'); ?></option>
                    <?php
                    $allCities = db()->select("
                        SELECT DISTINCT city 
                        FROM properties 
                        WHERE city IS NOT NULL 
                        AND city != '' 
                        ORDER BY city
                    ");
                    foreach ($allCities as $c): ?>
                        <option value="<?php echo htmlspecialchars($c['city']); ?>">
                            <?php echo htmlspecialchars($c['city']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group-drive">
                <label class="form-label-drive"><?php echo __('documents.property', [], 'Property'); ?> *</label>
                <select class="form-control-drive" name="property_id" id="propertySelect" required disabled>
                    <option value=""><?php echo __('documents.first_select_city', [], 'First select a city...'); ?></option>
                </select>
            </div>
            
            <div class="form-group-drive">
                <label class="form-label-drive"><?php echo __('documents.category', [], 'Category (Folder)'); ?> *</label>
                <select class="form-control-drive" name="folder_id" required>
                    <option value=""><?php echo __('documents.select_category', [], 'Select category...'); ?></option>
                    <?php foreach ($folders as $folder): ?>
                        <option value="<?php echo $folder['id']; ?>">
                            <i class="fas <?php echo $folder['icon']; ?>"></i>
                            <?php echo __($folder['name'], [], $folder['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group-drive">
                <label class="form-label-drive"><?php echo __('documents.description', [], 'Description'); ?></label>
                <textarea class="form-control-drive" 
                          name="description" 
                          rows="3" 
                          placeholder="<?php echo __('documents.description_placeholder', [], 'Optional description of the documents...'); ?>"></textarea>
            </div>
            
            
            <div class="modal-footer-drive">
                <button type="button" class="btn-drive btn-drive-outline" onclick="closeUploadModal()">
                    <i class="fas fa-times"></i> <?php echo __('documents.cancel', [], 'Cancel'); ?>
                </button>
                <button type="submit" class="btn-drive btn-drive-primary">
                    <i class="fas fa-upload"></i> <?php echo __('documents.upload', [], 'Upload Files'); ?>
                </button>
            </form>
        </div>
    </div>

<!-- LOADING OVERLAY -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner"></div>
        <div><?php echo __('documents.uploading', [], 'Uploading files...'); ?></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// VARIABLES GLOBALES
let selectedFiles = [];

// ABRIR/CERRAR MODALES
function openUploadModal() {
    document.getElementById('uploadModal').classList.add('active');
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.remove('active');
    document.getElementById('uploadForm').reset();
    selectedFiles = [];
    document.getElementById('filesPreview').innerHTML = '';
}

// DRAG AND DROP
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');

dropzone.addEventListener('click', () => fileInput.click());

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    const files = Array.from(e.dataTransfer.files);
    handleFiles(files);
});

fileInput.addEventListener('change', (e) => {
    const files = Array.from(e.target.files);
    handleFiles(files);
});

function handleFiles(files) {
    selectedFiles = [...selectedFiles, ...files];
    renderFilesPreview();
}

function renderFilesPreview() {
    const preview = document.getElementById('filesPreview');
    preview.innerHTML = '';
    
    selectedFiles.forEach((file, index) => {
        const fileExt = file.name.split('.').pop().toLowerCase();
        const icon = getFileIcon(fileExt);
        
        const div = document.createElement('div');
        div.className = 'file-preview-item';
        div.innerHTML = `
            <i class="${icon} file-icon-preview"></i>
            <div class="file-info-preview">
                <div class="file-name-preview">${file.name}</div>
                <div class="file-size-preview">${formatFileSize(file.size)}</div>
            </div>
            <button type="button" class="file-remove-btn" onclick="removeFile(${index})">
                <i class="fas fa-times"></i>
            </button>
        `;
        preview.appendChild(div);
    });
}

function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderFilesPreview();
}

function getFileIcon(ext) {
    const icons = {
        'pdf': 'fas fa-file-pdf',
        'doc': 'fas fa-file-word',
        'docx': 'fas fa-file-word',
        'xls': 'fas fa-file-excel',
        'xlsx': 'fas fa-file-excel',
        'jpg': 'fas fa-file-image',
        'jpeg': 'fas fa-file-image',
        'png': 'fas fa-file-image',
        'gif': 'fas fa-file-image',
    };
    return icons[ext] || 'fas fa-file';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// CARGAR PROPIEDADES POR CIUDAD
function loadPropertiesByCity() {
    const city = document.getElementById('citySelect').value;
    const propertySelect = document.getElementById('propertySelect');
    
    if (!city) {
        propertySelect.disabled = true;
        propertySelect.innerHTML = '<option value="">Primero selecciona una ciudad...</option>';
        return;
    }
    
    fetch(`ajax/get-properties-by-city.php?city=${encodeURIComponent(city)}`)
        .then(response => response.json())
        .then(data => {
            propertySelect.disabled = false;
            propertySelect.innerHTML = '<option value="">Seleccionar propiedad...</option>';
            
            data.properties.forEach(property => {
                const option = document.createElement('option');
                option.value = property.id;
                option.textContent = `${property.reference} - ${property.title}`;
                propertySelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudieron cargar las propiedades', 'error');
        });
}

// ENVIAR FORMULARIO
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (selectedFiles.length === 0) {
        Swal.fire('<?php echo __('documents.error', [], 'Error'); ?>', '<?php echo __('documents.select_files', [], 'You must select at least one file'); ?>', 'warning');
        return;
    }
    
    const formData = new FormData();
    
    // Agregar archivos
    selectedFiles.forEach(file => {
        formData.append('files[]', file);
    });
    
    // Agregar datos del formulario
    formData.append('city', document.querySelector('[name="city"]').value);
    formData.append('property_id', document.querySelector('[name="property_id"]').value);
    formData.append('folder_id', document.querySelector('[name="folder_id"]').value);
    formData.append('description', document.querySelector('[name="description"]').value);
    
    // Mostrar loading
    document.getElementById('loadingOverlay').classList.add('active');
    
    fetch('ajax/upload-documents.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingOverlay').classList.remove('active');
        
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '<?php echo __('documents.success', [], 'Success!'); ?>',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
            closeUploadModal();
            setTimeout(() => location.reload(), 2000);
        } else {
            Swal.fire('<?php echo __('documents.error', [], 'Error'); ?>', data.message, 'error');
        }
    })
    .catch(error => {
        document.getElementById('loadingOverlay').classList.remove('active');
        console.error('Error:', error);
        Swal.fire('<?php echo __('documents.error', [], 'Error'); ?>', '<?php echo __('documents.upload_error', [], 'There was an error uploading the files'); ?>', 'error');
    });
});

// ABRIR CIUDAD
function openCity(city) {
    window.location.href = `documentos-ciudad.php?city=${encodeURIComponent(city)}`;
}

// BÚSQUEDA GLOBAL
let searchTimeout;
function performSearch() {
    clearTimeout(searchTimeout);
    const searchTerm = document.getElementById('globalSearch').value;
    
    searchTimeout = setTimeout(() => {
        if (searchTerm.length >= 2) {
            fetch(`ajax/search-documents.php?q=${encodeURIComponent(searchTerm)}`)
                .then(response => response.json())
                .then(data => {
                    // Implementar mostrar resultados
                    console.log('Resultados:', data);
                })
                .catch(error => console.error('Error:', error));
        }
    }, 300);
}

// ACTUALIZAR DATOS
function refreshData() {
    location.reload();
}
</script>

<?php include 'footer.php'; ?>