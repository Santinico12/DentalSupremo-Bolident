<?php
//ZONA HORARIA AMERICA/LA_PAZ
date_default_timezone_set('America/La_Paz');
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Consultorio.php';
require_once '../src/models/Event.php';

// Incluir mPDF
require_once '../vendor/autoload.php';

$appointmentModel = new Appointment($pdo);
$consultorioModel = new Consultorio($pdo);
$eventModel = new Event($pdo);

$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$consultorio_id = $_GET['consultorio_id'] ?? '';
$tipo = $_GET['tipo'] ?? 'citas';

if ($consultorio_id === "") {
    $citas = $appointmentModel->getByDateRange($fecha_inicio, $fecha_fin);
    $eventos = $eventModel->getByDateRange($fecha_inicio, $fecha_fin);
} else {
    $citas = $appointmentModel->getByDateRangeAndConsultorio($fecha_inicio, $fecha_fin, $consultorio_id);
    $eventos = $eventModel->getByDateRangeAndConsultorio($fecha_inicio, $fecha_fin, $consultorio_id);
}

// Funcion para convertir caracteres especiales a UTF-8
function convertToUtf8($text) {
    if (is_array($text)) {
        return array_map('convertToUtf8', $text);
    }
    
    if ($text === null) {
        return '';
    }
    
    // Detectar y convertir encoding si no es UTF-8
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
    
    // Limpiar caracteres no imprimibles que pueden causar problemas
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    
    return $text;
}

// Convertir textos a UTF-8
$fecha_inicio_fmt = date('d/m/Y', strtotime($fecha_inicio));
$fecha_fin_fmt = date('d/m/Y', strtotime($fecha_fin));

// Obtener nombre de consultorio
$consultorio_name = 'Todos';
if ($consultorio_id !== "") {
    $consultorio = $consultorioModel->getById($consultorio_id);
    $consultorio_name = isset($consultorio['nombre']) ? $consultorio['nombre'] : 'No especificado';
}

// Construir tabla HTML
$tabla_html = '';
if (!empty($citas)) {
    $tabla_html = '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background-color: #6B1D49; color: white; font-weight: bold;">
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Cliente</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Doctor</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Fecha</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Inicio</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Fin</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Descripcion</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Duracion</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Estado</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Consultorio</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 0;
    foreach ($citas as $cita) {
        $contador++;
        $bg_color = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';
        
        $fecha = isset($cita['fecha']) ? $cita['fecha'] : '';
        $fecha_fmt = date('d/m/Y', strtotime($fecha));
        $hora_inicio = date('H:i', strtotime($fecha));
        $hora_fin = isset($cita['finDeCita']) ? date('H:i', strtotime($cita['finDeCita'])) : 'N/A';
        
        $estado = isset($cita['estado']) ? ucfirst(strtolower($cita['estado'])) : 'Activo';
        $doctor = isset($cita['doctor_nombre']) ? $cita['doctor_nombre'] : 'Sin asignar';
        $descripcion = isset($cita['descripcion']) ? substr($cita['descripcion'], 0, 30) : '';
        
        $tabla_html .= '<tr style="background-color: ' . $bg_color . '; border-bottom: 1px solid #ddd;">
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($cita['cliente_nombre']) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($doctor) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . $fecha_fmt . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . $hora_inicio . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . $hora_fin . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($descripcion) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . (int)$cita['duracion_estimada'] . ' min</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . convertToUtf8($estado) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($cita['consultorio_nombre']) . '</td>
        </tr>';
    }
    
    $tabla_html .= '</tbody></table>';
}

