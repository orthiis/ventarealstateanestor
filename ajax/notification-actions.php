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
        
        // ============ MARCAR COMO LEÍDA ============
        case 'mark_read':
            $notificationId = (int)$_POST['id'];
            
            if (!$notificationId) {
                throw new Exception('ID de notificación no válido');
            }
            
            // Verificar que la notificación pertenezca al usuario
            $notification = db()->selectOne(
                "SELECT * FROM notifications WHERE id = ? AND user_id = ?",
                [$notificationId, $currentUser['id']]
            );
            
            if (!$notification) {
                throw new Exception('Notificación no encontrada');
            }
            
            db()->update('notifications', [
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s')
            ], ['id' => $notificationId]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ MARCAR TODAS COMO LEÍDAS ============
        case 'mark_all_read':
            db()->query(
                "UPDATE notifications SET is_read = 1, read_at = ? WHERE user_id = ? AND is_read = 0",
                [date('Y-m-d H:i:s'), $currentUser['id']]
            );
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ELIMINAR NOTIFICACIÓN ============
        case 'delete':
            $notificationId = (int)$_POST['id'];
            
            if (!$notificationId) {
                throw new Exception('ID de notificación no válido');
            }
            
            // Verificar que la notificación pertenezca al usuario
            $notification = db()->selectOne(
                "SELECT * FROM notifications WHERE id = ? AND user_id = ?",
                [$notificationId, $currentUser['id']]
            );
            
            if (!$notification) {
                throw new Exception('Notificación no encontrada');
            }
            
            db()->delete('notifications', ['id' => $notificationId]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ELIMINAR TODAS LAS LEÍDAS ============
        case 'delete_all_read':
            db()->query(
                "DELETE FROM notifications WHERE user_id = ? AND is_read = 1",
                [$currentUser['id']]
            );
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ OBTENER NOTIFICACIONES (para polling) ============
        case 'get_notifications':
            $notifications = db()->select(
                "SELECT * FROM notifications 
                 WHERE user_id = ? AND is_read = 0 
                 ORDER BY created_at DESC 
                 LIMIT 10",
                [$currentUser['id']]
            );
            
            $count = db()->count('notifications', 'user_id = ? AND is_read = 0', [$currentUser['id']]);
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => $count
            ]);
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