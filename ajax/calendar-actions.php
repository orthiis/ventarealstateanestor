<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    
    switch ($action) {
        
        // ============ CREAR EVENTO ============
        case 'create':
            $required = ['title', 'event_type', 'start_datetime', 'end_datetime'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }
            
            // Validar que la fecha de fin sea posterior a la de inicio
            if (strtotime($_POST['end_datetime']) <= strtotime($_POST['start_datetime'])) {
                throw new Exception('La fecha de fin debe ser posterior a la fecha de inicio');
            }
            
            // Procesar asistentes
            $attendees = !empty($_POST['attendees']) ? $_POST['attendees'] : '[]';
            if (is_string($attendees)) {
                $attendees = json_decode($attendees, true);
            }
            if (!is_array($attendees)) {
                $attendees = [];
            }
            
            $data = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'event_type' => $_POST['event_type'],
                'start_datetime' => $_POST['start_datetime'],
                'end_datetime' => $_POST['end_datetime'],
                'all_day' => isset($_POST['all_day']) ? 1 : 0,
                'location' => trim($_POST['location'] ?? ''),
                'attendees' => json_encode($attendees),
                'related_client_id' => !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null,
                'related_property_id' => !empty($_POST['related_property_id']) ? (int)$_POST['related_property_id'] : null,
                'color' => $_POST['color'] ?? '#3B82F6',
                'reminder_minutes' => !empty($_POST['reminder_minutes']) ? (int)$_POST['reminder_minutes'] : null,
                'status' => 'scheduled',
                'created_by' => $currentUser['id']
            ];
            
            $eventId = db()->insert('calendar_events', $data);
            
            // Log de actividad
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'create',
                'entity_type' => 'calendar_event',
                'entity_id' => $eventId,
                'description' => 'Evento creado: ' . $data['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true, 'event_id' => $eventId]);
            break;
            
        // ============ EDITAR EVENTO ============
        case 'update':
            $eventId = (int)$_POST['id'];
            
            if (!$eventId) {
                throw new Exception('ID de evento no válido');
            }
            
            // Verificar permisos
            $event = db()->selectOne("SELECT * FROM calendar_events WHERE id = ?", [$eventId]);
            if (!$event) {
                throw new Exception('Evento no encontrado');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $event['created_by'] != $currentUser['id']) {
                throw new Exception('No tienes permisos para editar este evento');
            }
            
            // Validar fechas
            if (strtotime($_POST['end_datetime']) <= strtotime($_POST['start_datetime'])) {
                throw new Exception('La fecha de fin debe ser posterior a la fecha de inicio');
            }
            
            // Procesar asistentes
            $attendees = !empty($_POST['attendees']) ? $_POST['attendees'] : '[]';
            if (is_string($attendees)) {
                $attendees = json_decode($attendees, true);
            }
            if (!is_array($attendees)) {
                $attendees = [];
            }
            
            $data = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'event_type' => $_POST['event_type'],
                'start_datetime' => $_POST['start_datetime'],
                'end_datetime' => $_POST['end_datetime'],
                'all_day' => isset($_POST['all_day']) ? 1 : 0,
                'location' => trim($_POST['location'] ?? ''),
                'attendees' => json_encode($attendees),
                'related_client_id' => !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null,
                'related_property_id' => !empty($_POST['related_property_id']) ? (int)$_POST['related_property_id'] : null,
                'color' => $_POST['color'] ?? '#3B82F6',
                'reminder_minutes' => !empty($_POST['reminder_minutes']) ? (int)$_POST['reminder_minutes'] : null
            ];
            
            db()->update('calendar_events', $data, ['id' => $eventId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'calendar_event',
                'entity_id' => $eventId,
                'description' => 'Evento actualizado: ' . $data['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ACTUALIZAR FECHA (DRAG & DROP) ============
        case 'update_date':
            $eventId = (int)$_POST['id'];
            $startDate = $_POST['start'];
            $endDate = $_POST['end'] ?? null;
            
            if (!$eventId || !$startDate) {
                throw new Exception('Datos incompletos');
            }
            
            // Verificar permisos
            $event = db()->selectOne("SELECT * FROM calendar_events WHERE id = ?", [$eventId]);
            if (!$event) {
                throw new Exception('Evento no encontrado');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $event['created_by'] != $currentUser['id']) {
                throw new Exception('No tienes permisos');
            }
            
            // Si no hay fecha de fin, calcular basado en la duración original
            if (!$endDate) {
                $originalStart = strtotime($event['start_datetime']);
                $originalEnd = strtotime($event['end_datetime']);
                $duration = $originalEnd - $originalStart;
                $endDate = date('Y-m-d H:i:s', strtotime($startDate) + $duration);
            }
            
            $data = [
                'start_datetime' => date('Y-m-d H:i:s', strtotime($startDate)),
                'end_datetime' => date('Y-m-d H:i:s', strtotime($endDate))
            ];
            
            db()->update('calendar_events', $data, ['id' => $eventId]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ CAMBIAR ESTADO ============
        case 'update_status':
            $eventId = (int)$_POST['id'];
            $newStatus = $_POST['status'];
            
            $validStatuses = ['scheduled', 'completed', 'cancelled', 'rescheduled'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Estado no válido');
            }
            
            // Verificar permisos
            $event = db()->selectOne("SELECT * FROM calendar_events WHERE id = ?", [$eventId]);
            if (!$event) {
                throw new Exception('Evento no encontrado');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $event['created_by'] != $currentUser['id']) {
                throw new Exception('No tienes permisos');
            }
            
            db()->update('calendar_events', ['status' => $newStatus], ['id' => $eventId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'calendar_event',
                'entity_id' => $eventId,
                'description' => "Estado de evento cambiado a: {$newStatus}",
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ELIMINAR EVENTO ============
        case 'delete':
            $eventId = (int)$_POST['id'];
            
            // Verificar permisos
            $event = db()->selectOne("SELECT * FROM calendar_events WHERE id = ?", [$eventId]);
            if (!$event) {
                throw new Exception('Evento no encontrado');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $event['created_by'] != $currentUser['id']) {
                throw new Exception('Solo el creador o un administrador puede eliminar este evento');
            }
            
            db()->delete('calendar_events', ['id' => $eventId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'delete',
                'entity_type' => 'calendar_event',
                'entity_id' => $eventId,
                'description' => 'Evento eliminado: ' . $event['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}