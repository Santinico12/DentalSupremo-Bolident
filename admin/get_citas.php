<?php
require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';

$appointmentModel = new Appointment($pdo);
$citas = $appointmentModel->getAll();

$fechas = [];
foreach ($citas as $cita) {
    $fecha = date('Y-m-d', strtotime($cita['fecha']));
    
    if (!isset($fechas[$fecha])) {
        $fechas[$fecha] = [
            'title' => 'HAY CITAS PROGRAMADAS',
            'start' => $fecha,
            'backgroundColor' => 'green',
            'borderColor' => 'green',
            'textColor' => 'white',
            'extendedProps' => [
                'citas' => []
            ]
        ];
    }
    
    $fechas[$fecha]['extendedProps']['citas'][] = [
        'id' => $cita['id'],
        'cliente' => $cita['cliente_nombre'],
        'hora' => date('H:i', strtotime($cita['fecha'])),
        'consultorio' => $cita['consultorio_nombre'],
        'consultorio_color' => $cita['consultorio_color'],
        'estado' => isset($cita['estado']) ? $cita['estado'] : 'activo'
    ];
}

echo json_encode(array_values($fechas));
?>
