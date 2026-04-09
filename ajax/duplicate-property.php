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
    
    // Obtener propiedad original
    $property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$propertyId]);
    
    if (!$property) {
        throw new Exception('Propiedad no encontrada');
    }
    
    db()->beginTransaction();
    
    // Duplicar propiedad
    unset($property['id']);
    $property['reference'] = '#' . strtoupper(uniqid());
    $property['title'] = $property['title'] . ' (Copia)';
    $property['status'] = 'draft';
    $property['created_at'] = date('Y-m-d H:i:s');
    $property['updated_at'] = date('Y-m-d H:i:s');
    
    $newId = db()->insert('properties', $property);
    
    // Duplicar características
    $features = db()->select("SELECT feature_id FROM property_features WHERE property_id = ?", [$propertyId]);
    foreach($features as $feature) {
        db()->insert('property_features', [
            'property_id' => $newId,
            'feature_id' => $feature['feature_id']
        ]);
    }
    
    // Duplicar imágenes
    $images = db()->select("SELECT * FROM property_images WHERE property_id = ?", [$propertyId]);
    foreach($images as $image) {
        unset($image['id']);
        $image['property_id'] = $newId;
        db()->insert('property_images', $image);
    }
    
    logActivity('create', 'property', $newId, "Propiedad duplicada desde #{$propertyId}");
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'new_id' => $newId,
        'message' => 'Propiedad duplicada correctamente'
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}