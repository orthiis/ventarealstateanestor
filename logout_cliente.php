<?php
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al login de clientes
header('Location: ../login-clientes.php');
exit;
?>