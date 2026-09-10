<?php
require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Consultorio.php';

date_default_timezone_set('America/La_Paz');

$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

$appointmentModel = new Appointment($pdo);
$citas = $appointmentModel->getByDate($fecha);

// Filtrar: solo ACTIVAS y que se aproximan
$now = new DateTime('now', new DateTimeZone('America/La_Paz'));
$selectedDate = new DateTime($fecha, new DateTimeZone('America/La_Paz'));
$today = $now->format('Y-m-d');

$filtradas = [];
foreach ($citas as $c) {
    // Solo activas
    if (isset($c['estado']) && strtolower($c['estado']) !== 'activo') {
        continue;
    }
    // Si es hoy: solo futuras
    if ($selectedDate->format('Y-m-d') === $today) {
        $cDate = new DateTime($c['fecha'], new DateTimeZone('America/La_Paz'));
        if ($cDate < $now) { continue; }
    }
    // Si es una fecha pasada: no mostrar
    if ($selectedDate->format('Y-m-d') < $today) {
        continue;
    }
    $filtradas[] = $c;
}
$citas = $filtradas;

$consultorioModel = new Consultorio($pdo);
$consultorios = $consultorioModel->getAll();

// Organizar citas por consultorio
$citas_por_consultorio = [];
foreach ($citas as $cita) {
    $consultorio_id = $cita['consultorio_id'];
    if (!isset($citas_por_consultorio[$consultorio_id])) {
        $citas_por_consultorio[$consultorio_id] = [];
    }
    $citas_por_consultorio[$consultorio_id][] = $cita;
}

// Generar el HTML
if (empty($citas)) {
    echo "No hay citas activas pr¨®ximas para esta fecha.";
} else {
    foreach ($consultorios as $consultorio) {
        $consultorio_id = $consultorio['id'];
        if (isset($citas_por_consultorio[$consultorio_id])) {
            $color = htmlspecialchars($consultorio['color'], ENT_QUOTES, 'UTF-8');
            $nombreConsultorio = htmlspecialchars($consultorio['nombre'], ENT_QUOTES, 'UTF-8');
            echo "<h3 style='color: {$color};'>{$nombreConsultorio}</h3>";
            echo "<ul>";
            foreach ($citas_por_consultorio[$consultorio_id] as $cita) {
                $cliente = htmlspecialchars($cita['cliente_nombre'], ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars((string)$cita['descripcion'], ENT_QUOTES, 'UTF-8');
                $dur = (int)$cita['duracion_estimada'];
                $hora = date('H:i', strtotime($cita['fecha']));
                echo "<li>{$cliente} - {$desc} - Duraci¨®n: {$dur} minutos - Hora: {$hora}</li>";
            }
            echo "</ul>";
        }
    }
}
?>
