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
    // Limpiar archivos de caché (si existen)
    $cacheDir = dirname(__DIR__) . '/cache';
    if (file_exists($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    // Log
    db()->insert('activity_log', [
        'user_id' => $currentUser['id'],
        'action' => 'clear_cache',
        'entity_type' => 'system',
        'entity_id' => null,
        'description' => 'Caché limpiado',
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Caché limpiado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}