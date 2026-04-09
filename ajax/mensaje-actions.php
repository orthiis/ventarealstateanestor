<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    
    switch ($action) {
        
        // ============ MARCAR COMO LEÍDA ============
        case 'mark_read':
            $inquiryId = (int)$_POST['id'];
            
            if (!$inquiryId) {
                throw new Exception('ID de consulta no válido');
            }
            
            db()->update('inquiries', [
                'status' => 'read'
            ], ['id' => $inquiryId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'inquiry',
                'entity_id' => $inquiryId,
                'description' => 'Consulta marcada como leída',
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ASIGNAR AGENTE ============
        case 'assign':
            $inquiryId = (int)$_POST['id'];
            $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : $currentUser['id'];
            $notifyAgent = isset($_POST['notify_agent']);
            
            if (!$inquiryId) {
                throw new Exception('ID de consulta no válido');
            }
            
            // Verificar que el usuario existe
            $user = db()->selectOne("SELECT * FROM users WHERE id = ?", [$userId]);
            if (!$user) {
                throw new Exception('Usuario no encontrado');
            }
            
            db()->update('inquiries', [
                'assigned_to' => $userId,
                'status' => 'read'
            ], ['id' => $inquiryId]);
            
            // Enviar notificación al agente si está marcado
            if ($notifyAgent) {
                // Aquí iría el código para enviar email de notificación
                // sendEmailNotification($user['email'], 'Nueva consulta asignada', ...);
            }
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'inquiry',
                'entity_id' => $inquiryId,
                'description' => "Consulta asignada a: {$user['first_name']} {$user['last_name']}",
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ RESPONDER CONSULTA ============
        case 'reply':
            $inquiryId = (int)$_POST['inquiry_id'];
            $responseText = trim($_POST['response_text']);
            $subject = trim($_POST['subject']);
            $sendEmail = isset($_POST['send_email']);
            
            if (!$inquiryId || !$responseText) {
                throw new Exception('Datos incompletos');
            }
            
            // Obtener datos de la consulta
            $inquiry = db()->selectOne("SELECT * FROM inquiries WHERE id = ?", [$inquiryId]);
            if (!$inquiry) {
                throw new Exception('Consulta no encontrada');
            }
            
            db()->beginTransaction();
            
            // Guardar respuesta en tabla inquiry_responses
            $responseId = db()->insert('inquiry_responses', [
                'inquiry_id' => $inquiryId,
                'user_id' => $currentUser['id'],
                'response_text' => $responseText,
                'sent_email' => $sendEmail ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Actualizar estado de la consulta
            db()->update('inquiries', [
                'status' => 'replied',
                'replied_at' => date('Y-m-d H:i:s')
            ], ['id' => $inquiryId]);
            
            // Enviar email si está marcado
            if ($sendEmail) {
                // Aquí iría el código para enviar el email
                // sendEmail($inquiry['email'], $subject, $responseText);
            }
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'inquiry',
                'entity_id' => $inquiryId,
                'description' => 'Respuesta enviada a consulta',
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            db()->commit();
            
            echo json_encode(['success' => true, 'response_id' => $responseId]);
            break;
            
        // ============ ARCHIVAR CONSULTA ============
        case 'archive':
            $inquiryId = (int)$_POST['id'];
            
            if (!$inquiryId) {
                throw new Exception('ID de consulta no válido');
            }
            
            db()->update('inquiries', [
                'status' => 'archived'
            ], ['id' => $inquiryId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'inquiry',
                'entity_id' => $inquiryId,
                'description' => 'Consulta archivada',
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ CONVERTIR A CLIENTE ============
        case 'convert_to_client':
            $inquiryId = (int)$_POST['inquiry_id'];
            $clientId = (int)$_POST['client_id'];
            
            if (!$inquiryId || !$clientId) {
                throw new Exception('Datos incompletos');
            }
            
            db()->update('inquiries', [
                'status' => 'converted',
                'client_id' => $clientId
            ], ['id' => $inquiryId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'update',
                'entity_type' => 'inquiry',
                'entity_id' => $inquiryId,
                'description' => 'Consulta convertida en cliente',
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        // ============ ELIMINAR CONSULTA ============
        case 'delete':
            $inquiryId = (int)$_POST['id'];
            
            // Solo administradores pueden eliminar
            if ($currentUser['role']['name'] !== 'administrador') {
                throw new Exception('No tienes permisos para eliminar consultas');
            }
            
            // Obtener datos antes de eliminar
            $inquiry = db()->selectOne("SELECT * FROM inquiries WHERE id = ?", [$inquiryId]);
            if (!$inquiry) {
                throw new Exception('Consulta no encontrada');
            }
            
            // Eliminar respuestas asociadas primero
            db()->query("DELETE FROM inquiry_responses WHERE inquiry_id = ?", [$inquiryId]);
            
            // Eliminar consulta
            db()->delete('inquiries', ['id' => $inquiryId]);
            
            // Log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'delete',
                'entity_type' => 'inquiry',
                'entity_id' => $inquiryId,
                'description' => 'Consulta eliminada: ' . $inquiry['name'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            echo json_encode(['success' => true]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}