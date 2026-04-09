<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Detalle del Evento';
$currentUser = getCurrentUser();

// Obtener ID del evento
$eventId = $_GET['id'] ?? 0;

// Obtener datos del evento
$event = db()->selectOne(
    "SELECT ce.*, 
     CONCAT(c.first_name, ' ', c.last_name) as client_name,
     c.email as client_email,
     c.phone_mobile as client_phone,
     p.reference as property_ref, 
     p.title as property_title,
     p.address as property_address,
     CONCAT(creator.first_name, ' ', creator.last_name) as creator_name,
     creator.email as creator_email
     FROM calendar_events ce
     LEFT JOIN clients c ON ce.related_client_id = c.id
     LEFT JOIN properties p ON ce.related_property_id = p.id
     LEFT JOIN users creator ON ce.created_by = creator.id
     WHERE ce.id = ?",
    [$eventId]
);

if (!$event) {
    setFlashMessage('error', 'Evento no encontrado');
    redirect('calendario.php');
}

// Verificar permisos
if ($currentUser['role']['name'] !== 'administrador' && $event['created_by'] != $currentUser['id']) {
    // Verificar si es asistente
    $attendees = json_decode($event['attendees'] ?? '[]', true);
    if (!in_array($currentUser['id'], $attendees)) {
        setFlashMessage('error', 'No tienes permisos para ver este evento');
        redirect('calendario.php');
    }
}

// Obtener asistentes
$attendees = json_decode($event['attendees'] ?? '[]', true);
$attendeesList = [];
if (!empty($attendees)) {
    $placeholders = implode(',', array_fill(0, count($attendees), '?'));
    $attendeesList = db()->select(
        "SELECT id, CONCAT(first_name, ' ', last_name) as name, email, profile_picture
         FROM users 
         WHERE id IN ($placeholders)",
        $attendees
    );
}

