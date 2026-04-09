<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Subir Nuevo Documento';
$currentUser = getCurrentUser();

// Obtener categorías
$categories = db()->select(
    "SELECT * FROM document_categories WHERE is_active = 1 ORDER BY display_order"
);

// Obtener clientes para asociar
$clients = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name, reference 
     FROM clients 
     WHERE is_active = 1 
     ORDER BY first_name 
     LIMIT 500"
);

// Obtener propiedades para asociar
$properties = db()->select(
    "SELECT id, reference, title, city 
     FROM properties 
     WHERE status NOT IN ('deleted', 'sold') 
     ORDER BY created_at DESC 
     LIMIT 500"
);

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
    --purple: #8b5cf6;
}

.upload-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* ============ HEADER ============ */
.page-header-modern {
    background: white;
    padding: 25px 30px;
    border-radius: 16px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title-modern {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 5px 0;
}

.page-subtitle-modern {
    color: #718096;
    margin: 0;
    font-size: 14px;
}

/* ============ UPLOAD ZONE ============ */
.upload-zone {
    background: white;
    border: 3px dashed #d1d5db;
    border-radius: 20px;
    padding: 60px 40px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    margin-bottom: 25px;
}

.upload-zone:hover {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
}

.upload-zone.dragover {
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    transform: scale(1.02);
}

.upload-zone.has-file {
    border-color: var(--success);
    background: rgba(16, 185, 129, 0.05);
}

.upload-icon {
    font-size: 64px;
    color: #9ca3af;
    margin-bottom: 20px;
    transition: all 0.3s;
}

.upload-zone:hover .upload-icon,
.upload-zone.dragover .upload-icon {
    color: var(--primary);
    transform: scale(1.1);
}

.upload-zone.has-file .upload-icon {
    color: var(--success);
}

.upload-title {
    font-size: 20px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
}

.upload-subtitle {
    color: #718096;
    font-size: 14px;
    margin-bottom: 20px;
}

.upload-hint {
    display: inline-block;
    background: #f3f4f6;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    color: #6b7280;
    margin-top: 10px;
}

/* ============ FILE PREVIEW ============ */
.file-preview-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: none;
}

.file-preview-card.active {
    display: block;
}

.file-preview-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f3f4f6;
    margin-bottom: 20px;
}

.file-icon-large {
    width: 80px;
    height: 80px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    flex-shrink: 0;
}

