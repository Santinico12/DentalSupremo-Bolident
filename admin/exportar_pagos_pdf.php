<?php
/**
 * Exportar Reporte General de Pagos a PDF (mPDF)
 * Genera el documento PDF del reporte de pagos con filtros activos,
 * resumen de estadísticas y desglose de transacciones.
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';
require_once '../vendor/autoload.php';

// Filtros
$fechaDesde = $_GET['desde'] ?? '';
$fechaHasta = $_GET['hasta'] ?? '';
$metodo = $_GET['metodo'] ?? '';
$busqueda = trim($_GET['search'] ?? '');

$whereClauses = [];
$params = [];

if ($fechaDesde) {
    $whereClauses[] = "DATE(p.fecha_pago) >= :desde";
    $params[':desde'] = $fechaDesde;
}
if ($fechaHasta) {
    $whereClauses[] = "DATE(p.fecha_pago) <= :hasta";
    $params[':hasta'] = $fechaHasta;
}
if ($metodo) {
    $whereClauses[] = "p.metodo_pago = :metodo";
    $params[':metodo'] = $metodo;
}
if ($busqueda) {
    $whereClauses[] = "(c.nombre LIKE :busqueda OR pr.numero LIKE :busqueda OR p.pagador_nombre LIKE :busqueda OR p.referencia LIKE :busqueda)";
    $params[':busqueda'] = '%' . $busqueda . '%';
}

$whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Obtener todos los pagos correspondientes al filtro
$sql = "SELECT p.*, 
               c.nombre as cliente_nombre, 
               c.telefono as cliente_telefono,
               pr.numero as presupuesto_numero,
               pr.total as presupuesto_total,
               (SELECT GROUP_CONCAT(COALESCE(NULLIF(pi.descripcion, ''), t.nombre) SEPARATOR ', ')
                FROM presupuesto_items pi
                LEFT JOIN tratamientos t ON pi.tratamiento_id = t.id
                WHERE pi.presupuesto_id = p.presupuesto_id
               ) as motivo_tratamientos
        FROM pagos p
        JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN presupuestos pr ON p.presupuesto_id = pr.id
        $whereSQL
        ORDER BY p.fecha_pago DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Métricas del reporte
$totalMonto = 0;
$totalEfectivo = 0;
$totalQr = 0;
$totalTransferencia = 0;
$cantidadPagos = count($pagos);

foreach ($pagos as $p) {
    $monto = floatval($p['monto']);
    $totalMonto += $monto;
    if ($p['metodo_pago'] === 'efectivo') $totalEfectivo += $monto;
    elseif ($p['metodo_pago'] === 'qr') $totalQr += $monto;
    elseif ($p['metodo_pago'] === 'transferencia') $totalTransferencia += $monto;
}

// Período
$textoPeriodo = 'Historial Completo';
if ($fechaDesde && $fechaHasta) {
    $textoPeriodo = date('d/m/Y', strtotime($fechaDesde)) . ' al ' . date('d/m/Y', strtotime($fechaHasta));
} elseif ($fechaDesde) {
    $textoPeriodo = 'Desde ' . date('d/m/Y', strtotime($fechaDesde));
} elseif ($fechaHasta) {
    $textoPeriodo = 'Hasta ' . date('d/m/Y', strtotime($fechaHasta));
}

// Filas de la tabla
$filasPagos = '';
if (!empty($pagos)) {
    $idx = 1;
    foreach ($pagos as $p) {
        $fecha = date('d/m/Y', strtotime($p['fecha_pago']));
        $hora = date('H:i', strtotime($p['fecha_pago']));
        $cliente = htmlspecialchars($p['cliente_nombre']);
        if (!empty($p['pagador_nombre']) && $p['pagador_nombre'] !== $p['cliente_nombre']) {
            $cliente .= '<div style="font-size: 7.5pt; color: #666;">Pagó: ' . htmlspecialchars($p['pagador_nombre']) . '</div>';
        }
        $preNumero = htmlspecialchars($p['presupuesto_numero'] ?? 'S/N');
        
        $motivo = !empty($p['motivo_tratamientos']) ? $p['motivo_tratamientos'] : 'Tratamiento Odontológico';
        if (strlen($motivo) > 55) {
            $motivo = substr($motivo, 0, 52) . '...';
        }
        $motivoHtml = htmlspecialchars($motivo);
        
        $metodoTxt = ucfirst($p['metodo_pago']);
        $montoFmt = number_format($p['monto'], 2);

        $bg = ($idx % 2 == 0) ? 'background-color: #F8FAFC;' : 'background-color: #ffffff;';

        $filasPagos .= "
        <tr style=\"$bg\">
            <td style=\"text-align: center; color: #777; padding: 6px;\">$idx</td>
            <td style=\"padding: 6px;\"><strong>$fecha</strong> <span style=\"font-size: 7.5pt; color: #777;\">$hora</span></td>
            <td style=\"padding: 6px;\"><strong>$cliente</strong></td>
            <td style=\"padding: 6px; color: #003B73; font-weight: bold;\">$preNumero</td>
            <td style=\"padding: 6px;\">$motivoHtml</td>
            <td style=\"text-align: center; padding: 6px; text-transform: uppercase;\">$metodoTxt</td>
            <td style=\"text-align: right; padding: 6px; font-weight: bold; color: #003B73;\">Bs $montoFmt</td>
        </tr>";
        $idx++;
    }

    $filasPagos .= "
    <tr style=\"background-color: #F1F5F9; font-weight: bold;\">
        <td colspan=\"6\" style=\"text-align: right; padding: 8px; font-weight: bold; color: #003B73; text-transform: uppercase;\">TOTAL RECAUDADO ($cantidadPagos pagos):</td>
        <td style=\"text-align: right; padding: 8px; font-weight: bold; color: #003B73; font-size: 10.5pt;\">Bs " . number_format($totalMonto, 2) . "</td>
    </tr>";
} else {
    $filasPagos = '<tr><td colspan="7" style="text-align: center; padding: 25px; color: #777;">No se registraron pagos con los filtros seleccionados.</td></tr>';
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 8.5pt; color: #222; }
    .header-table { width: 100%; border-bottom: 2px solid #003B73; padding-bottom: 8px; margin-bottom: 12px; }
    .clinic-title { color: #003B73; font-size: 15pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .clinic-sub { color: #2998EC; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; }
    .clinic-info { color: #555; font-size: 8pt; }
    
    .stats-table { width: 100%; margin-bottom: 12px; border-collapse: separate; }
    .stat-card {
        background-color: #F8FAFC;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 6px;
        text-align: center;
        width: 20%;
    }
    .stat-val { font-size: 11pt; font-weight: bold; color: #003B73; }
    .stat-lbl { font-size: 7.5pt; font-weight: bold; color: #666; text-transform: uppercase; }

    .payments-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
        border: 1px solid #e2e8f0;
    }
    .payments-table th {
        background-color: #003B73;
        color: #ffffff;
        font-size: 7.5pt;
        text-transform: uppercase;
        padding: 6px 4px;
        text-align: left;
    }
    .payments-table td { border-bottom: 1px solid #e2e8f0; }

    .footer {
        margin-top: 15px;
        padding-top: 5px;
        border-top: 1px solid #e2e8f0;
        font-size: 7pt;
        color: #777;
        text-align: center;
    }
</style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <div class="clinic-title">Dental Supremo</div>
                <div class="clinic-sub">Odontología por Especialidades</div>
                <div class="clinic-info">Sacaba, Cochabamba, Calle Bolivar. &bull; Tel: 72752039</div>
            </td>
            <td style="width: 35%; text-align: right; vertical-align: middle;">
                <div style="font-size: 12pt; font-weight: bold; color: #003B73; text-transform: uppercase;">Reporte de Pagos</div>
                <div style="font-size: 7.5pt; color: #555; margin-top: 2px;">
                    <strong>Período:</strong> ' . htmlspecialchars($textoPeriodo) . '<br>
                    <strong>Método:</strong> ' . ($metodo ? ucfirst($metodo) : 'Todos') . '<br>
                    <strong>Generado:</strong> ' . date('d/m/Y H:i') . '
                </div>
            </td>
        </tr>
    </table>

    <table class="stats-table">
        <tr>
            <td class="stat-card">
                <div class="stat-val">Bs ' . number_format($totalMonto, 2) . '</div>
                <div class="stat-lbl">Total Recaudado</div>
            </td>
            <td class="stat-card">
                <div class="stat-val">Bs ' . number_format($totalEfectivo, 2) . '</div>
                <div class="stat-lbl">Efectivo</div>
            </td>
            <td class="stat-card">
                <div class="stat-val">Bs ' . number_format($totalQr, 2) . '</div>
                <div class="stat-lbl">Pago QR</div>
            </td>
            <td class="stat-card">
                <div class="stat-val">Bs ' . number_format($totalTransferencia, 2) . '</div>
                <div class="stat-lbl">Transferencias</div>
            </td>
            <td class="stat-card">
                <div class="stat-val">' . $cantidadPagos . '</div>
                <div class="stat-lbl">Transacciones</div>
            </td>
        </tr>
    </table>

    <table class="payments-table">
        <thead>
            <tr>
                <th style="width: 20px; text-align: center;">#</th>
                <th style="width: 70px;">Fecha</th>
                <th>Paciente / Pagador</th>
                <th style="width: 65px;">Presupuesto</th>
                <th>Motivo / Tratamiento(s)</th>
                <th style="width: 65px; text-align: center;">Método</th>
                <th style="width: 75px; text-align: right;">Monto</th>
            </tr>
        </thead>
        <tbody>
            ' . $filasPagos . '
        </tbody>
    </table>

    <div class="footer">
        Dental Supremo &bull; Sacaba, Cochabamba, Calle Bolivar. &bull; Documento de Reporte Contable Oficial
    </div>

</body>
</html>';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'Letter',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);

    $mpdf->WriteHTML($html);
    $nombreArchivo = 'Reporte_Pagos_' . date('Ymd_His') . '.pdf';
    $mpdf->Output($nombreArchivo, 'I');
} catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error al generar PDF de pagos: ' . htmlspecialchars($e->getMessage());
}
