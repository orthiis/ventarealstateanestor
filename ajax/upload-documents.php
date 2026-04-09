<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$currentUser = getCurrentUser();

try {
    // Validar datos
    if (empty($_FILES['files'])) {
        throw new Exception('No se han seleccionado archivos');
    }
    
    $city = trim($_POST['city'] ?? '');
    $propertyId = (int)($_POST['property_id'] ?? 0);
    $folderId = (int)($_POST['folder_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    if (empty($city) || !$propertyId || !$folderId) {
        throw new Exception('Datos incompletos');
    }
    
    // Verificar que la propiedad existe
    $property = db()->selectOne("SELECT * FROM properties WHERE id = ?", [$propertyId]);
    if (!$property) {
        throw new Exception('Propiedad no encontrada');
    }
    
    // Crear directorio si no existe
    $uploadDir = "../uploads/documents/{$city}/" . $property['reference'] . "/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $uploadedCount = 0;
    $errors = [];
    
    // Procesar cada archivo
    foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
            $errors[] = "Error al subir " . $_FILES['files']['name'][$key];
            continue;
        }
        
        $originalName = $_FILES['files']['name'][$key];
        $fileSize = $_FILES['files']['size'][$key];
        $mimeType = $_FILES['files']['type'][$key];
        
        // Validar tipo de archivo
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                         'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                         'image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = "Tipo de archivo no permitido: {$originalName}";
            continue;
        }
        
        // Generar nombre único
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $ext;
        $filePath = $uploadDir . $fileName;
        
        // Mover archivo
        if (move_uploaded_file($tmpName, $filePath)) {
            // Guardar en base de datos
            $documentId = db()->insert('documents', [
                'city' => $city,
                'property_id' => $propertyId,
                'folder_id' => $folderId,
                'document_name' => pathinfo($originalName, PATHINFO_FILENAME),
                'file_name' => $originalName,
                'file_path' => str_replace('../', '', $filePath),
                'file_size' => $fileSize,
                'file_type' => $ext,
                'mime_type' => $mimeType,
                'description' => $description,
                'uploaded_by' => $currentUser['id'],
                'status' => 'active',
                'visibility' => 'private',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Registrar actividad
            db()->insert('document_tracking', [
                'document_id' => $documentId,
                'user_id' => $currentUser['id'],
                'action' => 'upload',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $uploadedCount++;
        } else {
            $errors[] = "Error al guardar {$originalName}";
        }
    }
    
    if ($uploadedCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => "{$uploadedCount} archivo(s) subido(s) correctamente",
            'uploaded' => $uploadedCount,
            'errors' => $errors
        ]);
    } else {
        throw new Exception('No se pudo subir ningún archivo. ' . implode(', ', $errors));
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}