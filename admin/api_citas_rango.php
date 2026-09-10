<?php
/**
 * API Cargar Citas y Eventos por Rango de Fechas (FullCalendar Dynamic Feed)
 * Soporta filtrado opcional por consultorio_id
 */
date_default_timezone_set('America/La_Paz');
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Event.php';

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$consultorioId = isset($_GET['consultorio_id']) && $_GET['consultorio_id'] !== '' ? intval($_GET['consultorio_id']) : null;

if (!$start || !$end) {
    echo json_encode([]);
    exit();
}

// Convertir fechas ISO de FullCalendar a MySQL datetime
$fechaInicio = date('Y-m-d H:i:s', strtotime($start));
$fechaFin = date('Y-m-d H:i:s', strtotime($end));

$appointmentModel = new Appointment($pdo);
$eventModel = new Event($pdo);

if ($consultorioId) {
    $citas = $appointmentModel->getByDateRangeAndConsultorio($fechaInicio, $fechaFin, $consultorioId);
    $eventos = $eventModel->getByDateRangeAndConsultorio($fechaInicio, $fechaFin, $consultorioId);
} else {
    $citas = $appointmentModel->getByDateRange($fechaInicio, $fechaFin);
    $eventos = $eventModel->getByDateRange($fechaInicio, $fechaFin);
}

$eventsList = [];

// Formatear Citas para FullCalendar
foreach ($citas as $cita) {
    // Si viene de un tab de consultorio específico, omitir cancelados/pospuestos si no aplican
    $eventsList[] = [
        'id' => (string)$cita['id'],
        'title' => $cita['cliente_nombre'],
        'start' => date("Y-m-d\TH:i:s", strtotime($cita['fecha'])),
        'end' => date("Y-m-d\TH:i:s", strtotime($cita['fecha'] . ' + ' . $cita['duracion_estimada'] . ' minutes')),
        'backgroundColor' => $cita['consultorio_color'],
        'borderColor' => $cita['consultorio_color'],
        'textColor' => '#000000',
        'extendedProps' => [
            'type' => 'cita',
            'telefono' => (string)$cita['telefono'],
            'duration' => (string)$cita['duracion_estimada'],
            'description' => (string)($cita['descripcion'] ?? ''),
            'consultorio_id' => (string)$cita['consultorio_id'],
            'consultorio_nombre' => (string)$cita['consultorio_nombre'],
            'consultorio_color' => (string)$cita['consultorio_color'],
            'doctor_id' => (string)($cita['doctor_id'] ?? '0'),
            'doctor_nombre' => (string)($cita['doctor_nombre'] ?? 'Sin asignar'),
            'estado' => (string)($cita['estado'] ?? 'activo')
        ]
    ];
}

// Formatear Eventos para FullCalendar
foreach ($eventos as $ev) {
    $eventsList[] = [
        'id' => 'ev_' . $ev['id'],
        'title' => '[E] ' . $ev['nombre'],
        'start' => date("Y-m-d\TH:i:s", strtotime($ev['fecha'])),
        'end' => date("Y-m-d\TH:i:s", strtotime($ev['fecha'] . ' + ' . $ev['duracion_estimada'] . ' minutes')),
        'backgroundColor' => $ev['color'] ?: '#808080',
        'borderColor' => $ev['color'] ?: '#808080',
        'textColor' => '#FFFFFF',
        'extendedProps' => [
            'type' => 'evento',
            'real_id' => (string)$ev['id'],
            'nombre' => (string)$ev['nombre'],
            'duration' => (string)$ev['duracion_estimada'],
            'description' => (string)($ev['descripcion'] ?? ''),
            'consultorio_id' => (string)$ev['consultorio_id'],
            'consultorio_nombre' => (string)$ev['consultorio_nombre'],
            'consultorio_color' => (string)($ev['color'] ?: '#808080')
        ]
    ];
}

echo json_encode($eventsList, JSON_UNESCAPED_UNICODE);
?>
