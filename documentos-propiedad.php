<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$currentUser = getCurrentUser();
$propertyId = $_GET['id'] ?? 0;

if (!$propertyId) {
    redirect('documentos.php');
}

$pageTitle = __('documents.documents', [], 'Documents') . ' - ' . __('documents.property', [], 'Property');

// Obtener datos de la propiedad
$property = db()->selectOne("
    SELECT * FROM properties WHERE id = ?
", [$propertyId]);

if (!$property) {
    redirect('documentos.php');
}

// Obtener documentos agrupados por carpeta
$folders = db()->select("
    SELECT 
        df.id,
        df.name,
        df.icon,
        df.color,
        df.description,
        COUNT(d.id) as document_count,
        COALESCE(SUM(d.file_size), 0) as total_size
    FROM document_folders df
    LEFT JOIN documents d ON df.id = d.folder_id 
        AND d.property_id = ? 
        AND d.status = 'active'
    WHERE df.is_active = 1
    GROUP BY df.id
    ORDER BY df.display_order
", [$propertyId]);

// Obtener todos los documentos
$documents = db()->select("
    SELECT 
        d.*,
        df.name as folder_name,
        df.icon as folder_icon,
        df.color as folder_color,
        CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
    FROM documents d
    LEFT JOIN document_folders df ON d.folder_id = df.id
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE d.property_id = ?
    AND d.status = 'active'
    ORDER BY d.created_at DESC
", [$propertyId]);

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
    --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
    --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.property-view-container {
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

/* ============= PROPERTY HEADER ============= */
.property-header {
    background: white;
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.property-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.property-title-section {
    display: flex;
    align-items: start;
    gap: 20px;
    margin-top: 10px;
    flex-wrap: wrap;
}

/* ============= IMAGEN DE PROPIEDAD ============= */
.property-image {
    width: 120px;
    height: 120px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    position: relative;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}

.property-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.property-image i {
    font-size: 48px;
    color: white;
}

.property-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0), rgba(0,0,0,0.3));
}

.property-title-section > div:nth-child(2) {
    flex: 1;
    min-width: 250px;
}

.property-title-section h1 {
    font-size: 28px;
    font-weight: 800;
    color: var(--dark-color);
    margin: 0 0 10px 0;
    line-height: 1.2;
}

.property-reference-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, #eff6ff, #dbeafe);
    color: var(--primary-color);
    font-weight: 700;
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 10px;
    border: 1px solid #bfdbfe;
}

.property-address-text {
    color: #6b7280;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
}

/* ============= BOTÓN SUBIR ARCHIVO (HEADER) ============= */
.btn-upload-property {
    padding: 12px 24px;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    text-decoration: none;
}

.btn-upload-property:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white;
}

/* ============= FOLDERS GRID - COMPACTO ============= */
.folders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.folder-card {
    background: white;
    border-radius: 12px;
    padding: 15px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: var(--transition);
    border-left: 3px solid transparent;
    position: relative;
    overflow: hidden;
}

.folder-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, rgba(0,0,0,0.02), rgba(0,0,0,0.01));
    border-radius: 50%;
    transform: translate(20px, -20px);
}

.folder-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.12);
}

.folder-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    position: relative;
    z-index: 1;
}

.folder-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.12);
    flex-shrink: 0;
}

.folder-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--dark-color);
    margin: 0;
    line-height: 1.3;
}

.folder-stats {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #6b7280;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

.folder-stats span {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ============= DOCUMENTS SECTION ============= */
.documents-section {
    background: white;
    padding: 30px;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-md);
}

.section-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--dark-color);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ============= VIEW TOGGLE ============= */
.view-toggle {
    display: flex;
    gap: 8px;
    background: #f3f4f6;
    padding: 3px;
    border-radius: 10px;
}

.toggle-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: var(--transition);
    font-weight: 600;
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
}

