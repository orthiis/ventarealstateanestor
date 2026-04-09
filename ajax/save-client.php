<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

try {
    $clientId = $_POST['id'] ?? null;
    
    // Validar datos requeridos
    $required = ['first_name', 'last_name', 'phone_mobile', 'client_type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    db()->beginTransaction();
    
    // Preparar datos
    $clientData = [
        'first_name' => sanitize($_POST['first_name']),
        'last_name' => sanitize($_POST['last_name']),
        'email' => sanitize($_POST['email'] ?? ''),
        'phone_mobile' => sanitize($_POST['phone_mobile']),
        'phone_home' => sanitize($_POST['phone_home'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'city' => sanitize($_POST['city'] ?? ''),
        'client_type' => sanitize($_POST['client_type']),
        'status' => sanitize($_POST['status'] ?? 'lead'),
        'source' => sanitize($_POST['source'] ?? 'website'),
        'budget_min' => !empty($_POST['budget_min']) ? (float)$_POST['budget_min'] : null,
        'budget_max' => !empty($_POST['budget_max']) ? (float)$_POST['budget_max'] : null,
        'bedrooms_desired' => !empty($_POST['bedrooms_desired']) ? (int)$_POST['bedrooms_desired'] : null,
        'priority' => sanitize($_POST['priority'] ?? 'medium'),
        'agent_id' => !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : $currentUser['id'],
        'notes' => sanitize($_POST['notes'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if ($clientId) {
        // Actualizar cliente existente
        db()->update('clients', $clientData, 'id = :id', ['id' => $clientId]);
        
        logActivity('update', 'client', $clientId, "Cliente actualizado");
        
        $message = 'Cliente actualizado correctamente';
        
    } else {
        // Crear nuevo cliente
        $clientData['reference'] = generateReference('CLI');
        $clientData['created_at'] = date('Y-m-d H:i:s');
        
        $clientId = db()->insert('clients', $clientData);
        
        logActivity('create', 'client', $clientId, "Cliente {$clientData['reference']} creado");
        
        $message = 'Cliente creado correctamente';
    }
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'client_id' => $clientId
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}