<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ver_documento_errors.log');

require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Ver Documento';
$currentUser = getCurrentUser();

// Obtener ID del documento
$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Log para debug
error_log("=== INICIANDO ver-documento.php ===");
error_log("ID recibido: " . $documentId);
error_log("Usuario actual: " . $currentUser['id']);

if(!$documentId || $documentId <= 0) {
    error_log("ERROR: ID inválido o no proporcionado");
    setFlashMessage('error', 'ID de documento inválido');
    header('Location: documentos.php');
    exit;
}

// Primero, verificar que el documento existe con una consulta simple
try {
    $documentExists = db()->selectOne(
        "SELECT id FROM documents WHERE id = ?",
        [$documentId]
    );
    
    if(!$documentExists) {
        error_log("ERROR: Documento con ID $documentId no existe en la base de datos");
        setFlashMessage('error', 'Documento no encontrado (ID: ' . $documentId . ')');
        header('Location: documentos.php');
        exit;
    }
    
    error_log("✓ Documento existe en BD");
    
} catch(Exception $e) {
    error_log("ERROR en verificación de existencia: " . $e->getMessage());
    setFlashMessage('error', 'Error al verificar documento');
    header('Location: documentos.php');
    exit;
}

// Obtener datos del documento con LEFT JOINs opcionales
try {
    $document = db()->selectOne(
        "SELECT d.*,
         dc.name as category_name, 
         dc.color as category_color, 
         dc.icon as category_icon,
         CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as uploaded_by_name,
         u.email as uploader_email
         FROM documents d
         LEFT JOIN document_categories dc ON d.category_id = dc.id
         LEFT JOIN users u ON d.uploaded_by = u.id
         WHERE d.id = ?",
        [$documentId]
    );
    
    if(!$document) {
        error_log("ERROR: No se pudo obtener datos del documento");
        setFlashMessage('error', 'Error al cargar documento');
        header('Location: documentos.php');
        exit;
    }
    
    error_log("✓ Documento cargado: " . $document['document_name']);
    
} catch(Exception $e) {
    error_log("ERROR en consulta de documento: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    setFlashMessage('error', 'Error de base de datos al cargar documento');
    header('Location: documentos.php');
    exit;
}

// Obtener nombre de entidad relacionada si existe
$relatedEntityName = null;
if(!empty($document['related_entity_type']) && !empty($document['related_entity_id'])) {
    try {
        if($document['related_entity_type'] === 'client') {
            $entity = db()->selectOne(
                "SELECT CONCAT(first_name, ' ', last_name) as name FROM clients WHERE id = ?",
                [$document['related_entity_id']]
            );
            $relatedEntityName = $entity['name'] ?? null;
        } elseif($document['related_entity_type'] === 'property') {
            $entity = db()->selectOne(
                "SELECT CONCAT(reference, ' - ', title) as name FROM properties WHERE id = ?",
                [$document['related_entity_id']]
            );
            $relatedEntityName = $entity['name'] ?? null;
        }
    } catch(Exception $e) {
        error_log("Advertencia: No se pudo cargar entidad relacionada - " . $e->getMessage());
    }
}

// Registrar vista
try {
    db()->insert('document_activity', [
        'document_id' => $documentId,
        'user_id' => $currentUser['id'],
        'action' => 'viewed',
        'action_details' => 'Documento visualizado',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    error_log("✓ Vista registrada en document_activity");
} catch(Exception $e) {
    error_log("Advertencia: No se pudo registrar vista - " . $e->getMessage());
}

// Obtener historial de actividad
try {
    $activities = db()->select(
        "SELECT da.*, 
         CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, 'Usuario')) as user_name,
         u.profile_picture
         FROM document_activity da
         LEFT JOIN users u ON da.user_id = u.id
         WHERE da.document_id = ?
         ORDER BY da.created_at DESC
         LIMIT 50",
        [$documentId]
    );
} catch(Exception $e) {
    error_log("Advertencia: No se pudo cargar actividad - " . $e->getMessage());
    $activities = [];
}

// Obtener comentarios
try {
    $comments = db()->select(
        "SELECT dc.*, 
         CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, 'Usuario')) as user_name,
         u.profile_picture
         FROM document_comments dc
         LEFT JOIN users u ON dc.user_id = u.id
         WHERE dc.document_id = ?
         ORDER BY dc.created_at ASC",
        [$documentId]
    );
} catch(Exception $e) {
    error_log("Advertencia: No se pudo cargar comentarios - " . $e->getMessage());
    $comments = [];
}

