<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Habilitar errores para debug (quitar en producción)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/documento_errors.log');

header('Content-Type: application/json');

try {
    requireLogin();
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Sesión no válida. Por favor inicia sesión.'
    ]);
    exit;
}

$currentUser = getCurrentUser();
$response = ['success' => false, 'message' => ''];

try {
    // Detectar si es multipart/form-data (subida de archivo) o JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if(strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
    } else {
        $action = $_POST['action'] ?? '';
        $input = $_POST;
    }
    
    // Si no hay acción y hay archivo, es un upload
    if(empty($action) && isset($_FILES['file'])) {
        $action = 'upload';
    }
    
    switch($action) {
        
        // ============ SUBIR DOCUMENTO ============
        case 'upload':
            
            // Validar que se subió un archivo
            if(!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
                    UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                    UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                    UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
                    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
                    UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                    UPLOAD_ERR_EXTENSION => 'Extensión de archivo no permitida'
                ];
                
                $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
                $errorMsg = $errorMessages[$errorCode] ?? 'Error desconocido al subir el archivo';
                
                throw new Exception($errorMsg);
            }
            
            $file = $_FILES['file'];
            
            // Validar campos requeridos
            if(empty($_POST['document_name'])) {
                throw new Exception('El nombre del documento es requerido');
            }
            
            if(empty($_POST['category_id'])) {
                throw new Exception('La categoría es requerida');
            }
            
            // Validar tamaño (10MB)
            $maxSize = 10 * 1024 * 1024; // 10 MB
            if($file['size'] > $maxSize) {
                throw new Exception('El archivo es demasiado grande. Máximo 10 MB');
            }
            
            // Validar tipo de archivo
            $allowedTypes = [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif'
            ];
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if(!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Tipo de archivo no permitido. Solo se aceptan: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, GIF');
            }
            
            // Obtener extensión
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            // Determinar carpeta según categoría
            $categoryId = intval($_POST['category_id']);
            $folderMap = [
                1 => 'contracts',
                2 => 'contracts',
                3 => 'properties',
                4 => 'clients',
                5 => 'legal',
                6 => 'financial',
                7 => 'templates',
                8 => 'others'
            ];
            
            $folder = $folderMap[$categoryId] ?? 'others';
            $uploadDir = __DIR__ . "/uploads/documents/$folder/";
            
            // Crear directorio si no existe
            if(!is_dir($uploadDir)) {
                if(!mkdir($uploadDir, 0755, true)) {
                    throw new Exception('No se pudo crear el directorio de subida');
                }
            }
            
            // Generar nombre único
            $fileName = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            $filePathRelative = "uploads/documents/$folder/" . $fileName;
            
            // Mover archivo
            if(!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Error al mover el archivo al servidor. Verifica los permisos de la carpeta uploads');
            }
            
            // Procesar tags
            $tags = null;
            if(!empty($_POST['tags'])) {
                $tagsArray = array_map('trim', explode(',', $_POST['tags']));
                $tags = json_encode($tagsArray);
            }
            
            // Preparar datos para insertar
            $documentData = [
                'document_name' => trim($_POST['document_name']),
                'document_number' => !empty($_POST['document_number']) ? trim($_POST['document_number']) : null,
                'file_name' => $file['name'],
                'file_path' => $filePathRelative,
                'file_size' => $file['size'],
                'file_type' => $extension,
                'mime_type' => $mimeType,
                'category_id' => $categoryId,
                'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
                'version' => 1,
                'is_template' => isset($_POST['is_template']) ? 1 : 0,
                'is_signed' => isset($_POST['is_signed']) ? 1 : 0,
                'signed_date' => !empty($_POST['signed_date']) ? $_POST['signed_date'] : null,
                'signed_by' => !empty($_POST['signed_by']) ? trim($_POST['signed_by']) : null,
                'related_entity_type' => $_POST['related_entity_type'] ?? 'general',
                'related_entity_id' => !empty($_POST['related_entity_id']) ? intval($_POST['related_entity_id']) : null,
                'status' => $_POST['status'] ?? 'active',
                'visibility' => $_POST['visibility'] ?? 'private',
                'expiration_date' => !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null,
                'tags' => $tags,
                'uploaded_by' => $currentUser['id'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Insertar en base de datos
            try {
                $documentId = db()->insert('documents', $documentData);
                
                if(!$documentId) {
                    // Si falla la BD, eliminar archivo
                    if(file_exists($filePath)) {
                        unlink($filePath);
                    }
                    throw new Exception('Error al guardar el documento en la base de datos');
                }
                
                // Registrar actividad en document_activity
                db()->insert('document_activity', [
                    'document_id' => $documentId,
                    'user_id' => $currentUser['id'],
                    'action' => 'uploaded',
                    'action_details' => 'Documento subido al sistema',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Registrar en activity_log general
                db()->insert('activity_log', [
                    'user_id' => $currentUser['id'],
                    'action' => 'created',
                    'entity_type' => 'document',
                    'entity_id' => $documentId,
                    'description' => 'Documento subido: ' . $documentData['document_name'],
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $response = [
                    'success' => true,
                    'message' => 'Documento subido exitosamente',
                    'document_id' => $documentId
                ];
                
            } catch(Exception $e) {
                // Si falla la BD, eliminar archivo
                if(file_exists($filePath)) {
                    unlink($filePath);
                }
                throw new Exception('Error en base de datos: ' . $e->getMessage());
            }
            
            break;
        
        // ============ ELIMINAR DOCUMENTO ============
        case 'delete':
            
            $documentId = $input['id'] ?? 0;
            
            if(!$documentId) {
                throw new Exception('ID de documento no proporcionado');
            }
            
            // Obtener documento
            $document = db()->selectOne("SELECT * FROM documents WHERE id = ?", [$documentId]);
            if(!$document) {
                throw new Exception('Documento no encontrado');
            }
            
            // Verificar permisos (solo admin o quien lo subió)
            if($currentUser['role']['name'] !== 'administrador' && $document['uploaded_by'] != $currentUser['id']) {
                throw new Exception('No tienes permisos para eliminar este documento');
            }
            
            // Eliminar archivo físico
            $fullPath = __DIR__ . '/' . $document['file_path'];
            if(file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Eliminar de BD
            db()->delete('documents', 'id = ?', [$documentId]);
            
            // Registrar en activity_log
            db()->insert('activity_log', [
                'user_id' => $currentUser['id'],
                'action' => 'deleted',
                'entity_type' => 'document',
                'entity_id' => $documentId,
                'description' => 'Documento eliminado: ' . $document['document_name'],
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Documento eliminado exitosamente'
            ];
            
            break;
        
        // ============ COMPARTIR DOCUMENTO ============
        case 'share':
            
            $documentId = $input['document_id'] ?? 0;
            $email = trim($input['email'] ?? '');
            $name = trim($input['name'] ?? '');
            $message = trim($input['message'] ?? '');
            $canDownload = isset($input['can_download']) ? (int)$input['can_download'] : 1;
            $expiryDays = isset($input['expiry_days']) ? intval($input['expiry_days']) : 7;
            
            if(!$documentId) {
                throw new Exception('ID de documento no proporcionado');
            }
            
            if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email inválido');
            }
            
            // Calcular fecha de expiración
            $expiryDate = null;
            if($expiryDays > 0) {
                $expiryDate = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
            }
            
            // Generar token único
            $shareToken = bin2hex(random_bytes(32));
            
            // Insertar en document_shares
            $shareId = db()->insert('document_shares', [
                'document_id' => $documentId,
                'shared_by' => $currentUser['id'],
                'shared_with_email' => $email,
                'shared_with_name' => $name,
                'share_token' => $shareToken,
                'message' => $message,
                'can_download' => $canDownload,
                'expiry_date' => $expiryDate,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Aquí podrías enviar un email al destinatario
            // sendEmail($email, 'Documento compartido', ...);
            
            $response = [
                'success' => true,
                'message' => 'Documento compartido exitosamente',
                'share_id' => $shareId
            ];
            
            break;
        
        // ============ AGREGAR COMENTARIO ============
        case 'add_comment':
            
            $documentId = $input['document_id'] ?? 0;
            $comment = trim($input['comment'] ?? '');
            
            if(!$documentId) {
                throw new Exception('ID de documento no proporcionado');
            }
            
            if(empty($comment)) {
                throw new Exception('El comentario no puede estar vacío');
            }
            
            $commentId = db()->insert('document_comments', [
                'document_id' => $documentId,
                'user_id' => $currentUser['id'],
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $response = [
                'success' => true,
                'message' => 'Comentario agregado exitosamente',
                'comment_id' => $commentId
            ];
            
            break;
        
        default:
            throw new Exception('Acción no válida: ' . $action);
    }
    
} catch(Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => DEBUG_MODE ? $e->getTraceAsString() : null
    ];
    
    // Log del error
    error_log('Error en documento-actions.php: ' . $e->getMessage());
}

// Enviar respuesta JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;