.toggle-btn.active {
    background: white;
    color: var(--primary-color);
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.toggle-btn:hover:not(.active) {
    color: var(--dark-color);
}

/* ============= DOCUMENTS LIST VIEW ============= */
.documents-list {
    display: grid;
    gap: 12px;
}

.documents-list.grid-view {
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.document-item {
    display: flex;
    align-items: center;
    padding: 15px;
    background: #f8fafc;
    border-radius: 12px;
    transition: var(--transition);
    cursor: pointer;
    border: 2px solid transparent;
}

.document-item:hover {
    background: #f1f5f9;
    transform: translateX(5px);
    border-color: var(--primary-color);
}

/* ============= DOCUMENTS GRID VIEW ============= */
.documents-list.grid-view .document-item {
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 20px;
}

.documents-list.grid-view .document-item:hover {
    transform: translateY(-5px);
}

.documents-list.grid-view .doc-icon {
    width: 70px;
    height: 70px;
    font-size: 32px;
    margin-right: 0;
    margin-bottom: 15px;
}

.documents-list.grid-view .doc-info {
    width: 100%;
}

.documents-list.grid-view .doc-name {
    white-space: normal;
    overflow: visible;
    text-overflow: clip;
}

.documents-list.grid-view .doc-meta {
    flex-direction: column;
    gap: 6px;
    align-items: center;
    margin-top: 10px;
}

.documents-list.grid-view .doc-actions {
    margin-top: 15px;
    width: 100%;
    justify-content: center;
}

/* ============= DOC ELEMENTS ============= */
.doc-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-right: 15px;
    flex-shrink: 0;
}

.doc-info {
    flex: 1;
    min-width: 0;
}

.doc-name {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 15px;
}

.doc-meta {
    font-size: 12px;
    color: #6b7280;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.doc-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.doc-actions {
    display: flex;
    gap: 8px;
}

.doc-action-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    background: white;
    color: #6b7280;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.doc-action-btn:hover {
    background: var(--primary-color);
    color: white;
    transform: scale(1.1);
}

.doc-action-btn.delete:hover {
    background: var(--danger-color);
}

/* ============= EMPTY STATE ============= */
.empty-documents {
    text-align: center;
    padding: 60px 20px;
}

.empty-documents i {
    font-size: 64px;
    color: #d1d5db;
    margin-bottom: 15px;
}

.empty-documents p {
    color: #6b7280;
    font-size: 16px;
}

/* ============= RESPONSIVE ============= */
@media (max-width: 1200px) {
    .folders-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    }
}

@media (max-width: 768px) {
    .property-view-container {
        padding: 15px;
    }
    
    .property-header {
        padding: 20px;
    }
    
    .property-title-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .property-image {
        width: 100%;
        height: 200px;
    }
    
    .property-title-section h1 {
        font-size: 22px;
    }
    
    .btn-upload-property {
        width: 100%;
        justify-content: center;
    }
    
    .folders-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .documents-list.grid-view {
        grid-template-columns: 1fr;
    }
    
    .view-toggle {
        width: 100%;
        justify-content: center;
    }
    
    .documents-section {
        padding: 20px;
    }
    
    .section-title {
        font-size: 18px;
    }
}

@media (max-width: 480px) {
    .folders-grid {
        grid-template-columns: 1fr;
    }
    
    .folder-card {
        padding: 12px;
    }
    
    .folder-icon {
        width: 35px;
        height: 35px;
        font-size: 16px;
    }
    
    .folder-name {
        font-size: 13px;
    }
}
</style>

