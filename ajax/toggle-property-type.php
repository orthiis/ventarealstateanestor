<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

if ($currentUser['role']['name'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

try {
    $id = (int)$_POST['id'];
    $isActive = (int)$_POST['is_active'];
    
    db()->update('property_types', [
        'is_active' => $isActive
    ], ['id' => $id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}