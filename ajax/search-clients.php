<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$query = $_GET['q'] ?? '';

try {
    if (strlen($query) < 2) {
        throw new Exception('Query too short');
    }
    
    $searchParam = "%{$query}%";
    
    $clients = db()->select("
        SELECT 
            id,
            CONCAT(first_name, ' ', last_name) as name,
            reference,
            email,
            phone_mobile as phone,
            phone_home,
            address
        FROM clients
        WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone_mobile LIKE ? OR reference LIKE ?)
        AND is_active = 1
        ORDER BY first_name, last_name
        LIMIT 20
    ", [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    
    echo json_encode([
        'success' => true,
        'clients' => $clients
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'clients' => []
    ]);
}