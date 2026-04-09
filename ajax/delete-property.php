<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $propertyId = $data['id'] ?? null;
    
    if (!$propertyId) {
        throw new Exception('ID de propiedad no proporcionado');
    }
    
    // Verificar permisos
    $currentUser = getCurrentUser();
    $property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$propertyId]);
    
    if (!$property) {
        throw new Exception('Propiedad no encontrada');
    }
    
    // Solo admin o el agente asignado pueden eliminar
    if ($currentUser['role']['name'] !== 'administrador' && $property['agent_id'] != $currentUser['id']) {
        throw new Exception('No tienes permisos para eliminar esta propiedad');
    }
    
    db()->beginTransaction();
    
    // Marcar como eliminado en lugar de borrar
    db()->update('properties',
        ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')],
        'id = :id',
        ['id' => $propertyId]
    );
    
    logActivity('delete', 'property', $propertyId, "Propiedad {$property['reference']} eliminada");
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Propiedad eliminada correctamente'
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}