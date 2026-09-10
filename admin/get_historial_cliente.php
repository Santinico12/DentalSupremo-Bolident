<?php
session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';

header('Content-Type: application/json; charset=utf-8');

$clienteId = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;

if ($clienteId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cliente inválido']);
    exit();
}

try {
    $appointmentModel = new Appointment($pdo);
    $citas = $appointmentModel->getByClientId($clienteId);

    // Format dates and add status labels
    $result = [];
    foreach ($citas as $cita) {
        $fechaObj = new DateTime($cita['fecha']);
        $result[] = [
            'id' => $cita['id'],
            'fecha' => $fechaObj->format('d/m/Y'),
            'hora' => $fechaObj->format('H:i'),
            'descripcion' => $cita['descripcion'] ?? '',
            'estado' => $cita['estado'] ?? 'activo',
            'consultorio_nombre' => $cita['consultorio_nombre'],
            'consultorio_color' => $cita['consultorio_color'],
            'doctor_nombre' => $cita['doctor_nombre'],
            'duracion_estimada' => $cita['duracion_estimada'] ?? 30
        ];
    }

    echo json_encode([
        'success' => true,
        'total' => count($result),
        'citas' => $result
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener el historial']);
}