// Decodificar JSON
$reminderText = '';
if ($event['reminder_minutes']) {
    $minutes = $event['reminder_minutes'];
    if ($minutes == 15) $reminderText = '15 minutos antes';
    elseif ($minutes == 30) $reminderText = '30 minutos antes';
    elseif ($minutes == 60) $reminderText = '1 hora antes';
    elseif ($minutes == 1440) $reminderText = '1 día antes';
    elseif ($minutes == 2880) $reminderText = '2 días antes';
    else $reminderText = $minutes . ' minutos antes';
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    .event-container {
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

    .page-title-modern {
        font-size: 28px;
        font-weight: 700;
        color: #2d3748;
        margin: 0 0 5px 0;
    }

    .breadcrumb {
        background: transparent;
        padding: 0;
        margin-bottom: 15px;
    }

    .event-detail-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .event-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }

    .event-title-section h1 {
        font-size: 32px;
        font-weight: 700;
        color: #1f2937;
        margin: 0 0 10px 0;
    }

    .event-type-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        display: inline-block;
    }

    .status-badge {
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-scheduled {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-completed {
        background: #d1fae5;
        color: #065f46;
    }

    .status-cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-rescheduled {
        background: #fef3c7;
        color: #92400e;
    }

    .info-section {
        margin-bottom: 30px;
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

    .info-item {
        display: flex;
        align-items: start;
        gap: 12px;
        padding: 12px;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .info-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
    }

    .info-content {
        flex: 1;
    }

    .info-label {
        font-size: 12px;
        color: #6b7280;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .info-value {
        font-size: 15px;
        color: #1f2937;
        font-weight: 500;
    }

    .attendee-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #f9fafb;
        border-radius: 8px;
        margin-bottom: 10px;
    }

    .attendee-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 18px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn-modern {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        border: none;
        transition: all 0.3s;
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="event-container">
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="calendario.php">Calendario</a></li>
                <li class="breadcrumb-item active">Detalle del Evento</li>
            </ol>
        </nav>

        <!-- Detalle del Evento -->
        <div class="event-detail-card">
            
            <!-- Header -->
            <div class="event-header">
                <div class="event-title-section">
                    <h1><?php echo htmlspecialchars($event['title']); ?></h1>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                        <span class="event-type-badge" style="background: <?php echo $event['color']; ?>; color: white;">
                            <?php 
                            $types = [
                                'visit' => '🏠 Visita',
                                'meeting' => '🤝 Reunión',
                                'call' => '📞 Llamada',
                                'signing' => '✍️ Firma',
                                'deadline' => '⏰ Fecha Límite',
                                'other' => '📌 Otro'
                            ];
                            echo $types[$event['event_type']] ?? $event['event_type'];
                            ?>
                        </span>
                        <span class="status-badge status-<?php echo $event['status']; ?>">
                            <?php 
                            $statuses = [
                                'scheduled' => 'Programado',
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                                'rescheduled' => 'Reprogramado'
                            ];
                            echo $statuses[$event['status']] ?? $event['status'];
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <?php if ($event['status'] === 'scheduled' && 
                              ($currentUser['role']['name'] === 'administrador' || $event['created_by'] == $currentUser['id'])): ?>
                    <button class="btn btn-success btn-modern" onclick="updateEventStatus(<?php echo $event['id']; ?>, 'completed')">
                        <i class="fas fa-check"></i> Marcar como Completado
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($currentUser['role']['name'] === 'administrador' || $event['created_by'] == $currentUser['id']): ?>
                    <a href="editar-evento.php?id=<?php echo $event['id']; ?>" class="btn btn-primary btn-modern">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <button class="btn btn-danger btn-modern" onclick="deleteEvent(<?php echo $event['id']; ?>)">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                    <?php endif; ?>
                    
                    <a href="calendario.php" class="btn btn-outline-secondary btn-modern">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Descripción -->
                    <?php if ($event['description']): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-align-left"></i> Descripción</h5>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="info-content">
                                <p style="margin: 0; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Fecha y Hora -->
                    <div class="info-section">
                        <h5><i class="fas fa-clock"></i> Fecha y Hora</h5>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Inicio</div>
                                <div class="info-value">
                                    <?php echo date('l, d \d\e F \d\e Y - h:i A', strtotime($event['start_datetime'])); ?>
                                </div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-calendar-times"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Fin</div>
                                <div class="info-value">
                                    <?php echo date('l, d \d\e F \d\e Y - h:i A', strtotime($event['end_datetime'])); ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($event['all_day']): ?>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-value">
                                    <span style="color: #f59e0b;">⭐ Evento de todo el día</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php 
                        $duration = (strtotime($event['end_datetime']) - strtotime($event['start_datetime'])) / 60;
                        $hours = floor($duration / 60);
                        $minutes = $duration % 60;
                        ?>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Duración</div>
                                <div class="info-value">
                                    <?php 
                                    if ($hours > 0) echo $hours . ' hora' . ($hours > 1 ? 's' : '');
                                    if ($hours > 0 && $minutes > 0) echo ' y ';
                                    if ($minutes > 0) echo $minutes . ' minuto' . ($minutes > 1 ? 's' : '');
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ubicación -->
                    <?php if ($event['location']): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-map-marker-alt"></i> Ubicación</h5>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-location-arrow"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-value"><?php echo htmlspecialchars($event['location']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recordatorio -->
                    <?php if ($reminderText): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-bell"></i> Recordatorio</h5>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-alarm-clock"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-value"><?php echo $reminderText; ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <!-- Asistentes -->
                    <?php if (!empty($attendeesList)): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-users"></i> Asistentes (<?php echo count($attendeesList); ?>)</h5>
                        <?php foreach ($attendeesList as $attendee): ?>
                        <div class="attendee-card">
                            <?php if ($attendee['profile_picture']): ?>
                                <img src="<?php echo $attendee['profile_picture']; ?>" class="attendee-avatar" alt="">
                            <?php else: ?>
                                <div class="attendee-avatar">
                                    <?php echo strtoupper(substr($attendee['name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo htmlspecialchars($attendee['name']); ?>
                                </div>
                                <div style="font-size: 13px; color: #6b7280;">
                                    <?php echo htmlspecialchars($attendee['email']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Cliente Relacionado -->
                    <?php if ($event['client_name']): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-user-tie"></i> Cliente</h5>
                        <div class="attendee-card">
                            <div class="attendee-avatar" style="background: #10b981;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo htmlspecialchars($event['client_name']); ?>
                                </div>
                                <?php if ($event['client_email']): ?>
                                <div style="font-size: 13px; color: #6b7280;">
                                    📧 <?php echo htmlspecialchars($event['client_email']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['client_phone']): ?>
                                <div style="font-size: 13px; color: #6b7280;">
                                    📱 <?php echo htmlspecialchars($event['client_phone']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Propiedad Relacionada -->
                    <?php if ($event['property_ref']): ?>
                    <div class="info-section">
                        <h5><i class="fas fa-home"></i> Propiedad</h5>
                        <div class="attendee-card">
                            <div class="attendee-avatar" style="background: #3b82f6;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo htmlspecialchars($event['property_ref']); ?>
                                </div>
                                <?php if ($event['property_title']): ?>
                                <div style="font-size: 13px; color: #6b7280;">
                                    <?php echo htmlspecialchars($event['property_title']); ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['property_address']): ?>
                                <div style="font-size: 12px; color: #9ca3af;">
                                    📍 <?php echo htmlspecialchars($event['property_address']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Creado Por -->
                    <div class="info-section">
                        <h5><i class="fas fa-user-check"></i> Información Adicional</h5>
                        <div style="background: #f9fafb; padding: 15px; border-radius: 8px;">
                            <div style="margin-bottom: 10px;">
                                <div style="font-size: 12px; color: #6b7280;">Creado por</div>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo htmlspecialchars($event['creator_name']); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6b7280;">Fecha de creación</div>
                                <div style="font-weight: 500; color: #1f2937;">
                                    <?php echo date('d/m/Y H:i', strtotime($event['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateEventStatus(eventId, newStatus) {
    if (!confirm('¿Estás seguro de cambiar el estado de este evento?')) return;
    
    fetch('ajax/calendar-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_status&id=${eventId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al actualizar el evento');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el evento');
    });
}

function deleteEvent(eventId) {
    if (!confirm('¿Estás seguro de eliminar este evento? Esta acción no se puede deshacer.')) return;
    
    fetch('ajax/calendar-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&id=${eventId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Evento eliminado exitosamente');
            window.location.href = 'calendario.php';
        } else {
            alert('❌ Error: ' + (data.message || 'No se pudo eliminar el evento'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar el evento');
    });
}
</script>

<?php include 'footer.php'; ?>