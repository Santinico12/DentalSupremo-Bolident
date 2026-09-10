<?php
/**
 * Imprimir Reporte General de Pagos
 * Formato imprimible de transacciones con filtros de fecha, método de pago y resumen financiero.
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';

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

// Obtener todos los pagos correspondientes al filtro (sin paginación para el reporte)
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

// Descripción del período para el encabezado
$textoPeriodo = 'Historial Completo';
if ($fechaDesde && $fechaHasta) {
    $textoPeriodo = date('d/m/Y', strtotime($fechaDesde)) . ' al ' . date('d/m/Y', strtotime($fechaHasta));
} elseif ($fechaDesde) {
    $textoPeriodo = 'Desde ' . date('d/m/Y', strtotime($fechaDesde));
} elseif ($fechaHasta) {
    $textoPeriodo = 'Hasta ' . date('d/m/Y', strtotime($fechaHasta));
}

// Query string para exportar PDF
$queryParams = http_build_query([
    'desde' => $fechaDesde,
    'hasta' => $fechaHasta,
    'metodo' => $metodo,
    'search' => $busqueda
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Pagos - Dra. Tatiana Ruiz</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @page {
            size: letter portrait;
            margin: 10mm 12mm 12mm 12mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            background-color: #f5f5f5;
            color: #222;
            font-size: 11.5px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Barra de acciones */
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
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.15s ease;
        }
        .btn-act:hover { transform: translateY(-1px); }
        .btn-print { background: #6B1D49; color: white; }
        .btn-pdf { background: #dc3545; color: white; }
        .btn-close { background: #6c757d; color: white; }

        .report-wrapper {
            max-width: 215mm;
            margin: 25px auto 40px;
            background: white;
            padding: 20mm 15mm;
            border-radius: 6px;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
        }

        /* Membrete */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #6B1D49;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .clinic-title {
            color: #6B1D49;
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .clinic-sub {
            font-size: 10px;
            font-weight: 700;
            color: #C47D9F;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .clinic-contact {
            font-size: 9.5px;
            color: #555;
            margin-top: 2px;
        }

        .report-title-box {
            text-align: right;
            vertical-align: middle;
        }
        .report-title-box h2 {
            color: #6B1D49;
            font-size: 15px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .report-title-box .meta {
            font-size: 10px;
            color: #666;
        }

        /* Métricas */
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            border-collapse: separate;
            border-spacing: 8px 0;
        }
        .stat-card {
            display: table-cell;
            width: 20%;
            background: #fdf8fa;
            border: 1px solid #ebdbe4;
            border-radius: 8px;
            padding: 10px 8px;
            text-align: center;
        }
        .stat-card .val {
            font-size: 13px;
            font-weight: 800;
            color: #6B1D49;
            margin-bottom: 2px;
        }
        .stat-card .lbl {
            font-size: 8.5px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
        }

        /* Tabla de Pagos */
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
            margin-bottom: 15px;
            border: 1px solid #e0d0d9;
        }
        .payments-table th {
            background: #6B1D49;
            color: white;
            padding: 8px 6px;
            font-weight: 700;
            font-size: 9.5px;
            text-transform: uppercase;
            text-align: left;
        }
        .payments-table th.text-center { text-align: center; }
        .payments-table th.text-right { text-align: right; }
        .payments-table td {
            padding: 7px 6px;
            border-bottom: 1px solid #f0e4eb;
            color: #222;
            vertical-align: middle;
        }
        .payments-table td.text-center { text-align: center; }
        .payments-table td.text-right { text-align: right; }
        .payments-table tr:nth-child(even) { background: #fdfafc; }

        .badge-method {
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-efectivo { background: #d1ecf1; color: #0c5460; }
        .badge-qr { background: #e2d5f1; color: #6f42c1; }
        .badge-transferencia { background: #d4edda; color: #155724; }

        .total-row td {
            background: #f8eff4 !important;
            border-top: 2px solid #6B1D49;
            font-weight: 800;
            font-size: 11px;
            color: #6B1D49;
            padding: 9px 6px;
        }

        .report-footer {
            margin-top: 25px;
            padding-top: 10px;
            border-top: 1px solid #ebdbe4;
            display: flex;
            justify-content: space-between;
            font-size: 9px;
            color: #777;
        }

        @media print {
            body { background: white; }
            .action-bar { display: none !important; }
            .report-wrapper {
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

    <!-- Acciones -->
    <div class="action-bar">
        <a href="pagos.php" class="btn-act btn-close">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <button onclick="window.print()" class="btn-act btn-print">
            <i class="fas fa-print"></i> Imprimir Reporte
        </button>
        <a href="exportar_pagos_pdf.php?<?php echo $queryParams; ?>" class="btn-act btn-pdf">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </a>
    </div>

    <div class="report-wrapper">
        <!-- Membrete Institucional -->
        <table class="header-table">
            <tr>
                <td style="width: 50px; vertical-align: middle;">
                    <svg width="42" height="42" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M50 8C33 8 20 22 20 38C20 54 28 66 34 78C37 84 39 92 44 92C48 92 49 84 50 80C51 84 52 92 56 92C61 92 63 84 66 78C72 66 80 54 80 38C80 22 67 8 50 8Z" fill="#6B1D49"/>
                        <circle cx="50" cy="36" r="10" fill="#C47D9F"/>
                    </svg>
                </td>
                <td style="padding-left: 10px; vertical-align: middle;">
                    <div class="clinic-title">Dra. Tatiana Ruiz</div>
                    <div class="clinic-sub">Cirujano Dentista &bull; Clínica Dental Bolident</div>
                    <div class="clinic-contact">Calle Beni 377 casi Tomas Frias Edif. BELIZE &bull; Tel: +591 79999200</div>
                </td>
                <td class="report-title-box">
                    <h2>Reporte de Pagos</h2>
                    <div class="meta">
                        <strong>Período:</strong> <?php echo htmlspecialchars($textoPeriodo); ?><br>
                        <strong>Filtro Método:</strong> <?php echo $metodo ? ucfirst($metodo) : 'Todos'; ?><br>
                        <strong>Generado:</strong> <?php echo date('d/m/Y H:i'); ?>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Métricas Resumen -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="val">Bs <?php echo number_format($totalMonto, 2); ?></div>
                <div class="lbl">Total Recaudado</div>
            </div>
            <div class="stat-card">
                <div class="val">Bs <?php echo number_format($totalEfectivo, 2); ?></div>
                <div class="lbl">Efectivo</div>
            </div>
            <div class="stat-card">
                <div class="val">Bs <?php echo number_format($totalQr, 2); ?></div>
                <div class="lbl">Pago QR</div>
            </div>
            <div class="stat-card">
                <div class="val">Bs <?php echo number_format($totalTransferencia, 2); ?></div>
                <div class="lbl">Transferencias</div>
            </div>
            <div class="stat-card">
                <div class="val"><?php echo $cantidadPagos; ?></div>
                <div class="lbl">Transacciones</div>
            </div>
        </div>

        <!-- Tabla de Pagos -->
        <table class="payments-table">
            <thead>
                <tr>
                    <th style="width: 25px;" class="text-center">#</th>
                    <th style="width: 70px;">Fecha</th>
                    <th>Paciente / Pagador</th>
                    <th style="width: 80px;">Presupuesto</th>
                    <th>Motivo / Tratamiento(s)</th>
                    <th style="width: 75px;" class="text-center">Método</th>
                    <th style="width: 85px;" class="text-right">Monto (Bs)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pagos)): ?>
                    <?php $idx = 1; foreach ($pagos as $p): ?>
                    <tr>
                        <td class="text-center" style="color: #777; font-size: 9.5px;"><?php echo $idx++; ?></td>
                        <td>
                            <strong><?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?></strong>
                            <div style="font-size: 8.5px; color: #777;"><?php echo date('H:i', strtotime($p['fecha_pago'])); ?></div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['cliente_nombre']); ?></strong>
                            <?php if (!empty($p['pagador_nombre']) && $p['pagador_nombre'] !== $p['cliente_nombre']): ?>
                                <div style="font-size: 8.5px; color: #666;">Pagó: <?php echo htmlspecialchars($p['pagador_nombre']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color: #6B1D49;"><?php echo htmlspecialchars($p['presupuesto_numero'] ?? 'S/N'); ?></strong>
                        </td>
                        <td>
                            <?php 
                            $motivo = !empty($p['motivo_tratamientos']) ? $p['motivo_tratamientos'] : 'Tratamiento Odontológico';
                            // Truncar si es muy largo
                            if (strlen($motivo) > 65) {
                                $motivo = substr($motivo, 0, 62) . '...';
                            }
                            echo htmlspecialchars($motivo);
                            ?>
                        </td>
                        <td class="text-center">
                            <span class="badge-method badge-<?php echo $p['metodo_pago']; ?>">
                                <?php echo ucfirst($p['metodo_pago']); ?>
                            </span>
                        </td>
                        <td class="text-right" style="font-weight: 700; color: #6B1D49;">
                            Bs <?php echo number_format($p['monto'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="6" style="text-align: right; text-transform: uppercase;">
                            TOTAL RECAUDADO (<?php echo $cantidadPagos; ?> pagos):
                        </td>
                        <td class="text-right">
                            Bs <?php echo number_format($totalMonto, 2); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 25px; color: #777;">
                            No se encontraron pagos registrados para el período o filtros seleccionados.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="report-footer">
            <span>Dra. Tatiana Ruiz &bull; Clínica Dental Bolident</span>
            <span>Documento Oficial de Reporte Contable de Ingresos</span>
        </div>
    </div>

</body>
</html>
