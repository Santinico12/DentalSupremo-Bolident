<?php
/**
 * Guardar Evolución Clínica
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lista_clientes.php');
    exit();
}

$clienteId = intval($_POST['cliente_id'] ?? 0);
if (!$clienteId) {
    header('Location: lista_clientes.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Evolucion.php';

$evolucionModel = new Evolucion($pdo);

$data = [
    'cliente_id' => $clienteId,
    'doctor_id' => $_POST['doctor_id'] ?: null,
    'fecha_atencion' => date('Y-m-d H:i:s'),
    'motivo_consulta' => $_POST['motivo_consulta'],
    'examen_clinico' => $_POST['examen_clinico'] ?? '',
    'diagnostico' => $_POST['diagnostico'] ?? '',
    'tratamiento_realizado' => $_POST['tratamiento_realizado'],
    'dientes_tratados' => $_POST['dientes_tratados'] ?? '',
    'materiales_usados' => $_POST['materiales_usados'] ?? '',
    'indicaciones_paciente' => $_POST['indicaciones_paciente'] ?? '',
    'receta_medica' => $_POST['receta_medica'] ?? '',
    'proxima_cita' => $_POST['proxima_cita'] ?? '',
    'updated_by' => $_SESSION['user']['username'] ?? 'admin'
];

if ($evolucionModel->crear($data)) {
    $_SESSION['message'] = 'Evolución registrada correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al registrar la evolución';
    $_SESSION['message_type'] = 'danger';
}

header('Location: historia_clinica.php?id=' . $clienteId . '#evoluciones');
exit();
?>