// HTML del documento
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 3px solid #6B1D49;
            margin-bottom: 20px;
        }
        .header-logo {
            width: 60px;
            height: 60px;
            margin-right: 20px;
        }
        .header-text h1 {
            margin: 0;
            color: #343a40;
            font-size: 24px;
        }
        .header-text p {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 12px;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #6B1D49;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 3px;
        }
        .info-box-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .info-box-label {
            font-weight: bold;
            color: #343a40;
        }
        .info-box-value {
            color: #666;
        }
        .section-title {
            background-color: #6B1D49;
            color: white;
            padding: 10px 15px;
            margin-top: 20px;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 13px;
            border-radius: 3px;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 12px;
        }
        .footer-line {
            border-top: 1px solid #6B1D49;
            margin-top: 30px;
            padding-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #999;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        th {
            background-color: #6B1D49;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #6B1D49;
        }
        td {
            padding: 8px 10px;
            border: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo">
            <img src="assets/images/logo_DraTatianaRuiz.png" alt="Logo Dra. Tatiana Ruiz" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <div class="header-text">
            <h1>INFORME DE CITAS</h1>
            <p>Dra. Tatiana Ruiz - ' . date('d/m/Y H:i') . '</p>
        </div>
    </div>

    <div class="info-box">
        <div class="info-box-row">
            <span class="info-box-label">Periodo:</span>
            <span class="info-box-value">' . $fecha_inicio_fmt . ' a ' . $fecha_fin_fmt . '</span>
        </div>
        <div class="info-box-row">
            <span class="info-box-label">Consultorio:</span>
            <span class="info-box-value">' . convertToUtf8($consultorio_name) . '</span>
        </div>
        <div class="info-box-row">
            <span class="info-box-label">Total de citas:</span>
            <span class="info-box-value">' . count($citas) . '</span>
        </div>
        <div class="info-box-row">
            <span class="info-box-label">Total de eventos:</span>
            <span class="info-box-value">' . count($eventos) . '</span>
        </div>
    </div>';

if (!empty($citas)) {
    $html .= '<div class="section-title">DETALLE DE CITAS</div>' . $tabla_html;
} else {
    $html .= '<div class="empty-state">No hay citas en el rango de fechas especificado</div>';
}

// Tabla de eventos
if (!empty($eventos)) {
    $tabla_eventos_html = '<div class="section-title">DETALLE DE EVENTOS</div>';
    $tabla_eventos_html .= '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background-color: #6B1D49; color: white; font-weight: bold;">
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Nombre del Evento</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Fecha</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Inicio</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Fin</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Descripcion</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #ddd; font-size: 11px;">Duracion</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px;">Consultorio</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 0;
    foreach ($eventos as $evento) {
        $contador++;
        $bg_color = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';
        
        $fecha = isset($evento['fecha']) ? $evento['fecha'] : '';
        $fecha_fmt = date('d/m/Y', strtotime($fecha));
        $hora_inicio = date('H:i', strtotime($fecha));
        $hora_fin = isset($evento['finDeEvento']) ? date('H:i', strtotime($evento['finDeEvento'])) : 'N/A';
        $descripcion = isset($evento['descripcion']) ? substr($evento['descripcion'], 0, 30) : '';
        
        $tabla_eventos_html .= '<tr style="background-color: ' . $bg_color . '; border-bottom: 1px solid #ddd;">
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($evento['nombre']) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . $fecha_fmt . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . $hora_inicio . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . $hora_fin . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($descripcion) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 10px;">' . (int)$evento['duracion_estimada'] . ' min</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($evento['consultorio_nombre']) . '</td>
        </tr>';
    }
    
    $tabla_eventos_html .= '</tbody></table>';
    $html .= $tabla_eventos_html;
}

$html .= '<div class="footer-line">Reporte generado automáticamente - Dra. Tatiana Ruiz</div>
</body>
</html>';

// Crear PDF con mPDF
try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_header' => 5,
        'margin_footer' => 5,
        'default_font' => 'dejavusans'
    ]);
    
    // Configurar charset de entrada
    $mpdf->charset_in = 'UTF-8';
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('Informe_Citas_' . date('d-m-Y') . '.pdf', 'D');
} catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
}
?>