.file-icon-large.pdf { background: #fee2e2; color: #dc2626; }
.file-icon-large.word { background: #dbeafe; color: #2563eb; }
.file-icon-large.excel { background: #d1fae5; color: #059669; }
.file-icon-large.image { background: #fef3c7; color: #d97706; }
.file-icon-large.other { background: #f3f4f6; color: #6b7280; }

.file-preview-info {
    flex: 1;
}

.file-preview-name {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin: 0 0 5px 0;
}

.file-preview-meta {
    color: #718096;
    font-size: 14px;
}

.btn-remove-file {
    padding: 10px 20px;
    background: #fee2e2;
    color: #dc2626;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-remove-file:hover {
    background: #fecaca;
    transform: translateY(-2px);
}

/* ============ FORM SECTIONS ============ */
.form-card-modern {
    background: white;
    border-radius: 16px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-section-title i {
    color: var(--primary);
    font-size: 20px;
}

/* ============ FORM ELEMENTS ============ */
.form-label-modern {
    font-weight: 600;
    color: #4b5563;
    font-size: 14px;
    margin-bottom: 8px;
    display: block;
}

.form-label-modern.required::after {
    content: " *";
    color: var(--danger);
}

.form-input-modern {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
    width: 100%;
}

.form-input-modern:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-select-modern {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
    width: 100%;
    background: white;
}

.form-select-modern:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-textarea-modern {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 14px;
    transition: all 0.3s ease;
    width: 100%;
    resize: vertical;
    font-family: inherit;
}

.form-textarea-modern:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* ============ CHECKBOX & SWITCH ============ */
.checkbox-group {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.checkbox-card {
    flex: 1;
    min-width: 200px;
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-card:hover {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.05);
}

.checkbox-card input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-card.checked {
    border-color: var(--primary);
    background: rgba(102, 126, 234, 0.1);
}

/* ============ BUTTONS ============ */
.btn-modern {
    padding: 12px 24px;
    border-radius: 12px;
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

.btn-primary-modern:hover:not(:disabled) {
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    transform: translateY(-2px);
}

.btn-primary-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
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

/* ============ PROGRESS BAR ============ */
.upload-progress-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: none;
}

.upload-progress-card.active {
    display: block;
}

.progress-bar-container {
    background: #e5e7eb;
    height: 40px;
    border-radius: 20px;
    overflow: hidden;
    position: relative;
}

.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary) 0%, var(--purple) 100%);
    border-radius: 20px;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
}

.progress-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 3px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-right: 10px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ============ FORM ACTIONS ============ */
.form-actions {
    background: white;
    border-radius: 16px;
    padding: 20px 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    .upload-container {
        padding: 15px;
    }
    
    .page-header-modern {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .upload-zone {
        padding: 40px 20px;
    }
    
    .form-card-modern {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .checkbox-group {
        flex-direction: column;
    }
}
</style>

<div class="upload-container">
    
    <!-- Header -->
    <div class="page-header-modern">
        <div>
            <h2 class="page-title-modern">
                <i class="fas fa-cloud-upload-alt" style="color: var(--primary);"></i>
                Subir Nuevo Documento
            </h2>
            <p class="page-subtitle-modern">
                Completa la información y sube tu documento al sistema
            </p>
        </div>
        <div>
            <a href="documentos.php" class="btn-modern btn-secondary-modern">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <form id="uploadDocumentForm" enctype="multipart/form-data">
        
        <!-- Upload Zone -->
        <div class="upload-zone" id="uploadZone">
            <input type="file" id="fileInput" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" style="display: none;" required>
            <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h3 class="upload-title">Arrastra y suelta tu archivo aquí</h3>
            <p class="upload-subtitle">o haz clic para seleccionar desde tu computadora</p>
            <span class="upload-hint">
                <i class="fas fa-info-circle"></i>
                Formatos: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF • Tamaño máximo: 10 MB
            </span>
        </div>

        <!-- File Preview -->
        <div class="file-preview-card" id="filePreview">
            <div class="file-preview-header">
                <div class="file-icon-large" id="fileIconPreview">
                    <i class="fas fa-file"></i>
                </div>
                <div class="file-preview-info">
                    <h3 class="file-preview-name" id="fileName"></h3>
                    <p class="file-preview-meta" id="fileMeta"></p>
                </div>
                <button type="button" class="btn-remove-file" onclick="removeFile()">
                    <i class="fas fa-times"></i> Quitar archivo
                </button>
            </div>
        </div>

        <!-- Upload Progress -->
        <div class="upload-progress-card" id="uploadProgress">
            <h4 style="margin: 0 0 15px 0; color: #2d3748; font-size: 16px;">
                <span class="progress-spinner"></span>
                Subiendo documento...
            </h4>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" id="progressBar" style="width: 0%">0%</div>
            </div>
        </div>

        <!-- Información Básica -->
        <div class="form-card-modern">
            <div class="form-section-title">
                <i class="fas fa-info-circle"></i>
                Información del Documento
            </div>

            <div class="row g-4">
                <div class="col-md-8">
                    <label class="form-label-modern required">Nombre del Documento</label>
                    <input type="text" class="form-input-modern" name="document_name" id="documentName" required
                           placeholder="Ej: Contrato de Compraventa - Juan Pérez">
                </div>

                <div class="col-md-4">
                    <label class="form-label-modern">Número/Referencia</label>
                    <input type="text" class="form-input-modern" name="document_number" 
                           placeholder="Ej: CONT-2025-001">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern required">Categoría</label>
                    <select class="form-select-modern" name="category_id" required>
                        <option value="">Seleccionar categoría...</option>
                        <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern">Estado</label>
                    <select class="form-select-modern" name="status">
                        <option value="draft">Borrador</option>
                        <option value="active" selected>Activo</option>
                        <option value="archived">Archivado</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern">Visibilidad</label>
                    <select class="form-select-modern" name="visibility">
                        <option value="private" selected>Privado</option>
                        <option value="public">Público</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Descripción</label>
                    <textarea class="form-textarea-modern" name="description" rows="4" 
                              placeholder="Descripción detallada del documento..."></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Etiquetas (separadas por comas)</label>
                    <input type="text" class="form-input-modern" name="tags" 
                           placeholder="contrato, importante, 2025">
                </div>

                <div class="col-md-6">
                    <label class="form-label-modern">Fecha de Expiración</label>
                    <input type="date" class="form-input-modern" name="expiration_date">
                </div>
            </div>
        </div>

        <!-- Relacionar con -->
        <div class="form-card-modern">
            <div class="form-section-title">
                <i class="fas fa-link"></i>
                Relacionar Documento
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <label class="form-label-modern">Tipo de Relación</label>
                    <select class="form-select-modern" name="related_entity_type" id="entityType" onchange="toggleEntitySelects()">
                        <option value="general">General (sin relación)</option>
                        <option value="client">Cliente</option>
                        <option value="property">Propiedad</option>
                    </select>
                </div>

                <div class="col-md-8" id="clientSelectDiv" style="display: none;">
                    <label class="form-label-modern">Seleccionar Cliente</label>
                    <select class="form-select-modern" id="clientSelect">
                        <option value="">Buscar cliente...</option>
                        <?php foreach($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>">
                            <?php echo htmlspecialchars($client['reference'] . ' - ' . $client['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-8" id="propertySelectDiv" style="display: none;">
                    <label class="form-label-modern">Seleccionar Propiedad</label>
                    <select class="form-select-modern" id="propertySelect">
                        <option value="">Buscar propiedad...</option>
                        <?php foreach($properties as $prop): ?>
                        <option value="<?php echo $prop['id']; ?>">
                            <?php echo htmlspecialchars($prop['reference'] . ' - ' . $prop['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Opciones Adicionales -->
        <div class="form-card-modern">
            <div class="form-section-title">
                <i class="fas fa-cog"></i>
                Opciones Adicionales
            </div>

            <div class="checkbox-group">
                <label class="checkbox-card">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="checkbox" name="is_template" id="isTemplate">
                        <div>
                            <div style="font-weight: 600; color: #2d3748; margin-bottom: 3px;">
                                <i class="fas fa-copy"></i> Es una plantilla
                            </div>
                            <div style="font-size: 12px; color: #718096;">
                                Marcar como plantilla reutilizable
                            </div>
                        </div>
                    </div>
                </label>

                <label class="checkbox-card">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <input type="checkbox" name="is_signed" id="isSigned" onchange="toggleSignedFields()">
                        <div>
                            <div style="font-weight: 600; color: #2d3748; margin-bottom: 3px;">
                                <i class="fas fa-certificate"></i> Documento firmado
                            </div>
                            <div style="font-size: 12px; color: #718096;">
                                Marcar si el documento está firmado
                            </div>
                        </div>
                    </div>
                </label>
            </div>

            <!-- Campos de firma (ocultos por defecto) -->
            <div id="signedFields" style="display: none; margin-top: 20px;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label-modern">Fecha de Firma</label>
                        <input type="date" class="form-input-modern" name="signed_date">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-modern">Firmado por</label>
                        <input type="text" class="form-input-modern" name="signed_by" 
                               placeholder="Nombre de quien firma">
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="documentos.php" class="btn-modern btn-secondary-modern">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" class="btn-modern btn-primary-modern" id="submitBtn">
                <i class="fas fa-cloud-upload-alt"></i> Subir Documento
            </button>
        </div>

    </form>

</div>

<script>
// Variables globales
let selectedFile = null;
const uploadZone = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const uploadProgress = document.getElementById('uploadProgress');
const submitBtn = document.getElementById('submitBtn');

// ========== DRAG & DROP ==========
uploadZone.addEventListener('click', () => fileInput.click());

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadZone.addEventListener(eventName, e => {
        e.preventDefault();
        e.stopPropagation();
    });
});

['dragenter', 'dragover'].forEach(eventName => {
    uploadZone.addEventListener(eventName, () => uploadZone.classList.add('dragover'));
});

['dragleave', 'drop'].forEach(eventName => {
    uploadZone.addEventListener(eventName, () => uploadZone.classList.remove('dragover'));
});

uploadZone.addEventListener('drop', function(e) {
    const files = e.dataTransfer.files;
    if(files.length > 0) {
        fileInput.files = files;
        handleFileSelect({ target: { files: files } });
    }
});

// ========== FILE SELECTION ==========
fileInput.addEventListener('change', handleFileSelect);

function handleFileSelect(e) {
    const file = e.target.files[0];
    if(!file) return;
    
    // Validar tamaño (10MB)
    if(file.size > 10 * 1024 * 1024) {
        alert('❌ El archivo es demasiado grande. Máximo 10 MB.');
        fileInput.value = '';
        return;
    }
    
    selectedFile = file;
    showFilePreview(file);
    
    // Auto-llenar nombre si está vacío
    const nameInput = document.getElementById('documentName');
    if(!nameInput.value) {
        const fileName = file.name.replace(/\.[^/.]+$/, "");
        nameInput.value = fileName;
    }
}

function showFilePreview(file) {
    uploadZone.classList.add('has-file');
    filePreview.classList.add('active');
    
    // Determinar tipo de archivo
    const ext = file.name.split('.').pop().toLowerCase();
    let iconClass = 'other';
    let icon = 'fa-file';
    
    if(ext === 'pdf') {
        iconClass = 'pdf';
        icon = 'fa-file-pdf';
    } else if(['doc', 'docx'].includes(ext)) {
        iconClass = 'word';
        icon = 'fa-file-word';
    } else if(['xls', 'xlsx'].includes(ext)) {
        iconClass = 'excel';
        icon = 'fa-file-excel';
    } else if(['jpg', 'jpeg', 'png', 'gif'].includes(ext)) {
        iconClass = 'image';
        icon = 'fa-file-image';
    }
    
    const iconEl = document.getElementById('fileIconPreview');
    iconEl.className = `file-icon-large ${iconClass}`;
    iconEl.innerHTML = `<i class="fas ${icon}"></i>`;
    
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileMeta').textContent = formatBytes(file.size) + ' • ' + ext.toUpperCase();
}

function removeFile() {
    selectedFile = null;
    fileInput.value = '';
    uploadZone.classList.remove('has-file');
    filePreview.classList.remove('active');
}

function formatBytes(bytes) {
    if(bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// ========== TOGGLE FIELDS ==========
function toggleEntitySelects() {
    const entityType = document.getElementById('entityType').value;
    document.getElementById('clientSelectDiv').style.display = entityType === 'client' ? 'block' : 'none';
    document.getElementById('propertySelectDiv').style.display = entityType === 'property' ? 'block' : 'none';
}

function toggleSignedFields() {
    const isSigned = document.getElementById('isSigned').checked;
    document.getElementById('signedFields').style.display = isSigned ? 'block' : 'none';
}

// ========== FORM SUBMIT ==========
document.getElementById('uploadDocumentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if(!selectedFile) {
        alert('❌ Por favor selecciona un archivo');
        return;
    }
    
    const formData = new FormData(this);
    
    // Agregar el ID de entidad correcto según el tipo
    const entityType = document.getElementById('entityType').value;
    if(entityType === 'client') {
        const clientId = document.getElementById('clientSelect').value;
        if(clientId) formData.append('related_entity_id', clientId);
    } else if(entityType === 'property') {
        const propertyId = document.getElementById('propertySelect').value;
        if(propertyId) formData.append('related_entity_id', propertyId);
    }
    
    // Deshabilitar botón
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="progress-spinner"></span> Subiendo...';
    
    // Mostrar progreso
    uploadProgress.classList.add('active');
    filePreview.style.display = 'none';
    
    // Simular progreso
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 15;
        if(progress > 90) progress = 90;
        document.getElementById('progressBar').style.width = progress + '%';
        document.getElementById('progressBar').textContent = Math.round(progress) + '%';
    }, 200);
    
    try {
        const response = await fetch('documento-actions.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        clearInterval(progressInterval);
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('progressBar').textContent = '100%';
        
        if(result.success) {
            setTimeout(() => {
                alert('✅ Documento subido exitosamente');
                window.location.href = 'ver-documento.php?id=' + result.document_id;
            }, 500);
        } else {
            alert('❌ Error: ' + (result.message || 'No se pudo subir el documento'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Subir Documento';
            uploadProgress.classList.remove('active');
            filePreview.style.display = 'block';
        }
    } catch(error) {
        clearInterval(progressInterval);
        console.error('Error:', error);
        alert('❌ Error de conexión al subir el documento');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Subir Documento';
        uploadProgress.classList.remove('active');
        filePreview.style.display = 'block';
    }
});
</script>

<?php include 'footer.php'; ?>