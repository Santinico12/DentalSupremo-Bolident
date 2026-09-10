<?php
/**
 * Subir Archivo Clínico
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lista_clientes.php');
    exit();
}

$clienteId = intval($_POST['cliente_id'] ?? 0);
if (!$clienteId || !isset($_FILES['archivo'])) {
    header('Location: lista_clientes.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/ArchivoClinico.php';

$archivoModel = new ArchivoClinico($pdo);

// Validar archivo
$file = $_FILES['archivo'];
// Solo permitir PDF e imágenes (NO Word, Excel, etc.)
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$maxSize = 5 * 1024 * 1024; // 5MB máximo

// Validar error de subida
if ($file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['message'] = 'Error al subir el archivo';
    $_SESSION['message_type'] = 'danger';
    header('Location: historia_clinica.php?id=' . $clienteId);
    exit();
}

// Validar tamaño
if ($file['size'] > $maxSize) {
    $_SESSION['message'] = 'El archivo es demasiado grande. Máximo permitido: 5MB';
    $_SESSION['message_type'] = 'danger';
    header('Location: historia_clinica.php?id=' . $clienteId);
    exit();
}

// Validar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    $_SESSION['message'] = 'Tipo de archivo no permitido. Solo se aceptan: PDF, JPG, PNG, GIF, WebP';
    $_SESSION['message_type'] = 'danger';
    header('Location: historia_clinica.php?id=' . $clienteId);
    exit();
}

// Validar extensión
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    $_SESSION['message'] = 'Extensión de archivo no permitida. Solo se aceptan: PDF, JPG, PNG, GIF, WebP';
    $_SESSION['message_type'] = 'danger';
    header('Location: historia_clinica.php?id=' . $clienteId);
    exit();
}

$data = [
    'tipo' => $_POST['tipo'] ?? 'otro',
    'descripcion' => $_POST['descripcion'] ?? '',
    'fecha_toma' => $_POST['fecha_toma'] ?? date('Y-m-d'),
    'dientes_relacionados' => $_POST['dientes_relacionados'] ?? ''
];

if ($archivoModel->subir($clienteId, $file, $data)) {
    $_SESSION['message'] = 'Archivo subido correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al guardar el archivo';
    $_SESSION['message_type'] = 'danger';
}

header('Location: historia_clinica.php?id=' . $clienteId . '#archivos');
exit();
?>
