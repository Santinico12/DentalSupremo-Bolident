<?php
/**
 * Eliminar Archivo Clínico
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$id = intval($_GET['id'] ?? 0);
$clienteId = intval($_GET['cliente_id'] ?? 0);

if (!$id || !$clienteId) {
    header('Location: lista_clientes.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/ArchivoClinico.php';

$archivoModel = new ArchivoClinico($pdo);

if ($archivoModel->eliminar($id)) {
    $_SESSION['message'] = 'Archivo eliminado correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al eliminar el archivo';
    $_SESSION['message_type'] = 'danger';
}

header('Location: historia_clinica.php?id=' . $clienteId . '#archivos');
exit();
?>
