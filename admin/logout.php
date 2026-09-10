<?php
// Iniciar la sesión
session_start();

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destruir la sesión
session_destroy();

// Establecer un encabezado de no caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirigir al login (preservando el mensaje de timeout si aplica)
$redirectUrl = isset($_GET['timeout']) ? 'login.php?timeout=1' : 'login.php';
header('Location: ' . $redirectUrl);
exit();
?>