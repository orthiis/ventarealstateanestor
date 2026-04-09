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
    // Obtener todas las tablas
    $tables = db()->select("SHOW TABLES");
    
    $optimized = 0;
    foreach ($tables as $table) {
        $tableName = array_values($table)[0];
        db()->query("OPTIMIZE TABLE `{$tableName}`");
        $optimized++;
    }
    
    // Log
    db()->insert('activity_log', [
        'user_id' => $currentUser['id'],
        'action' => 'optimize',
        'entity_type' => 'database',
        'entity_id' => null,
        'description' => "Base de datos optimizada: {$optimized} tablas",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "{$optimized} tablas optimizadas"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}