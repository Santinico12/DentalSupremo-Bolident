<?php
/**
 * Ver Detalle de Pago
 * Muestra información completa del pago, comprobante y el motivo del pago (Presupuesto e Items de Tratamiento)
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: pagos.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';
require_once '../src/models/Presupuesto.php';
require_once '../src/helpers/numeros_a_letras.php';

$pagoModel = new Pago($pdo);
$pago = $pagoModel->getById($id);

if (!$pago) {
    $_SESSION['message'] = 'Pago no encontrado';
    $_SESSION['message_type'] = 'danger';
    header('Location: pagos.php');
    exit();
}

// Cargar información completa del presupuesto (motivo del pago)
$presupuestoModel = new Presupuesto($pdo);
$presupuesto = $presupuestoModel->getById($pago['presupuesto_id']);
$itemsPresupuesto = $presupuesto['items'] ?? [];

// Cálculos financieros del presupuesto
$totalPresupuesto = floatval($presupuesto['total'] ?? 0);
$montoPagadoAcumulado = floatval($presupuesto['monto_pagado'] ?? 0);
$saldoPendiente = max(0, $totalPresupuesto - $montoPagadoAcumulado);
$porcentajePagado = $totalPresupuesto > 0 ? min(100, round(($montoPagadoAcumulado / $totalPresupuesto) * 100)) : 100;

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { 
        --primary: #6B1D49; 
        --primary-dark: #531438; 
        --accent: #C47D9F; 
        --light-bg: #fdf8fa;
        --border-color: #f0e2ea;
    }
    body { background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%); min-height: 100vh; }
    .page-container { max-width: 950px; margin: 0 auto; padding: 20px 15px 40px; }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 22px 28px;
        color: white;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        box-shadow: 0 4px 15px rgba(107, 29, 73, 0.25);
    }
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.45rem; display: flex; align-items: center; gap: 10px; }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

    .btn-header {
        padding: 9px 18px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.88rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-print { background: white; color: var(--primary-dark); }
    .btn-print:hover { background: #f8f9fa; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    
    .btn-pdf { background: #dc3545; color: white; }
    .btn-pdf:hover { background: #bb2d3b; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3); }
    
    .btn-back { background: rgba(255,255,255,0.2); color: white; }
    .btn-back:hover { background: rgba(255,255,255,0.3); }

    .detail-card { 
        background: white; 
        border-radius: 16px; 
        padding: 26px; 
        box-shadow: 0 3px 15px rgba(0,0,0,0.05); 
        margin-bottom: 25px; 
        border: 1px solid var(--border-color);
    }
    .detail-card-title {
        margin: 0 0 20px 0;
        padding-bottom: 14px;
        border-bottom: 2px solid #f6eff3;
        color: var(--primary-dark);
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .monto-banner {
        background: linear-gradient(135deg, rgba(107, 29, 73, 0.05), rgba(196, 125, 159, 0.1));
        border: 2px dashed var(--accent);
        border-radius: 14px;
        padding: 20px;
        text-align: center;
        margin-bottom: 24px;
    }
    .monto-banner .label { font-size: 0.88rem; font-weight: 600; color: #777; text-transform: uppercase; letter-spacing: 0.5px; }
    .monto-banner .monto-grande { font-size: 2.3rem; font-weight: 800; color: var(--primary-dark); line-height: 1.2; margin: 4px 0; }
    .monto-banner .monto-letras { font-size: 0.85rem; color: #666; font-style: italic; font-weight: 500; }
    
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px 24px;
    }
    .detail-row { 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        padding: 10px 0; 
        border-bottom: 1px solid #f7f1f4; 
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-weight: 600; color: #666; font-size: 0.9rem; }
    .detail-value { text-align: right; font-size: 0.92rem; color: #222; font-weight: 500; }
    
    .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
    .badge-efectivo { background: #d1ecf1; color: #0c5460; }
    .badge-qr { background: #e2d5f1; color: #6f42c1; }
    .badge-transferencia { background: #d4edda; color: #155724; }
    .badge-aprobado { background: #cce5ff; color: #004085; }
    .badge-pagado { background: #d4edda; color: #155724; }
    .badge-pendiente { background: #fff3cd; color: #856404; }

    /* Motivo / Presupuesto info */
    .motivo-header {
        background: #fdf6fa;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    .motivo-header .info-item { display: flex; flex-direction: column; gap: 2px; }
    .motivo-header .info-title { font-size: 0.75rem; color: #777; text-transform: uppercase; font-weight: 600; }
    .motivo-header .info-val { font-size: 0.95rem; font-weight: 700; color: #333; }

    /* Tabla de Tratamientos */
    .treatments-table-wrapper {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid #f0e6ec;
        margin-bottom: 20px;
    }
    .treatments-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.88rem;
    }
    .treatments-table th {
        background: #f8eff4;
        color: var(--primary-dark);
        padding: 11px 14px;
        font-weight: 700;
        text-align: left;
        border-bottom: 2px solid #ecd8e4;
        white-space: nowrap;
    }
    .treatments-table th.text-right { text-align: right; }
    .treatments-table th.text-center { text-align: center; }
    .treatments-table td {
        padding: 11px 14px;
        border-bottom: 1px solid #f5edf2;
        color: #333;
        vertical-align: middle;
    }
    .treatments-table td.text-right { text-align: right; font-weight: 600; }
    .treatments-table td.text-center { text-align: center; }
    .treatments-table tr:hover { background-color: #fdfafc; }

    /* Balance Cards */
    .balance-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 12px;
        margin-top: 15px;
    }
    .balance-card {
        background: #faf8fa;
        border-radius: 10px;
        padding: 14px;
        text-align: center;
        border: 1px solid #eee;
    }
    .balance-card .val { font-size: 1.25rem; font-weight: 800; margin-top: 4px; }
    .balance-card .lbl { font-size: 0.75rem; font-weight: 600; color: #666; text-transform: uppercase; }
    .balance-card.total .val { color: #333; }
    .balance-card.pagado .val { color: #28a745; }
    .balance-card.actual .val { color: var(--primary); }
    .balance-card.saldo .val { color: #dc3545; }

    .comprobante-preview { text-align: center; margin-top: 15px; }
    .comprobante-preview img { max-width: 100%; max-height: 420px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }

    @media (max-width: 768px) {
        .page-header { flex-direction: column; text-align: center; }
        .header-actions { justify-content: center; width: 100%; }
        .detail-card { padding: 18px; }
        .detail-row { flex-direction: column; align-items: flex-start; gap: 4px; }
        .detail-value { text-align: left; }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-receipt"></i> Detalle del Pago #<?php echo $pago['id']; ?></h1>
        <div class="header-actions">
            <a href="imprimir_recibo_pago.php?id=<?php echo $pago['id']; ?>" target="_blank" class="btn-header btn-print" title="Imprimir Recibo Oficial">
                <i class="fas fa-print"></i> Imprimir Recibo
            </a>
            <a href="exportar_recibo_pdf.php?id=<?php echo $pago['id']; ?>" class="btn-header btn-pdf" title="Descargar Comprobante en PDF">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </a>
            <a href="pagos.php" class="btn-header btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert" style="border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; background: white; border: 1px solid #ddd;">
            <div>
                <i class="fas fa-<?php echo ($_SESSION['message_type'] ?? '') === 'warning' ? 'exclamation-triangle' : (($_SESSION['message_type'] ?? '') === 'danger' ? 'times-circle' : 'check-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($_SESSION['message']); ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7;">&times;</button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- TARJETA 1: DATOS DE LA TRANSACCIÓN DE PAGO -->
    <div class="detail-card">
        <div class="detail-card-title">
            <span><i class="fas fa-money-check-alt me-2" style="color: var(--primary);"></i> Transacción de Pago</span>
            <span class="badge badge-<?php echo $pago['metodo_pago']; ?>">
                <?php 
                $iconosMetodo = ['efectivo' => 'fa-money-bill', 'qr' => 'fa-qrcode', 'transferencia' => 'fa-exchange-alt'];
                echo '<i class="fas ' . ($iconosMetodo[$pago['metodo_pago']] ?? 'fa-credit-card') . ' me-1"></i>';
                echo ucfirst($pago['metodo_pago']); 
                ?>
            </span>
        </div>

        <div class="monto-banner">
            <div class="label">Monto Pagado en esta Operación</div>
            <div class="monto-grande">Bs <?php echo number_format($pago['monto'], 2); ?></div>
            <div class="monto-letras">SON: <?php echo htmlspecialchars(numeroALetrasBolivianos($pago['monto'])); ?></div>
        </div>
        
        <div class="detail-grid">
            <div>
                <div class="detail-row">
                    <span class="detail-label"><i class="far fa-calendar-alt me-1 text-muted"></i> Fecha y Hora</span>
                    <span class="detail-value"><?php echo date('d/m/Y - H:i', strtotime($pago['fecha_pago'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="far fa-user me-1 text-muted"></i> Paciente</span>
                    <span class="detail-value"><strong><?php echo htmlspecialchars($pago['cliente_nombre']); ?></strong></span>
                </div>
                <?php if (!empty($pago['cliente_telefono'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-phone me-1 text-muted"></i> Teléfono Paciente</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pago['cliente_telefono']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($pago['pagador_nombre'] && $pago['pagador_nombre'] !== $pago['cliente_nombre']): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-hand-holding-usd me-1 text-muted"></i> Pagado por</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pago['pagador_nombre']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pago['pagador_ci'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="far fa-id-card me-1 text-muted"></i> C.I. de quien pagó</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pago['pagador_ci']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-file-invoice me-1 text-muted"></i> N° de Presupuesto</span>
                    <span class="detail-value">
                        <a href="ver_presupuesto.php?id=<?php echo $pago['presupuesto_id']; ?>" style="color: var(--primary); font-weight: 700; text-decoration: none;">
                            <?php echo htmlspecialchars($pago['presupuesto_numero']); ?> <i class="fas fa-external-link-alt" style="font-size: 0.75rem;"></i>
                        </a>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-wallet me-1 text-muted"></i> Método</span>
                    <span class="detail-value"><?php echo ucfirst($pago['metodo_pago']); ?></span>
                </div>
                <?php if (!empty($pago['banco'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-university me-1 text-muted"></i> Banco / Entidad</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pago['banco']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($pago['referencia'])): ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-hashtag me-1 text-muted"></i> N° Transacción / Ref</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pago['referencia']); ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user-check me-1 text-muted"></i> Registrado por</span>
                    <span class="detail-value"><?php echo htmlspecialchars($pago['registrado_por'] ?? 'Administración'); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($pago['notas'])): ?>
        <div style="margin-top: 15px; padding: 12px 15px; background: #fffdf9; border-left: 3px solid #ffc107; border-radius: 6px; font-size: 0.88rem; color: #555;">
            <strong><i class="far fa-sticky-note me-1"></i> Notas del Pago:</strong> <?php echo nl2br(htmlspecialchars($pago['notas'])); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TARJETA 2: MOTIVO DEL PAGO (INFORMACIÓN DEL PRESUPUESTO Y TRATAMIENTOS) -->
    <div class="detail-card">
        <div class="detail-card-title">
            <span>
                <i class="fas fa-notes-medical me-2" style="color: var(--primary);"></i>
                Motivo del Pago: Presupuesto <?php echo htmlspecialchars($pago['presupuesto_numero']); ?>
            </span>
            <span class="badge badge-<?php echo $presupuesto['estado'] ?? 'pendiente'; ?>">
                Estado: <?php echo ucfirst($presupuesto['estado'] ?? 'Pendiente'); ?>
            </span>
        </div>

        <div class="motivo-header">
            <div class="info-item">
                <span class="info-title">N° Presupuesto</span>
                <span class="info-val"><?php echo htmlspecialchars($presupuesto['numero'] ?? $pago['presupuesto_numero']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-title">Fecha Presupuesto</span>
                <span class="info-val"><?php echo !empty($presupuesto['fecha']) ? date('d/m/Y', strtotime($presupuesto['fecha'])) : '-'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-title">Doctor / Especialista</span>
                <span class="info-val"><?php echo htmlspecialchars($presupuesto['doctor_nombre'] ?? 'Dra. Tatiana Ruiz'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-title">Tratamientos Contratados</span>
                <span class="info-val"><?php echo count($itemsPresupuesto); ?> procedimiento(s)</span>
            </div>
        </div>

        <h4 style="font-size: 0.95rem; font-weight: 700; color: #444; margin-bottom: 10px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-teeth-open" style="color: var(--accent);"></i> Procedimientos y Tratamientos Presupuestados:
        </h4>

        <?php if (!empty($itemsPresupuesto)): ?>
        <div class="treatments-table-wrapper">
            <table class="treatments-table">
                <thead>
                    <tr>
                        <th style="width: 35px;" class="text-center">#</th>
                        <th>Tratamiento / Concepto</th>
                        <th class="text-center">Pieza Dental</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($itemsPresupuesto as $item): ?>
                    <tr>
                        <td class="text-center text-muted"><?php echo $idx++; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($item['descripcion'] ?: ($item['tratamiento_nombre'] ?? 'Tratamiento Odontológico')); ?></strong>
                            <?php if (!empty($item['tratamiento_codigo'])): ?>
                                <span style="font-size: 0.75rem; color: #888; margin-left: 5px;">(<?php echo htmlspecialchars($item['tratamiento_codigo']); ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($item['diente'])): ?>
                                <span style="background: #f0e6ec; color: var(--primary); padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">
                                    <?php echo htmlspecialchars($item['diente']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo $item['cantidad']; ?></td>
                        <td class="text-right">Bs <?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td class="text-right">Bs <?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="padding: 20px; text-align: center; color: #888; background: #fdfafc; border-radius: 8px; margin-bottom: 15px;">
            <i class="fas fa-info-circle me-1"></i> No se detallaron ítems individuales en este presupuesto.
        </div>
        <?php endif; ?>

        <!-- Resumen de Estado de Cuenta del Presupuesto -->
        <h4 style="font-size: 0.95rem; font-weight: 700; color: #444; margin: 15px 0 10px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-balance-scale" style="color: var(--accent);"></i> Estado de Cuenta del Presupuesto:
        </h4>

        <div class="balance-grid">
            <div class="balance-card total">
                <div class="lbl">Total Presupuesto</div>
                <div class="val">Bs <?php echo number_format($totalPresupuesto, 2); ?></div>
            </div>
            <div class="balance-card pagado">
                <div class="lbl">Total Pagado a la Fecha</div>
                <div class="val">Bs <?php echo number_format($montoPagadoAcumulado, 2); ?></div>
            </div>
            <div class="balance-card actual">
                <div class="lbl">Este Pago</div>
                <div class="val">Bs <?php echo number_format($pago['monto'], 2); ?></div>
            </div>
            <div class="balance-card saldo">
                <div class="lbl">Saldo Pendiente</div>
                <div class="val">Bs <?php echo number_format($saldoPendiente, 2); ?></div>
            </div>
        </div>

        <!-- Barra de Progreso de Pago -->
        <div style="margin-top: 18px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 600; color: #666; margin-bottom: 5px;">
                <span>Progreso de Cancelación</span>
                <span><?php echo $porcentajePagado; ?>% cubierto</span>
            </div>
            <div style="background: #e9ecef; border-radius: 10px; height: 10px; overflow: hidden;">
                <div style="background: linear-gradient(90deg, var(--accent), var(--primary)); width: <?php echo $porcentajePagado; ?>%; height: 100%;"></div>
            </div>
        </div>

        <?php if (!empty($presupuesto['notas'])): ?>
        <div style="margin-top: 15px; font-size: 0.85rem; color: #666; background: #faf8f9; padding: 10px 14px; border-radius: 8px;">
            <strong>Condiciones / Observaciones del Presupuesto:</strong> <?php echo nl2br(htmlspecialchars($presupuesto['notas'])); ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- TARJETA 3: COMPROBANTE ADJUNTO (SI EXISTE) -->
    <?php if ($pago['comprobante_ruta']): ?>
    <div class="detail-card">
        <div class="detail-card-title">
            <span><i class="fas fa-paperclip me-2" style="color: var(--primary);"></i> Comprobante Adjunto Digital</span>
            <a href="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" target="_blank" class="btn-header btn-print" style="font-size: 0.8rem; padding: 6px 12px;">
                <i class="fas fa-external-link-alt me-1"></i> Ver Pantalla Completa
            </a>
        </div>
        <div class="comprobante-preview">
            <?php if ($pago['comprobante_tipo'] === 'pdf'): ?>
            <div style="margin-bottom: 12px;">
                <iframe src="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" 
                        style="width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 8px;">
                </iframe>
            </div>
            <a href="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" download class="btn-header btn-pdf" style="display: inline-flex;">
                <i class="fas fa-download me-1"></i> Descargar Comprobante PDF
            </a>
            <?php else: ?>
            <a href="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" target="_blank">
                <img src="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" alt="Comprobante Adjunto">
            </a>
            <div style="margin-top: 10px;">
                <a href="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" download class="btn-header btn-pdf" style="display: inline-flex;">
                    <i class="fas fa-download me-1"></i> Descargar Imagen
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
