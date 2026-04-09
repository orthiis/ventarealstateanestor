<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destruir todas las variables de sesión del cliente
unset($_SESSION['client_id']);
unset($_SESSION['client_email']);
unset($_SESSION['client_name']);

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir la sesión completamente
session_destroy();

// Redirigir al login usando la función url()
header('Location: ' . url('login-clientes.php'));
exit;