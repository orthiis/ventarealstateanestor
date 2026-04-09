<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

// Habilitar reporte de errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Verificar autenticación
try {
    requireLogin();
    $currentUser = getCurrentUser();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Obtener acción
$action = $_POST['action'] ?? $_REQUEST['action'] ?? '';

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'No se especificó ninguna acción'
    ]);
    exit;
}

try {
    
    switch ($action) {
        
        // ============ CREAR CLIENTE ============
        case 'create':
            // Validar campos requeridos
            $requiredFields = ['first_name', 'last_name', 'phone_mobile', 'client_type', 'agent_id'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                throw new Exception('Faltan campos requeridos: ' . implode(', ', $missingFields));
            }
            
            // Generar referencia única
            $reference = 'CLI-' . strtoupper(substr(uniqid(), -8));
            
            // Preparar datos para insertar (solo columnas que existen en la BD)
            $data = [
                'reference' => $reference,
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'document_id' => !empty($_POST['document_id']) ? trim($_POST['document_id']) : null,
                'document_type' => !empty($_POST['document_type']) ? trim($_POST['document_type']) : 'cedula',
                'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
                'phone_mobile' => trim($_POST['phone_mobile']),
                'phone_home' => !empty($_POST['phone_home']) ? trim($_POST['phone_home']) : null,
                'address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
                'city' => !empty($_POST['city']) ? trim($_POST['city']) : null,
                'state_province' => !empty($_POST['state_province']) ? trim($_POST['state_province']) : null,
                'country' => !empty($_POST['country']) ? trim($_POST['country']) : 'República Dominicana',
                'postal_code' => !empty($_POST['postal_code']) ? trim($_POST['postal_code']) : null,
                'date_of_birth' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                'client_type' => $_POST['client_type'],
                'status' => !empty($_POST['status']) ? $_POST['status'] : 'lead',
                'source' => !empty($_POST['source']) ? $_POST['source'] : 'website',
                'budget_min' => !empty($_POST['budget_min']) ? floatval($_POST['budget_min']) : null,
                'budget_max' => !empty($_POST['budget_max']) ? floatval($_POST['budget_max']) : null,
                'property_type_interest' => !empty($_POST['interest_property_types']) ? json_encode($_POST['interest_property_types']) : null,
                'locations_interest' => !empty($_POST['interest_locations']) ? json_encode($_POST['interest_locations']) : null,
                'bedrooms_desired' => !empty($_POST['desired_bedrooms']) ? intval($_POST['desired_bedrooms']) : null,
                'bathrooms_desired' => !empty($_POST['desired_bathrooms']) ? intval($_POST['desired_bathrooms']) : null,
                'must_have_features' => !empty($_POST['desired_features']) ? json_encode($_POST['desired_features']) : null,
                'estimated_decision_date' => !empty($_POST['estimated_decision_date']) ? $_POST['estimated_decision_date'] : null,
                'priority' => !empty($_POST['priority']) ? $_POST['priority'] : 'medium',
                'probability' => !empty($_POST['closing_probability']) ? intval($_POST['closing_probability']) : 50,
                'agent_id' => intval($_POST['agent_id']),
                'notes' => !empty($_POST['internal_notes']) ? trim($_POST['internal_notes']) : null,
                'tags' => !empty($_POST['tags']) ? json_encode($_POST['tags']) : null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'last_contact_date' => date('Y-m-d H:i:s')
            ];
            
            // Insertar en la base de datos
            $clientId = db()->insert('clients', $data);
            
            if (!$clientId) {
                throw new Exception('Error al insertar el cliente en la base de datos');
            }
            
            // Registrar actividad
            try {
                db()->insert('activity_log', [
                    'user_id' => $currentUser['id'],
                    'action' => 'created',
                    'entity_type' => 'client',
                    'entity_id' => $clientId,
                    'new_values' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                // Si falla el log, no es crítico, continuamos
                error_log('Error al registrar actividad: ' . $e->getMessage());
            }
            
            // Crear primera interacción
            try {
                db()->insert('client_interactions', [
                    'client_id' => $clientId,
                    'user_id' => $currentUser['id'],
                    'interaction_type' => 'note',
                    'interaction_date' => date('Y-m-d H:i:s'),
                    'notes' => 'Cliente registrado en el sistema',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                // Si falla la interacción, no es crítico
                error_log('Error al crear interacción inicial: ' . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Cliente creado exitosamente',
                'client_id' => $clientId,
                'reference' => $reference
            ]);
            break;
        
        // ============ ACTUALIZAR CLIENTE ============
        case 'update':
            $clientId = $_POST['id'] ?? null;
            
            if (!$clientId) {
                throw new Exception('ID de cliente no especificado');
            }
            
            // Validar campos requeridos
            $requiredFields = ['first_name', 'last_name', 'phone_mobile', 'client_type', 'agent_id'];
            $missingFields = [];
            
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                throw new Exception('Faltan campos requeridos: ' . implode(', ', $missingFields));
            }
            
            // Obtener datos antiguos para el log
            $oldData = db()->selectOne("SELECT * FROM clients WHERE id = ?", [$clientId]);
            
            if (!$oldData) {
                throw new Exception('Cliente no encontrado');
            }
            
            // Preparar datos para actualizar (solo columnas que existen en la BD)
            $data = [
                'first_name' => trim($_POST['first_name']),
                'last_name' => trim($_POST['last_name']),
                'document_id' => !empty($_POST['document_id']) ? trim($_POST['document_id']) : null,
                'document_type' => !empty($_POST['document_type']) ? trim($_POST['document_type']) : 'cedula',
                'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
                'phone_mobile' => trim($_POST['phone_mobile']),
                'phone_home' => !empty($_POST['phone_home']) ? trim($_POST['phone_home']) : null,
                'address' => !empty($_POST['address']) ? trim($_POST['address']) : null,
                'city' => !empty($_POST['city']) ? trim($_POST['city']) : null,
                'state_province' => !empty($_POST['state_province']) ? trim($_POST['state_province']) : null,
                'country' => !empty($_POST['country']) ? trim($_POST['country']) : 'República Dominicana',
                'postal_code' => !empty($_POST['postal_code']) ? trim($_POST['postal_code']) : null,
                'date_of_birth' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
                'client_type' => $_POST['client_type'],
                'status' => !empty($_POST['status']) ? $_POST['status'] : 'lead',
                'source' => !empty($_POST['source']) ? $_POST['source'] : 'website',
                'budget_min' => !empty($_POST['budget_min']) ? floatval($_POST['budget_min']) : null,
                'budget_max' => !empty($_POST['budget_max']) ? floatval($_POST['budget_max']) : null,
                'property_type_interest' => !empty($_POST['interest_property_types']) ? json_encode($_POST['interest_property_types']) : null,
                'locations_interest' => !empty($_POST['interest_locations']) ? json_encode($_POST['interest_locations']) : null,
                'bedrooms_desired' => !empty($_POST['desired_bedrooms']) ? intval($_POST['desired_bedrooms']) : null,
                'bathrooms_desired' => !empty($_POST['desired_bathrooms']) ? intval($_POST['desired_bathrooms']) : null,
                'must_have_features' => !empty($_POST['desired_features']) ? json_encode($_POST['desired_features']) : null,
                'estimated_decision_date' => !empty($_POST['estimated_decision_date']) ? $_POST['estimated_decision_date'] : null,
                'priority' => !empty($_POST['priority']) ? $_POST['priority'] : 'medium',
                'probability' => !empty($_POST['closing_probability']) ? intval($_POST['closing_probability']) : 50,
                'agent_id' => intval($_POST['agent_id']),
                'notes' => !empty($_POST['internal_notes']) ? trim($_POST['internal_notes']) : null,
                'tags' => !empty($_POST['tags']) ? json_encode($_POST['tags']) : null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Actualizar en la base de datos
            $result = db()->update('clients', $data, 'id = ?', [$clientId]);
            
            // Registrar actividad
            try {
                db()->insert('activity_log', [
                    'user_id' => $currentUser['id'],
                    'action' => 'updated',
                    'entity_type' => 'client',
                    'entity_id' => $clientId,
                    'old_values' => json_encode($oldData),
                    'new_values' => json_encode($data),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                error_log('Error al registrar actividad: ' . $e->getMessage());
            }
            
            // Si cambió el estado, registrar interacción
            if ($oldData['status'] !== $data['status']) {
                try {
                    db()->insert('client_interactions', [
                        'client_id' => $clientId,
                        'user_id' => $currentUser['id'],
                        'interaction_type' => 'note',
                        'interaction_date' => date('Y-m-d H:i:s'),
                        'notes' => "Estado cambiado de '{$oldData['status']}' a '{$data['status']}'",
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e) {
                    error_log('Error al crear interacción de cambio de estado: ' . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Cliente actualizado exitosamente'
            ]);
            break;
        
        // ============ ELIMINAR CLIENTE ============
        case 'delete':
            $clientId = $_POST['id'] ?? $_REQUEST['id'] ?? null;
            
            if (!$clientId) {
                throw new Exception('ID de cliente no especificado');
            }
            
            // Verificar que el cliente existe
            $client = db()->selectOne("SELECT * FROM clients WHERE id = ?", [$clientId]);
            
            if (!$client) {
                throw new Exception('Cliente no encontrado');
            }
            
            // Soft delete
            db()->update('clients', [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$clientId]);
            
            // Registrar actividad
            try {
                db()->insert('activity_log', [
                    'user_id' => $currentUser['id'],
                    'action' => 'deleted',
                    'entity_type' => 'client',
                    'entity_id' => $clientId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                error_log('Error al registrar actividad: ' . $e->getMessage());
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Cliente eliminado exitosamente'
            ]);
            break;
        
        // ============ ACTIVAR CLIENTE ============
        case 'activate':
            $clientId = $_POST['id'] ?? $_REQUEST['id'] ?? null;
            
            if (!$clientId) {
                throw new Exception('ID de cliente no especificado');
            }
            
            db()->update('clients', [
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$clientId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Cliente activado exitosamente'
            ]);
            break;
        
        // ============ AÑADIR INTERACCIÓN ============
        case 'add_interaction':
            $data = [
                'client_id' => $_POST['client_id'],
                'user_id' => $currentUser['id'],
                'interaction_type' => $_POST['interaction_type'],
                'interaction_date' => $_POST['interaction_date'] ?? date('Y-m-d H:i:s'),
                'notes' => $_POST['notes'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            db()->insert('client_interactions', $data);
            
            // Actualizar última fecha de contacto
            db()->update('clients', [
                'last_contact_date' => date('Y-m-d H:i:s')
            ], 'id = ?', [$_POST['client_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Interacción registrada exitosamente'
            ]);
            break;
        
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch (Exception $e) {
    // Log del error
    error_log('Error en cliente-actions.php: ' . $e->getMessage());
    error_log('Trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}