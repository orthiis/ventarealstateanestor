<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

try {
    $city = $_GET['city'] ?? '';
    
    if (empty($city)) {
        throw new Exception('Ciudad no especificada');
    }
    
    $properties = db()->select("
        SELECT 
            id,
            reference,
            title,
            address
        FROM properties
        WHERE city = ?
        AND status != 'deleted'
        ORDER BY reference
    ", [$city]);
    
    echo json_encode([
        'success' => true,
        'properties' => $properties
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}