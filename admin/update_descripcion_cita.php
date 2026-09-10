<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';

header('Content-Type: application/json');

$appointmentModel = new Appointment($pdo);
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !array_key_exists('descripcion', $input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit();
}

$id = (int)$input['id'];
$descripcion = trim((string)$input['descripcion']);
// Normalizar: sin saltos de línea ni espaciados excesivos
$descripcion = preg_replace("/[\r\n]+/u", ' ', $descripcion);
$descripcion = preg_replace('/\s{2,}/u', ' ', $descripcion);

$ok = $appointmentModel->updateDescripcion($id, $descripcion);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la descripción.']);
}
?>
