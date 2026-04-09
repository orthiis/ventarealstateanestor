<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

$lang = $_GET['lang'] ?? 'es';

// Validar idioma
if (!in_array($lang, ['es', 'en'])) {
    $lang = 'es';
}

// Guardar en sesi¨®n
$_SESSION['user_language'] = $lang;

// Guardar en base de datos
$currentUser = getCurrentUser();
if ($currentUser) {
    try {
        db()->update('users', [
            'language' => $lang
        ], 'id = ?', [$currentUser['id']]);
    } catch (Exception $e) {
        // Si falla, al menos guardamos en sesi¨®n
        error_log('Error updating language: ' . $e->getMessage());
    }
}

// Redirigir de vuelta
$referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $referer);
exit;