<div class="property-view-container">
    
    <!-- HEADER -->
    <div class="property-header">
        <div class="breadcrumb-drive">
            <a href="documentos.php"><i class="fas fa-folder-open"></i> <?php echo __('documents.file_cabinets', [], 'File Cabinets'); ?></a>
            <i class="fas fa-chevron-right"></i>
            <a href="documentos-ciudad.php?city=<?php echo urlencode($property['city']); ?>">
                <?php echo htmlspecialchars($property['city']); ?>
            </a>
            <i class="fas fa-chevron-right"></i>
            <span><?php echo htmlspecialchars($property['reference']); ?></span>
        </div>
        
        <div class="property-title-section">
            <div class="property-image">
                <?php
                // Obtener imagen principal de la propiedad
                $mainImage = db()->selectOne("
                    SELECT image_path 
                    FROM property_images 
                    WHERE property_id = ? 
                    AND is_main = 1 
                    LIMIT 1
                ", [$propertyId]);
                
                $hasImage = false;
                $webImagePath = '';
                
                if ($mainImage && !empty($mainImage['image_path'])):
                    $imagePath = $mainImage['image_path'];
                    
                    // Limpiar la ruta - quitar ruta absoluta del servidor
                    // Buscar 'uploads/' y tomar desde ahí
                    if (strpos($imagePath, 'uploads/') !== false) {
                        $webImagePath = substr($imagePath, strpos($imagePath, 'uploads/'));
                    } else {
                        $webImagePath = $imagePath;
                    }
                    
                    // Asegurar que NO empiece con / para usar ruta relativa
                    $webImagePath = ltrim($webImagePath, '/');
                    
                    // Verificar si existe el archivo
                    if (file_exists($webImagePath)) {
                        $hasImage = true;
                    }
                endif;
                
                if ($hasImage):
                ?>
                    <img src="<?php echo htmlspecialchars($webImagePath); ?>" 
                         alt="<?php echo htmlspecialchars($property['title']); ?>">
                    <div class="property-image-overlay"></div>
                <?php else: ?>
                    <i class="fas fa-building"></i>
                    <div class="property-image-overlay"></div>
                <?php endif; ?>
            </div>
            
            <div>
                <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                <div class="property-reference-badge">
                    <i class="fas fa-tag"></i> 
                    <?php echo htmlspecialchars($property['reference']); ?>
                </div>
                <div class="property-address-text">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?php echo htmlspecialchars($property['address']); ?>
                </div>
            </div>
            
            <button class="btn-upload-property" onclick="openUploadModal(<?php echo $propertyId; ?>)">
                <i class="fas fa-cloud-upload-alt"></i>
                <?php echo __('documents.upload_file', [], 'Upload File'); ?>
            </button>
        </div>
    </div>

    <!-- FOLDERS -->
    <div class="documents-section mb-4">
        <div class="section-title">
            <i class="fas fa-folder"></i>
            <?php echo __('documents.folders', [], 'Folders'); ?>
        </div>
        <div class="folders-grid">
            <?php foreach ($folders as $folder): ?>
            <div class="folder-card" 
                 style="border-left-color: <?php echo $folder['color']; ?>;"
                 onclick="filterByFolder(<?php echo $folder['id']; ?>)">
                <div class="folder-header">
                    <div class="folder-icon" style="background-color: <?php echo $folder['color']; ?>;">
                        <i class="fas <?php echo $folder['icon']; ?>"></i>
                    </div>
                    <h4 class="folder-name"><?php echo __($folder['name'], [], $folder['name']); ?></h4>
                </div>
                <div class="folder-stats">
                    <span><i class="fas fa-file"></i> <?php echo $folder['document_count']; ?> <?php echo __('documents.files', [], 'files'); ?></span>
                    <span><?php echo number_format($folder['total_size'] / 1024, 0); ?> KB</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DOCUMENTS LIST -->
    <div class="documents-section">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="section-title mb-0">
                <i class="fas fa-file-alt"></i>
                <?php echo __('documents.all_documents', [], 'All Documents'); ?>
            </div>
            <div class="view-toggle">
                <button class="toggle-btn" onclick="setView('list')">
                    <i class="fas fa-list"></i> <?php echo __('documents.list', [], 'List'); ?>
                </button>
                <button class="toggle-btn active" onclick="setView('grid')">
                    <i class="fas fa-th"></i> <?php echo __('documents.grid', [], 'Grid'); ?>
                </button>
            </div>
        </div>
        
        <div class="documents-list" id="documentsList">
            <?php if (empty($documents)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-alt" style="font-size: 48px; color: #d1d5db;"></i>
                    <p class="text-muted mt-3"><?php echo __('documents.no_documents_property', [], 'No documents in this property'); ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                <?php
                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                $iconClass = match($ext) {
                    'pdf' => 'fas fa-file-pdf',
                    'doc', 'docx' => 'fas fa-file-word',
                    'xls', 'xlsx' => 'fas fa-file-excel',
                    'jpg', 'jpeg', 'png', 'gif' => 'fas fa-file-image',
                    default => 'fas fa-file'
                };
                $iconColor = match($ext) {
                    'pdf' => '#ef4444',
                    'doc', 'docx' => '#3b82f6',
                    'xls', 'xlsx' => '#10b981',
                    'jpg', 'jpeg', 'png', 'gif' => '#f59e0b',
                    default => '#6b7280'
                };
                ?>
                <div class="document-item" data-folder="<?php echo $doc['folder_id']; ?>">
                    <div class="doc-icon" style="background-color: <?php echo $iconColor; ?>20; color: <?php echo $iconColor; ?>;">
                        <i class="<?php echo $iconClass; ?>"></i>
                    </div>
                    <div class="doc-info">
                        <div class="doc-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                        <div class="doc-meta">
                            <span><i class="fas fa-folder"></i> <?php echo htmlspecialchars($doc['folder_name']); ?></span>
                            <span><i class="fas fa-hdd"></i> <?php echo number_format($doc['file_size'] / 1024, 0); ?> KB</span>
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="doc-actions">
                        <button class="doc-action-btn" onclick="viewDocument(<?php echo $doc['id']; ?>)" title="<?php echo __('documents.view', [], 'View'); ?>">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="doc-action-btn" onclick="downloadDocument(<?php echo $doc['id']; ?>)" title="<?php echo __('documents.download', [], 'Download'); ?>">
                            <i class="fas fa-download"></i>
                        </button>
                        <?php if ($currentUser['role']['name'] === 'administrador' || $doc['uploaded_by'] == $currentUser['id']): ?>
                        <button class="doc-action-btn" onclick="deleteDocument(<?php echo $doc['id']; ?>)" title="<?php echo __('documents.delete', [], 'Delete'); ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>


// Vista actual
let currentView = 'grid'; // Por defecto cuadrícula

// Filtrar por carpeta
function filterByFolder(folderId) {
    const items = document.querySelectorAll('.document-item');
    let visibleCount = 0;
    
    items.forEach(item => {
        if (item.dataset.folder == folderId) {
            item.style.display = 'flex';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });
    
    if (visibleCount === 0) {
        Swal.fire({
            icon: 'info',
            title: 'Sin documentos',
            text: 'No hay documentos en esta carpeta',
            timer: 2000,
            showConfirmButton: false
        });
    }
}

// Cambiar vista
function setView(view) {
    const buttons = document.querySelectorAll('.toggle-btn');
    const documentsList = document.getElementById('documentsList');
    
    // Actualizar botones
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.closest('.toggle-btn').classList.add('active');
    
    // Cambiar vista
    currentView = view;
    if (view === 'grid') {
        documentsList.classList.add('grid-view');
    } else {
        documentsList.classList.remove('grid-view');
    }
}

// Ver documento
function viewDocument(id) {
    window.open(`ajax/view-document.php?id=${id}`, '_blank');
}

// Descargar documento
function downloadDocument(id) {
    window.location.href = `ajax/download-document.php?id=${id}`;
}

// Eliminar documento
function deleteDocument(id) {
    Swal.fire({
        title: '<?php echo __('documents.delete_confirm', [], 'Delete document?'); ?>',
        text: '<?php echo __('documents.delete_warning', [], 'This action cannot be undone'); ?>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: '<i class="fas fa-trash"></i> <?php echo __('documents.yes_delete', [], 'Yes, delete'); ?>',
        cancelButtonText: '<i class="fas fa-times"></i> <?php echo __('documents.cancel', [], 'Cancel'); ?>'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/delete-document.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo __('documents.deleted', [], 'Deleted!'); ?>',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('<?php echo __('documents.error', [], 'Error'); ?>', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('<?php echo __('documents.error', [], 'Error'); ?>', '<?php echo __('documents.delete_error', [], 'There was a problem deleting the document'); ?>', 'error');
            });
        }
    });
}

function openUploadModal(propertyId) {
    window.location.href = `documentos.php?upload=1&property=${propertyId}`;
}

// Establecer vista por defecto al cargar
document.addEventListener('DOMContentLoaded', function() {
    const documentsList = document.getElementById('documentsList');
    if (documentsList) {
        documentsList.classList.add('grid-view');
    }
});

function filterByFolder(folderId) {
    const items = document.querySelectorAll('.document-item');
    items.forEach(item => {
        if (item.dataset.folder == folderId) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function setView(view) {
    const buttons = document.querySelectorAll('.toggle-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.closest('.toggle-btn').classList.add('active');
    
    // Implementar cambio de vista si es necesario
}

function viewDocument(id) {
    window.open(`ajax/view-document.php?id=${id}`, '_blank');
}

function downloadDocument(id) {
    window.location.href = `ajax/download-document.php?id=${id}`;
}

function deleteDocument(id) {
    Swal.fire({
        title: '¿Eliminar documento?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`ajax/delete-document.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Eliminado!', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

function openUploadModal(propertyId) {
    // Redirigir a documentos.php con parámetro de propiedad
    window.location.href = `documentos.php?upload=1&property=${propertyId}`;
}
</script>

<?php include 'footer.php'; ?>