<?php
/**
 * Renombrar/Editar datos de Archivo Clínico
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lista_clientes.php');
    exit();
}

$id = intval($_POST['id'] ?? 0);
$clienteId = intval($_POST['cliente_id'] ?? 0);

if (!$id || !$clienteId) {
    header('Location: lista_clientes.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/ArchivoClinico.php';

$archivoModel = new ArchivoClinico($pdo);

$data = [
    'descripcion' => trim($_POST['descripcion'] ?? ''),
    'tipo' => trim($_POST['tipo'] ?? 'otro'),
    'fecha_toma' => trim($_POST['fecha_toma'] ?? date('Y-m-d'))
];

if ($archivoModel->actualizar($id, $data)) {
    $_SESSION['message'] = 'Archivo actualizado correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al actualizar el archivo';
    $_SESSION['message_type'] = 'danger';
}

header('Location: historia_clinica.php?id=' . $clienteId . '#archivos');
exit();
?>
