<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$pageTitle = 'Editar Evento';
$currentUser = getCurrentUser();

// Obtener ID del evento
$eventId = $_GET['id'] ?? 0;

// Obtener datos del evento
$event = db()->selectOne("SELECT * FROM calendar_events WHERE id = ?", [$eventId]);

if (!$event) {
    setFlashMessage('error', 'Evento no encontrado');
    redirect('calendario.php');
}

// Verificar permisos
if ($currentUser['role']['name'] !== 'administrador' && $event['created_by'] != $currentUser['id']) {
    setFlashMessage('error', 'No tienes permisos para editar este evento');
    redirect('calendario.php');
}

// Obtener usuarios para asignar
$users = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name
     FROM users 
     WHERE status = 'active' 
     ORDER BY first_name"
);

// Obtener clientes activos
$clients = db()->select(
    "SELECT id, CONCAT(first_name, ' ', last_name) as name, reference
     FROM clients 
     WHERE is_active = 1 
     ORDER BY first_name 
     LIMIT 100"
);

// Obtener propiedades disponibles
$properties = db()->select(
    "SELECT id, reference, title, city
     FROM properties 
     WHERE status IN ('available', 'reserved') 
     ORDER BY created_at DESC 
     LIMIT 100"
);

// Decodificar asistentes
$attendees = json_decode($event['attendees'] ?? '[]', true);

include 'header.php';
include 'sidebar.php';