// Decodificar tags
$tags = !empty($document['tags']) ? json_decode($document['tags'], true) : [];
if(!is_array($tags)) $tags = [];

// Determinar tipo de archivo y si se puede previsualizar
$fileExt = strtolower(pathinfo($document['file_name'] ?? '', PATHINFO_EXTENSION));
$canPreview = in_array($fileExt, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
$isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
$isPDF = $fileExt === 'pdf';
$isOffice = in_array($fileExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']);

error_log("=== CARGA EXITOSA - Mostrando página ===");

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

.document-viewer-container {
    padding: 30px;
    background: #f8f9fa;
    min-height: calc(100vh - 80px);
}

/* ============ HEADER DEL DOCUMENTO ============ */
.document-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    border-radius: 16px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.document-icon-large {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    margin-right: 20px;
    backdrop-filter: blur(10px);
}

.action-btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 10px 20px;
    background: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-action:hover {
    background: rgba(255,255,255,0.3);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-2px);
    color: white;
}

.btn-action.btn-danger {
    background: rgba(239, 68, 68, 0.3);
    border-color: rgba(239, 68, 68, 0.5);
}

.btn-action.btn-danger:hover {
    background: rgba(239, 68, 68, 0.5);
}

/* ============ INFO CARDS ============ */
.info-card {
    background: white;
    border-radius: 16px;
    padding: 25px;
    border: 1px solid #e5e7eb;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.info-card h5 {
    font-size: 18px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-card h5 i {
    color: var(--primary);
}

.info-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid #f3f4f6;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #6b7280;
    min-width: 180px;
    font-size: 14px;
}

.info-value {
    color: #1f2937;
    flex: 1;
    font-size: 14px;
}

/* ============ PREVIEW CONTAINER ============ */
.preview-container {
    width: 100%;
    height: 700px;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    background: #f9fafb;
    position: relative;
}

.preview-container iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.preview-container img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}

/* ============ NO PREVIEW AVAILABLE ============ */
.no-preview-card {
    text-align: center;
    padding: 80px 40px;
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    border-radius: 16px;
}

