<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

try {
    // Obtener filtro de usuario (solo para administradores)
    $userFilter = '';
    $params = [];
    
    if (isset($_GET['user']) && !empty($_GET['user']) && $currentUser['role']['name'] === 'administrador') {
        $userFilter = "AND created_by = ?";
        $params[] = (int)$_GET['user'];
    } elseif ($currentUser['role']['name'] !== 'administrador') {
        // Si no es admin, solo ver sus eventos o eventos donde es asistente
        $userFilter = "AND (created_by = ? OR JSON_CONTAINS(attendees, ?))";
        $params[] = $currentUser['id'];
        $params[] = json_encode((string)$currentUser['id']);
    }
    
    // Obtener eventos
    $query = "SELECT ce.*, 
             CONCAT(c.first_name, ' ', c.last_name) as client_name,
             p.reference as property_ref, p.title as property_title,
             CONCAT(u.first_name, ' ', u.last_name) as creator_name
             FROM calendar_events ce
             LEFT JOIN clients c ON ce.related_client_id = c.id
             LEFT JOIN properties p ON ce.related_property_id = p.id
             LEFT JOIN users u ON ce.created_by = u.id
             WHERE 1=1 $userFilter
             ORDER BY ce.start_datetime ASC";
    
    $events = db()->select($query, $params);
    
    // Formatear eventos para FullCalendar
    $formattedEvents = [];
    foreach ($events as $event) {
        $formattedEvents[] = [
            'id' => $event['id'],
            'title' => $event['title'],
            'description' => $event['description'],
            'start_datetime' => $event['start_datetime'],
            'end_datetime' => $event['end_datetime'],
            'all_day' => $event['all_day'],
            'event_type' => $event['event_type'],
            'location' => $event['location'],
            'color' => $event['color'],
            'status' => $event['status'],
            'client_name' => $event['client_name'],
            'property_ref' => $event['property_ref'],
            'property_title' => $event['property_title'],
            'creator_name' => $event['creator_name']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'events' => $formattedEvents
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}