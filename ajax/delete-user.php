<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

if ($currentUser['role']['name'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = $data['id'] ?? null;
    
    if (!$userId) {
        throw new Exception('ID de usuario no proporcionado');
    }
    
    // No permitir eliminar el propio usuario
    if ($userId == $currentUser['id']) {
        throw new Exception('No puedes eliminar tu propio usuario');
    }
    
    // Verificar que el usuario existe
    $user = db()->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    db()->beginTransaction();
    
    // En lugar de eliminar, marcar como inactivo
    db()->update('users', 
        ['status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')],
        'id = :id',
        ['id' => $userId]
    );
    
    logActivity('delete', 'user', $userId, "Usuario {$user['username']} desactivado");
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuario desactivado correctamente'
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}