<?php
// Widget de notificaciones para incluir en el header
$unreadCount = db()->count('notifications', 'user_id = ? AND is_read = 0', [$currentUser['id']]);
$recentNotifications = db()->select(
    "SELECT * FROM notifications 
     WHERE user_id = ? AND is_read = 0 
     ORDER BY created_at DESC 
     LIMIT 5",
    [$currentUser['id']]
);
?>

<style>
.notification-bell {
    position: relative;
    margin-right: 20px;
}

.notification-bell .btn {
    background: transparent;
    border: none;
    color: #6b7280;
    font-size: 20px;
    position: relative;
    padding: 8px;
}

.notification-bell .btn:hover {
    color: #667eea;
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 380px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    margin-top: 10px;
    display: none;
    z-index: 1000;
    max-height: 500px;
    overflow-y: auto;
}

.notification-dropdown.show {
    display: block;
}

.notification-dropdown-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-dropdown-header h6 {
    margin: 0;
    font-weight: 600;
    color: #1f2937;
}

.notification-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background 0.2s;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item.unread {
    background: #f0f4ff;
}

.notification-item-content {
    display: flex;
    gap: 12px;
}

.notification-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-item-text {
    flex: 1;
}

.notification-item-title {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 4px 0;
}

.notification-item-message {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 4px 0;
}

.notification-item-time {
    font-size: 12px;
    color: #9ca3af;
}

.notification-dropdown-footer {
    padding: 12px 20px;
    text-align: center;
    border-top: 1px solid #e5e7eb;
}

.notification-dropdown-footer a {
    color: #667eea;
    font-weight: 600;
    text-decoration: none;
    font-size: 14px;
}

.notification-dropdown-footer a:hover {
    color: #764ba2;
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #9ca3af;
}

.notification-empty i {
    font-size: 48px;
    margin-bottom: 10px;
    opacity: 0.5;
}
</style>

<div class="notification-bell">
    <button class="btn" onclick="toggleNotifications()" id="notificationBell">
        <i class="fas fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="notification-badge" id="notificationBadge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <h6>Notificaciones</h6>
            <?php if ($unreadCount > 0): ?>
            <button class="btn btn-sm btn-link" onclick="markAllNotificationsRead()" style="font-size: 12px; padding: 0;">
                Marcar todas
            </button>
            <?php endif; ?>
        </div>
        
        <div id="notificationList">
            <?php if (empty($recentNotifications)): ?>
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No hay notificaciones</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentNotifications as $notif): 
                    $icon = 'fa-bell';
                    $iconBg = '#dbeafe';
                    $iconColor = '#1e40af';
                    
                    switch ($notif['notification_type']) {
                        case 'new_task':
                        case 'task_assigned':
                            $icon = 'fa-tasks';
                            break;
                        case 'task_due':
                        case 'task_overdue':
                            $icon = 'fa-exclamation-triangle';
                            $iconBg = '#fef3c7';
                            $iconColor = '#92400e';
                            break;
                        case 'new_client':
                        case 'client_assigned':
                            $icon = 'fa-user-plus';
                            $iconBg = '#d1fae5';
                            $iconColor = '#065f46';
                            break;
                        case 'new_inquiry':
                            $icon = 'fa-envelope';
                            break;
                        case 'property_sold':
                        case 'property_rented':
                            $icon = 'fa-check-circle';
                            $iconBg = '#d1fae5';
                            $iconColor = '#065f46';
                            break;
                    }
                ?>
                <div class="notification-item unread" onclick="handleNotificationClick(<?php echo $notif['id']; ?>, '<?php echo $notif['link_url'] ?? ''; ?>')">
                    <div class="notification-item-content">
                        <div class="notification-item-icon" style="background: <?php echo $iconBg; ?>; color: <?php echo $iconColor; ?>;">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-item-text">
                            <p class="notification-item-title"><?php echo htmlspecialchars($notif['title']); ?></p>
                            <?php if ($notif['message']): ?>
                            <p class="notification-item-message"><?php echo substr(htmlspecialchars($notif['message']), 0, 60); ?>...</p>
                            <?php endif; ?>
                            <p class="notification-item-time"><?php echo timeAgo($notif['created_at']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-dropdown-footer">
            <a href="notificaciones.php">Ver todas las notificaciones</a>
        </div>
    </div>
</div>

<script>
function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const bell = document.getElementById('notificationBell');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

function handleNotificationClick(notificationId, linkUrl) {
    // Marcar como leída
    fetch('ajax/notification-actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&id=${notificationId}`
    });
    
    // Redirigir si hay URL
    if (linkUrl && linkUrl !== '') {
        setTimeout(() => {
            window.location.href = linkUrl;
        }, 200);
    }
}

function markAllNotificationsRead() {
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
        }
    });
}

// Polling para actualizar notificaciones cada 30 segundos
setInterval(function() {
    fetch('ajax/notification-actions.php?action=get_notifications')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
            }
        });
}, 30000);

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        if (!badge) {
            const bell = document.getElementById('notificationBell');
            const newBadge = document.createElement('span');
            newBadge.className = 'notification-badge';
            newBadge.id = 'notificationBadge';
            newBadge.textContent = count > 9 ? '9+' : count;
            bell.appendChild(newBadge);
        } else {
            badge.textContent = count > 9 ? '9+' : count;
        }
    } else if (badge) {
        badge.remove();
    }
}
</script>