<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

$currentUser = getCurrentUser();

try {
    $documentId = (int)($_GET['id'] ?? 0);
    
    if (!$documentId) {
        die('ID de documento no válido');
    }
    
    $document = db()->selectOne("SELECT * FROM documents WHERE id = ?", [$documentId]);
    
    if (!$document) {
        die('Documento no encontrado');
    }
    
    $filePath = "../" . $document['file_path'];
    
    if (!file_exists($filePath)) {
        die('Archivo no encontrado en el servidor');
    }
    
    // Registrar descarga
    db()->update('documents',
        ['downloads_count' => $document['downloads_count'] + 1],
        'id = ?',
        [$documentId]
    );
    
    db()->insert('document_tracking', [
        'document_id' => $documentId,
        'user_id' => $currentUser['id'],
        'action' => 'download',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Enviar archivo
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Disposition: attachment; filename="' . $document['file_name'] . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}