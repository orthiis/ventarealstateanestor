<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $clientId = $data['id'] ?? null;
    
    if (!$clientId) {
        throw new Exception('ID de cliente no proporcionado');
    }
    
    $client = db()->selectOne("SELECT * FROM clients WHERE id = ?", [$clientId]);
    
    if (!$client) {
        throw new Exception('Cliente no encontrado');
    }
    
    db()->beginTransaction();
    
    // Marcar como inactivo en lugar de eliminar
    db()->update('clients',
        ['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')],
        'id = :id',
        ['id' => $clientId]
    );
    
    logActivity('delete', 'client', $clientId, "Cliente {$client['reference']} desactivado");
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cliente eliminado correctamente'
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}