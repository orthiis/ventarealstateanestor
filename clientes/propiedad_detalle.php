<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';
require_once 'includes/functions.php';

session_start();
requireClientLogin();

$currentClient = getCurrentClient();
$propertyId = $_GET['id'] ?? 0;

// Verificar que la propiedad pertenece al cliente
$property = getClientProperty($currentClient['id'], $propertyId);

if (!$property) {
    $_SESSION['flash_message'] = 'Propiedad no encontrada o no tienes acceso a ella.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: /clientes/propiedades.php');
    exit;
}

$pageTitle = $property['property_title'];

// Obtener documentos, comentarios e imágenes
$documents = getPropertyDocuments($currentClient['id'], $propertyId);
$comments = getPropertyComments($currentClient['id'], $propertyId);
$images = db()->select("SELECT * FROM property_images WHERE property_id = ? ORDER BY is_main DESC, display_order ASC", [$propertyId]);

// Marcar mensajes como leídos
markCommentsAsRead($currentClient['id'], $propertyId);

include 'includes/header.php';
?>

<style>
    .property-gallery {
        position: relative;
        height: 400px;
        overflow: hidden;
        border-radius: 12px;
    }
    
    .property-gallery img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .gallery-thumbnails {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding: 10px 0;
    }
    
    .gallery-thumbnail {
        width: 100px;
        height: 80px;
        border-radius: 8px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s;
    }
    
    .gallery-thumbnail:hover,
    .gallery-thumbnail.active {
        border-color: var(--primary-color);
    }
    
    .gallery-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .info-row {
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .document-item {
        padding: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .document-item:hover {
        background: #f8fafc;
        border-color: var(--primary-color);
    }
</style>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/clientes/dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="/clientes/propiedades.php">Mis Propiedades</a></li>
        <li class="breadcrumb-item active"><?php echo htmlspecialchars($property['property_reference']); ?></li>
    </ol>
</nav>

<!-- Property Header -->
<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-2"><?php echo htmlspecialchars($property['property_title']); ?></h1>
            <p class="text-muted mb-0">
                <i class="fas fa-map-marker-alt me-2"></i>
                <?php echo htmlspecialchars($property['address']); ?>, <?php echo htmlspecialchars($property['city']); ?>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <?php echo getTransactionTypeBadge($property['transaction_type']); ?>
            <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($property['property_reference']); ?></span>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-lg-8">
        <!-- Gallery -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <?php if (!empty($images)): ?>
                    <div class="property-gallery" id="mainGallery">
                        <img src="<?php echo htmlspecialchars($images[0]['image_url']); ?>" alt="Propiedad" id="mainImage">
                    </div>
                    <?php if (count($images) > 1): ?>
                        <div class="gallery-thumbnails px-3 pb-3">
                            <?php foreach ($images as $index => $image): ?>
                                <div class="gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     onclick="changeImage('<?php echo htmlspecialchars($image['image_url']); ?>', this)">
                                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>" alt="Thumbnail">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="property-gallery bg-light d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <i class="fas fa-image fa-5x text-muted mb-3"></i>
                            <p class="text-muted">No hay imágenes disponibles</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Property Information -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i> Información de la Propiedad</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <small class="text-muted d-block">Tipo de Propiedad</small>
                            <strong><?php echo htmlspecialchars($property['property_type'] ?? 'N/A'); ?></strong>
                        </div>
                        <div class="info-row">
                            <small class="text-muted d-block">Habitaciones</small>
                            <strong><i class="fas fa-bed me-1"></i> <?php echo $property['bedrooms'] ?? 'N/A'; ?></strong>
                        </div>
                        <div class="info-row">
                            <small class="text-muted d-block">Baños</small>
                            <strong><i class="fas fa-bath me-1"></i> <?php echo $property['bathrooms'] ?? 'N/A'; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <small class="text-muted d-block">Área Total</small>
                            <strong><?php echo number_format($property['area_total'] ?? 0, 2); ?> m²</strong>
                        </div>
                        <div class="info-row">
                            <small class="text-muted d-block">Estacionamientos</small>
                            <strong><i class="fas fa-car me-1"></i> <?php echo $property['parking_spaces'] ?? 'N/A'; ?></strong>
                        </div>
                        <div class="info-row">
                            <small class="text-muted d-block">Año de Construcción</small>
                            <strong><?php echo $property['year_built'] ?? 'N/A'; ?></strong>
                        </div>
                    </div>
                </div>
                
                <?php if ($property['description']): ?>
                    <hr>
                    <h6 class="mb-2">Descripción</h6>
                    <p class="text-muted mb-0"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Transaction Details -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-file-contract me-2 text-success"></i> Detalles de la Transacción</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-row">
                            <small class="text-muted d-block">Código de Transacción</small>
                            <strong><?php echo htmlspecialchars($property['transaction_code']); ?></strong>
                        </div>
                        <?php if ($property['transaction_type'] == 'sale'): ?>
                            <div class="info-row">
                                <small class="text-muted d-block">Precio de Venta</small>
                                <strong class="text-success">$<?php echo number_format($property['sale_price'], 2); ?></strong>
                            </div>
                            <div class="info-row">
                                <small class="text-muted d-block">Fecha de Cierre</small>
                                <strong><?php echo date('d/m/Y', strtotime($property['closing_date'])); ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="info-row">
                                <small class="text-muted d-block">Renta Mensual</small>
                                <strong class="text-primary">$<?php echo number_format($property['monthly_payment'], 2); ?></strong>
                            </div>
                            <div class="info-row">
                                <small class="text-muted d-block">Duración del Contrato</small>
                                <strong><?php echo $property['rent_duration_months']; ?> meses</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="info-row">
                            <small class="text-muted d-block">Estado de Pago</small>
                            <?php
                            $paymentBadges = [
                                'pending' => '<span class="badge bg-warning">Pendiente</span>',
                                'partial' => '<span class="badge bg-info">Pago Parcial</span>',
                                'completed' => '<span class="badge bg-success">Completado</span>',
                                'overdue' => '<span class="badge bg-danger">Atrasado</span>',
                            ];
                            echo $paymentBadges[$property['payment_status']] ?? '<span class="badge bg-secondary">Desconocido</span>';
                            ?>
                        </div>
                        <?php if ($property['move_in_date']): ?>
                            <div class="info-row">
                                <small class="text-muted d-block">Fecha de Mudanza</small>
                                <strong><?php echo date('d/m/Y', strtotime($property['move_in_date'])); ?></strong>
                            </div>
                        <?php endif; ?>
                        <?php if ($property['rent_end_date']): ?>
                            <div class="info-row">
                                <small class="text-muted d-block">Fin del Contrato</small>
                                <strong><?php echo date('d/m/Y', strtotime($property['rent_end_date'])); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Documents Section -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-folder-open me-2 text-warning"></i> Mis Documentos</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                    <i class="fas fa-upload me-1"></i> Subir Documento
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-file fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No has subido documentos aún.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($documents as $doc): ?>
                            <div class="col-md-6">
                                <div class="document-item d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="fas <?php echo getFileIcon($doc['file_type']); ?> fa-2x"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($doc['document_name']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo formatFileSize($doc['file_size']); ?> • 
                                            <?php echo date('d/m/Y', strtotime($doc['upload_date'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                           class="btn btn-sm btn-outline-primary" 
                                           download 
                                           target="_blank">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Agent Info -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user-tie me-2 text-info"></i> Tu Agente</h5>
            </div>
            <div class="card-body text-center">
                <?php if ($property['agent_picture']): ?>
                    <img src="<?php echo htmlspecialchars($property['agent_picture']); ?>" 
                         class="rounded-circle mb-3" 
                         style="width: 80px; height: 80px; object-fit: cover;" 
                         alt="Agente">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-4x text-muted mb-3"></i>
                <?php endif; ?>
                <h6 class="mb-2"><?php echo htmlspecialchars($property['agent_name']); ?></h6>
                <?php if ($property['agent_email']): ?>
                    <p class="mb-1 small">
                        <i class="fas fa-envelope me-1"></i>
                        <a href="mailto:<?php echo htmlspecialchars($property['agent_email']); ?>">
                            <?php echo htmlspecialchars($property['agent_email']); ?>
                        </a>
                    </p>
                <?php endif; ?>
                <?php if ($property['agent_phone']): ?>
                    <p class="mb-0 small">
                        <i class="fas fa-phone me-1"></i>
                        <a href="tel:<?php echo htmlspecialchars($property['agent_phone']); ?>">
                            <?php echo htmlspecialchars($property['agent_phone']); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chat/Comments Section -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-comments me-2 text-primary"></i> Mensajes</h5>
            </div>
            <div class="card-body p-0">
                <div class="chat-container" id="chatContainer">
                    <?php if (empty($comments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-comment fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No hay mensajes aún.<br>Envía tu primera consulta.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="message <?php echo $comment['sender_type']; ?>">
                                <div class="message-bubble">
                                    <?php if ($comment['sender_type'] == 'admin' && $comment['admin_name']): ?>
                                        <strong class="d-block mb-1"><?php echo htmlspecialchars($comment['admin_name']); ?></strong>
                                    <?php endif; ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($comment['message'])); ?></p>
                                    <div class="message-time">
                                        <?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white">
                <form id="chatForm" onsubmit="sendMessage(event)">
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="messageInput" 
                               placeholder="Escribe tu mensaje..." 
                               required>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Subir Documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadDocumentForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="property_id" value="<?php echo $propertyId; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre del Documento *</label>
                        <input type="text" name="document_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descripción (Opcional)</label>
                        <textarea name="document_description" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Archivo *</label>
                        <input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                        <small class="text-muted">Formatos permitidos: PDF, JPG, PNG, DOC, DOCX (Máx. 5MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-1"></i> Subir Documento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additionalScripts = <<<HTML
<script>
// Gallery functionality
function changeImage(imageUrl, element) {
    document.getElementById('mainImage').src = imageUrl;
    document.querySelectorAll('.gallery-thumbnail').forEach(thumb => {
        thumb.classList.remove('active');
    });
    element.classList.add('active');
}

// Chat functionality
function sendMessage(e) {
    e.preventDefault();
    
    const messageInput = document.getElementById('messageInput');
    const message = messageInput.value.trim();
    
    if (!message) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('property_id', '{$propertyId}');
    formData.append('message', message);
    
    fetch('/clientes/ajax/chat_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageInput.value = '';
            loadMessages();
        } else {
            alert('Error al enviar mensaje: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al enviar el mensaje');
    });
}

function loadMessages() {
    fetch('/clientes/ajax/chat_handler.php?action=get_messages&property_id={$propertyId}')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const chatContainer = document.getElementById('chatContainer');
            chatContainer.innerHTML = '';
            
            if (data.messages.length === 0) {
                chatContainer.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-comment fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hay mensajes aún.<br>Envía tu primera consulta.</p>
                    </div>
                `;
            } else {
                data.messages.forEach(msg => {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'message ' + msg.sender_type;
                    
                    let adminName = '';
                    if (msg.sender_type === 'admin' && msg.admin_name) {
                        adminName = '<strong class="d-block mb-1">' + msg.admin_name + '</strong>';
                    }
                    
                    messageDiv.innerHTML = `
                        <div class="message-bubble">
                            \${adminName}
                            <p class="mb-0">\${msg.message.replace(/\n/g, '<br>')}</p>
                            <div class="message-time">\${msg.created_at}</div>
                        </div>
                    `;
                    
                    chatContainer.appendChild(messageDiv);
                });
                
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }
    });
}

// Upload document
document.getElementById('uploadDocumentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('/clientes/ajax/upload_document.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Documento subido exitosamente');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al subir el documento');
    });
});

// Auto-refresh messages every 30 seconds
setInterval(loadMessages, 30000);
</script>
HTML;

include 'includes/footer.php';
?>