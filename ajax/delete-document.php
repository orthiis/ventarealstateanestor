<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$currentUser = getCurrentUser();

try {
    $documentId = (int)($_GET['id'] ?? 0);
    
    if (!$documentId) {
        throw new Exception('ID de documento no válido');
    }
    
    // Obtener documento
    $document = db()->selectOne("SELECT * FROM documents WHERE id = ?", [$documentId]);
    
    if (!$document) {
        throw new Exception('Documento no encontrado');
    }
    
    // Verificar permisos
    if ($currentUser['role']['name'] !== 'administrador' && $document['uploaded_by'] != $currentUser['id']) {
        throw new Exception('No tienes permisos para eliminar este documento');
    }
    
    // Eliminar archivo físico
    $filePath = "../" . $document['file_path'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    // Marcar como eliminado en BD (soft delete)
    db()->update('documents', 
        ['status' => 'deleted', 'updated_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [$documentId]
    );
    
    // Registrar actividad
    db()->insert('document_tracking', [
        'document_id' => $documentId,
        'user_id' => $currentUser['id'],
        'action' => 'delete',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documento eliminado correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}