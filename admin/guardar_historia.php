<?php
/**
 * Guardar Historia Clínica (Ficha Médica)
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
require_once '../src/models/HistoriaClinica.php';

$historiaModel = new HistoriaClinica($pdo);

$data = [
    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
    'sexo' => $_POST['sexo'] ?: null,
    'ocupacion' => $_POST['ocupacion'] ?? '',
    'direccion' => $_POST['direccion'] ?? '',
    'email' => $_POST['email'] ?? '',
    'contacto_emergencia_nombre' => $_POST['contacto_emergencia_nombre'] ?? '',
    'contacto_emergencia_telefono' => $_POST['contacto_emergencia_telefono'] ?? '',
    'contacto_emergencia_parentesco' => $_POST['contacto_emergencia_parentesco'] ?? '',
    'grupo_sanguineo' => $_POST['grupo_sanguineo'] ?? '',
    'alergias' => $_POST['alergias'] ?? '',
    'enfermedades_sistemicas' => $_POST['enfermedades_sistemicas'] ?? '',
    'medicamentos_actuales' => $_POST['medicamentos_actuales'] ?? '',
    'cirugias_previas' => $_POST['cirugias_previas'] ?? '',
    'hospitalizaciones' => $_POST['hospitalizaciones'] ?? '',
    'ultima_visita_dentista' => $_POST['ultima_visita_dentista'] ?: null,
    'experiencia_anestesia' => $_POST['experiencia_anestesia'] ?? '',
    'habitos' => $_POST['habitos'] ?? '',
    'higiene_bucal' => $_POST['higiene_bucal'] ?? '',
    'embarazo' => isset($_POST['embarazo']) ? 1 : 0,
    'lactancia' => isset($_POST['lactancia']) ? 1 : 0,
    'observaciones' => $_POST['observaciones'] ?? ''
];

if ($historiaModel->guardar($clienteId, $data)) {
    $_SESSION['message'] = 'Ficha médica guardada correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al guardar la ficha médica';
    $_SESSION['message_type'] = 'danger';
}

header('Location: historia_clinica.php?id=' . $clienteId);
exit();
?>
