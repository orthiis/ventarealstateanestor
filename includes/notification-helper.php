<?php
/**
 * Helper para crear notificaciones en el sistema
 */

/**
 * Crear una notificación
 */
function createNotification($userId, $type, $title, $message = null, $linkUrl = null, $relatedEntityType = null, $relatedEntityId = null) {
    try {
        $data = [
            'user_id' => $userId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'link_url' => $linkUrl,
            'icon' => getNotificationIcon($type),
            'is_read' => 0,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id' => $relatedEntityId
        ];
        
        return db()->insert('notifications', $data);
    } catch (Exception $e) {
        error_log("Error creando notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Notificar nueva tarea asignada
 */
function notifyTaskAssigned($taskId, $assignedToUserId, $taskTitle, $assignedByUserName) {
    $title = "Nueva tarea asignada";
    $message = "{$assignedByUserName} te ha asignado la tarea: {$taskTitle}";
    $linkUrl = "ver-tarea.php?id={$taskId}";
    
    return createNotification(
        $assignedToUserId,
        'task_assigned',
        $title,
        $message,
        $linkUrl,
        'task',
        $taskId
    );
}

/**
 * Notificar tarea próxima a vencer
 */
function notifyTaskDueSoon($taskId, $userId, $taskTitle, $hoursLeft) {
    $title = "Tarea próxima a vencer";
    $message = "La tarea '{$taskTitle}' vence en {$hoursLeft} horas";
    $linkUrl = "ver-tarea.php?id={$taskId}";
    
    return createNotification(
        $userId,
        'task_due',
        $title,
        $message,
        $linkUrl,
        'task',
        $taskId
    );
}

/**
 * Notificar tarea vencida
 */
function notifyTaskOverdue($taskId, $userId, $taskTitle) {
    $title = "Tarea vencida";
    $message = "La tarea '{$taskTitle}' está vencida. Por favor complétala lo antes posible.";
    $linkUrl = "ver-tarea.php?id={$taskId}";
    
    return createNotification(
        $userId,
        'task_overdue',
        $title,
        $message,
        $linkUrl,
        'task',
        $taskId
    );
}

/**
 * Notificar nuevo cliente asignado
 */
function notifyClientAssigned($clientId, $assignedToUserId, $clientName, $assignedByUserName) {
    $title = "Nuevo cliente asignado";
    $message = "{$assignedByUserName} te ha asignado el cliente: {$clientName}";
    $linkUrl = "ver-cliente.php?id={$clientId}";
    
    return createNotification(
        $assignedToUserId,
        'client_assigned',
        $title,
        $message,
        $linkUrl,
        'client',
        $clientId
    );
}

/**
 * Notificar nueva consulta/lead
 */
function notifyNewInquiry($inquiryId, $userId, $clientName, $propertyReference) {
    $title = "Nueva consulta recibida";
    $message = "{$clientName} ha enviado una consulta sobre la propiedad {$propertyReference}";
    $linkUrl = "mensajes.php?id={$inquiryId}";
    
    return createNotification(
        $userId,
        'new_inquiry',
        $title,
        $message,
        $linkUrl,
        'inquiry',
        $inquiryId
    );
}

/**
 * Notificar propiedad vendida
 */
function notifyPropertySold($propertyId, $userId, $propertyReference, $salePrice) {
    $title = "¡Propiedad vendida!";
    $message = "La propiedad {$propertyReference} ha sido vendida por " . formatCurrency($salePrice);
    $linkUrl = "ver-propiedad.php?id={$propertyId}";
    
    return createNotification(
        $userId,
        'property_sold',
        $title,
        $message,
        $linkUrl,
        'property',
        $propertyId
    );
}

/**
 * Notificar propiedad alquilada
 */
function notifyPropertyRented($propertyId, $userId, $propertyReference, $rentPrice) {
    $title = "¡Propiedad alquilada!";
    $message = "La propiedad {$propertyReference} ha sido alquilada por " . formatCurrency($rentPrice) . "/mes";
    $linkUrl = "ver-propiedad.php?id={$propertyId}";
    
    return createNotification(
        $userId,
        'property_rented',
        $title,
        $message,
        $linkUrl,
        'property',
        $propertyId
    );
}

/**
 * Notificar nueva propiedad agregada
 */
function notifyNewProperty($propertyId, $userId, $propertyReference, $propertyTitle) {
    $title = "Nueva propiedad agregada";
    $message = "Se ha agregado la propiedad {$propertyReference}: {$propertyTitle}";
    $linkUrl = "ver-propiedad.php?id={$propertyId}";
    
    return createNotification(
        $userId,
        'new_property',
        $title,
        $message,
        $linkUrl,
        'property',
        $propertyId
    );
}

/**
 * Notificar reunión próxima
 */
function notifyMeetingReminder($eventId, $userId, $eventTitle, $eventDate) {
    $title = "Recordatorio de reunión";
    $message = "Tienes una reunión programada: {$eventTitle} el " . date('d/m/Y H:i', strtotime($eventDate));
    $linkUrl = "calendario.php?event={$eventId}";
    
    return createNotification(
        $userId,
        'meeting_reminder',
        $title,
        $message,
        $linkUrl,
        'calendar_event',
        $eventId
    );
}

/**
 * Notificación del sistema
 */
function notifySystem($userId, $title, $message, $linkUrl = null) {
    return createNotification(
        $userId,
        'system',
        $title,
        $message,
        $linkUrl
    );
}

/**
 * Notificar a múltiples usuarios
 */
function notifyMultipleUsers($userIds, $type, $title, $message = null, $linkUrl = null, $relatedEntityType = null, $relatedEntityId = null) {
    $results = [];
    foreach ($userIds as $userId) {
        $results[] = createNotification(
            $userId,
            $type,
            $title,
            $message,
            $linkUrl,
            $relatedEntityType,
            $relatedEntityId
        );
    }
    return $results;
}

/**
 * Notificar a todos los administradores
 */
function notifyAllAdmins($type, $title, $message = null, $linkUrl = null) {
    $admins = db()->select(
        "SELECT id FROM users WHERE role_id = 1 AND status = 'active'"
    );
    
    $adminIds = array_column($admins, 'id');
    return notifyMultipleUsers($adminIds, $type, $title, $message, $linkUrl);
}

/**
 * Notificar a un equipo/oficina
 */
function notifyTeam($officeId, $type, $title, $message = null, $linkUrl = null) {
    $teamMembers = db()->select(
        "SELECT id FROM users WHERE office_id = ? AND status = 'active'",
        [$officeId]
    );
    
    $memberIds = array_column($teamMembers, 'id');
    return notifyMultipleUsers($memberIds, $type, $title, $message, $linkUrl);
}

/**
 * Obtener icono según tipo de notificación
 */
function getNotificationIcon($type) {
    $icons = [
        'new_task' => 'fa-tasks',
        'task_assigned' => 'fa-tasks',
        'task_due' => 'fa-exclamation-triangle',
        'task_overdue' => 'fa-exclamation-triangle',
        'new_client' => 'fa-user-plus',
        'client_assigned' => 'fa-user-plus',
        'new_inquiry' => 'fa-envelope',
        'property_sold' => 'fa-check-circle',
        'property_rented' => 'fa-check-circle',
        'new_property' => 'fa-home',
        'meeting_reminder' => 'fa-calendar-alt',
        'system' => 'fa-cog',
        'default' => 'fa-bell'
    ];
    
    return $icons[$type] ?? $icons['default'];
}

/**
 * Marcar notificaciones como leídas por entidad
 */
function markNotificationsReadByEntity($userId, $entityType, $entityId) {
    try {
        db()->query(
            "UPDATE notifications 
             SET is_read = 1, read_at = ? 
             WHERE user_id = ? 
             AND related_entity_type = ? 
             AND related_entity_id = ? 
             AND is_read = 0",
            [date('Y-m-d H:i:s'), $userId, $entityType, $entityId]
        );
        return true;
    } catch (Exception $e) {
        error_log("Error marcando notificaciones como leídas: " . $e->getMessage());
        return false;
    }
}

/**
 * Eliminar notificaciones antiguas (más de X días)
 */
function cleanOldNotifications($daysOld = 30) {
    try {
        $date = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        db()->query(
            "DELETE FROM notifications WHERE created_at < ? AND is_read = 1",
            [$date]
        );
        return true;
    } catch (Exception $e) {
        error_log("Error limpiando notificaciones antiguas: " . $e->getMessage());
        return false;
    }
}

/**
 * Contar notificaciones no leídas
 */
function getUnreadNotificationCount($userId) {
    return db()->count('notifications', 'user_id = ? AND is_read = 0', [$userId]);
}

/**
 * Obtener últimas notificaciones no leídas
 */
function getRecentUnreadNotifications($userId, $limit = 5) {
    return db()->select(
        "SELECT * FROM notifications 
         WHERE user_id = ? AND is_read = 0 
         ORDER BY created_at DESC 
         LIMIT ?",
        [$userId, $limit]
    );
}