<?php
/**
 * Exportar Caja a PDF
 * Genera un reporte en PDF de los movimientos de caja aplicando los filtros activos
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Caja.php';
require_once '../vendor/autoload.php';

$cajaModel = new Caja($pdo);

// Filtros
$fechaDesde = $_GET['desde'] ?? date('Y-m-01');
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
$filtroTipo = $_GET['tipo'] ?? '';
$filtroMetodo = $_GET['metodo'] ?? '';

// Obtener estadísticas y movimientos
$stats = $cajaModel->getEstadisticas($fechaDesde, $fechaHasta);
$movimientos = $cajaModel->getMovimientos($fechaDesde, $fechaHasta, $filtroTipo, $filtroMetodo);

// Función para UTF-8
function convertToUtf8($text) {
    if (is_array($text)) {
        return array_map('convertToUtf8', $text);
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
    return $text;
}

// Formatear texto del tipo de movimiento
function getTipoText($tipo) {
    switch ($tipo) {
        case 'ingreso_presupuesto':
            return 'Presupuesto';
        case 'ingreso_otro':
            return 'Ingreso General';
        case 'egreso':
            return 'Egreso';
        default:
            return 'Movimiento';
    }
}

// Construir tabla HTML
$tabla_html = '';
if (!empty($movimientos)) {
    $tabla_html = '<table class="movimientos-table" style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="background-color: #6B1D49;">
                <th style="background-color: #6B1D49; color: #ffffff !important; padding: 8px 10px; text-align: left; border: 1px solid #531438; font-size: 10px; width: 15%;">Fecha</th>
                <th style="background-color: #6B1D49; color: #ffffff !important; padding: 8px 10px; text-align: left; border: 1px solid #531438; font-size: 10px; width: 15%;">Tipo</th>
                <th style="background-color: #6B1D49; color: #ffffff !important; padding: 8px 10px; text-align: left; border: 1px solid #531438; font-size: 10px; width: 32%;">Concepto</th>
                <th style="background-color: #6B1D49; color: #ffffff !important; padding: 8px 10px; text-align: center; border: 1px solid #531438; font-size: 10px; width: 13%;">Método</th>
                <th style="background-color: #6B1D49; color: #ffffff !important; padding: 8px 10px; text-align: left; border: 1px solid #531438; font-size: 10px; width: 13%;">Responsable</th>
                <th style="background-color: #6B1D49; color: #ffffff !important; padding: 8px 10px; text-align: right; border: 1px solid #531438; font-size: 10px; width: 12%;">Monto</th>
            </tr>
        </thead>
        <tbody>';
    
    $contador = 0;
    foreach ($movimientos as $mov) {
        $contador++;
        $bg_color = ($contador % 2 == 0) ? '#f8f9fa' : '#ffffff';
        $esIngreso = strpos($mov['tipo_movimiento'], 'ingreso') !== false;
        
        $monto_prefix = $esIngreso ? '+' : '-';
        $monto_style = $esIngreso ? 'color: #28a745; font-weight: bold;' : 'color: #dc3545; font-weight: bold;';
        
        $metodo = $mov['tipo_movimiento'] !== 'egreso' ? ucfirst($mov['metodo_pago']) : '—';
        $concepto = $mov['nombre'] . (!empty($mov['descripcion']) ? ' - ' . $mov['descripcion'] : '');

        $tabla_html .= '<tr style="background-color: ' . $bg_color . '; border-bottom: 1px solid #ddd;">
            <td style="padding: 8px 10px; border: 1px solid #ddd; font-size: 9px;">' . date('d/m/Y H:i', strtotime($mov['fecha'])) . '</td>
            <td style="padding: 8px 10px; border: 1px solid #ddd; font-size: 9px;">' . getTipoText($mov['tipo_movimiento']) . '</td>
            <td style="padding: 8px 10px; border: 1px solid #ddd; font-size: 9px;">' . convertToUtf8($concepto) . '</td>
            <td style="padding: 8px 10px; border: 1px solid #ddd; font-size: 9px; text-align: center;">' . $metodo . '</td>
            <td style="padding: 8px 10px; border: 1px solid #ddd; font-size: 9px;">' . convertToUtf8($mov['realizado_por']) . '</td>
            <td style="padding: 8px 10px; border: 1px solid #ddd; font-size: 9px; text-align: right; ' . $monto_style . '">' . $monto_prefix . ' Bs ' . number_format($mov['monto'], 2) . '</td>
        </tr>';
    }
    
    $tabla_html .= '</tbody></table>';
}

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Segoe UI", Arial, sans-serif; color: #333; margin: 0; padding: 0; }
        .header { border-bottom: 3px solid #6B1D49; padding-bottom: 15px; margin-bottom: 20px; }
        .header-logo { float: left; width: 60px; height: 60px; margin-right: 15px; }
        .header-text h1 { margin: 0; color: #343a40; font-size: 20px; }
        .header-text p { margin: 5px 0 0 0; color: #666; font-size: 11px; }
        .info-box { background-color: #f8f9fa; border-left: 4px solid #6B1D49; padding: 12px; margin-bottom: 20px; border-radius: 3px; }
        .info-box-table { width: 100%; border-collapse: collapse; border: none; }
        .info-box-table td { padding: 4px 0; border: none; font-size: 10px; }
        .info-box-label { font-weight: bold; color: #343a40; }
        .info-box-value { color: #666; }
        .stats-table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px; }
        .stats-table th { background-color: #f8f9fa; color: #555; font-size: 10px; padding: 8px; border: 1px solid #ddd; text-align: center; }
        .stats-table td { padding: 10px; border: 1px solid #ddd; text-align: center; font-size: 12px; font-weight: bold; }
        .movimientos-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .movimientos-table th { background-color: #6B1D49 !important; color: #ffffff !important; font-size: 10px; font-weight: bold; border: 1px solid #531438; }
        .section-title { background-color: #6B1D49; color: white; padding: 8px 12px; margin-top: 20px; margin-bottom: 10px; font-weight: bold; font-size: 11px; border-radius: 3px; text-transform: uppercase; }
        .empty-state { text-align: center; padding: 30px; color: #999; font-size: 11px; }
        .footer-line { border-top: 1px solid #6B1D49; margin-top: 30px; padding-top: 15px; text-align: center; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo">
            <img src="assets/images/logo_DraTatianaRuiz.png" alt="Logo Dra. Tatiana Ruiz" style="width: 100%; height: 100%; object-fit: contain;">
        </div>
        <div class="header-text">
            <h1>REPORTE DE CAJA Y FINANZAS</h1>
            <p>Dra. Tatiana Ruiz - ' . date('d/m/Y H:i') . '</p>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="info-box">
        <table class="info-box-table">
            <tr>
                <td class="info-box-label" style="width: 18%;">Rango de Fechas:</td>
                <td class="info-box-value" style="width: 32%;">' . date('d/m/Y', strtotime($fechaDesde)) . ' al ' . date('d/m/Y', strtotime($fechaHasta)) . '</td>
                <td class="info-box-label" style="width: 18%;">Generado Por:</td>
                <td class="info-box-value" style="width: 32%;">' . convertToUtf8($_SESSION['user']['username']) . '</td>
            </tr>
            <tr>
                <td class="info-box-label">Filtro Tipo:</td>
                <td class="info-box-value">' . ($filtroTipo ? ucfirst(str_replace('_', ' ', $filtroTipo)) : 'Todos') . '</td>
                <td class="info-box-label">Filtro Método:</td>
                <td class="info-box-value">' . ($filtroMetodo ? ucfirst($filtroMetodo) : 'Todos') . '</td>
            </tr>
        </table>
    </div>

    <div class="section-title">RESUMEN DEL BALANCE</div>
    <table class="stats-table">
        <thead>
            <tr>
                <th style="width: 33.3%;">Ingresos Totales</th>
                <th style="width: 33.3%;">Egresos Totales</th>
                <th style="width: 33.3%;">Balance Neto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="color: #28a745;">Bs ' . number_format($stats['total_ingresos'], 2) . '</td>
                <td style="color: #dc3545;">Bs ' . number_format($stats['total_egresos'], 2) . '</td>
                <td style="color: ' . ($stats['saldo_neto'] >= 0 ? '#28a745' : '#dc3545') . ';">Bs ' . number_format($stats['saldo_neto'], 2) . '</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">DETALLE DE MOVIMIENTOS</div>';

if (!empty($movimientos)) {
    $html .= $tabla_html;
} else {
    $html .= '<div class="empty-state">No se registraron movimientos en el período seleccionado.</div>';
}

$html .= '<div class="footer-line">Reporte financiero generado automáticamente - Dra. Tatiana Ruiz</div>
</body>
</html>';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('Reporte_Caja_' . date('d-m-Y') . '.pdf', 'I'); // Abre en pestaña nueva
} catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error al generar PDF: ' . htmlspecialchars($e->getMessage());
}
?>
