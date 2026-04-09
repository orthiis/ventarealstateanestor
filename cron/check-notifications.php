<?php
/**
 * Script CRON para generar notificaciones automáticas
 * Ejecutar cada hora o según necesidad
 * 
 * Agregar a crontab:
 * 0 * * * * php /path/to/crm/cron/check-notifications.php
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/includes/notification-helper.php';

echo "Iniciando verificación de notificaciones...\n";

// ============ VERIFICAR TAREAS VENCIDAS ============
echo "Verificando tareas vencidas...\n";

$overdueTasks = db()->select(
    "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as assigned_name
     FROM tasks t
     LEFT JOIN users u ON t.assigned_to = u.id
     WHERE t.due_date < NOW() 
     AND t.status IN ('pending', 'in_progress')
     AND t.id NOT IN (
         SELECT related_entity_id 
         FROM notifications 
         WHERE related_entity_type = 'task' 
         AND notification_type = 'task_overdue' 
         AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
     )"
);

foreach ($overdueTasks as $task) {
    notifyTaskOverdue($task['id'], $task['assigned_to'], $task['title']);
    echo "- Notificación de tarea vencida enviada a {$task['assigned_name']}\n";
}

// ============ VERIFICAR TAREAS PRÓXIMAS A VENCER (24h) ============
echo "Verificando tareas próximas a vencer...\n";

$dueSoonTasks = db()->select(
    "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as assigned_name,
     TIMESTAMPDIFF(HOUR, NOW(), t.due_date) as hours_left
     FROM tasks t
     LEFT JOIN users u ON t.assigned_to = u.id
     WHERE t.due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
     AND t.status IN ('pending', 'in_progress')
     AND t.id NOT IN (
         SELECT related_entity_id 
         FROM notifications 
         WHERE related_entity_type = 'task' 
         AND notification_type = 'task_due' 
         AND created_at > DATE_SUB(NOW(), INTERVAL 12 HOUR)
     )"
);

foreach ($dueSoonTasks as $task) {
    notifyTaskDueSoon($task['id'], $task['assigned_to'], $task['title'], $task['hours_left']);
    echo "- Notificación de tarea próxima enviada a {$task['assigned_name']}\n";
}

// ============ VERIFICAR REUNIONES PRÓXIMAS (1h antes) ============
echo "Verificando reuniones próximas...\n";

$upcomingMeetings = db()->select(
    "SELECT ce.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
     FROM calendar_events ce
     LEFT JOIN users u ON ce.created_by = u.id
     WHERE ce.start_datetime BETWEEN DATE_ADD(NOW(), INTERVAL 50 MINUTE) AND DATE_ADD(NOW(), INTERVAL 70 MINUTE)
     AND ce.status = 'scheduled'
     AND ce.event_type IN ('meeting', 'visit')
     AND ce.id NOT IN (
         SELECT related_entity_id 
         FROM notifications 
         WHERE related_entity_type = 'calendar_event' 
         AND notification_type = 'meeting_reminder' 
         AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
     )"
);

foreach ($upcomingMeetings as $meeting) {
    notifyMeetingReminder(
        $meeting['id'], 
        $meeting['created_by'], 
        $meeting['title'], 
        $meeting['start_datetime']
    );
    echo "- Recordatorio de reunión enviado a {$meeting['user_name']}\n";
}

// ============ LIMPIAR NOTIFICACIONES ANTIGUAS ============
echo "Limpiando notificaciones antiguas...\n";
cleanOldNotifications(30); // Eliminar notificaciones leídas de más de 30 días
echo "Notificaciones antiguas eliminadas\n";

echo "Verificación completada!\n";