<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Si está autenticado, redirigir al calendario
header('Location: calendario.php');
exit();
?>