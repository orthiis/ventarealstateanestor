<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $imageId = $data['id'] ?? null;
    
    if (!$imageId) {
        throw new Exception('ID de imagen no proporcionado');
    }
    
    $image = db()->selectOne("SELECT * FROM property_images WHERE id = ?", [$imageId]);
    
    if (!$image) {
        throw new Exception('Imagen no encontrada');
    }
    
    db()->beginTransaction();
    
    // Eliminar archivo físico
    if ($image['image_path'] && file_exists($image['image_path'])) {
        unlink($image['image_path']);
    }
    
    // Eliminar de base de datos
    db()->delete('property_images', 'id = ?', [$imageId]);
    
    logActivity('delete', 'property_image', $imageId, "Imagen de propiedad eliminada");
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Imagen eliminada correctamente'
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}