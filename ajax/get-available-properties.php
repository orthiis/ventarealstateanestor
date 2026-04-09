<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');
requireLogin();

$type = $_GET['type'] ?? 'sale';
$search = $_GET['search'] ?? '';

try {
    $where = ['p.is_active = 1'];
    $params = [];
    
    // Filtrar por tipo de transacción
    if ($type === 'sale') {
        $where[] = 'p.availability_status IN ("available", "for_sale")';
    } else {
        $where[] = 'p.availability_status IN ("available", "for_rent")';
    }
    
    // Búsqueda
    if (!empty($search)) {
        $where[] = '(p.reference LIKE ? OR p.title LIKE ? OR p.address LIKE ?)';
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $properties = db()->select("
        SELECT p.id, p.reference, p.title, p.address, p.sale_price, p.rent_price,
               p.main_image, p.property_type, p.bedrooms, p.bathrooms, p.area
        FROM properties p
        WHERE {$whereClause}
        ORDER BY p.created_at DESC
        LIMIT 50
    ", $params);
    
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