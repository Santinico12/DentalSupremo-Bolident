<?php
/**
 * Ver Detalle de Presupuesto
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: presupuestos.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Presupuesto.php';
require_once '../src/models/Pago.php';

$presupuestoModel = new Presupuesto($pdo);
$pagoModel = new Pago($pdo);
$presupuesto = $presupuestoModel->getById($_GET['id']);

if (!$presupuesto) {
    $_SESSION['message'] = 'Presupuesto no encontrado';
    $_SESSION['message_type'] = 'danger';
    header('Location: presupuestos.php');
    exit();
}

// Cambiar estado si se solicita
if (isset($_GET['estado'])) {
    $nuevoEstado = $_GET['estado'];
    if (in_array($nuevoEstado, ['enviado', 'aprobado', 'rechazado'])) {
        $presupuestoModel->cambiarEstado($presupuesto['id'], $nuevoEstado);
        header('Location: ver_presupuesto.php?id=' . $presupuesto['id']);
        exit();
    }
}

// Obtener pagos si ya está aprobado o pagado
$pagos = [];
$totalPagado = 0;
if (in_array($presupuesto['estado'], ['aprobado', 'pagado'])) {
    $pagos = $pagoModel->getByPresupuesto($presupuesto['id']);
    $totalPagado = $pagoModel->getTotalPagado($presupuesto['id']);
}

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #003B73; --primary-dark: #062846; --accent: #2998EC; }
    body { background: linear-gradient(135deg, #F4F9FD 0%, #f3e6ed 100%); }
    .page-container { max-width: 900px; margin: 0 auto; padding: 20px; }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 25px 30px;
        color: white;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.4rem; }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-header { padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
    .btn-pdf { background: #dc3545; color: white; }
    .btn-whatsapp { background: #25D366; color: white; }
    .btn-back { background: rgba(255,255,255,0.2); color: white; }

    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .card-title { font-weight: 700; font-size: 1rem; color: #333; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
    .info-item label { display: block; font-size: 0.8rem; color: #888; margin-bottom: 4px; }
    .info-item span { font-weight: 600; color: #333; font-size: 1rem; }

    .badge-estado { padding: 8px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; }
    .badge-borrador { background: #ffc107; color: #000; }
    .badge-enviado { background: #17a2b8; color: #fff; }
    .badge-aprobado { background: #28a745; color: #fff; }
    .badge-rechazado { background: #dc3545; color: #fff; }
    .badge-pagado { background: linear-gradient(135deg, #28a745, #20c997); color: #fff; }

    /* Pagos section */
    .pago-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        border-left: 4px solid var(--primary);
        transition: box-shadow 0.2s;
    }
    .pago-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.08); }

    .pago-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .pago-monto { font-weight: 800; font-size: 1.15rem; color: #28a745; }

    .pago-metodo {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .pago-metodo.efectivo { background: #d4edda; color: #155724; }
    .pago-metodo.qr { background: #d1ecf1; color: #0c5460; }
    .pago-metodo.transferencia { background: #fff3cd; color: #856404; }

    .pago-details {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 0.85rem;
        color: #666;
        margin-bottom: 10px;
    }
    .pago-details span { display: flex; align-items: center; gap: 5px; }

    .pago-comprobante {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #e9ecef;
    }

    .comprobante-label {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #555;
        margin-bottom: 8px;
    }
    .comprobante-label i { color: #28a745; }

    .comprobante-preview {
        width: 100%;
        border-radius: 10px;
        border: 2px solid #e9ecef;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        position: relative;
    }

    .comprobante-preview img {
        width: 100%;
        max-height: 500px;
        object-fit: contain;
        display: block;
        background: #fafafa;
        cursor: pointer;
        transition: opacity 0.2s;
    }
    .comprobante-preview img:hover { opacity: 0.92; }

    .comprobante-preview iframe {
        width: 100%;
        height: 400px;
        border: none;
    }

    .comprobante-preview .pdf-fallback {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 30px;
        background: #fff5f5;
        gap: 10px;
    }
    .comprobante-preview .pdf-fallback i { font-size: 2rem; color: #c62828; }
    .comprobante-preview .pdf-fallback span { font-size: 0.85rem; color: #666; }

    .comprobante-actions {
        display: flex;
        gap: 8px;
        margin-top: 8px;
    }
    .btn-comprobante {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 14px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        text-decoration: none;
        transition: background 0.2s;
    }
    .btn-comprobante.ver { background: #e3f2fd; color: #1565c0; }
    .btn-comprobante.ver:hover { background: #bbdefb; }
    .btn-comprobante.descargar { background: #e8f5e9; color: #2e7d32; }
    .btn-comprobante.descargar:hover { background: #c8e6c9; }

    .comprobante-info {
        font-size: 0.82rem;
        color: #555;
    }
    .comprobante-info a { color: var(--primary); font-weight: 600; text-decoration: none; }
    .comprobante-info a:hover { text-decoration: underline; }

    .pago-summary {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
    }
    .pago-summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 0.9rem;
    }
    .pago-summary-row.total { font-weight: 700; font-size: 1rem; }
    .pago-summary-row .value { font-weight: 700; }
    .pago-summary-row .value.green { color: #28a745; }
    .pago-summary-row .value.red { color: #dc3545; }

    .progress-bar-container {
        width: 100%;
        height: 8px;
        background: #e9ecef;
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
    }
    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #20c997);
        border-radius: 4px;
        transition: width 0.5s ease;
    }

    .btn-ir-pago {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: var(--primary);
        color: white;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 600;
        transition: background 0.2s;
    }
    .btn-ir-pago:hover { background: var(--primary-dark); color: white; }

    /* Tabla con scroll en móvil */
    .table-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .items-table { width: 100%; border-collapse: collapse; min-width: 500px; }
    .items-table th { background: #f8f9fa; padding: 12px 15px; text-align: left; font-weight: 700; font-size: 0.85rem; white-space: nowrap; }
    .items-table td { padding: 12px 15px; border-bottom: 1px solid #eee; }
    .items-table .text-right { text-align: right; }
    .items-table .monto { font-weight: 700; color: var(--primary); white-space: nowrap; }

    .totales { margin-top: 20px; background: #f8f9fa; border-radius: 12px; padding: 20px; }
    .total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 1rem; }
    .total-row.final { font-size: 1.4rem; font-weight: 800; color: var(--primary); border-top: 2px solid #ddd; padding-top: 15px; }

    .estado-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
    .btn-estado { padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .btn-aprobar { background: #28a745; color: white; }
    .btn-rechazar { background: #dc3545; color: white; }
    .btn-enviar { background: #17a2b8; color: white; }

    /* === RESPONSIVE MÓVIL === */
    @media (max-width: 768px) {
        .page-container { padding: 10px; }
        .page-header { 
            padding: 15px; 
            flex-direction: column; 
            text-align: center; 
        }
        .page-header h1 { font-size: 1.1rem; }
        .header-actions { 
            width: 100%; 
            justify-content: center; 
        }
        .btn-header { 
            padding: 8px 12px; 
            font-size: 0.8rem; 
        }
        
        .card { padding: 15px; margin-bottom: 15px; }
        .card-title { font-size: 0.9rem; margin-bottom: 15px; }
        
        .info-grid { gap: 12px; }
        .info-item label { font-size: 0.75rem; }
        .info-item span { font-size: 0.9rem; }
        
        /* Tabla scrolleable */
        .table-wrapper {
            margin: 0 -15px;
            padding: 0 15px;
            width: calc(100% + 30px);
        }
        .items-table th, .items-table td { 
            padding: 10px 12px; 
            font-size: 0.85rem; 
        }
        
        .totales { padding: 15px; }
        .total-row { font-size: 0.9rem; }
        .total-row.final { font-size: 1.2rem; }
        
        .estado-buttons { justify-content: center; }
        .btn-estado { 
            padding: 10px 18px; 
            font-size: 0.85rem; 
            flex: 1;
            justify-content: center;
        }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-invoice-dollar me-2"></i><?php echo $presupuesto['numero']; ?></h1>
            <span class="badge-estado badge-<?php echo $presupuesto['estado']; ?>" style="margin-top: 10px; display: inline-block;">
                <?php echo ucfirst($presupuesto['estado']); ?>
            </span>
        </div>
        <div class="header-actions">
            <a href="generar_presupuesto_pdf.php?id=<?php echo $presupuesto['id']; ?>" target="_blank" class="btn-header btn-pdf">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
            <a href="https://wa.me/<?php echo $presupuesto['cliente_telefono']; ?>?text=Hola%20<?php echo urlencode($presupuesto['cliente_nombre']); ?>,%20le%20enviamos%20su%20presupuesto%20<?php echo $presupuesto['numero']; ?>%20por%20Bs%20<?php echo number_format($presupuesto['total'], 2); ?>" target="_blank" class="btn-header btn-whatsapp">
                <i class="fab fa-whatsapp"></i> Enviar
            </a>
            <a href="presupuestos.php" class="btn-header btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert" style="border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <i class="fas fa-<?php echo ($_SESSION['message_type'] ?? '') === 'warning' ? 'exclamation-triangle' : (($_SESSION['message_type'] ?? '') === 'danger' ? 'times-circle' : 'check-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($_SESSION['message']); ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7;">&times;</button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Datos del paciente -->
    <div class="card">
        <div class="card-title"><i class="fas fa-user"></i> Datos del Paciente</div>
        <div class="info-grid">
            <div class="info-item">
                <label>Nombre</label>
                <span><?php echo htmlspecialchars($presupuesto['cliente_nombre']); ?></span>
            </div>
            <div class="info-item">
                <label>Teléfono</label>
                <span><?php echo htmlspecialchars($presupuesto['cliente_telefono']); ?></span>
            </div>
            <div class="info-item">
                <label>Doctor</label>
                <span><?php echo $presupuesto['doctor_nombre'] ?? 'No asignado'; ?></span>
            </div>
            <div class="info-item">
                <label>Fecha</label>
                <span><?php echo date('d/m/Y', strtotime($presupuesto['fecha'])); ?></span>
            </div>
        </div>
    </div>

    <!-- Detalle de tratamientos -->
    <div class="card">
        <div class="card-title"><i class="fas fa-tooth"></i> Detalle de Tratamientos</div>
        
        <div class="table-wrapper">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Tratamiento</th>
                        <th>Diente</th>
                        <th class="text-right">Cant.</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presupuesto['items'] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                        <td><?php echo $item['diente'] ?: '-'; ?></td>
                        <td class="text-right"><?php echo $item['cantidad']; ?></td>
                        <td class="text-right">Bs <?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td class="text-right monto">Bs <?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="totales">
            <div class="total-row">
                <span>Subtotal:</span>
                <span>Bs <?php echo number_format($presupuesto['subtotal'], 2); ?></span>
            </div>
            <?php if ($presupuesto['descuento_monto'] > 0): ?>
            <div class="total-row" style="color: #28a745;">
                <span>Descuento:</span>
                <span>- Bs <?php echo number_format($presupuesto['descuento_monto'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row final">
                <span>TOTAL:</span>
                <span>Bs <?php echo number_format($presupuesto['total'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Notas -->
    <?php if (!empty($presupuesto['notas'])): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-sticky-note"></i> Notas</div>
        <p style="margin: 0; color: #666;"><?php echo nl2br(htmlspecialchars($presupuesto['notas'])); ?></p>
    </div>
    <?php endif; ?>

    <!-- Acciones de estado -->
    <?php if ($presupuesto['estado'] === 'borrador'): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-tasks"></i> Acciones</div>
        <div class="estado-buttons">
            <a href="?id=<?php echo $presupuesto['id']; ?>&estado=enviado" class="btn-estado btn-enviar">
                <i class="fas fa-paper-plane"></i> Marcar como Enviado
            </a>
            <a href="?id=<?php echo $presupuesto['id']; ?>&estado=aprobado" class="btn-estado btn-aprobar">
                <i class="fas fa-check"></i> Aprobar
            </a>
        </div>
    </div>
    <?php elseif ($presupuesto['estado'] === 'enviado'): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-tasks"></i> Acciones</div>
        <div class="estado-buttons">
            <a href="?id=<?php echo $presupuesto['id']; ?>&estado=aprobado" class="btn-estado btn-aprobar">
                <i class="fas fa-check"></i> Aprobar
            </a>
            <a href="?id=<?php echo $presupuesto['id']; ?>&estado=rechazado" class="btn-estado btn-rechazar">
                <i class="fas fa-times"></i> Rechazar
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($presupuesto['estado'] === 'aprobado'): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-dollar-sign"></i> Registrar Pago</div>
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <a href="registrar_pago.php?presupuesto_id=<?php echo $presupuesto['id']; ?>" 
               class="btn-estado" style="background: linear-gradient(135deg, #28a745, #20963c); color: white; padding: 12px 24px; font-size: 1rem; border-radius: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 700; box-shadow: 0 3px 10px rgba(40,167,69,0.3); transition: all 0.2s;">
                <i class="fas fa-credit-card"></i> Ir a Pagar
            </a>
            <?php if (isset($totalPagado) && $totalPagado > 0): ?>
            <span style="font-size: 0.88rem; color: #666;">
                Pendiente: <strong style="color: #dc3545;">Bs <?php echo number_format($presupuesto['total'] - $totalPagado, 2); ?></strong>
            </span>
            <?php else: ?>
            <span style="font-size: 0.88rem; color: #666;">
                Total a pagar: <strong style="color: var(--primary);">Bs <?php echo number_format($presupuesto['total'], 2); ?></strong>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pagos registrados -->
    <?php if (in_array($presupuesto['estado'], ['aprobado', 'pagado']) && !empty($pagos)): ?>
    <div class="card">
        <div class="card-title"><i class="fas fa-receipt"></i> Pagos Registrados</div>
        
        <div class="pago-summary">
            <div class="pago-summary-row total">
                <span>Total Presupuesto:</span>
                <span class="value">Bs <?php echo number_format($presupuesto['total'], 2); ?></span>
            </div>
            <div class="pago-summary-row">
                <span>Total Pagado:</span>
                <span class="value green">Bs <?php echo number_format($totalPagado, 2); ?></span>
            </div>
            <?php $saldo = $presupuesto['total'] - $totalPagado; ?>
            <?php if ($saldo > 0): ?>
            <div class="pago-summary-row">
                <span>Saldo Pendiente:</span>
                <span class="value red">Bs <?php echo number_format($saldo, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo min(100, ($totalPagado / max(1, $presupuesto['total'])) * 100); ?>%;"></div>
            </div>
        </div>

        <?php foreach ($pagos as $pago): ?>
        <div class="pago-card">
            <div class="pago-header">
                <span class="pago-monto">Bs <?php echo number_format($pago['monto'], 2); ?></span>
                <span class="pago-metodo <?php echo $pago['metodo_pago']; ?>">
                    <?php 
                    $metodoIcono = ['efectivo' => 'fa-money-bill', 'qr' => 'fa-qrcode', 'transferencia' => 'fa-exchange-alt'];
                    $metodoTexto = ['efectivo' => 'Efectivo', 'qr' => 'QR', 'transferencia' => 'Transferencia'];
                    ?>
                    <i class="fas <?php echo $metodoIcono[$pago['metodo_pago']] ?? 'fa-money-bill'; ?>"></i>
                    <?php echo $metodoTexto[$pago['metodo_pago']] ?? ucfirst($pago['metodo_pago']); ?>
                </span>
            </div>
            <div class="pago-details">
                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($pago['fecha_pago'])); ?></span>
                <?php if (!empty($pago['referencia'])): ?>
                <span><i class="fas fa-hashtag"></i> Ref: <?php echo htmlspecialchars($pago['referencia']); ?></span>
                <?php endif; ?>
                <?php if (!empty($pago['banco'])): ?>
                <span><i class="fas fa-university"></i> <?php echo htmlspecialchars($pago['banco']); ?></span>
                <?php endif; ?>
                <?php if (!empty($pago['pagador_nombre'])): ?>
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($pago['pagador_nombre']); ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($pago['notas'])): ?>
            <div style="font-size: 0.82rem; color: #888; margin-bottom: 8px; font-style: italic;">
                <i class="fas fa-sticky-note"></i> <?php echo htmlspecialchars($pago['notas']); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($pago['comprobante_ruta'])): ?>
            <div class="pago-comprobante">
                <div class="comprobante-label">
                    <i class="fas fa-check-circle"></i> Comprobante de Pago
                </div>
                <?php 
                $ext = strtolower($pago['comprobante_tipo'] ?? pathinfo($pago['comprobante_ruta'], PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                ?>
                <div class="comprobante-preview">
                    <?php if ($isImage): ?>
                    <img src="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" 
                         alt="Comprobante de pago" 
                         onclick="window.open('<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>', '_blank')">
                    <?php elseif ($ext === 'pdf'): ?>
                    <iframe src="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>"></iframe>
                    <?php else: ?>
                    <div class="pdf-fallback">
                        <i class="fas fa-file-alt"></i>
                        <span>Archivo: <?php echo htmlspecialchars(basename($pago['comprobante_ruta'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="comprobante-actions">
                    <a href="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" target="_blank" class="btn-comprobante ver">
                        <i class="fas fa-expand"></i> Ver completo
                    </a>
                    <a href="<?php echo htmlspecialchars($pago['comprobante_ruta']); ?>" download class="btn-comprobante descargar">
                        <i class="fas fa-download"></i> Descargar
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-top: 10px; text-align: right;">
                <a href="ver_pago.php?id=<?php echo $pago['id']; ?>" class="btn-ir-pago" style="font-size: 0.78rem; padding: 6px 12px;">
                    <i class="fas fa-eye"></i> Ver detalle del pago
                </a>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top: 15px; text-align: center;">
            <a href="pagos.php" class="btn-ir-pago" style="background: #f0f0f0; color: #555;">
                <i class="fas fa-list"></i> Ver todos los pagos
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
