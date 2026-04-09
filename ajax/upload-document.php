<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

try {
    // Validar que se haya subido un archivo
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se ha subido ningún archivo o hubo un error');
    }
    
    $file = $_FILES['document'];
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $relatedPropertyId = !empty($_POST['related_property_id']) ? (int)$_POST['related_property_id'] : null;
    $relatedClientId = !empty($_POST['related_client_id']) ? (int)$_POST['related_client_id'] : null;
    $isTemplate = isset($_POST['is_template']) ? 1 : 0;
    $isConfidential = isset($_POST['is_confidential']) ? 1 : 0;
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    // Validaciones
    if (empty($name)) {
        throw new Exception('El nombre del documento es requerido');
    }
    
    if (empty($category)) {
        throw new Exception('La categoría es requerida');
    }
    
    // Validar tamaño (max 50MB)
    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        throw new Exception('El archivo es demasiado grande. Máximo 50MB');
    }
    
    // Extensiones permitidas
    $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        throw new Exception('Tipo de archivo no permitido. Extensiones permitidas: ' . implode(', ', $allowedExtensions));
    }
    
    // Crear directorio de uploads si no existe
    $uploadDir = dirname(__DIR__) . '/uploads/documents';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Crear subdirectorio por año/mes
    $subDir = date('Y') . '/' . date('m');
    $fullUploadDir = $uploadDir . '/' . $subDir;
    if (!file_exists($fullUploadDir)) {
        mkdir($fullUploadDir, 0755, true);
    }
    
    // Generar nombre único para el archivo
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $filePath = $fullUploadDir . '/' . $fileName;
    $fileUrl = 'uploads/documents/' . $subDir . '/' . $fileName;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Error al guardar el archivo');
    }
    
    // Determinar tipo MIME
    $mimeType = mime_content_type($filePath);
    
    // Insertar en base de datos
    db()->beginTransaction();
    
    $documentId = db()->insert('documents', [
        'name' => $name,
        'description' => $description,
        'file_type' => $extension,
        'mime_type' => $mimeType,
        'file_size' => $file['size'],
        'file_url' => $fileUrl,
        'category' => $category,
        'related_property_id' => $relatedPropertyId,
        'related_client_id' => $relatedClientId,
        'uploaded_by' => $currentUser['id'],
        'is_template' => $isTemplate,
        'is_confidential' => $isConfidential,
        'expiry_date' => $expiryDate,
        'view_count' => 0,
        'is_active' => 1
    ]);
    
    // Log de actividad
    db()->insert('activity_log', [
        'user_id' => $currentUser['id'],
        'action' => 'create',
        'entity_type' => 'document',
        'entity_id' => $documentId,
        'description' => "Documento subido: {$name}",
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento subido exitosamente',
        'document_id' => $documentId
    ]);
    
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    
    // Eliminar archivo si se subió pero hubo error en BD
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}