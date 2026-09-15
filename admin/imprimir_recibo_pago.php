<?php
/**
 * Imprimir Recibo Oficial de Pago
 * Formato oficial imprimible con membrete, motivo del pago (presupuesto y tratamientos),
 * importes en números y letras, y líneas de firma.
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

$pagoModel = new Pago($pdo);
$pago = $pagoModel->getById($id);

if (!$pago) {
    exit('Pago no encontrado');
}

// Cargar información del presupuesto y tratamientos
$presupuestoModel = new Presupuesto($pdo);
$presupuesto = $presupuestoModel->getById($pago['presupuesto_id']);
$itemsPresupuesto = $presupuesto['items'] ?? [];

// CI del cliente si existe en historia_clinica
$clienteCi = '';
try {
    $stmtCi = $pdo->prepare("SELECT ci FROM historia_clinica WHERE cliente_id = :cid ORDER BY id DESC LIMIT 1");
    $stmtCi->execute([':cid' => $pago['cliente_id']]);
    $clienteCi = $stmtCi->fetchColumn() ?: '';
} catch (Exception $e) {}

// Totales acumulados
$totalPresupuesto = floatval($presupuesto['total'] ?? 0);
$montoPagadoAcumulado = floatval($presupuesto['monto_pagado'] ?? 0);
$saldoPendiente = max(0, $totalPresupuesto - $montoPagadoAcumulado);

$numeroRecibo = 'REC-' . str_pad($pago['id'], 6, '0', STR_PAD_LEFT);
$montoEnLetras = numeroALetrasBolivianos($pago['monto']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Pago <?php echo $numeroRecibo; ?> - <?php echo htmlspecialchars($pago['cliente_nombre']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @page {
            size: letter portrait;
            margin: 10mm 15mm 12mm 15mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            background-color: #F4F9FD;
            color: #222;
            font-size: 12px;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Barra de acciones flotante (se oculta al imprimir) */
        .action-bar {
            position: fixed;
            top: 15px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 9999;
            background: rgba(255, 255, 255, 0.95);
            padding: 8px 12px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            backdrop-filter: blur(8px);
        }
        .btn-act {
            padding: 9px 18px;
            border-radius: 8px;
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-act:hover { transform: translateY(-1px); }
        .btn-print { background: #003B73; color: white; }
        .btn-pdf { background: #dc3545; color: white; }
        .btn-close { background: #6c757d; color: white; }

        /* Contenedor del documento */
        .receipt-wrapper {
            max-width: 210mm;
            margin: 30px auto 40px;
            background: white;
            padding: 25mm 20mm;
            border-radius: 6px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
            position: relative;
        }

        /* Membrete */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #003B73;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .header-clinic h1 {
            color: #003B73;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .header-clinic .specialty {
            font-size: 11px;
            font-weight: 700;
            color: #2998EC;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .header-clinic .contact {
            font-size: 10px;
            color: #555;
            line-height: 1.35;
        }
        .header-receipt-box {
            text-align: right;
            vertical-align: top;
            width: 220px;
        }
        .receipt-pill {
            background: linear-gradient(135deg, #003B73, #062846);
            color: white;
            padding: 8px 14px;
            border-radius: 8px;
            display: inline-block;
            text-align: center;
            margin-bottom: 6px;
            min-width: 170px;
        }
        .receipt-pill .title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .receipt-pill .number { font-size: 17px; font-weight: 900; letter-spacing: 0.5px; }
        .receipt-date { font-size: 10px; color: #555; }

        /* Cuadros de información en 2 columnas */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 14px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 0 6px;
        }
        .info-col:first-child { padding-left: 0; }
        .info-col:last-child { padding-right: 0; }

        .info-card {
            background: #F8FAFC;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
               .info-card-title {
            font-size: 11px;
            font-weight: 800;
            color: #003B73;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px dashed #dbeafe;
            padding-bottom: 4px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 11px;
        }
        .info-lbl { color: #666; font-weight: 600; }
        .info-val { color: #222; font-weight: 700; text-align: right; }

        /* Sección de Motivo / Presupuesto */
        .section-header {
            background: #003B73;
            color: white;
            padding: 6px 12px;
            border-radius: 6px 6px 0 0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
        }

        /* Tabla de tratamientos */
        .treatments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 14px;
            border: 1px solid #dbeafe;
            border-top: none;
        }
        .treatments-table th {
            background: #eff6ff;
            color: #003B73;
            padding: 7px 10px;
            text-align: left;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            border-bottom: 1px solid #dbeafe;
        }
        .treatments-table th.text-right { text-align: right; }
        .treatments-table th.text-center { text-align: center; }
        .treatments-table td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #333;
        }
        .treatments-table td.text-right { text-align: right; font-weight: 600; }
        .treatments-table td.text-center { text-align: center; }
        .treatments-table tr:nth-child(even) { background: #f8fafc; }

        /* Banner de Importe en Letras */
        .literal-box {
            background: #f0f7ff;
            border-left: 4px solid #003B73;
            border-radius: 0 6px 6px 0;
            padding: 9px 14px;
            margin-bottom: 14px;
            font-size: 11.5px;
        }
        .literal-box strong { color: #003B73; }

        /* Bloque de Totales y Saldos */
        .totals-table-wrapper {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .totals-notes {
            display: table-cell;
            width: 55%;
            vertical-align: top;
            padding-right: 15px;
            font-size: 10px;
            color: #666;
        }
        .totals-box {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            background: #f8fafc;
            border: 1.5px solid #003B73;
            border-radius: 8px;
            padding: 10px 14px;
        }
        .total-item {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            font-size: 11px;
            color: #444;
        }
        .total-item.highlight {
            border-top: 2px solid #003B73;
            margin-top: 5px;
            padding-top: 6px;
            font-size: 14px;
            font-weight: 800;
            color: #003B73;
        }    }
        .total-item.saldo {
            font-weight: 700;
            color: #dc3545;
            border-top: 1px dashed #e2e8f0;
            margin-top: 4px;
            padding-top: 4px;
        }

        /* Firmas */
        .signatures-grid {
            display: table;
            width: 100%;
            margin-top: 35px;
        }
        .sig-col {
            display: table-cell;
            width: 50%;
            vertical-align: bottom;
            text-align: center;
            padding: 0 25px;
        }
        .sig-line {
            border-bottom: 1.5px solid #444;
            height: 45px;
            margin-bottom: 5px;
        }
        .sig-title {
            font-size: 11px;
            font-weight: 700;
            color: #222;
            text-transform: uppercase;
        }
        .sig-subtitle {
            font-size: 10px;
            color: #666;
        }

        /* Pie de página */
        .receipt-footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 9.5px;
            color: #777;
        }

        @media print {
            body { background: white; }
            .action-bar { display: none !important; }
            .receipt-wrapper {
                margin: 0;
                padding: 0;
                box-shadow: none;
                max-width: 100%;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Acciones Flotantes -->
    <div class="action-bar">
        <a href="ver_pago.php?id=<?php echo $pago['id']; ?>" class="btn-act btn-close">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <button onclick="window.print()" class="btn-act btn-print">
            <i class="fas fa-print"></i> Imprimir Recibo
        </button>
        <a href="exportar_recibo_pdf.php?id=<?php echo $pago['id']; ?>" class="btn-act btn-pdf">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </a>
    </div>

    <!-- Recibo Oficial Documento -->
    <div class="receipt-wrapper">
        <!-- Membrete -->
        <table class="header-table">
            <tr>
                <td style="width: 60px; vertical-align: middle;">
                    <!-- Emblema Odontológico SVG -->
                    <svg width="46" height="46" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M50 8C33 8 20 22 20 38C20 54 28 66 34 78C37 84 39 92 44 92C48 92 49 84 50 80C51 84 52 92 56 92C61 92 63 84 66 78C72 66 80 54 80 38C80 22 67 8 50 8Z" fill="#003B73"/>
                        <path d="M50 18C40 18 32 26 32 36C32 46 38 56 42 66C44 71 45 76 47 78C48 76 49 71 50 67C51 71 52 76 53 78C55 76 56 71 58 66C62 56 68 46 68 36C68 26 60 18 50 18Z" fill="white" opacity="0.9"/>
                        <circle cx="50" cy="36" r="10" fill="#2998EC"/>
                    </svg>
                </td>
                <td class="header-clinic" style="vertical-align: middle; padding-left: 10px;">
                    <h1>Dental Supremo</h1>
                    <div class="specialty">Odontología por Especialidades</div>
                    <div class="contact">
                        Sacaba, Cochabamba, Calle Bolivar.<br>
                        Teléfono / WhatsApp: 72752039
                    </div>
                </td>
                <td class="header-receipt-box">
                    <div class="receipt-pill">
                        <div class="title">Recibo de Caja Oficial</div>
                        <div class="number"><?php echo $numeroRecibo; ?></div>
                    </div>
                    <div class="receipt-date">
                        <strong>Fecha de Emisión:</strong> <?php echo date('d/m/Y H:i', strtotime($pago['fecha_pago'])); ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Datos del Paciente y de la Transacción -->
        <div class="info-grid">
            <div class="info-col">
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-user me-1"></i> Datos del Paciente</div>
                    <div class="info-row">
                        <span class="info-lbl">Paciente:</span>
                        <span class="info-val"><?php echo htmlspecialchars($pago['cliente_nombre']); ?></span>
                    </div>
                    <?php if (!empty($clienteCi)): ?>
                    <div class="info-row">
                        <span class="info-lbl">C.I. Paciente:</span>
                        <span class="info-val"><?php echo htmlspecialchars($clienteCi); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pago['cliente_telefono'])): ?>
                    <div class="info-row">
                        <span class="info-lbl">Teléfono / Celular:</span>
                        <span class="info-val"><?php echo htmlspecialchars($pago['cliente_telefono']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pago['pagador_nombre'] && $pago['pagador_nombre'] !== $pago['cliente_nombre']): ?>
                    <div class="info-row" style="border-top: 1px dashed #e2e8f0; padding-top: 3px; margin-top: 3px;">
                        <span class="info-lbl">Pagado por (Tercero):</span>
                        <span class="info-val"><?php echo htmlspecialchars($pago['pagador_nombre']); ?></span>
                    </div>
                    <?php if (!empty($pago['pagador_ci'])): ?>
                    <div class="info-row">
                        <span class="info-lbl">C.I. Pagador:</span>
                        <span class="info-val"><?php echo htmlspecialchars($pago['pagador_ci']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="info-col">
                <div class="info-card">
                    <div class="info-card-title"><i class="fas fa-receipt me-1"></i> Datos del Pago</div>
                    <div class="info-row">
                        <span class="info-lbl">Presupuesto Referencia:</span>
                        <span class="info-val" style="color: #003B73;"><?php echo htmlspecialchars($pago['presupuesto_numero']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-lbl">Forma de Pago:</span>
                        <span class="info-val" style="text-transform: uppercase;">
                            <?php 
                            $metodoTxt = ucfirst($pago['metodo_pago']);
                            if (!empty($pago['banco'])) {
                                $metodoTxt .= ' (' . htmlspecialchars($pago['banco']) . ')';
                            }
                            echo $metodoTxt;
                            ?>
                        </span>
                    </div>
                    <?php if (!empty($pago['referencia'])): ?>
                    <div class="info-row">
                        <span class="info-lbl">N° Ref / Transacción:</span>
                        <span class="info-val"><?php echo htmlspecialchars($pago['referencia']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-lbl">Registrado por:</span>
                        <span class="info-val"><?php echo htmlspecialchars($pago['registrado_por'] ?? 'Administración'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MOTIVO DEL PAGO: INFORMACIÓN DEL PRESUPUESTO -->
        <div class="section-header">
            <span><i class="fas fa-file-invoice-dollar me-1"></i> Motivo del Pago: Presupuesto <?php echo htmlspecialchars($pago['presupuesto_numero']); ?></span>
            <span style="font-size: 10px; font-weight: normal; opacity: 0.9;">
                Especialista: <?php echo htmlspecialchars($presupuesto['doctor_nombre'] ?? 'Dental Supremo'); ?>
            </span>
        </div>

        <table class="treatments-table">
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">#</th>
                    <th>Tratamiento / Procedimiento Odontológico</th>
                    <th style="width: 70px;" class="text-center">Pieza</th>
                    <th style="width: 50px;" class="text-center">Cant.</th>
                    <th style="width: 90px;" class="text-right">P. Unitario</th>
                    <th style="width: 100px;" class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($itemsPresupuesto)): ?>
                    <?php $i = 1; foreach ($itemsPresupuesto as $item): ?>
                    <tr>
                        <td class="text-center" style="color: #777;"><?php echo $i++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['descripcion'] ?: ($item['tratamiento_nombre'] ?? 'Tratamiento Dental')); ?></strong>
                            <?php if (!empty($item['tratamiento_codigo'])): ?>
                                <span style="font-size: 9.5px; color: #777;">(<?php echo htmlspecialchars($item['tratamiento_codigo']); ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo !empty($item['diente']) ? htmlspecialchars($item['diente']) : '-'; ?></td>
                        <td class="text-center"><?php echo $item['cantidad']; ?></td>
                        <td class="text-right">Bs <?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td class="text-right">Bs <?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #777; padding: 12px;">
                            Pago correspondiente al Presupuesto N° <?php echo htmlspecialchars($pago['presupuesto_numero']); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Monto en Letras -->
        <div class="literal-box">
            <strong>SON:</strong> <?php echo htmlspecialchars($montoEnLetras); ?>
        </div>

        <!-- Resumen Financiero y Notas -->
        <div class="totals-table-wrapper">
            <div class="totals-notes">
                <?php if (!empty($pago['notas'])): ?>
                    <p><strong>Observaciones de este pago:</strong> <?php echo htmlspecialchars($pago['notas']); ?></p>
                <?php endif; ?>
                <?php if (!empty($presupuesto['notas'])): ?>
                    <p style="margin-top: 4px;"><strong>Observaciones del presupuesto:</strong> <?php echo htmlspecialchars($presupuesto['notas']); ?></p>
                <?php endif; ?>
                <p style="margin-top: 6px; font-style: italic; color: #888;">
                    * Este recibo certifica la recepción del monto indicado como abono o cancelación total del presupuesto odontológico acordado.
                </p>
            </div>

            <div class="totals-box">
                <div class="total-item">
                    <span>Total Presupuesto:</span>
                    <span>Bs <?php echo number_format($totalPresupuesto, 2); ?></span>
                </div>
                <div class="total-item">
                    <span>Abonos Anteriores:</span>
                    <span>Bs <?php echo number_format(max(0, $montoPagadoAcumulado - $pago['monto']), 2); ?></span>
                </div>
                <div class="total-item highlight">
                    <span>ESTE PAGO:</span>
                    <span>Bs <?php echo number_format($pago['monto'], 2); ?></span>
                </div>
                <div class="total-item saldo">
                    <span>Saldo Restante:</span>
                    <span>Bs <?php echo number_format($saldoPendiente, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Líneas de Firmas -->
        <div class="signatures-grid">
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-title">Entregué Conforme</div>
                <div class="sig-subtitle">
                    <?php echo htmlspecialchars($pago['pagador_nombre'] ?: $pago['cliente_nombre']); ?>
                    <?php if (!empty($pago['pagador_ci']) || !empty($clienteCi)): ?>
                        <br>C.I.: <?php echo htmlspecialchars($pago['pagador_ci'] ?: $clienteCi); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="sig-col">
                <div class="sig-line"></div>
                <div class="sig-title">Recibí Conforme</div>
                <div class="sig-subtitle">
                    Clínica Dental Supremo
                </div>
            </div>
        </div>

        <!-- Pie de Página -->
        <div class="receipt-footer">
            Dental Supremo &bull; Sacaba, Cochabamba, Calle Bolivar. &bull; Tel: 72752039
        </div>
    </div>

</body>
</html>
