<?php
/**
 * Exportar Recibo de Pago a PDF (mPDF)
 * Genera el documento PDF oficial del recibo con membrete institucional,
 * motivo del pago (presupuesto e items de tratamiento), importes en letras y firmas.
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    exit('ID de pago no especificado');
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';
require_once '../src/models/Presupuesto.php';
require_once '../src/helpers/numeros_a_letras.php';
require_once '../vendor/autoload.php';

$pagoModel = new Pago($pdo);
$pago = $pagoModel->getById($id);

if (!$pago) {
    exit('Pago no encontrado');
}

// Cargar información del presupuesto y tratamientos
$presupuestoModel = new Presupuesto($pdo);
$presupuesto = $presupuestoModel->getById($pago['presupuesto_id']);
$itemsPresupuesto = $presupuesto['items'] ?? [];

// CI del paciente si existe
$clienteCi = '';
try {
    $stmtCi = $pdo->prepare("SELECT ci FROM historia_clinica WHERE cliente_id = :cid ORDER BY id DESC LIMIT 1");
    $stmtCi->execute([':cid' => $pago['cliente_id']]);
    $clienteCi = $stmtCi->fetchColumn() ?: '';
} catch (Exception $e) {}

// Totales
$totalPresupuesto = floatval($presupuesto['total'] ?? 0);
$montoPagadoAcumulado = floatval($presupuesto['monto_pagado'] ?? 0);
$saldoPendiente = max(0, $totalPresupuesto - $montoPagadoAcumulado);
$abonosAnteriores = max(0, $montoPagadoAcumulado - $pago['monto']);

$numeroRecibo = 'REC-' . str_pad($pago['id'], 6, '0', STR_PAD_LEFT);
$montoEnLetras = numeroALetrasBolivianos($pago['monto']);

// Filas de tratamientos
$filasTratamientos = '';
if (!empty($itemsPresupuesto)) {
    $idx = 1;
    foreach ($itemsPresupuesto as $item) {
        $desc = htmlspecialchars($item['descripcion'] ?: ($item['tratamiento_nombre'] ?? 'Tratamiento Dental'));
        if (!empty($item['tratamiento_codigo'])) {
            $desc .= ' <span style="font-size: 8pt; color: #666;">(' . htmlspecialchars($item['tratamiento_codigo']) . ')</span>';
        }
        $diente = !empty($item['diente']) ? htmlspecialchars($item['diente']) : '-';
        $cant = intval($item['cantidad']);
        $pUnit = number_format($item['precio_unitario'], 2);
        $subt = number_format($item['subtotal'], 2);

        $bg = ($idx % 2 == 0) ? 'background-color: #fdfafc;' : 'background-color: #ffffff;';

        $filasTratamientos .= "
        <tr style=\"$bg\">
            <td style=\"text-align: center; color: #777; padding: 6px;\">$idx</td>
            <td style=\"padding: 6px;\"><strong>$desc</strong></td>
            <td style=\"text-align: center; padding: 6px;\">$diente</td>
            <td style=\"text-align: center; padding: 6px;\">$cant</td>
            <td style=\"text-align: right; padding: 6px;\">Bs $pUnit</td>
            <td style=\"text-align: right; padding: 6px; font-weight: bold;\">Bs $subt</td>
        </tr>";
        $idx++;
    }
} else {
    $filasTratamientos = '<tr><td colspan="6" style="text-align: center; padding: 12px; color: #777;">Pago correspondiente al Presupuesto N° ' . htmlspecialchars($pago['presupuesto_numero']) . '</td></tr>';
}

$metodoTexto = ucfirst($pago['metodo_pago']);
if (!empty($pago['banco'])) {
    $metodoTexto .= ' (' . htmlspecialchars($pago['banco']) . ')';
}

$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 9.5pt; color: #222; }
    .header-table { width: 100%; border-bottom: 2.5px solid #6B1D49; padding-bottom: 10px; margin-bottom: 12px; }
    .clinic-title { color: #6B1D49; font-size: 16pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .clinic-sub { color: #C47D9F; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
    .clinic-info { color: #555; font-size: 8pt; line-height: 1.3; }
    
    .receipt-badge {
        background-color: #6B1D49;
        color: #ffffff;
        padding: 6px 12px;
        border-radius: 6px;
        text-align: center;
    }
    .receipt-num { font-size: 13pt; font-weight: bold; }
    
    .info-table { width: 100%; margin-bottom: 10px; }
    .info-card {
        background-color: #fcf8fa;
        border: 1px solid #ebdbe4;
        border-radius: 6px;
        padding: 8px 10px;
        font-size: 8.5pt;
    }
    .card-title {
        color: #6B1D49;
        font-weight: bold;
        font-size: 8.5pt;
        text-transform: uppercase;
        border-bottom: 1px dashed #ebdbe4;
        padding-bottom: 3px;
        margin-bottom: 5px;
    }
    .row-item { margin-bottom: 2px; }
    .lbl { color: #666; font-weight: bold; }
    .val { color: #111; }

    .section-title {
        background-color: #6B1D49;
        color: #ffffff;
        font-weight: bold;
        font-size: 9pt;
        padding: 5px 8px;
        text-transform: uppercase;
        margin-top: 10px;
    }

    .table-items {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
        border: 1px solid #ebdbe4;
    }
    .table-items th {
        background-color: #f7eff3;
        color: #6B1D49;
        font-size: 8pt;
        text-transform: uppercase;
        padding: 6px;
        border-bottom: 1px solid #ebdbe4;
    }
    .table-items td { border-bottom: 1px solid #f2e9ee; }

    .literal-box {
        background-color: #fdf8fa;
        border-left: 3px solid #6B1D49;
        padding: 6px 10px;
        margin: 10px 0;
        font-size: 9pt;
    }

    .totals-table { width: 100%; margin-top: 5px; }
    .totals-box {
        background-color: #fcf8fa;
        border: 1.5px solid #6B1D49;
        border-radius: 6px;
        padding: 8px 10px;
        font-size: 8.5pt;
    }
    .highlight-monto {
        border-top: 1.5px solid #6B1D49;
        margin-top: 4px;
        padding-top: 4px;
        font-size: 11pt;
        font-weight: bold;
        color: #6B1D49;
    }

    .sig-table { width: 100%; margin-top: 40px; }
    .sig-line { border-top: 1px solid #333; width: 80%; margin: 0 auto 4px auto; }
    .sig-title { font-weight: bold; font-size: 8.5pt; text-transform: uppercase; }
    .sig-sub { font-size: 7.5pt; color: #666; }

    .footer {
        margin-top: 20px;
        padding-top: 6px;
        border-top: 1px solid #ebdbe4;
        text-align: center;
        font-size: 7.5pt;
        color: #777;
    }
</style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 65%;">
                <div class="clinic-title">Dra. Tatiana Ruiz</div>
                <div class="clinic-sub">Cirujano Dentista &bull; Odontología Especializada</div>
                <div class="clinic-info">
                    Calle Beni 377 casi Tomas Frias Edif. BELIZE &bull; Cochabamba, Bolivia<br>
                    Teléfono / WhatsApp: +591 79999200
                </div>
            </td>
            <td style="width: 35%; text-align: right; vertical-align: top;">
                <div class="receipt-badge">
                    <div style="font-size: 8pt; text-transform: uppercase;">Recibo Oficial de Pago</div>
                    <div class="receipt-num">' . $numeroRecibo . '</div>
                </div>
                <div style="font-size: 8pt; color: #555; margin-top: 4px;">
                    <strong>Fecha:</strong> ' . date('d/m/Y H:i', strtotime($pago['fecha_pago'])) . '
                </div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 5px;">
                <div class="info-card">
                    <div class="card-title">Datos del Paciente</div>
                    <div class="row-item"><span class="lbl">Paciente:</span> <span class="val"><strong>' . htmlspecialchars($pago['cliente_nombre']) . '</strong></span></div>' .
                    (!empty($clienteCi) ? '<div class="row-item"><span class="lbl">C.I.:</span> <span class="val">' . htmlspecialchars($clienteCi) . '</span></div>' : '') .
                    (!empty($pago['cliente_telefono']) ? '<div class="row-item"><span class="lbl">Teléfono:</span> <span class="val">' . htmlspecialchars($pago['cliente_telefono']) . '</span></div>' : '') .
                    (($pago['pagador_nombre'] && $pago['pagador_nombre'] !== $pago['cliente_nombre']) ? 
                        '<div class="row-item" style="border-top: 1px dashed #ebdbe4; margin-top: 2px; padding-top: 2px;"><span class="lbl">Pagó (Tercero):</span> <span class="val">' . htmlspecialchars($pago['pagador_nombre']) . '</span></div>' .
                        (!empty($pago['pagador_ci']) ? '<div class="row-item"><span class="lbl">C.I. Pagador:</span> <span class="val">' . htmlspecialchars($pago['pagador_ci']) . '</span></div>' : '')
                    : '') . '
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 5px;">
                <div class="info-card">
                    <div class="card-title">Detalles de la Transacción</div>
                    <div class="row-item"><span class="lbl">Presupuesto N°:</span> <span class="val" style="color: #6B1D49; font-weight: bold;">' . htmlspecialchars($pago['presupuesto_numero']) . '</span></div>
                    <div class="row-item"><span class="lbl">Método de Pago:</span> <span class="val">' . $metodoTexto . '</span></div>' .
                    (!empty($pago['referencia']) ? '<div class="row-item"><span class="lbl">N° Transacción:</span> <span class="val">' . htmlspecialchars($pago['referencia']) . '</span></div>' : '') .
                    '<div class="row-item"><span class="lbl">Registrado por:</span> <span class="val">' . htmlspecialchars($pago['registrado_por'] ?? 'Administración') . '</span></div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">
        Motivo del Pago: Presupuesto ' . htmlspecialchars($pago['presupuesto_numero']) . '
    </div>

    <table class="table-items">
        <thead>
            <tr>
                <th style="width: 25px; text-align: center;">#</th>
                <th style="text-align: left;">Tratamiento / Procedimiento Odontológico</th>
                <th style="width: 50px; text-align: center;">Pieza</th>
                <th style="width: 40px; text-align: center;">Cant.</th>
                <th style="width: 80px; text-align: right;">P. Unit.</th>
                <th style="width: 90px; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            ' . $filasTratamientos . '
        </tbody>
    </table>

    <div class="literal-box">
        <strong>SON:</strong> ' . htmlspecialchars($montoEnLetras) . '
    </div>

    <table class="totals-table">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 15px; font-size: 8pt; color: #555;">' .
                (!empty($pago['notas']) ? '<p><strong>Notas de Pago:</strong> ' . htmlspecialchars($pago['notas']) . '</p>' : '') .
                (!empty($presupuesto['notas']) ? '<p style="margin-top: 3px;"><strong>Notas Presupuesto:</strong> ' . htmlspecialchars($presupuesto['notas']) . '</p>' : '') . '
                <p style="margin-top: 5px; font-style: italic; color: #888;">
                    * Este comprobante certifica la recepción del pago odontológico para el tratamiento especificado.
                </p>
            </td>
            <td style="width: 45%; vertical-align: top;">
                <div class="totals-box">
                    <table style="width: 100%; font-size: 8.5pt;">
                        <tr>
                            <td style="color: #555;">Total Presupuesto:</td>
                            <td style="text-align: right; font-weight: bold;">Bs ' . number_format($totalPresupuesto, 2) . '</td>
                        </tr>
                        <tr>
                            <td style="color: #555;">Abonos Anteriores:</td>
                            <td style="text-align: right;">Bs ' . number_format($abonosAnteriores, 2) . '</td>
                        </tr>
                        <tr class="highlight-monto">
                            <td style="color: #6B1D49; font-weight: bold; padding-top: 5px;">ESTE PAGO:</td>
                            <td style="text-align: right; color: #6B1D49; font-weight: bold; padding-top: 5px; font-size: 11pt;">Bs ' . number_format($pago['monto'], 2) . '</td>
                        </tr>
                        <tr>
                            <td style="color: #dc3545; font-weight: bold; padding-top: 4px;">Saldo Restante:</td>
                            <td style="text-align: right; color: #dc3545; font-weight: bold; padding-top: 4px;">Bs ' . number_format($saldoPendiente, 2) . '</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="sig-table">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: bottom;">
                <div class="sig-line"></div>
                <div class="sig-title">Entregué Conforme</div>
                <div class="sig-sub">' . htmlspecialchars($pago['pagador_nombre'] ?: $pago['cliente_nombre']) . '</div>
            </td>
            <td style="width: 50%; text-align: center; vertical-align: bottom;">
                <div class="sig-line"></div>
                <div class="sig-title">Recibí Conforme</div>
                <div class="sig-sub">Dra. Tatiana Ruiz &bull; Bolident</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Dra. Tatiana Ruiz &bull; Calle Beni 377 casi Tomas Frias Edif. BELIZE &bull; Tel: +591 79999200 &bull; Cochabamba, Bolivia
    </div>

</body>
</html>';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'Letter',
        'margin_left' => 14,
        'margin_right' => 14,
        'margin_top' => 12,
        'margin_bottom' => 12
    ]);

    $mpdf->WriteHTML($html);
    $nombreArchivo = 'Recibo_Pago_' . $numeroRecibo . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pago['cliente_nombre']) . '.pdf';
    $mpdf->Output($nombreArchivo, 'I'); // Abre inline en navegador para ver o descargar
} catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    echo 'Error al generar el PDF del recibo: ' . htmlspecialchars($e->getMessage());
}
