<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $imageId = $data['image_id'] ?? null;
    $propertyId = $data['property_id'] ?? null;
    
    if (!$imageId || !$propertyId) {
        throw new Exception('Datos incompletos');
    }
    
    db()->beginTransaction();
    
    // Quitar imagen principal actual
    db()->update('property_images', 
        ['is_main' => 0],
        'property_id = :property_id',
        ['property_id' => $propertyId]
    );
    
    // Establecer nueva imagen principal
    db()->update('property_images',
        ['is_main' => 1],
        'id = :id',
        ['id' => $imageId]
    );
    
    db()->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}