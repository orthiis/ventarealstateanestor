<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $taskId = $data['task_id'] ?? null;
    $status = $data['status'] ?? 'pending';
    
    if(!$taskId) {
        throw new Exception('ID de tarea no proporcionado');
    }
    
    $updateData = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if($status === 'completed') {
        $updateData['completed_at'] = date('Y-m-d H:i:s');
    }
    
    db()->update('tasks', $updateData, 'id = :id', ['id' => $taskId]);
    
    echo json_encode(['success' => true]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}