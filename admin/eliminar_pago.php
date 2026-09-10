<?php
/**
 * Eliminar Pago
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: pagos.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';

$pagoModel = new Pago($pdo);

if ($pagoModel->eliminar($id)) {
    $_SESSION['message'] = 'Pago eliminado correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al eliminar el pago';
    $_SESSION['message_type'] = 'danger';
}

header('Location: pagos.php');
exit();
?>
