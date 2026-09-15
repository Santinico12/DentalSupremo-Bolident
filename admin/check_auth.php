<?php
/**
 * Dental Supremo - Verificador de Autenticación y Control de Inactividad
 * Cierra automáticamente la sesión tras 15 minutos sin interacción por seguridad.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Encabezados de seguridad para evitar caché en navegadores
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Tiempo límite de inactividad: 15 minutos (900 segundos)
$inactivityLimit = 900;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivityLimit)) {
    // Sesión expirada por inactividad
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
    
    // Si la petición es AJAX / JSON, responder con error 401
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'session_expired' => true,
            'message' => 'Tu sesión ha expirado por inactividad.'
        ]);
        exit();
    }
    
    header('Location: login.php?timeout=1');
    exit();
}

// Actualizar marca de tiempo de la última actividad
$_SESSION['last_activity'] = time();
?>