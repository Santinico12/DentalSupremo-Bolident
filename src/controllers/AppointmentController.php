<?php

require_once __DIR__ . '/../models/Appointment.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $fecha = $_POST['fecha'];
    $descripcion = $_POST['descripcion'];
    $duracion_estimada = $_POST['duracion_estimada'];

    $appointmentModel = new Appointment($pdo);
    $appointmentId = $appointmentModel->create($cliente_id, $fecha, $descripcion, $duracion_estimada);

    if ($appointmentId) {
        header('Location: dashboard.php');
    } else {
        echo "Error al registrar la cita.";
    }
}
?>