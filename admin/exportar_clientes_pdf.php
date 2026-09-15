<?php
//ZONA HORARIA AMERICA/LA_PAZ
date_default_timezone_set('America/La_Paz');
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Client.php';

// Incluir mPDF
require_once '../vendor/autoload.php';

$clientModel = new Client($pdo);

// Obtener parámetro de búsqueda si existe
$search = $_GET['search'] ?? '';

if ($search !== '') {
    $clientes = $clientModel->search($search);
} else {
    $clientes = $clientModel->getAll();
}

// Función para convertir caracteres especiales a UTF-8
function convertToUtf8($text) {
    if (is_array($text)) {
        return array_map('convertToUtf8', $text);
    }
    
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
    return $text;
}

// Construir tabla HTML
$tabla_html = '';
if (!empty($clientes)) {
    $tabla_html = '<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background-color: #003B73; color: white; font-weight: bold;">
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px; width: 40%;">Nombre</th>
                <th style="padding: 12px; text-align: left; border: 1px solid #ddd; font-size: 11px; width: 30%;">Teléfono</th>
                
            </tr>
        </thead>
        <tbody>';
    
    $contador = 0;
    foreach ($clientes as $cliente) {
        $contador++;
        $bg_color = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';
        
        $fecha_registro = isset($cliente['created_at']) ? date('d/m/Y', strtotime($cliente['created_at'])) : 'N/A';
        
        $tabla_html .= '<tr style="background-color: ' . $bg_color . '; border-bottom: 1px solid #ddd;">
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($cliente['nombre']) . '</td>
            <td style="padding: 10px; border: 1px solid #ddd; font-size: 10px;">' . convertToUtf8($cliente['telefono']) . '</td>
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
            border-bottom: 3px solid #003B73;
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
            border-left: 4px solid #003B73;
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
            background-color: #003B73;
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
            border-top: 1px solid #003B73;
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
            background-color: #003B73;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #003B73;
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
            <img src="assets/images/logo_Dentality.png" alt="Dental Supremo" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <div class="header-text">
            <h1>LISTA DE CLIENTES</h1>
            <p>Dental Supremo - ' . date('d/m/Y H:i') . '</p>
        </div>
    </div>

    <div class="info-box">
        <div class="info-box-row">
            <span class="info-box-label">Total de Clientes:</span>
            <span class="info-box-value">' . count($clientes) . '</span>
        </div>
        <div class="info-box-row">
            <span class="info-box-label">Fecha de Generación:</span>
            <span class="info-box-value">' . date('d/m/Y H:i:s') . '</span>
        </div>
        ' . ($search !== '' ? '<div class="info-box-row">
            <span class="info-box-label">Búsqueda:</span>
            <span class="info-box-value">' . convertToUtf8($search) . '</span>
        </div>' : '') . '
    </div>';

if (!empty($clientes)) {
    $html .= '<div class="section-title">DIRECTORIO DE PACIENTES</div>' . $tabla_html;
} else {
    $html .= '<div class="empty-state">No hay clientes en el sistema</div>';
}

$html .= '<div class="footer-line">Reporte generado automáticamente - Dental Supremo</div>
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
        'margin_footer' => 5
    ]);
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('Lista_Clientes_' . date('d-m-Y') . '.pdf', 'D');
} catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
}
?>
