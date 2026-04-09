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

try {
    $propertyId = $_POST['property_id'] ?? 0;
    $documentName = trim($_POST['document_name'] ?? '');
    $documentDescription = trim($_POST['document_description'] ?? '');
    
    // Validaciones
    if (empty($documentName)) {
        throw new Exception('El nombre del documento es requerido');
    }
    
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Error al subir el archivo');
    }
    
    // Verificar que el cliente tiene acceso a esta propiedad
    $property = getClientProperty($currentClient['id'], $propertyId);
    if (!$property) {
        throw new Exception('No tienes acceso a esta propiedad');
    }
    
    $file = $_FILES['document_file'];
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validar tamaño (máximo 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
        throw new Exception('El archivo no debe superar los 5MB');
    }
    
    // Validar tipo de archivo
    $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Permitidos: PDF, JPG, PNG, DOC, DOCX, XLS, XLSX');
    }
    
    // Crear directorio si no existe
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/client_documents/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generar nombre único para el archivo
    $newFileName = 'client_' . $currentClient['id'] . '_prop_' . $propertyId . '_' . time() . '_' . uniqid() . '.' . $fileType;
    $filePath = $uploadDir . $newFileName;
    $filePathDb = '/uploads/client_documents/' . $newFileName;
    
    // Mover archivo
    if (!move_uploaded_file($fileTmp, $filePath)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // Obtener MIME type
    $mimeType = mime_content_type($filePath);
    
    // Insertar en base de datos
    db()->insert('client_property_documents', [
        'client_id' => $currentClient['id'],
        'property_id' => $propertyId,
        'transaction_id' => $property['id'],
        'document_name' => sanitizeInput($documentName),
        'document_description' => !empty($documentDescription) ? sanitizeInput($documentDescription) : null,
        'file_name' => $fileName,
        'file_path' => $filePathDb,
        'file_size' => $fileSize,
        'file_type' => $fileType,
        'mime_type' => $mimeType,
        'uploaded_by_client' => 1,
        'uploaded_by_user_id' => null,
        'is_visible_to_client' => 1,
        'upload_date' => date('Y-m-d H:i:s'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento subido exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}