.no-preview-icon {
    width: 120px;
    height: 120px;
    background: white;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 64px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.no-preview-icon.pdf { color: #dc2626; }
.no-preview-icon.word { color: #2563eb; }
.no-preview-icon.excel { color: #059669; }
.no-preview-icon.other { color: #6b7280; }

/* ============ TAGS ============ */
.tag-badge {
    display: inline-block;
    padding: 6px 14px;
    background: #f3f4f6;
    color: #374151;
    border-radius: 20px;
    font-size: 12px;
    margin-right: 8px;
    margin-bottom: 8px;
    font-weight: 600;
}

/* ============ ACTIVITY & COMMENTS ============ */
.activity-item {
    display: flex;
    padding: 15px;
    border-bottom: 1px solid #f3f4f6;
    transition: all 0.2s;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item:hover {
    background: #f9fafb;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    font-size: 16px;
    flex-shrink: 0;
}

.activity-uploaded { background: #dbeafe; color: #1e40af; }
.activity-viewed { background: #f3e8ff; color: #7c3aed; }
.activity-downloaded { background: #d1fae5; color: #065f46; }
.activity-shared { background: #fef3c7; color: #92400e; }
.activity-edited { background: #fce7f3; color: #be185d; }

.comment-item {
    background: #f9fafb;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid #e5e7eb;
}

.comment-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.comment-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
}

.comment-form textarea {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 12px;
    width: 100%;
    resize: vertical;
    min-height: 100px;
    font-size: 14px;
}

.comment-form textarea:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

.btn-primary-modern:hover {
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    transform: translateY(-2px);
    color: white;
}

.btn-danger-modern {
    background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
    color: white;
}

.btn-danger-modern:hover {
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
    transform: translateY(-2px);
    color: white;
}

/* ============ RESPONSIVE ============ */
@media (max-width: 768px) {
    .document-viewer-container {
        padding: 15px;
    }
    
    .document-header {
        padding: 25px;
    }
    
    .action-btn-group {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .preview-container {
        height: 400px;
    }
}
</style>

<div class="document-viewer-container">
    
    <!-- Header del Documento -->
    <div class="document-header">
        <div class="d-flex align-items-start flex-wrap">
            <div class="document-icon-large">
                <?php
                if($isPDF) {
                    echo '<i class="fas fa-file-pdf"></i>';
                } elseif($isImage) {
                    echo '<i class="fas fa-file-image"></i>';
                } elseif(in_array($fileExt, ['doc', 'docx'])) {
                    echo '<i class="fas fa-file-word"></i>';
                } elseif(in_array($fileExt, ['xls', 'xlsx'])) {
                    echo '<i class="fas fa-file-excel"></i>';
                } else {
                    echo '<i class="fas fa-file"></i>';
                }
                ?>
            </div>
            
            <div class="flex-grow-1">
                <h2 style="margin: 0 0 10px 0; font-size: 28px;">
                    <?php echo htmlspecialchars($document['document_name']); ?>
                </h2>
                
                <?php if(!empty($document['document_number'])): ?>
                <div style="font-size: 16px; opacity: 0.9; margin-bottom: 10px;">
                    <strong>Nº:</strong> <?php echo htmlspecialchars($document['document_number']); ?>
                </div>
                <?php endif; ?>
                
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <?php if(!empty($document['category_name'])): ?>
                    <span class="badge" style="background: rgba(255,255,255,0.3); font-size: 13px; padding: 6px 14px;">
                        <i class="fas <?php echo $document['category_icon'] ?? 'fa-folder'; ?>"></i>
                        <?php echo htmlspecialchars($document['category_name']); ?>
                    </span>
                    <?php endif; ?>
                    
                    <span class="badge" style="background: rgba(255,255,255,0.3); font-size: 13px; padding: 6px 14px;">
                        <i class="fas fa-code-branch"></i> Versión <?php echo $document['version'] ?? 1; ?>
                    </span>
                    
                    <?php if(!empty($document['is_signed'])): ?>
                    <span class="badge" style="background: #10b981; font-size: 13px; padding: 6px 14px;">
                        <i class="fas fa-certificate"></i> Firmado
                    </span>
                    <?php endif; ?>
                    
                    <?php if(!empty($document['is_template'])): ?>
                    <span class="badge" style="background: #ec4899; font-size: 13px; padding: 6px 14px;">
                        <i class="fas fa-copy"></i> Plantilla
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="action-btn-group" style="margin-left: 20px;">
                <a href="<?php echo $document['file_path']; ?>" download class="btn-action">
                    <i class="fas fa-download"></i> Descargar
                </a>
                <button class="btn-action" onclick="printDocument()">
                    <i class="fas fa-print"></i> Imprimir
                </button>
                <button class="btn-action btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
                <a href="documentos.php" class="btn-action">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        
        <!-- Columna Principal (Previsualización) -->
        <div class="col-lg-8">
            
            <!-- Previsualización del Documento -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-eye"></i> Previsualización del Documento
                </h5>
                
                <?php if($canPreview): ?>
                    <div class="preview-container">
                        <?php if($isPDF): ?>
                            <!-- Visor de PDF -->
                            <iframe src="<?php echo $document['file_path']; ?>#toolbar=0&navpanes=0&scrollbar=0" 
                                    type="application/pdf">
                            </iframe>
                            
                        <?php elseif($isImage): ?>
                            <!-- Visor de Imagen -->
                            <img src="<?php echo $document['file_path']; ?>" 
                                 alt="<?php echo htmlspecialchars($document['document_name']); ?>"
                                 onclick="openImageFullscreen(this.src)"
                                 style="cursor: zoom-in;">
                                 
                        <?php endif; ?>
                    </div>
                    
                    <?php if($isImage): ?>
                    <p style="text-align: center; color: #6b7280; margin-top: 10px; font-size: 13px;">
                        <i class="fas fa-info-circle"></i> Haz clic en la imagen para verla en pantalla completa
                    </p>
                    <?php endif; ?>
                    
                <?php elseif($isOffice): ?>
                    <!-- Documentos de Office -->
                    <div class="no-preview-card">
                        <div class="no-preview-icon <?php echo in_array($fileExt, ['doc', 'docx']) ? 'word' : 'excel'; ?>">
                            <i class="fas fa-file-<?php echo in_array($fileExt, ['doc', 'docx']) ? 'word' : 'excel'; ?>"></i>
                        </div>
                        <h3 style="color: #2d3748; margin-bottom: 10px;">
                            Documento de Microsoft <?php echo in_array($fileExt, ['doc', 'docx']) ? 'Word' : 'Excel'; ?>
                        </h3>
                        <p style="color: #6b7280; margin-bottom: 20px; max-width: 500px; margin-left: auto; margin-right: auto;">
                            Los documentos de Office no pueden visualizarse directamente en el navegador. 
                            Descarga el archivo para abrirlo con la aplicación correspondiente.
                        </p>
                        <a href="<?php echo $document['file_path']; ?>" download class="btn-modern btn-primary-modern">
                            <i class="fas fa-download"></i> Descargar y Abrir con 
                            <?php echo in_array($fileExt, ['doc', 'docx']) ? 'Word' : 'Excel'; ?>
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Otros tipos de archivo -->
                    <div class="no-preview-card">
                        <div class="no-preview-icon other">
                            <i class="fas fa-file"></i>
                        </div>
                        <h3 style="color: #2d3748; margin-bottom: 10px;">
                            Previsualización no disponible
                        </h3>
                        <p style="color: #6b7280; margin-bottom: 20px;">
                            Este tipo de archivo (<?php echo strtoupper($fileExt); ?>) no puede visualizarse en el navegador.
                        </p>
                        <a href="<?php echo $document['file_path']; ?>" download class="btn-modern btn-primary-modern">
                            <i class="fas fa-download"></i> Descargar Archivo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Descripción -->
            <?php if(!empty($document['description'])): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-align-left"></i> Descripción
                </h5>
                <p style="color: #4b5563; line-height: 1.8; margin: 0;">
                    <?php echo nl2br(htmlspecialchars($document['description'])); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Comentarios -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-comments"></i> Comentarios (<?php echo count($comments); ?>)
                </h5>
                
                <!-- Lista de comentarios -->
                <?php if(empty($comments)): ?>
                    <p style="text-align: center; color: #9ca3af; padding: 20px 0;">
                        No hay comentarios aún. Sé el primero en comentar.
                    </p>
                <?php else: ?>
                    <?php foreach($comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <div class="comment-avatar">
                                <?php echo strtoupper(substr($comment['user_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong style="color: #2d3748; font-size: 14px;">
                                    <?php echo htmlspecialchars($comment['user_name']); ?>
                                </strong>
                                <div style="color: #9ca3af; font-size: 12px;">
                                    <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <p style="color: #4b5563; margin: 0; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Formulario para agregar comentario -->
                <form class="comment-form" id="commentForm" style="margin-top: 20px;">
                    <label style="font-weight: 600; color: #4b5563; margin-bottom: 8px; display: block;">
                        Agregar Comentario
                    </label>
                    <textarea id="commentText" placeholder="Escribe tu comentario aquí..." required></textarea>
                    <button type="submit" class="btn-modern btn-primary-modern" style="margin-top: 10px;">
                        <i class="fas fa-paper-plane"></i> Enviar Comentario
                    </button>
                </form>
            </div>
            
        </div>
        
        <!-- Columna Lateral (Información) -->
        <div class="col-lg-4">
            
            <!-- Información del Documento -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-info-circle"></i> Información Completa
                </h5>
                
                <div class="info-row">
                    <div class="info-label">Nombre</div>
                    <div class="info-value">
                        <strong><?php echo htmlspecialchars($document['document_name']); ?></strong>
                    </div>
                </div>
                
                <?php if(!empty($document['document_number'])): ?>
                <div class="info-row">
                    <div class="info-label">Número/Referencia</div>
                    <div class="info-value"><?php echo htmlspecialchars($document['document_number']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($document['category_name'])): ?>
                <div class="info-row">
                    <div class="info-label">Categoría</div>
                    <div class="info-value">
                        <span class="badge" style="background: <?php echo $document['category_color'] ?? '#6b7280'; ?>; color: white; padding: 4px 12px;">
                            <?php echo htmlspecialchars($document['category_name']); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div class="info-value">
                        <span class="badge" style="background: <?php 
                            echo $document['status'] === 'active' ? '#10b981' : 
                                ($document['status'] === 'draft' ? '#6b7280' : '#f59e0b'); 
                        ?>; color: white; padding: 4px 12px;">
                            <?php echo ucfirst($document['status']); ?>
                        </span>
                    </div>
                </div>
                
                <?php if(!empty($document['visibility'])): ?>
                <div class="info-row">
                    <div class="info-label">Visibilidad</div>
                    <div class="info-value"><?php echo ucfirst($document['visibility']); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <div class="info-label">Tipo de Archivo</div>
                    <div class="info-value">
                        <strong style="text-transform: uppercase;"><?php echo $fileExt; ?></strong>
                    </div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Tamaño</div>
                    <div class="info-value"><?php echo formatBytes($document['file_size'] ?? 0); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Subido por</div>
                    <div class="info-value"><?php echo htmlspecialchars($document['uploaded_by_name'] ?? 'Desconocido'); ?></div>
                </div>
                
                <div class="info-row">
                    <div class="info-label">Fecha de Subida</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($document['created_at'])); ?></div>
                </div>
                
                <?php if(!empty($document['expiration_date'])): ?>
                <div class="info-row">
                    <div class="info-label">Fecha de Expiración</div>
                    <div class="info-value" style="color: #ef4444;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo date('d/m/Y', strtotime($document['expiration_date'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($document['is_signed']) && !empty($document['signed_date'])): ?>
                <div class="info-row">
                    <div class="info-label">Fecha de Firma</div>
                    <div class="info-value"><?php echo date('d/m/Y', strtotime($document['signed_date'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if(!empty($document['is_signed']) && !empty($document['signed_by'])): ?>
                <div class="info-row">
                    <div class="info-label">Firmado por</div>
                    <div class="info-value"><?php echo htmlspecialchars($document['signed_by']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if($relatedEntityName): ?>
                <div class="info-row">
                    <div class="info-label">Relacionado con</div>
                    <div class="info-value">
                        <i class="fas fa-<?php echo $document['related_entity_type'] === 'client' ? 'user' : 'building'; ?>"></i>
                        <?php echo htmlspecialchars($relatedEntityName); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Tags -->
            <?php if(!empty($tags)): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-tags"></i> Etiquetas
                </h5>
                <div>
                    <?php foreach($tags as $tag): ?>
                        <span class="tag-badge"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Actividad Reciente -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-history"></i> Actividad Reciente
                </h5>
                
                <?php if(empty($activities)): ?>
                    <p style="text-align: center; color: #9ca3af; padding: 20px 0;">
                        Sin actividad reciente
                    </p>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach(array_slice($activities, 0, 10) as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon activity-<?php echo $activity['action']; ?>">
                                <i class="fas fa-<?php 
                                    echo $activity['action'] === 'uploaded' ? 'cloud-upload-alt' : 
                                        ($activity['action'] === 'viewed' ? 'eye' : 
                                        ($activity['action'] === 'downloaded' ? 'download' : 
                                        ($activity['action'] === 'shared' ? 'share' : 'edit'))); 
                                ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div style="font-size: 13px; color: #2d3748; margin-bottom: 3px;">
                                    <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                    <?php 
                                    $actions = [
                                        'uploaded' => 'subió el documento',
                                        'viewed' => 'visualizó el documento',
                                        'downloaded' => 'descargó el documento',
                                        'shared' => 'compartió el documento',
                                        'edited' => 'editó el documento'
                                    ];
                                    echo $actions[$activity['action']] ?? 'realizó una acción';
                                    ?>
                                </div>
                                <div style="font-size: 12px; color: #9ca3af;">
                                    <?php echo timeAgo($activity['created_at']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
    </div>

</div>

<!-- Modal para imagen en pantalla completa -->
<div id="imageFullscreenModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; cursor: zoom-out;" onclick="closeImageFullscreen()">
    <img id="fullscreenImage" style="max-width: 95%; max-height: 95%; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <button onclick="closeImageFullscreen()" style="position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; font-size: 30px; cursor: pointer; width: 50px; height: 50px; border-radius: 50%; backdrop-filter: blur(10px);">
        <i class="fas fa-times"></i>
    </button>
</div>

<script>
console.log('Ver documento cargado - ID: <?php echo $documentId; ?>');

// ========== IMPRIMIR DOCUMENTO ==========
function printDocument() {
    window.print();
}

// ========== IMAGEN EN PANTALLA COMPLETA ==========
function openImageFullscreen(src) {
    document.getElementById('fullscreenImage').src = src;
    document.getElementById('imageFullscreenModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeImageFullscreen() {
    event.stopPropagation();
    document.getElementById('imageFullscreenModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
    if(e.key === 'Escape') {
        closeImageFullscreen();
    }
});

// ========== ELIMINAR DOCUMENTO ==========
function confirmDelete() {
    if(confirm('¿Estás seguro de que deseas ELIMINAR PERMANENTEMENTE este documento?\n\nEsta acción NO se puede deshacer.\n\nSe eliminará:\n- El archivo del documento\n- Todos los registros relacionados\n- Todo el historial de actividad\n\n¿Continuar?')) {
        deleteDocument();
    }
}

async function deleteDocument() {
    console.log('Eliminando documento ID: <?php echo $documentId; ?>');
    
    try {
        const response = await fetch('ajax/document-actions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'delete',
                id: <?php echo $documentId; ?>
            })
        });
        
        const result = await response.json();
        
        if(result.success) {
            showNotification('success', 'Documento eliminado exitosamente');
            setTimeout(() => {
                window.location.href = 'documentos.php';
            }, 1500);
        } else {
            showNotification('error', result.message || 'Error al eliminar documento');
        }
    } catch(error) {
        console.error('Error:', error);
        showNotification('error', 'Error de conexión al eliminar documento');
    }
}

// ========== AGREGAR COMENTARIO ==========
document.getElementById('commentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const comment = document.getElementById('commentText').value.trim();
    if(!comment) return;
    
    try {
        const response = await fetch('ajax/document-actions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'add_comment',
                document_id: <?php echo $documentId; ?>,
                comment: comment
            })
        });
        
        const result = await response.json();
        
        if(result.success) {
            showNotification('success', 'Comentario agregado exitosamente');
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('error', result.message || 'Error al agregar comentario');
        }
    } catch(error) {
        console.error('Error:', error);
        showNotification('error', 'Error de conexión');
    }
});

// ========== NOTIFICACIONES ==========
function showNotification(type, message) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 9999;
        font-weight: 600;
        animation: slideIn 0.3s ease;
    `;
    
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// CSS para animaciones
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    @media print {
        .document-viewer-container > *:not(.preview-container) {
            display: none !important;
        }
        .preview-container {
            height: auto !important;
            border: none !important;
        }
    }
`;
document.head.appendChild(style);
</script>

<?php include 'footer.php'; ?>