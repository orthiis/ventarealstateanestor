<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Detalle de Consulta';
$currentUser = getCurrentUser();

// Obtener ID de la consulta
$inquiryId = $_GET['id'] ?? 0;

// Obtener datos de la consulta
$inquiry = db()->selectOne(
    "SELECT i.*, 
     CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_name,
     assigned.email as assigned_email,
     p.reference as property_ref, 
     p.title as property_title,
     p.price as property_price,
     p.city as property_city,
     (SELECT image_url FROM property_images WHERE property_id = p.id AND is_main = 1 LIMIT 1) as property_image,
     c.id as client_id,
     CONCAT(c.first_name, ' ', c.last_name) as client_name,
     c.reference as client_reference
     FROM inquiries i
     LEFT JOIN users assigned ON i.assigned_to = assigned.id
     LEFT JOIN properties p ON i.property_id = p.id
     LEFT JOIN clients c ON i.client_id = c.id
     WHERE i.id = ?",
    [$inquiryId]
);

if (!$inquiry) {
    setFlashMessage('error', 'Consulta no encontrada');
    redirect('mensajes.php');
}

// Marcar como leída si es nueva
if ($inquiry['status'] === 'new') {
    db()->update('inquiries', ['status' => 'read'], ['id' => $inquiryId]);
    $inquiry['status'] = 'read';
}

// Obtener usuarios para asignar
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name, email
     FROM users 
     WHERE status = 'active' 
     ORDER BY first_name"
);

// Obtener respuestas/notas de esta consulta
$responses = db()->select(
    "SELECT ir.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
     FROM inquiry_responses ir
     LEFT JOIN users u ON ir.user_id = u.id
     WHERE ir.inquiry_id = ?
     ORDER BY ir.created_at ASC",
    [$inquiryId]
);

include 'header.php';
include 'sidebar.php';
?>

