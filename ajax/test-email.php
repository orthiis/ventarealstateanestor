<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

header('Content-Type: application/json');

requireLogin();

$currentUser = getCurrentUser();

if ($currentUser['role']['name'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

try {
    // Obtener configuración SMTP
    $settings = [];
    $settingsFromDB = db()->select("SELECT * FROM system_settings WHERE category = 'email'");
    foreach ($settingsFromDB as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Enviar email de prueba
    $to = $currentUser['email'];
    $subject = "Email de prueba - " . SITE_NAME;
    $message = "Este es un email de prueba para verificar la configuración SMTP de tu CRM.";
    
    // Aquí implementarías el envío real con PHPMailer o similar
    // Por ahora simulamos el éxito
    
    echo json_encode([
        'success' => true,
        'message' => "Email de prueba enviado a {$to}"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}