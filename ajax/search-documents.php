<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

try {
    $query = trim($_GET['q'] ?? '');
    
    if (strlen($query) < 2) {
        throw new Exception('La búsqueda debe tener al menos 2 caracteres');
    }
    
    $searchTerm = "%{$query}%";
    
    // Buscar documentos
    $documents = db()->select("
        SELECT 
            d.*,
            p.reference as property_reference,
            p.title as property_title,
            p.city,
            df.name as folder_name,
            df.color as folder_color
        FROM documents d
        LEFT JOIN properties p ON d.property_id = p.id
        LEFT JOIN document_folders df ON d.folder_id = df.id
        WHERE d.status = 'active'
        AND (
            d.document_name LIKE ? OR
            d.file_name LIKE ? OR
            d.description LIKE ? OR
            p.reference LIKE ? OR
            p.title LIKE ? OR
            p.city LIKE ?
        )
        LIMIT 50
    ", array_fill(0, 6, $searchTerm));
    
    echo json_encode([
        'success' => true,
        'results' => $documents,
        'count' => count($documents)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}