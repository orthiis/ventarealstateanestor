<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$query = $_GET['q'] ?? '';

if (strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

$searchTerm = "%{$query}%";
$results = [];

try {
    // Buscar en propiedades
    $properties = db()->select(
        "SELECT id, reference, title, 'property' as type 
         FROM properties 
         WHERE (reference LIKE ? OR title LIKE ? OR address LIKE ?) 
         AND status != 'deleted' 
         LIMIT 5",
        [$searchTerm, $searchTerm, $searchTerm]
    );
    
    foreach ($properties as $prop) {
        $results[] = [
            'title' => $prop['reference'] . ' - ' . $prop['title'],
            'description' => 'Propiedad',
            'type' => 'Inmueble',
            'url' => 'editar-propiedad.php?id=' . $prop['id']
        ];
    }
    
    // Buscar en clientes
    $clients = db()->select(
        "SELECT id, first_name, last_name, email, 'client' as type 
         FROM clients 
         WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) 
         LIMIT 5",
        [$searchTerm, $searchTerm, $searchTerm]
    );
    
    foreach ($clients as $client) {
        $results[] = [
            'title' => $client['first_name'] . ' ' . $client['last_name'],
            'description' => $client['email'],
            'type' => 'Cliente',
            'url' => 'editar-cliente.php?id=' . $client['id']
        ];
    }
    
    // Buscar en usuarios
    $users = db()->select(
        "SELECT id, first_name, last_name, email, 'user' as type 
         FROM users 
         WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) 
         LIMIT 5",
        [$searchTerm, $searchTerm, $searchTerm]
    );
    
    foreach ($users as $user) {
        $results[] = [
            'title' => $user['first_name'] . ' ' . $user['last_name'],
            'description' => $user['email'],
            'type' => 'Usuario',
            'url' => 'usuarios.php?id=' . $user['id']
        ];
    }
    
    echo json_encode($results);
    
} catch (Exception $e) {
    echo json_encode([]);
}