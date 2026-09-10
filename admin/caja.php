<?php
/**
 * Gestión de Caja / Finanzas
 * Dashboard con balance general (ingresos y egresos)
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Caja.php';

$cajaModel = new Caja($pdo);

// Filtros
$fechaDesde = $_GET['desde'] ?? date('Y-m-01'); // Por defecto inicio del mes actual
$fechaHasta = $_GET['hasta'] ?? date('Y-m-d');
$filtroTipo = $_GET['tipo'] ?? ''; // ''=todos, 'ingreso', 'egreso', etc
$filtroMetodo = $_GET['metodo'] ?? '';

// Obtener estadísticas y movimientos
$stats = $cajaModel->getEstadisticas($fechaDesde, $fechaHasta);
$movimientos = $cajaModel->getMovimientos($fechaDesde, $fechaHasta, $filtroTipo, $filtroMetodo);

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { 
        --primary: #003B73; 
        --primary-dark: #062846; 
        --accent: #2998EC;
        --success: #28a745;
        --danger: #dc3545;
        --info: #17a2b8;
    }
    body { background: linear-gradient(135deg, #F4F9FD 0%, #f3e6ed 100%); }
    .page-container { width: 100%; margin: 0; padding: 0; }
    
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
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; gap: 12px; }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-header { padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; border: none; cursor: pointer; }
    .btn-ingreso { background: #28a745; color: white; }
    .btn-ingreso:hover { background: #218838; }
    .btn-egreso { background: #dc3545; color: white; }
    .btn-egreso:hover { background: #c82333; }
    .btn-back { background: rgba(255,255,255,0.2); color: white; }
    .btn-back:hover { background: rgba(255,255,255,0.3); }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
    .stat-card.ingresos::before { background: var(--success); }
    .stat-card.egresos::before { background: var(--danger); }
    .stat-card.balance::before { background: var(--primary); }
    .stat-number { font-size: 1.6rem; font-weight: 800; color: #333; }
    .stat-number.text-success { color: var(--success) !important; }
    .stat-number.text-danger { color: var(--danger) !important; }
    .stat-label { color: #666; font-size: 0.85rem; margin-top: 5px; font-weight: 600; }
    .stat-sub { font-size: 0.75rem; color: #999; margin-top: 2px; }

    /* Filters Bar */
    .filters-bar { background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .filter-group { display: flex; align-items: center; gap: 8px; }
    .filter-group label { font-weight: 600; font-size: 0.85rem; color: #555; white-space: nowrap; }
    .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; }
    .btn-filter-action { padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; text-decoration: none; cursor: pointer; }
    
    /* Table Container */
    .table-container { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th { background: #f8f9fa; padding: 14px; text-align: left; font-weight: 700; color: #555; font-size: 0.85rem; border-bottom: 2px solid #eee; }
    .table td { padding: 14px; border-bottom: 1px solid #eee; vertical-align: middle; font-size: 0.9rem; }
    .table tr:hover { background: #fafafa; }

    .monto-ingreso { font-weight: 700; color: var(--success); }
    .monto-egreso { font-weight: 700; color: var(--danger); }

    .badge-tipo { padding: 5px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; display: inline-flex; align-items: center; gap: 4px; }
    .badge-ingreso-presupuesto { background: #e2f0d9; color: #385723; }
    .badge-ingreso-otro { background: #e1f5fe; color: #0288d1; }
    .badge-egreso { background: #fce4ec; color: #c2185b; }

    .badge-metodo { padding: 3px 8px; border-radius: 12px; font-size: 0.72rem; font-weight: 600; text-transform: capitalize; }
    .badge-efectivo { background: #e2f0d9; color: #385723; }
    .badge-qr { background: #f3e5f5; color: #7b1fa2; }
    .badge-transferencia { background: #fff9c4; color: #f57f17; }
    .badge-otro { background: #eceff1; color: #37474f; }

    .btn-action { width: 32px; height: 32px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; margin: 0 2px; transition: all 0.2s; text-decoration: none; }
    .btn-delete { background: #ffebee; color: #c62828; }
    .btn-delete:hover { transform: scale(1.1); }

    /* Mobile Cards */
    .mobile-cards { display: none; }
    .m-card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 12px; border-left: 5px solid #ccc; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
    .m-card.ingreso_presupuesto { border-left-color: var(--success); }
    .m-card.ingreso_otro { border-left-color: var(--info); }
    .m-card.egreso { border-left-color: var(--danger); }
    .m-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
    .m-card-title { font-weight: 700; font-size: 0.95rem; color: #333; }
    .m-card-monto { font-size: 1.1rem; font-weight: 800; }
    .m-card-body { font-size: 0.82rem; color: #666; margin-bottom: 10px; }
    .m-card-footer { display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; border-top: 1px solid #f0f0f0; padding-top: 8px; }

    @media (max-width: 991px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .page-header { padding: 15px 20px; flex-direction: column; text-align: center; }
        .page-header h1 { font-size: 1.2rem; }
        .header-actions { width: 100%; justify-content: center; }
        .btn-header { font-size: 0.8rem; padding: 8px 12px; }
        .stats-grid { grid-template-columns: 1fr; }
        .filters-bar { flex-direction: column; align-items: stretch; gap: 10px; }
        .filter-group { flex-direction: column; align-items: stretch; }
        .filter-group label { margin-bottom: 4px; }
        .desktop-table { display: none; }
        .mobile-cards { display: block; }
    }
</style>

<div class="page-container">
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert" style="border-radius: 12px; margin-bottom: 20px;">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove()"></button>
        </div>
        <?php 
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        ?>
    <?php endif; ?>

    <div class="page-header">
        <h1><i class="fas fa-cash-register"></i> Caja y Finanzas</h1>
        <div class="header-actions">
            <a href="registrar_ingreso.php" class="btn-header btn-ingreso">
                <i class="fas fa-plus-circle"></i> Nuevo Ingreso
            </a>
            <a href="registrar_egreso.php" class="btn-header btn-egreso">
                <i class="fas fa-minus-circle"></i> Nuevo Egreso
            </a>
            <a href="exportar_caja_pdf.php?desde=<?php echo $fechaDesde; ?>&hasta=<?php echo $fechaHasta; ?>&tipo=<?php echo $filtroTipo; ?>&metodo=<?php echo $filtroMetodo; ?>" target="_blank" class="btn-header" style="background: #dc3545; color: white;">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </a>
            <a href="dashboard.php" class="btn-header btn-back">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card ingresos">
            <div class="stat-number text-success">Bs <?php echo number_format($stats['total_ingresos'], 2); ?></div>
            <div class="stat-label">Ingresos Totales</div>
            <div class="stat-sub">
                Presupuestos: Bs <?php echo number_format($stats['total_pagos'], 2); ?> | 
                Otros: Bs <?php echo number_format($stats['total_otros_ingresos'], 2); ?>
            </div>
        </div>
        <div class="stat-card egresos">
            <div class="stat-number text-danger">Bs <?php echo number_format($stats['total_egresos'], 2); ?></div>
            <div class="stat-label">Egresos Totales</div>
            <div class="stat-sub">Gastos y pagos de servicios</div>
        </div>
        <div class="stat-card balance">
            <?php $esPositivo = $stats['saldo_neto'] >= 0; ?>
            <div class="stat-number <?php echo $esPositivo ? 'text-success' : 'text-danger'; ?>">
                Bs <?php echo number_format($stats['saldo_neto'], 2); ?>
            </div>
            <div class="stat-label">Balance Neto</div>
            <div class="stat-sub">Resultado (Ingresos - Egresos)</div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <form method="GET" class="filters-bar">
        <div class="filter-group">
            <label>Desde:</label>
            <input type="date" name="desde" value="<?php echo $fechaDesde; ?>">
        </div>
        <div class="filter-group">
            <label>Hasta:</label>
            <input type="date" name="hasta" value="<?php echo $fechaHasta; ?>">
        </div>
        <div class="filter-group">
            <label>Tipo:</label>
            <select name="tipo">
                <option value="" <?php echo $filtroTipo === '' ? 'selected' : ''; ?>>Todos los movimientos</option>
                <option value="ingreso" <?php echo $filtroTipo === 'ingreso' ? 'selected' : ''; ?>>Todos los Ingresos</option>
                <option value="ingreso_presupuesto" <?php echo $filtroTipo === 'ingreso_presupuesto' ? 'selected' : ''; ?>>Ingresos de Presupuesto</option>
                <option value="ingreso_otro" <?php echo $filtroTipo === 'ingreso_otro' ? 'selected' : ''; ?>>Otros Ingresos</option>
                <option value="egreso" <?php echo $filtroTipo === 'egreso' ? 'selected' : ''; ?>>Egresos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Método de Pago:</label>
            <select name="metodo">
                <option value="" <?php echo $filtroMetodo === '' ? 'selected' : ''; ?>>Todos</option>
                <option value="efectivo" <?php echo $filtroMetodo === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
                <option value="qr" <?php echo $filtroMetodo === 'qr' ? 'selected' : ''; ?>>QR</option>
                <option value="transferencia" <?php echo $filtroMetodo === 'transferencia' ? 'selected' : ''; ?>>Transferencia</option>
            </select>
        </div>
        <div style="display: flex; gap: 8px; margin-left: auto;">
            <button type="submit" class="btn-filter-action" style="background: var(--primary); color: white;">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <?php if ($fechaDesde || $fechaHasta || $filtroTipo || $filtroMetodo): ?>
                <a href="caja.php" class="btn-filter-action" style="background: #e9ecef; color: #555; text-align: center;">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Listado de Movimientos -->
    <div class="table-container">
        <div class="table-header">
            <h3 style="margin: 0; font-weight: 700;"><i class="fas fa-history me-2"></i>Historial de Movimientos <span style="font-weight: 400; font-size: 0.85rem; color: #888;">(<?php echo count($movimientos); ?> registrados)</span></h3>
            <input type="text" id="searchCaja" placeholder="Buscar concepto o persona..." style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem;" oninput="filtrarTabla()">
        </div>

        <?php if (empty($movimientos)): ?>
            <div style="text-align: center; padding: 50px 20px; color: #888;">
                <i class="fas fa-exchange-alt" style="font-size: 3.5rem; opacity: 0.3; margin-bottom: 15px;"></i>
                <h4>Sin movimientos en este rango</h4>
                <p>Modifica los filtros de fecha o añade un nuevo ingreso/egreso.</p>
            </div>
        <?php else: ?>
            <!-- Desktop Table -->
            <div class="desktop-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Concepto</th>
                            <th>Método</th>
                            <th>Responsable</th>
                            <th>Monto</th>
                            <th style="width: 100px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cajaTableBody">
                        <?php foreach ($movimientos as $mov): 
                            $esIngreso = str_contains($mov['type'] ?? $mov['tipo_movimiento'], 'ingreso');
                            $tipoText = [
                                'ingreso_presupuesto' => 'Presupuesto',
                                'ingreso_otro' => 'Ingreso General',
                                'egreso' => 'Egreso'
                            ][$mov['tipo_movimiento']] ?? 'Movimiento';
                        ?>
                        <tr class="mov-row" data-concept="<?php echo strtolower(htmlspecialchars($mov['nombre'])); ?>" data-resp="<?php echo strtolower(htmlspecialchars($mov['realizado_por'])); ?>">
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($mov['fecha'])); ?></strong>
                                <div style="font-size: 0.78rem; color: #888;"><?php echo date('H:i', strtotime($mov['fecha'])); ?></div>
                            </td>
                            <td>
                                <span class="badge-tipo badge-<?php echo $mov['tipo_movimiento']; ?>">
                                    <?php if ($mov['tipo_movimiento'] === 'egreso'): ?>
                                        <i class="fas fa-arrow-down"></i>
                                    <?php else: ?>
                                        <i class="fas fa-arrow-up"></i>
                                    <?php endif; ?>
                                    <?php echo $tipoText; ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($mov['presupuesto_id'])): ?>
                                    <a href="ver_presupuesto.php?id=<?php echo $mov['presupuesto_id']; ?>" 
                                       style="color: var(--primary-dark); font-weight: 700; text-decoration: none;"
                                       onmouseover="this.style.textDecoration='underline'"
                                       onmouseout="this.style.textDecoration='none'">
                                        <i class="fas fa-external-link-alt me-1" style="font-size: 0.8rem;"></i>
                                        <?php echo htmlspecialchars($mov['nombre']); ?>
                                    </a>
                                <?php else: ?>
                                    <strong><?php echo htmlspecialchars($mov['nombre']); ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($mov['descripcion'])): ?>
                                    <div style="font-size: 0.8rem; color: #777; margin-top: 3px; font-style: italic;">
                                        <?php echo htmlspecialchars($mov['descripcion']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($mov['tipo_movimiento'] !== 'egreso'): ?>
                                    <span class="badge-metodo badge-<?php echo $mov['metodo_pago']; ?>">
                                        <?php 
                                        $iconos = ['efectivo' => 'fa-money-bill', 'qr' => 'fa-qrcode', 'transferencia' => 'fa-exchange-alt'];
                                        echo '<i class="fas ' . ($iconos[$mov['metodo_pago']] ?? 'fa-credit-card') . ' me-1"></i>';
                                        echo ucfirst($mov['metodo_pago']); 
                                        ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #aaa;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($mov['realizado_por']); ?></td>
                            <td class="<?php echo $esIngreso ? 'monto-ingreso' : 'monto-egreso'; ?>">
                                <?php echo $esIngreso ? '+' : '-'; ?> Bs <?php echo number_format($mov['monto'], 2); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($mov['comprobante_ruta']): ?>
                                    <a href="#" onclick="verComprobante('<?php echo htmlspecialchars($mov['comprobante_ruta']); ?>', '<?php echo htmlspecialchars($mov['comprobante_tipo']); ?>')" class="btn-action" style="background: #f3e5f5; color: #7b1fa2;" title="Ver Comprobante">
                                        <i class="fas fa-paperclip"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if ($mov['tipo_movimiento'] !== 'ingreso_presupuesto'): ?>
                                    <button class="btn-action btn-delete" onclick="eliminarMovimiento(<?php echo $mov['id']; ?>, '<?php echo $mov['tipo_movimiento']; ?>')" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards -->
            <div class="mobile-cards">
                <?php foreach ($movimientos as $mov): 
                    $esIngreso = str_contains($mov['tipo_movimiento'], 'ingreso');
                    $tipoText = [
                        'ingreso_presupuesto' => 'Presupuesto',
                        'ingreso_otro' => 'Ingreso General',
                        'egreso' => 'Egreso'
                    ][$mov['tipo_movimiento']] ?? 'Movimiento';
                ?>
                <div class="m-card <?php echo $mov['tipo_movimiento']; ?> mov-row" data-concept="<?php echo strtolower(htmlspecialchars($mov['nombre'])); ?>" data-resp="<?php echo strtolower(htmlspecialchars($mov['realizado_por'])); ?>">
                    <div class="m-card-header">
                        <div>
                            <span class="badge-tipo badge-<?php echo $mov['tipo_movimiento']; ?>" style="margin-bottom: 6px;">
                                <?php echo $tipoText; ?>
                            </span>
                            <div class="m-card-title">
                                <?php if (!empty($mov['presupuesto_id'])): ?>
                                    <a href="ver_presupuesto.php?id=<?php echo $mov['presupuesto_id']; ?>" style="color: var(--primary-dark); font-weight: 700; text-decoration: underline;">
                                        <i class="fas fa-external-link-alt me-1" style="font-size: 0.75rem;"></i>
                                        <?php echo htmlspecialchars($mov['nombre']); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($mov['nombre']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="m-card-monto <?php echo $esIngreso ? 'monto-ingreso' : 'monto-egreso'; ?>">
                            <?php echo $esIngreso ? '+' : '-'; ?> Bs <?php echo number_format($mov['monto'], 2); ?>
                        </div>
                    </div>
                    <div class="m-card-body">
                        <?php if (!empty($mov['descripcion'])): ?>
                            <div style="font-style: italic; margin-bottom: 5px;"><?php echo htmlspecialchars($mov['descripcion']); ?></div>
                        <?php endif; ?>
                        <div><i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($mov['realizado_por']); ?></div>
                    </div>
                    <div class="m-card-footer">
                        <div>
                            <i class="fas fa-calendar-alt me-1"></i> <?php echo date('d/m/Y H:i', strtotime($mov['fecha'])); ?>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <?php if ($mov['comprobante_ruta']): ?>
                                <a href="#" onclick="verComprobante('<?php echo htmlspecialchars($mov['comprobante_ruta']); ?>', '<?php echo htmlspecialchars($mov['comprobante_tipo']); ?>')" class="btn-action" style="background: #f3e5f5; color: #7b1fa2; width: 28px; height: 28px; font-size: 0.8rem;">
                                    <i class="fas fa-paperclip"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($mov['tipo_movimiento'] !== 'ingreso_presupuesto'): ?>
                                <button class="btn-action btn-delete" onclick="eliminarMovimiento(<?php echo $mov['id']; ?>, '<?php echo $mov['tipo_movimiento']; ?>')" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para ver comprobante -->
<div id="modalComprobante" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: white; border-radius: 16px; width: 100%; max-width: 1000px; max-height: 90%; overflow: hidden; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; font-weight: 600;"><i class="fas fa-file-alt me-2"></i>Comprobante del Movimiento</h5>
            <button onclick="cerrarModalComprobante()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 1.2rem;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="contenidoComprobante" style="padding: 20px; text-align: center; max-height: calc(90vh - 80px); overflow: auto;">
            <!-- Aquí se cargará la imagen o PDF -->
        </div>
    </div>
</div>

<script>
function filtrarTabla() {
    const search = document.getElementById('searchCaja').value.toLowerCase().trim();
    
    document.querySelectorAll('.mov-row').forEach(row => {
        const concept = row.dataset.concept || '';
        const resp = row.dataset.resp || '';
        
        if (search === '' || concept.includes(search) || resp.includes(search)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function eliminarMovimiento(id, tipo) {
    if (confirm('¿Estás seguro de eliminar este registro? Esta acción es irreversible.')) {
        window.location.href = `eliminar_movimiento.php?id=${id}&tipo=${tipo}`;
    }
}

function verComprobante(ruta, tipo) {
    const modal = document.getElementById('modalComprobante');
    const contenido = document.getElementById('contenidoComprobante');
    
    if (tipo === 'pdf' || ruta.toLowerCase().endsWith('.pdf')) {
        contenido.innerHTML = `
            <iframe src="${ruta}" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
            <div style="margin-top: 15px;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-download me-2"></i>Descargar
                </a>
            </div>
        `;
    } else {
        contenido.innerHTML = `
            <img src="${ruta}" style="max-width: 100%; max-height: 60vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);" alt="Comprobante">
            <div style="margin-top: 15px;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-download me-2"></i>Descargar
                </a>
            </div>
        `;
    }
    
    modal.style.display = 'flex';
}

function cerrarModalComprobante() {
    document.getElementById('modalComprobante').style.display = 'none';
    document.getElementById('contenidoComprobante').innerHTML = '';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalComprobante();
});
document.getElementById('modalComprobante').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalComprobante();
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
