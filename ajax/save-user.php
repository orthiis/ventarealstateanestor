<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

// Solo administradores pueden crear/editar usuarios
if ($currentUser['role']['name'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'No tienes permisos']);
    exit;
}

try {
    $userId = $_POST['id'] ?? null;
    
    // Validar datos requeridos
    $required = ['first_name', 'last_name', 'username', 'email', 'role_id'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("El campo {$field} es requerido");
        }
    }
    
    // Validar email
    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Verificar username único
    $existingUser = db()->selectOne(
        "SELECT id FROM users WHERE username = ? AND id != ?",
        [$_POST['username'], $userId ?? 0]
    );
    
    if ($existingUser) {
        throw new Exception('El nombre de usuario ya existe');
    }
    
    // Verificar email único
    $existingEmail = db()->selectOne(
        "SELECT id FROM users WHERE email = ? AND id != ?",
        [$_POST['email'], $userId ?? 0]
    );
    
    if ($existingEmail) {
        throw new Exception('El email ya está registrado');
    }
    
    db()->beginTransaction();
    
    // Preparar datos
    $userData = [
        'username' => sanitize($_POST['username']),
        'email' => sanitize($_POST['email']),
        'first_name' => sanitize($_POST['first_name']),
        'last_name' => sanitize($_POST['last_name']),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'role_id' => (int)$_POST['role_id'],
        'office_id' => !empty($_POST['office_id']) ? (int)$_POST['office_id'] : null,
        'status' => sanitize($_POST['status'] ?? 'active'),
        'position' => sanitize($_POST['position'] ?? ''),
        'specialization' => sanitize($_POST['specialization'] ?? ''),
        'commission_sale' => (float)($_POST['commission_sale'] ?? 0),
        'commission_rent' => (float)($_POST['commission_rent'] ?? 0),
        'show_in_public_website' => isset($_POST['show_in_public_website']) ? 1 : 0,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if ($userId) {
        // Actualizar usuario existente
        
        // Solo actualizar contraseña si se proporciona
        if (!empty($_POST['password'])) {
            if ($_POST['password'] !== $_POST['password_confirm']) {
                throw new Exception('Las contraseñas no coinciden');
            }
            
            if (strlen($_POST['password']) < 6) {
                throw new Exception('La contraseña debe tener al menos 6 caracteres');
            }
            
            $userData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        
        db()->update('users', $userData, 'id = :id', ['id' => $userId]);
        
        logActivity('update', 'user', $userId, "Usuario {$userData['username']} actualizado");
        
        $message = 'Usuario actualizado correctamente';
        
    } else {
        // Crear nuevo usuario
        
        if (empty($_POST['password'])) {
            throw new Exception('La contraseña es requerida');
        }
        
        if ($_POST['password'] !== $_POST['password_confirm']) {
            throw new Exception('Las contraseñas no coinciden');
        }
        
        if (strlen($_POST['password']) < 6) {
            throw new Exception('La contraseña debe tener al menos 6 caracteres');
        }
        
        $userData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $userData['created_at'] = date('Y-m-d H:i:s');
        
        $userId = db()->insert('users', $userData);
        
        logActivity('create', 'user', $userId, "Usuario {$userData['username']} creado");
        
        $message = 'Usuario creado correctamente';
    }
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    db()->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}