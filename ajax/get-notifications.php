<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

try {
    // Obtener notificaciones no leídas
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
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}