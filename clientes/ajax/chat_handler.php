<?php
require_once '../../config.php';
require_once '../../database.php';
require_once '../../functions.php';
require_once '../includes/functions.php';

session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$currentClient = getCurrentClient();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'send_message':
            $propertyId = $_POST['property_id'] ?? 0;
            $message = trim($_POST['message'] ?? '');
            
            if (empty($message)) {
                throw new Exception('El mensaje no puede estar vacío');
            }
            
            // Verificar que el cliente tiene acceso a esta propiedad
            $property = getClientProperty($currentClient['id'], $propertyId);
            if (!$property) {
                throw new Exception('No tienes acceso a esta propiedad');
            }
            
            // Insertar mensaje
            db()->insert('client_property_comments', [
                'client_id' => $currentClient['id'],
                'property_id' => $propertyId,
                'transaction_id' => $property['id'],
                'user_id' => null,
                'sender_type' => 'client',
                'message' => sanitizeInput($message),
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Mensaje enviado correctamente'
            ]);
            break;
            
        case 'get_messages':
            $propertyId = $_GET['property_id'] ?? 0;
            
            // Verificar acceso
            $property = getClientProperty($currentClient['id'], $propertyId);
            if (!$property) {
                throw new Exception('No tienes acceso a esta propiedad');
            }
            
            // Obtener mensajes
            $comments = getPropertyComments($currentClient['id'], $propertyId);
            
            // Marcar como leídos
            markCommentsAsRead($currentClient['id'], $propertyId);
            
            // Formatear mensajes
            $messages = [];
            foreach ($comments as $comment) {
                $messages[] = [
                    'id' => $comment['id'],
                    'sender_type' => $comment['sender_type'],
                    'message' => htmlspecialchars($comment['message']),
                    'admin_name' => $comment['admin_name'] ?? '',
                    'created_at' => date('d/m/Y H:i', strtotime($comment['created_at'])),
                    'is_read' => $comment['is_read']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;
            
        case 'mark_as_read':
            $propertyId = $_POST['property_id'] ?? 0;
            
            markCommentsAsRead($currentClient['id'], $propertyId);
            
            echo json_encode([
                'success' => true,
                'message' => 'Mensajes marcados como leídos'
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}