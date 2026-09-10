<?php
session_start();
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';

$appointmentModel = new Appointment($pdo);

// Verificar si se recibiио una solicitud POST con datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['id'])) {
    $id = $input['id'];

    if ($appointmentModel->delete($id)) {
        echo json_encode(['success' => true, 'message' => 'Cita eliminada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la cita.']);
    }
} 
?>