<style>
    .inquiry-container {
        padding: 30px;
        background: #f8f9fa;
    }

    .page-header-modern {
        background: white;
        padding: 25px 30px;
        border-radius: 16px;
        margin-bottom: 25px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 15px;
    }

    .inquiry-detail-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .inquiry-header {
        display: flex;
        justify-content: between;
        align-items: start;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }

    .sender-info {
        flex: 1;
    }

    .sender-avatar {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .sender-name {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 5px 0;
    }

    .sender-contact {
        color: #6b7280;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 5px;
        margin: 5px 0;
    }

    .status-badge {
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        display: inline-block;
    }

    .status-new {
        background: #d1fae5;
        color: #065f46;
    }

    .status-read {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-replied {
        background: #ede9fe;
        color: #5b21b6;
    }

    .status-converted {
        background: #fef3c7;
        color: #92400e;
    }

    .status-archived {
        background: #f3f4f6;
        color: #4b5563;
    }

    .message-content {
        background: #f9fafb;
        padding: 25px;
        border-radius: 12px;
        border-left: 4px solid #667eea;
        margin: 20px 0;
    }

    .message-content p {
        margin: 0;
        line-height: 1.8;
        color: #374151;
        white-space: pre-wrap;
    }

    .info-section {
        margin-bottom: 25px;
    }

    .info-section h5 {
        font-size: 16px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-section h5 i {
        color: #667eea;
    }

    .info-card {
        background: #f9fafb;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 15px;
    }

    .property-card {
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .property-card img {
        width: 100%;
        height: 200px;
        object-fit: cover;
    }

    .property-card-body {
        padding: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 20px;
    }

    .btn-modern {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        transition: all 0.3s;
    }

    .response-section {
        margin-top: 30px;
        padding-top: 30px;
        border-top: 2px solid #e5e7eb;
    }

    .response-item {
        background: #f9fafb;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 15px;
        border-left: 4px solid #667eea;
    }

    .response-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .response-user {
        font-weight: 600;
        color: #1f2937;
    }

    .response-date {
        font-size: 12px;
        color: #9ca3af;
    }

    .response-content {
        color: #374151;
        line-height: 1.6;
    }

    .reply-form {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="inquiry-container">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="mensajes.php">Mensajes</a></li>
                <li class="breadcrumb-item active">Detalle de Consulta</li>
            </ol>
        </nav>

        <div class="row">
            <div class="col-lg-8">
                <!-- Detalle de la Consulta -->
                <div class="inquiry-detail-card">
                    
                    <!-- Header -->
                    <div class="inquiry-header">
                        <div class="sender-info">
                            <div class="sender-avatar">
                                <?php echo strtoupper(substr($inquiry['name'], 0, 1)); ?>
                            </div>
                            <h2 class="sender-name"><?php echo htmlspecialchars($inquiry['name']); ?></h2>
                            <div class="sender-contact">
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>">
                                    <?php echo htmlspecialchars($inquiry['email']); ?>
                                </a>
                            </div>
                            <?php if ($inquiry['phone']): ?>
                            <div class="sender-contact">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?php echo htmlspecialchars($inquiry['phone']); ?>">
                                    <?php echo htmlspecialchars($inquiry['phone']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <div class="sender-contact">
                                <i class="fas fa-calendar"></i>
                                Recibido: <?php echo date('d/m/Y H:i', strtotime($inquiry['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div>
                            <span class="status-badge status-<?php echo $inquiry['status']; ?>">
                                <?php 
                                $statusLabels = [
                                    'new' => 'Nueva',
                                    'read' => 'Leída',
                                    'replied' => 'Respondida',
                                    'converted' => 'Convertida',
                                    'archived' => 'Archivada'
                                ];
                                echo $statusLabels[$inquiry['status']];
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- Asunto -->
                    <?php if ($inquiry['subject']): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-tag"></i> Asunto</h5>
                        <div class="info-card">
                            <?php echo htmlspecialchars($inquiry['subject']); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Mensaje -->
                    <div class="info-section">
                        <h5><i class="fas fa-comment-alt"></i> Mensaje</h5>
                        <div class="message-content">
                            <p><?php echo nl2br(htmlspecialchars($inquiry['message'])); ?></p>
                        </div>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="action-buttons">
                        <?php if ($inquiry['status'] !== 'converted'): ?>
                        <button class="btn btn-success btn-modern" onclick="convertToClient()">
                            <i class="fas fa-user-plus"></i> Convertir en Cliente
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($inquiry['status'] !== 'replied'): ?>
                        <button class="btn btn-primary btn-modern" onclick="document.getElementById('replyForm').scrollIntoView({behavior: 'smooth'})">
                            <i class="fas fa-reply"></i> Responder
                        </button>
                        <?php endif; ?>

                        <button class="btn btn-info btn-modern" data-bs-toggle="modal" data-bs-target="#assignModal">
                            <i class="fas fa-user-tag"></i> Asignar Agente
                        </button>

                        <button class="btn btn-warning btn-modern" onclick="createTask()">
                            <i class="fas fa-tasks"></i> Crear Tarea
                        </button>

                        <?php if ($inquiry['status'] !== 'archived'): ?>
                        <button class="btn btn-outline-secondary btn-modern" onclick="archiveInquiry()">
                            <i class="fas fa-archive"></i> Archivar
                        </button>
                        <?php endif; ?>

                        <a href="mensajes.php" class="btn btn-outline-secondary btn-modern">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                    </div>

                    <!-- Historial de Respuestas -->
                    <?php if (!empty($responses)): ?>
                    <div class="response-section">
                        <h5><i class="fas fa-history"></i> Historial de Respuestas (<?php echo count($responses); ?>)</h5>
                        
                        <?php foreach ($responses as $response): ?>
                        <div class="response-item">
                            <div class="response-header">
                                <div class="response-user">
                                    <i class="fas fa-user-circle"></i>
                                    <?php echo htmlspecialchars($response['user_name']); ?>
                                </div>
                                <div class="response-date">
                                    <?php echo date('d/m/Y H:i', strtotime($response['created_at'])); ?>
                                </div>
                            </div>
                            <div class="response-content">
                                <?php echo nl2br(htmlspecialchars($response['response_text'])); ?>
                            </div>
                            <?php if ($response['sent_email']): ?>
                            <div style="margin-top: 10px; font-size: 12px; color: #10b981;">
                                <i class="fas fa-check-circle"></i> Email enviado
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Formulario de Respuesta -->
                <div class="reply-form" id="replyForm">
                    <h5 style="margin-bottom: 20px;">
                        <i class="fas fa-reply" style="color: #667eea;"></i> Responder Consulta
                    </h5>
                    
                    <form id="responseForm">
                        <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Para:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($inquiry['name'] . ' <' . $inquiry['email'] . '>'); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Asunto:</label>
                            <input type="text" class="form-control" name="subject" 
                                   value="Re: <?php echo htmlspecialchars($inquiry['subject'] ?? 'Consulta sobre propiedad'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Respuesta:</label>
                            <textarea class="form-control" name="response_text" rows="8" required 
                                      placeholder="Escribe tu respuesta aquí..."></textarea>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="send_email" id="sendEmail" checked>
                            <label class="form-check-label" for="sendEmail">
                                Enviar respuesta por email al cliente
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Enviar Respuesta
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('responseForm').reset()">
                                <i class="fas fa-times"></i> Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Propiedad Relacionada -->
                <?php if ($inquiry['property_ref']): ?>
                <div class="info-section">
                    <h5><i class="fas fa-home"></i> Propiedad de Interés</h5>
                    <div class="property-card">
                        <img src="<?php echo $inquiry['property_image'] ?: 'assets/img/no-image.jpg'; ?>" alt="">
                        <div class="property-card-body">
                            <h6 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($inquiry['property_ref']); ?></h6>
                            <p style="font-size: 14px; color: #6b7280; margin: 0 0 10px 0;">
                                <?php echo htmlspecialchars($inquiry['property_title']); ?>
                            </p>
                            <?php if ($inquiry['property_city']): ?>
                            <p style="font-size: 13px; color: #9ca3af; margin: 0 0 10px 0;">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($inquiry['property_city']); ?>
                            </p>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <strong style="color: #10b981; font-size: 18px;">
                                    $<?php echo number_format($inquiry['property_price']); ?>
                                </strong>
                                <a href="ver-propiedad.php?id=<?php echo $inquiry['property_id']; ?>" 
                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Ver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cliente Convertido -->
                <?php if ($inquiry['client_name']): ?>
                <div class="info-section">
                    <h5><i class="fas fa-check-circle" style="color: #10b981;"></i> Cliente Convertido</h5>
                    <div class="info-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo htmlspecialchars($inquiry['client_name']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($inquiry['client_reference']); ?></small>
                            </div>
                            <a href="ver-cliente.php?id=<?php echo $inquiry['client_id']; ?>" 
                               class="btn btn-sm btn-success" target="_blank">
                                <i class="fas fa-user"></i> Ver Cliente
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Agente Asignado -->
                <div class="info-section">
                    <h5><i class="fas fa-user-tie"></i> Agente Asignado</h5>
                    <div class="info-card">
                        <?php if ($inquiry['assigned_name']): ?>
                        <div>
                            <strong><?php echo htmlspecialchars($inquiry['assigned_name']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($inquiry['assigned_email']); ?>
                            </small>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-2">
                            <p class="text-muted mb-2">Sin agente asignado</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
                                <i class="fas fa-user-plus"></i> Asignar Agente
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Información Adicional -->
                <div class="info-section">
                    <h5><i class="fas fa-info-circle"></i> Información Adicional</h5>
                    <div class="info-card">
                        <div style="margin-bottom: 10px;">
                            <small class="text-muted">Página de origen</small>
                            <div style="font-size: 13px; word-break: break-all;">
                                <?php echo htmlspecialchars($inquiry['source_page'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <small class="text-muted">IP del cliente</small>
                            <div style="font-size: 13px;">
                                <?php echo htmlspecialchars($inquiry['ip_address'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div>
                            <small class="text-muted">Fecha de recepción</small>
                            <div style="font-size: 13px;">
                                <?php echo date('d/m/Y H:i:s', strtotime($inquiry['created_at'])); ?>
                            </div>
                        </div>
                        <?php if ($inquiry['replied_at']): ?>
                        <div style="margin-top: 10px;">
                            <small class="text-muted">Última respuesta</small>
                            <div style="font-size: 13px;">
                                <?php echo date('d/m/Y H:i:s', strtotime($inquiry['replied_at'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Asignar Agente -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-tag"></i> Asignar Agente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar Agente:</label>
                        <select class="form-select" name="user_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $inquiry['assigned_to'] == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notify_agent" id="notifyAgent" checked>
                        <label class="form-check-label" for="notifyAgent">
                            Notificar al agente por email
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="submitAssignment()">
                    <i class="fas fa-save"></i> Asignar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Enviar respuesta
document.getElementById('responseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'reply');
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
    
    fetch('ajax/mensaje-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Respuesta enviada exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo enviar la respuesta'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error al enviar la respuesta');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Asignar agente
function submitAssignment() {
    const formData = new FormData(document.getElementById('assignForm'));
    formData.append('action', 'assign');
    formData.append('id', <?php echo $inquiry['id']; ?>);
    
    fetch('ajax/mensaje-actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Agente asignado exitosamente');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo asignar'));
        }
    });
}

// Convertir a cliente
function convertToClient() {
    if (!confirm('¿Convertir esta consulta en cliente?')) return;
    
    // Redirigir a formulario de nuevo cliente con datos pre-llenados
    const params = new URLSearchParams({
        from_inquiry: <?php echo $inquiry['id']; ?>,
        name: '<?php echo addslashes($inquiry['name']); ?>',
        email: '<?php echo addslashes($inquiry['email']); ?>',
        phone: '<?php echo addslashes($inquiry['phone'] ?? ''); ?>',
        property_id: '<?php echo $inquiry['property_id'] ?? ''; ?>'
    });
    
    window.location.href = 'nuevo-cliente.php?' + params.toString();
}

// Crear tarea
function createTask() {
    const params = new URLSearchParams({
        title: 'Seguimiento: <?php echo addslashes($inquiry['name']); ?>',
        description: 'Dar seguimiento a consulta recibida el <?php echo date('d/m/Y', strtotime($inquiry['created_at'])); ?>',
        task_type: 'follow_up',
        property_id: '<?php echo $inquiry['property_id'] ?? ''; ?>'
    });
    
    window.location.href = 'nueva-tarea.php?' + params.toString();
}

// Archivar
function archiveInquiry() {
    if (!confirm('¿Archivar esta consulta?')) return;
    
    fetch('ajax/mensaje-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=archive&id=<?php echo $inquiry['id']; ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Consulta archivada');
            window.location.href = 'mensajes.php';
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo archivar'));
        }
    });
}
</script>

<?php include 'footer.php'; ?>