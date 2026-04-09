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
    // Crear directorio de backups si no existe
    $backupDir = dirname(__DIR__) . '/backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Nombre del archivo
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    // Comando mysqldump
    $command = sprintf(
        'mysqldump -h %s -u %s -p%s %s > %s',
        DB_HOST,
        DB_USER,
        DB_PASS,
        DB_NAME,
        $filepath
    );
    
    exec($command, $output, $result);
    
    if ($result === 0 && file_exists($filepath)) {
        // Log
        db()->insert('activity_log', [
            'user_id' => $currentUser['id'],
            'action' => 'backup',
            'entity_type' => 'database',
            'entity_id' => null,
            'description' => "Respaldo creado: {$filename}",
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]);
        
        echo json_encode([
            'success' => true,
            'file' => 'backups/' . $filename,
            'message' => 'Respaldo creado exitosamente'
        ]);
    } else {
        throw new Exception('No se pudo crear el respaldo');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}