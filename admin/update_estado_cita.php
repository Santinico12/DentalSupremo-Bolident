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

if (!$input || !isset($input['id']) || !isset($input['estado'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit();
}

$id = (int)$input['id'];
$estado = strtolower(trim($input['estado']));

$ok = $appointmentModel->updateEstado($id, $estado);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado no permitido o error al actualizar.']);
}
?>

