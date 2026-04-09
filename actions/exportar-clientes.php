<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

// Obtener filtros de la URL
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$agent = $_GET['agent'] ?? '';
$source = $_GET['source'] ?? '';
$search = $_GET['search'] ?? '';

// Construir WHERE clause
$where = ["is_active = 1"];
$params = [];

if (!empty($type)) {
    $where[] = "client_type = ?";
    $params[] = $type;
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $where[] = "priority = ?";
    $params[] = $priority;
}

if (!empty($agent)) {
    $where[] = "agent_id = ?";
    $params[] = $agent;
}

if (!empty($source)) {
    $where[] = "source = ?";
    $params[] = $source;
}

if (!empty($search)) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = implode(' AND ', $where);

// Obtener clientes
$clients = db()->select(
    "SELECT c.*, 
     CONCAT(u.first_name, ' ', u.last_name) as agent_name
     FROM clients c
     LEFT JOIN users u ON c.agent_id = u.id
     WHERE {$whereClause}
     ORDER BY c.created_at DESC",
    $params
);

// Generar CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=clientes_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Encabezados
fputcsv($output, [
    'ID',
    'Nombre',
    'Apellidos',
    'Email',
    'Teléfono',
    'Tipo',
    'Estado',
    'Prioridad',
    'Agente',
    'Fuente',
    'Presupuesto Min',
    'Presupuesto Max',
    'Fecha Registro'
]);

// Datos
foreach ($clients as $client) {
    fputcsv($output, [
        $client['id'],
        $client['first_name'],
        $client['last_name'],
        $client['email'],
        $client['phone_mobile'],
        $client['client_type'],
        $client['status'],
        $client['priority'],
        $client['agent_name'],
        $client['source'],
        $client['budget_min'],
        $client['budget_max'],
        date('d/m/Y', strtotime($client['created_at']))
    ]);
}

fclose($output);
exit;
?>