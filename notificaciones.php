<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Notificaciones';
$currentUser = getCurrentUser();

// Obtener filtros
$filter = $_GET['filter'] ?? 'all';

// Construir query
$where = ["user_id = ?"];
$params = [$currentUser['id']];

if ($filter === 'unread') {
    $where[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where[] = "is_read = 1";
}

$whereClause = implode(' AND ', $where);

// Obtener notificaciones
$notifications = db()->select(
    "SELECT * FROM notifications 
     WHERE {$whereClause}
     ORDER BY created_at DESC",
    $params
);

// Estadísticas
$stats = [
    'total' => db()->count('notifications', 'user_id = ?', [$currentUser['id']]),
    'unread' => db()->count('notifications', 'user_id = ? AND is_read = 0', [$currentUser['id']]),
    'read' => db()->count('notifications', 'user_id = ? AND is_read = 1', [$currentUser['id']])
];

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

    .notifications-container {
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

    .page-subtitle-modern {
        color: #718096;
        margin: 0;
        font-size: 14px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        margin: 10px 0;
    }

    .stat-label {
        color: #6b7280;
        font-size: 14px;
    }

    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
    }

    .filter-btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: 2px solid #e5e7eb;
        background: white;
        color: #6b7280;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
    }

    .filter-btn:hover {
        border-color: #667eea;
        color: #667eea;
    }

    .filter-btn.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        color: white;
    }

    .notification-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: all 0.3s;
        cursor: pointer;
        border-left: 4px solid #e5e7eb;
    }

    .notification-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    .notification-card.unread {
        background: #f0f4ff;
        border-left-color: #667eea;
    }

    .notification-header {
        display: flex;
        align-items: start;
        gap: 15px;
        margin-bottom: 10px;
    }

    .notification-icon {
        width: 45px;
        height: 45px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }

    .icon-success { background: #d1fae5; color: #065f46; }
    .icon-info { background: #dbeafe; color: #1e40af; }
    .icon-warning { background: #fef3c7; color: #92400e; }
    .icon-danger { background: #fee2e2; color: #991b1b; }

    .notification-content {
        flex: 1;
    }

    .notification-title {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        margin: 0 0 5px 0;
    }

    .notification-message {
        font-size: 14px;
        color: #6b7280;
        margin: 0 0 10px 0;
    }

    .notification-meta {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 13px;
        color: #9ca3af;
    }

    .notification-actions {
        display: flex;
        gap: 8px;
        margin-top: 10px;
    }

    .btn-notification {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: white;
        border-radius: 12px;
    }

    .empty-state i {
        font-size: 64px;
        color: #e2e8f0;
        margin-bottom: 20px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <div class="notifications-container">
        
        <!-- Header -->
        <div class="page-header-modern d-flex justify-content-between align-items-center">
            <div>
                <h2 class="page-title-modern">
                    <i class="fas fa-bell" style="color: #667eea;"></i> Notificaciones
                </h2>
                <p class="page-subtitle-modern">Mantente al día con todas tus alertas y actualizaciones</p>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card" style="border-top: 4px solid #667eea;">
                <div class="stat-label">Total</div>
                <div class="stat-number" style="color: #667eea;"><?php echo $stats['total']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #f59e0b;">
                <div class="stat-label">No Leídas</div>
                <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['unread']; ?></div>
            </div>
            <div class="stat-card" style="border-top: 4px solid #10b981;">
                <div class="stat-label">Leídas</div>
                <div class="stat-number" style="color: #10b981;"><?php echo $stats['read']; ?></div>
            </div>
        </div>

        <!-- Filtros y Acciones -->
        <div class="filter-bar">
            <div class="filter-buttons">
                <a href="notificaciones.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i> Todas
                </a>
                <a href="notificaciones.php?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> No Leídas
                </a>
                <a href="notificaciones.php?filter=read" class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open"></i> Leídas
                </a>
            </div>

            <div class="action-buttons">
                <button class="btn btn-outline-primary btn-sm" onclick="markAllAsRead()">
                    <i class="fas fa-check-double"></i> Marcar todas como leídas
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteAllRead()">
                    <i class="fas fa-trash"></i> Eliminar leídas
                </button>
            </div>
        </div>

        <!-- Listado de Notificaciones -->
        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h4 style="color: #6b7280;">No hay notificaciones</h4>
                <p style="color: #9ca3af;">¡Estás al día! No tienes notificaciones nuevas.</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notif): 
                $isUnread = $notif['is_read'] == 0;
                
                // Determinar icono y clase según tipo
                $iconClass = 'icon-info';
                $icon = 'fa-bell';
                
                switch ($notif['notification_type']) {
                    case 'new_task':
                    case 'task_assigned':
                        $icon = 'fa-tasks';
                        $iconClass = 'icon-info';
                        break;
                    case 'task_due':
                    case 'task_overdue':
                        $icon = 'fa-exclamation-triangle';
                        $iconClass = 'icon-warning';
                        break;
                    case 'new_client':
                    case 'client_assigned':
                        $icon = 'fa-user-plus';
                        $iconClass = 'icon-success';
                        break;
                    case 'new_inquiry':
                        $icon = 'fa-envelope';
                        $iconClass = 'icon-info';
                        break;
                    case 'property_sold':
                    case 'property_rented':
                        $icon = 'fa-check-circle';
                        $iconClass = 'icon-success';
                        break;
                    case 'new_property':
                        $icon = 'fa-home';
                        $iconClass = 'icon-info';
                        break;
                    case 'system':
                        $icon = 'fa-cog';
                        $iconClass = 'icon-info';
                        break;
                }
            ?>
            <div class="notification-card <?php echo $isUnread ? 'unread' : ''; ?>" 
                 onclick="handleNotificationClick(<?php echo $notif['id']; ?>, '<?php echo $notif['link_url'] ?? ''; ?>')">
                
                <div class="notification-header">
                    <div class="notification-icon <?php echo $iconClass; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    
                    <div class="notification-content">
                        <h5 class="notification-title">
                            <?php if ($isUnread): ?>
                                <span style="display: inline-block; width: 8px; height: 8px; background: #667eea; border-radius: 50%; margin-right: 8px;"></span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($notif['title']); ?>
                        </h5>
                        
                        <?php if ($notif['message']): ?>
                        <p class="notification-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="notification-meta">
                            <span>
                                <i class="far fa-clock"></i>
                                <?php echo timeAgo($notif['created_at']); ?>
                            </span>
                            
                            <?php if ($notif['notification_type']): ?>
                            <span>
                                <i class="fas fa-tag"></i>
                                <?php 
                                $typeLabels = [
                                    'new_task' => 'Nueva tarea',
                                    'task_assigned' => 'Tarea asignada',
                                    'task_due' => 'Tarea próxima',
                                    'task_overdue' => 'Tarea vencida',
                                    'new_client' => 'Nuevo cliente',
                                    'client_assigned' => 'Cliente asignado',
                                    'new_inquiry' => 'Nueva consulta',
                                    'property_sold' => 'Propiedad vendida',
                                    'property_rented' => 'Propiedad alquilada',
                                    'new_property' => 'Nueva propiedad',
                                    'system' => 'Sistema'
                                ];
                                echo $typeLabels[$notif['notification_type']] ?? $notif['notification_type'];
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-actions" onclick="event.stopPropagation();">
                            <?php if ($isUnread): ?>
                            <button class="btn btn-notification btn-primary" onclick="markAsRead(<?php echo $notif['id']; ?>)">
                                <i class="fas fa-check"></i> Marcar como leída
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-notification btn-outline-danger" onclick="deleteNotification(<?php echo $notif['id']; ?>)">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function handleNotificationClick(notificationId, linkUrl) {
    // Marcar como leída
    markAsRead(notificationId, false);
    
    // Si tiene URL, redirigir
    if (linkUrl && linkUrl !== '') {
        setTimeout(() => {
            window.location.href = linkUrl;
        }, 300);
    }
}

function markAsRead(notificationId, reload = true) {
    fetch('ajax/notification-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && reload) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function markAllAsRead() {
    if (!confirm('¿Marcar todas las notificaciones como leídas?')) return;
    
    fetch('ajax/notification-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al marcar notificaciones');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al marcar notificaciones');
    });
}

function deleteNotification(notificationId) {
    if (!confirm('¿Eliminar esta notificación?')) return;
    
    fetch('ajax/notification-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete&id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al eliminar notificación');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar notificación');
    });
}

function deleteAllRead() {
    if (!confirm('¿Eliminar todas las notificaciones leídas?')) return;
    
    fetch('ajax/notification-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error al eliminar notificaciones');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar notificaciones');
    });
}
</script>

<?php include 'footer.php'; ?>