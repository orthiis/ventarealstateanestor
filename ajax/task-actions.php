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
        
        // ============ CREAR TAREA ============
        case 'create':
            $required = ['title', 'task_type', 'assigned_to', 'priority'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }
            
            $data = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'task_type' => $_POST['task_type'],
                'priority' => $_POST['priority'],
                'status' => 'pending',
                'assigned_to' => (int)$_POST['assigned_to'],
                'related_client_id' => !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null,
                'related_property_id' => !empty($_POST['related_property_id']) ? (int)$_POST['related_property_id'] : null,
                'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
                'reminder_minutes' => !empty($_POST['reminder_minutes']) ? (int)$_POST['reminder_minutes'] : null,
                'created_by' => $currentUser['id']
            ];
            
            $taskId = db()->insert('tasks', $data);
            
            // Log de actividad
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'create',
                'entity_type' => 'task',
                'entity_id' => $taskId,
                'description' => 'Tarea creada: ' . $data['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true, 'task_id' => $taskId]);
            break;
            
        // ============ EDITAR TAREA ============
        case 'update':
            $taskId = (int)$_POST['id'];
            
            if (!$taskId) {
                throw new Exception('ID de tarea no válido');
            }
            
            // Verificar permisos
            $task = db()->selectOne("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) {
                throw new Exception('Tarea no encontrada');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $task['assigned_to'] != $currentUser['id'] && 
                $task['created_by'] != $currentUser['id']) {
                throw new Exception('No tienes permisos para editar esta tarea');
            }
            
            $data = [
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'task_type' => $_POST['task_type'],
                'priority' => $_POST['priority'],
                'assigned_to' => (int)$_POST['assigned_to'],
                'related_client_id' => !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null,
                'related_property_id' => !empty($_POST['related_property_id']) ? (int)$_POST['related_property_id'] : null,
                'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
                'reminder_minutes' => !empty($_POST['reminder_minutes']) ? (int)$_POST['reminder_minutes'] : null
            ];
            
            db()->update('tasks', $data, ['id' => $taskId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'task',
                'entity_id' => $taskId,
                'description' => 'Tarea actualizada: ' . $data['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ CAMBIAR ESTADO ============
        case 'update_status':
            $taskId = (int)$_POST['id'];
            $newStatus = $_POST['status'];
            
            $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception('Estado no válido');
            }
            
            // Verificar permisos
            $task = db()->selectOne("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) {
                throw new Exception('Tarea no encontrada');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $task['assigned_to'] != $currentUser['id'] && 
                $task['created_by'] != $currentUser['id']) {
                throw new Exception('No tienes permisos');
            }
            
            $updateData = ['status' => $newStatus];
            if ($newStatus === 'completed') {
                $updateData['completed_at'] = date('Y-m-d H:i:s');
            }
            
            db()->update('tasks', $updateData, ['id' => $taskId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'task',
                'entity_id' => $taskId,
                'description' => "Estado de tarea cambiado a: {$newStatus}",
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ELIMINAR TAREA ============
        case 'delete':
            $taskId = (int)$_POST['id'];
            
            // Verificar permisos
            $task = db()->selectOne("SELECT * FROM tasks WHERE id = ?", [$taskId]);
            if (!$task) {
                throw new Exception('Tarea no encontrada');
            }
            
            if ($currentUser['role']['name'] !== 'administrador' && 
                $task['created_by'] != $currentUser['id']) {
                throw new Exception('Solo el creador o un administrador puede eliminar esta tarea');
            }
            
            db()->delete('tasks', ['id' => $taskId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'delete',
                'entity_type' => 'task',
                'entity_id' => $taskId,
                'description' => 'Tarea eliminada: ' . $task['title'],
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