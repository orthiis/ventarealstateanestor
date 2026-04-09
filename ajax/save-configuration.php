<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

// Solo administradores
if ($currentUser['role']['name'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

try {
    $section = $_POST['section'] ?? '';
    unset($_POST['section']);
    
    db()->beginTransaction();
    
    // Guardar cada configuración
    foreach ($_POST as $key => $value) {
        // Determinar categoría según la sección
        $category = 'general';
        $dataType = 'string';
        
        switch ($section) {
            case 'email':
                $category = 'email';
                break;
            case 'notifications':
                $category = 'notifications';
                $dataType = 'boolean';
                break;
            case 'integrations':
                $category = 'integrations';
                break;
            case 'security':
                $category = 'security';
                break;
        }
        
        // Verificar si existe
        $existing = db()->selectOne(
            "SELECT id FROM system_settings WHERE setting_key = ?",
            [$key]
        );
        
        if ($existing) {
            // Actualizar
            db()->update('system_settings', [
                'setting_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['setting_key' => $key]);
        } else {
            // Insertar nuevo
            db()->insert('system_settings', [
                'setting_key' => $key,
                'setting_value' => $value,
                'data_type' => $dataType,
                'category' => $category,
                'description' => '',
                'is_public' => 0
            ]);
        }
    }
    
    db()->commit();
    
    // Log de actividad
    db()->insert('activity_log', [
        'user_id' => $currentUser['id'],
        'action' => 'update',
        'entity_type' => 'configuration',
        'entity_id' => null,
        'description' => "Configuración actualizada: {$section}",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}