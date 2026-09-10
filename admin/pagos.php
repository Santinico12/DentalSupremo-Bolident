<?php
/**
 * Gestión de Pagos con Paginación
 * Dashboard de pagos con estadísticas y lista de transacciones
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';

$pagoModel = new Pago($pdo);

// Filtros de fecha
$fechaDesde = $_GET['desde'] ?? '';
$fechaHasta = $_GET['hasta'] ?? '';

// Paginación
$porPagina = isset($_GET['pp']) ? intval($_GET['pp']) : 10;
$pagina = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$offset = ($pagina - 1) * $porPagina;

// Construir condiciones WHERE
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

$whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Contar total de pagos (con filtro)
$sqlCount = "SELECT COUNT(*) FROM pagos p $whereSQL";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalPagos = $stmtCount->fetchColumn();
$totalPaginas = ceil($totalPagos / $porPagina);

// Obtener pagos paginados
$sqlPagos = "SELECT p.*, 
             c.nombre as cliente_nombre, 
             pr.numero as presupuesto_numero
             FROM pagos p 
             LEFT JOIN clientes c ON p.cliente_id = c.id 
             LEFT JOIN presupuestos pr ON p.presupuesto_id = pr.id 
             $whereSQL
             ORDER BY p.fecha_pago DESC 
             LIMIT $porPagina OFFSET $offset";
$stmtPagos = $pdo->prepare($sqlPagos);
$stmtPagos->execute($params);
$pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);

$stats = $pagoModel->getEstadisticas(date('Y-m-01'), date('Y-m-d'));
$pagosHoy = $pagoModel->getPagosHoy();
$pendientes = $pagoModel->getPresupuestosPendientes();

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #6B1D49; --primary-dark: #531438; --accent: #C47D9F; }
    body { background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%); }
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
    .btn-header { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; border: none; cursor: pointer; }
    .btn-new { background: white; color: var(--primary-dark); }
    .btn-back { background: rgba(255,255,255,0.2); color: white; }

    /* Stats */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
    .stat-card.total::before { background: linear-gradient(90deg, var(--primary), var(--primary-dark)); }
    .stat-card.efectivo::before { background: #17a2b8; }
    .stat-card.qr::before { background: #6f42c1; }
    .stat-card.pendiente::before { background: #ffc107; }
    .stat-number { font-size: 1.8rem; font-weight: 800; color: #333; }
    .stat-label { color: #666; font-size: 0.85rem; margin-top: 5px; }

    /* Tabs */
    .tabs-nav { display: flex; gap: 5px; background: white; padding: 5px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); flex-wrap: wrap; }
    .tab-btn { padding: 12px 20px; border: none; background: transparent; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; color: #666; }
    .tab-btn:hover { background: #f0f0f0; }
    .tab-btn.active { background: var(--primary); color: white; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Tabla */
    .table-container { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th { background: #f8f9fa; padding: 14px; text-align: left; font-weight: 700; color: #555; font-size: 0.85rem; border-bottom: 2px solid #eee; }
    .table td { padding: 14px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .table tr:hover { background: #fafafa; }

    .monto { font-weight: 700; color: var(--primary-dark); font-size: 1rem; }
    .badge-metodo { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
    .badge-efectivo { background: #d1ecf1; color: #0c5460; }
    .badge-qr { background: #e2d5f1; color: #6f42c1; }
    .badge-transferencia { background: #d4edda; color: #155724; }

    .btn-action { width: 32px; height: 32px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; margin: 0 2px; transition: all 0.2s; text-decoration: none; }
    .btn-view { background: #e3f2fd; color: #1976d2; }
    .btn-delete { background: #ffebee; color: #c62828; }
    .btn-print-action { background: #f3e5f5; color: #6a1b9a; }
    .btn-print-action:hover { background: #e1bee7; color: #4a148c; }
    .btn-pdf-action { background: #ffebee; color: #c62828; }
    .btn-pdf-action:hover { background: #ffcdd2; color: #b71c1c; }
    .btn-action:hover { transform: scale(1.1); }

    /* Pendientes */
    .pendiente-card { background: #fff8f0; border-left: 4px solid var(--primary); border-radius: 10px; padding: 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .pendiente-info h4 { margin: 0 0 5px 0; font-size: 1rem; }
    .pendiente-info span { font-size: 0.85rem; color: #666; }
    .pendiente-monto { text-align: right; }
    .pendiente-monto .saldo { font-size: 1.2rem; font-weight: 700; color: var(--primary-dark); }
    .btn-pagar { background: var(--primary); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.85rem; }
    .btn-pagar:hover { background: var(--primary-dark); color: white; }

    /* Filtros */
    .filters-bar { background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .filter-group { display: flex; align-items: center; gap: 8px; }
    .filter-group label { font-weight: 600; font-size: 0.85rem; color: #555; }
    .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }

    @media (max-width: 768px) {
        .page-header { flex-direction: column; text-align: center; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .table-container { padding: 15px; overflow-x: auto; }
    }
</style>

<script>
// Función para ver comprobante (debe estar antes de la tabla que la usa)
function verComprobante(ruta, tipo) {
    const modal = document.getElementById('modalComprobante');
    const contenido = document.getElementById('contenidoComprobante');
    
    if (tipo === 'pdf') {
        contenido.innerHTML = `
            <iframe src="${ruta}" style="width: 100%; height: 70vh; border: none; border-radius: 8px;"></iframe>
            <div style="margin-top: 15px;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none;">
                    <i class="fas fa-download me-2"></i>Descargar
                </a>
            </div>
        `;
    } else {
        contenido.innerHTML = `
            <img src="${ruta}" style="max-width: 100%; max-height: 60vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);" alt="Comprobante">
            <div style="margin-top: 15px;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none;">
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
</script>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-money-bill-wave"></i> Gestión de Pagos</h1>
        <div class="header-actions">
            <button type="button" onclick="abrirReporteImprimir()" class="btn-header btn-new" title="Imprimir Reporte General de Pagos">
                <i class="fas fa-print"></i> Imprimir Reporte
            </button>
            <button type="button" onclick="abrirReportePdf()" class="btn-header" style="background: #dc3545; color: white;" title="Exportar Reporte a PDF">
                <i class="fas fa-file-pdf"></i> Exportar PDF
            </button>
            <a href="presupuestos.php" class="btn-header btn-back">
                <i class="fas fa-file-invoice-dollar"></i> Presupuestos
            </a>
            <a href="dashboard.php" class="btn-header btn-back">
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

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-number">Bs <?php echo number_format($stats['monto_total'] ?? 0, 0); ?></div>
            <div class="stat-label">Total Recaudado (<?php echo date('M Y'); ?>)</div>
        </div>
        <div class="stat-card efectivo">
            <div class="stat-number">Bs <?php echo number_format($stats['total_efectivo'] ?? 0, 0); ?></div>
            <div class="stat-label">Pagos en Efectivo</div>
        </div>
        <div class="stat-card qr">
            <div class="stat-number">Bs <?php echo number_format($stats['total_qr'] ?? 0, 0); ?></div>
            <div class="stat-label">Pagos por QR</div>
        </div>
        <div class="stat-card pendiente">
            <div class="stat-number"><?php echo count($pendientes); ?></div>
            <div class="stat-label">Pendientes de Pago</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-nav">
        <button class="tab-btn active" onclick="cambiarTab('todos')">
            <i class="fas fa-list"></i> Todos los Pagos
            <span style="background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;"><?php echo $stats['total_pagos'] ?? 0; ?></span>
        </button>
        <button class="tab-btn" onclick="cambiarTab('hoy')">
            <i class="fas fa-calendar-day"></i> Pagos de Hoy
            <span style="background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;"><?php echo count($pagosHoy); ?></span>
        </button>
        <button class="tab-btn" onclick="cambiarTab('pendientes')">
            <i class="fas fa-clock"></i> Pendientes
            <span style="background: rgba(0,0,0,0.1); padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;"><?php echo count($pendientes); ?></span>
        </button>
    </div>

    <!-- Tab: Todos los pagos -->
    <div id="tab-todos" class="tab-content active">
        <div class="table-container">
            <div class="table-header">
                <h3 style="margin: 0;"><i class="fas fa-history me-2"></i>Historial de Pagos <span style="font-weight: 400; font-size: 0.85rem; color: #888;">(<?php echo $totalPagos; ?> total)</span></h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="text" id="searchPago" placeholder="Buscar..." style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;" oninput="filtrarPagos()">
                    <select id="filterMetodo" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;" onchange="filtrarPagos()">
                        <option value="">Todos los métodos</option>
                        <option value="efectivo">Efectivo</option>
                        <option value="qr">QR</option>
                        <option value="transferencia">Transferencia</option>
                    </select>
                    <!-- Filtros de fecha -->
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.85rem; color: #666;">Desde:</label>
                        <input type="date" id="fechaDesde" value="<?php echo $fechaDesde; ?>" 
                               style="padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px;" onchange="filtrarPorFecha()">
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.85rem; color: #666;">Hasta:</label>
                        <input type="date" id="fechaHasta" value="<?php echo $fechaHasta; ?>" 
                               style="padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px;" onchange="filtrarPorFecha()">
                    </div>
                    <?php if ($fechaDesde || $fechaHasta): ?>
                    <a href="pagos.php" style="padding: 6px 10px; background: #dc3545; color: white; border-radius: 6px; text-decoration: none; font-size: 0.85rem;">
                        <i class="fas fa-times"></i> Limpiar
                    </a>
                    <?php endif; ?>
                    <button type="button" onclick="abrirReporteImprimir()" class="btn-action" style="width: auto; padding: 0 10px; height: 35px; background: #fdf0f6; color: var(--primary-dark); border: 1px solid var(--accent); gap: 5px; font-weight: 600; font-size: 0.82rem;" title="Imprimir reporte con estos filtros">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                    <button type="button" onclick="abrirReportePdf()" class="btn-action" style="width: auto; padding: 0 10px; height: 35px; background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; gap: 5px; font-weight: 600; font-size: 0.82rem;" title="Descargar PDF con estos filtros">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <select onchange="cambiarPorPagina(this.value)" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="10" <?php echo $porPagina == 10 ? 'selected' : ''; ?>>10 por pág</option>
                        <option value="25" <?php echo $porPagina == 25 ? 'selected' : ''; ?>>25 por pág</option>
                        <option value="50" <?php echo $porPagina == 50 ? 'selected' : ''; ?>>50 por pág</option>
                        <option value="100" <?php echo $porPagina == 100 ? 'selected' : ''; ?>>100 por pág</option>
                    </select>
                </div>
            </div>

            <?php if (empty($pagos)): ?>
            <div style="text-align: center; padding: 40px; color: #888;">
                <i class="fas fa-receipt" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                <h4>Sin pagos registrados</h4>
                <p>Registra el primer pago desde un presupuesto aprobado</p>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Presupuesto</th>
                        <th>Método</th>
                        <th>Monto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="pagosBody">
                    <?php foreach ($pagos as $pago): ?>
                    <tr class="pago-row" 
                        data-cliente="<?php echo strtolower(htmlspecialchars($pago['cliente_nombre'])); ?>"
                        data-metodo="<?php echo $pago['metodo_pago']; ?>">
                        <td>
                            <?php echo date('d/m/Y', strtotime($pago['fecha_pago'])); ?>
                            <div style="font-size: 0.8rem; color: #888;"><?php echo date('H:i', strtotime($pago['fecha_pago'])); ?></div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($pago['cliente_nombre']); ?></strong>
                            <?php if ($pago['pagador_nombre'] && $pago['pagador_nombre'] != $pago['cliente_nombre']): ?>
                            <div style="font-size: 0.8rem; color: #888;">Pagó: <?php echo htmlspecialchars($pago['pagador_nombre']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($pago['presupuesto_numero']); ?></td>
                        <td>
                            <span class="badge-metodo badge-<?php echo $pago['metodo_pago']; ?>">
                                <?php 
                                $iconos = ['efectivo' => 'fa-money-bill', 'qr' => 'fa-qrcode', 'transferencia' => 'fa-exchange-alt'];
                                echo '<i class="fas ' . ($iconos[$pago['metodo_pago']] ?? 'fa-credit-card') . ' me-1"></i>';
                                echo ucfirst($pago['metodo_pago']); 
                                ?>
                            </span>
                            <?php if ($pago['comprobante_ruta']): ?>
                            <a href="#" onclick="verComprobante('<?php echo $pago['comprobante_ruta']; ?>', '<?php echo $pago['comprobante_tipo']; ?>')" title="Ver comprobante" style="margin-left: 5px; color: #6f42c1;">
                                <i class="fas fa-paperclip"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="monto">Bs <?php echo number_format($pago['monto'], 2); ?></td>
                        <td>
                            <a href="ver_pago.php?id=<?php echo $pago['id']; ?>" class="btn-action btn-view" title="Ver detalle y motivo del pago">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="imprimir_recibo_pago.php?id=<?php echo $pago['id']; ?>" target="_blank" class="btn-action btn-print-action" title="Imprimir Recibo Oficial">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="exportar_recibo_pdf.php?id=<?php echo $pago['id']; ?>" target="_blank" class="btn-action btn-pdf-action" title="Descargar Recibo en PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <button class="btn-action btn-delete" onclick="eliminarPago(<?php echo $pago['id']; ?>)" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <div style="color: #666; font-size: 0.9rem;">
                    Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $porPagina, $totalPagos); ?> de <?php echo $totalPagos; ?>
                </div>
                <div style="display: flex; gap: 5px;">
                    <?php if ($pagina > 1): ?>
                    <a href="?p=1&pp=<?php echo $porPagina; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333;">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?p=<?php echo $pagina - 1; ?>&pp=<?php echo $porPagina; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333;">
                        <i class="fas fa-angle-left"></i> Anterior
                    </a>
                    <?php endif; ?>
                    
                    <span style="padding: 8px 15px; background: var(--primary); color: white; border-radius: 6px; font-weight: 600;">
                        <?php echo $pagina; ?> / <?php echo $totalPaginas; ?>
                    </span>
                    
                    <?php if ($pagina < $totalPaginas): ?>
                    <a href="?p=<?php echo $pagina + 1; ?>&pp=<?php echo $porPagina; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333;">
                        Siguiente <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?p=<?php echo $totalPaginas; ?>&pp=<?php echo $porPagina; ?>" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333;">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Pagos de hoy -->
    <div id="tab-hoy" class="tab-content">
        <div class="table-container">
            <h3 style="margin: 0 0 20px 0;"><i class="fas fa-calendar-day me-2"></i>Pagos de Hoy - <?php echo date('d/m/Y'); ?></h3>
            
            <?php if (empty($pagosHoy)): ?>
            <div style="text-align: center; padding: 40px; color: #888;">
                <i class="fas fa-coffee" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px;"></i>
                <h4>Sin pagos hoy</h4>
            </div>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Presupuesto</th>
                        <th>Método</th>
                        <th>Monto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalHoy = 0;
                    foreach ($pagosHoy as $pago): 
                        $totalHoy += $pago['monto'];
                    ?>
                    <tr>
                        <td><strong><?php echo date('H:i', strtotime($pago['fecha_pago'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($pago['cliente_nombre']); ?></td>
                        <td><?php echo htmlspecialchars($pago['presupuesto_numero']); ?></td>
                        <td>
                            <span class="badge-metodo badge-<?php echo $pago['metodo_pago']; ?>">
                                <?php echo ucfirst($pago['metodo_pago']); ?>
                            </span>
                            <?php if (!empty($pago['comprobante_ruta'])): ?>
                            <a href="#" onclick="verComprobante('<?php echo $pago['comprobante_ruta']; ?>', '<?php echo $pago['comprobante_tipo']; ?>')" title="Ver comprobante" style="margin-left: 5px; color: #6f42c1;">
                                <i class="fas fa-paperclip"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                        <td class="monto">Bs <?php echo number_format($pago['monto'], 2); ?></td>
                        <td>
                            <a href="ver_pago.php?id=<?php echo $pago['id']; ?>" class="btn-action btn-view" title="Ver detalle y motivo del pago">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="imprimir_recibo_pago.php?id=<?php echo $pago['id']; ?>" target="_blank" class="btn-action btn-print-action" title="Imprimir Recibo Oficial">
                                <i class="fas fa-print"></i>
                            </a>
                            <a href="exportar_recibo_pdf.php?id=<?php echo $pago['id']; ?>" target="_blank" class="btn-action btn-pdf-action" title="Descargar Recibo en PDF">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            <button class="btn-action btn-delete" onclick="eliminarPago(<?php echo $pago['id']; ?>)" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: 700;">
                        <td colspan="5" style="text-align: right;">Total del día:</td>
                        <td class="monto" style="font-size: 1.2rem;">Bs <?php echo number_format($totalHoy, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Pendientes -->
    <div id="tab-pendientes" class="tab-content">
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin: 0;"><i class="fas fa-clock me-2"></i>Presupuestos Pendientes de Pago <span id="contadorPendientes" style="font-weight: 400; font-size: 0.85rem; color: #888;">(<?php echo count($pendientes); ?> total)</span></h3>
            </div>
            
            <!-- Filtros para pendientes -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                <div style="position: relative; flex: 1; min-width: 200px;">
                    <input type="text" id="searchPendiente" placeholder="Buscar cliente o código..." 
                           style="width: 100%; padding: 10px 15px 10px 40px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.9rem;"
                           oninput="filtrarPendientes()">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="font-size: 0.85rem; color: #666;">Desde:</label>
                    <input type="date" id="fechaDesdePend" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px;" onchange="filtrarPendientes()">
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <label style="font-size: 0.85rem; color: #666;">Hasta:</label>
                    <input type="date" id="fechaHastaPend" style="padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px;" onchange="filtrarPendientes()">
                </div>
                <select id="pendientesPorPagina" onchange="filtrarPendientes()" style="padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px;">
                    <option value="5">5 por página</option>
                    <option value="10" selected>10 por página</option>
                    <option value="25">25 por página</option>
                    <option value="50">50 por página</option>
                </select>
            </div>
            
            <?php if (empty($pendientes)): ?>
            <div style="text-align: center; padding: 40px; color: #888;">
                <i class="fas fa-check-circle" style="font-size: 3rem; opacity: 0.3; margin-bottom: 15px; color: #28a745;"></i>
                <h4>¡Todo al día!</h4>
                <p>No hay presupuestos pendientes de pago</p>
            </div>
            <?php else: ?>
            <div id="pendientesContainer">
            <?php foreach ($pendientes as $index => $p): 
                $montoPagado = floatval($p['total']) - floatval($p['saldo_pendiente']);
                $porcentaje = floatval($p['total']) > 0 ? ($montoPagado / floatval($p['total'])) * 100 : 0;
            ?>
            <div class="pendiente-card pendiente-item" 
                 data-index="<?php echo $index; ?>"
                 data-cliente="<?php echo strtolower(htmlspecialchars($p['cliente_nombre'])); ?>"
                 data-numero="<?php echo strtolower($p['numero']); ?>"
                 data-fecha="<?php echo $p['fecha']; ?>">
                <div class="pendiente-info" style="flex: 1; min-width: 0;">
                    <h4><?php echo htmlspecialchars($p['cliente_nombre']); ?></h4>
                    <span><i class="fas fa-file-invoice me-1"></i><?php echo $p['numero']; ?> | Creado: <?php echo date('d/m/Y', strtotime($p['fecha'])); ?></span>
                    
                    <!-- Desglose de pago -->
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 8px; font-size: 0.82rem;">
                        <span style="color: #555;"><i class="fas fa-receipt me-1" style="color: #888;"></i>Total: <strong>Bs <?php echo number_format($p['total'], 2); ?></strong></span>
                        <?php if ($montoPagado > 0): ?>
                        <span style="color: #28a745;"><i class="fas fa-check-circle me-1"></i>Pagado: <strong>Bs <?php echo number_format($montoPagado, 2); ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <!-- Mini barra de progreso -->
                    <?php if ($montoPagado > 0): ?>
                    <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                        <div style="flex: 1; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; max-width: 200px;">
                            <div style="height: 100%; width: <?php echo min(100, $porcentaje); ?>%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 3px;"></div>
                        </div>
                        <span style="font-size: 0.75rem; font-weight: 700; color: #28a745;"><?php echo number_format($porcentaje, 0); ?>%</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="pendiente-monto" style="text-align: right; flex-shrink: 0;">
                    <div class="saldo">Bs <?php echo number_format($p['saldo_pendiente'], 2); ?></div>
                    <div style="font-size: 0.8rem; color: #dc3545; font-weight: 600;">Pendiente</div>
                </div>
                <a href="registrar_pago.php?presupuesto_id=<?php echo $p['id']; ?>" class="btn-pagar" style="flex-shrink: 0;">
                    <i class="fas fa-plus me-1"></i>Registrar Pago
                </a>
            </div>
            <?php endforeach; ?>
            </div>
            
            <!-- Controles de paginación pendientes -->
            <?php if (count($pendientes) > 5): ?>
            <div id="paginacionPendientes" style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                <div id="infoPendientes" style="color: #666; font-size: 0.9rem;"></div>
                <div style="display: flex; gap: 5px;">
                    <button onclick="cambiarPaginaPendientes(paginaActualPendientes - 1)" id="btnPrevPend" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; background: white; cursor: pointer;">
                        <i class="fas fa-angle-left"></i> Anterior
                    </button>
                    <span id="paginaActualPend" style="padding: 8px 15px; background: var(--primary); color: white; border-radius: 6px; font-weight: 600;"></span>
                    <button onclick="cambiarPaginaPendientes(paginaActualPendientes + 1)" id="btnNextPend" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; background: white; cursor: pointer;">
                        Siguiente <i class="fas fa-angle-right"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function cambiarTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelector(`[onclick="cambiarTab('${tabId}')"]`).classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
}

function filtrarPagos() {
    const search = document.getElementById('searchPago').value.toLowerCase();
    const metodo = document.getElementById('filterMetodo').value;
    
    document.querySelectorAll('.pago-row').forEach(row => {
        const cliente = row.dataset.cliente || '';
        const rowMetodo = row.dataset.metodo || '';
        
        let show = true;
        if (search && !cliente.includes(search)) show = false;
        if (metodo && rowMetodo !== metodo) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

function eliminarPago(id) {
    if (confirm('¿Estás seguro de eliminar este pago? Esta acción no se puede deshacer.')) {
        window.location.href = 'eliminar_pago.php?id=' + id;
    }
}

function cambiarPorPagina(valor) {
    const desde = document.getElementById('fechaDesde')?.value || '';
    const hasta = document.getElementById('fechaHasta')?.value || '';
    let url = '?p=1&pp=' + valor;
    if (desde) url += '&desde=' + desde;
    if (hasta) url += '&hasta=' + hasta;
    window.location.href = url;
}

function filtrarPorFecha() {
    const desde = document.getElementById('fechaDesde')?.value || '';
    const hasta = document.getElementById('fechaHasta')?.value || '';
    let url = '?p=1&pp=<?php echo $porPagina; ?>';
    if (desde) url += '&desde=' + desde;
    if (hasta) url += '&hasta=' + hasta;
    window.location.href = url;
}

function getFiltrosUrl() {
    const desde = document.getElementById('fechaDesde')?.value || '';
    const hasta = document.getElementById('fechaHasta')?.value || '';
    const metodo = document.getElementById('filterMetodo')?.value || '';
    const search = document.getElementById('searchPago')?.value || '';
    
    const params = new URLSearchParams();
    if (desde) params.append('desde', desde);
    if (hasta) params.append('hasta', hasta);
    if (metodo) params.append('metodo', metodo);
    if (search) params.append('search', search);
    return params.toString();
}

function abrirReporteImprimir() {
    const qs = getFiltrosUrl();
    window.open('imprimir_pagos.php' + (qs ? '?' + qs : ''), '_blank');
}

function abrirReportePdf() {
    const qs = getFiltrosUrl();
    window.open('exportar_pagos_pdf.php' + (qs ? '?' + qs : ''), '_blank');
}

// Paginación y filtrado de Pendientes (lado cliente)
let paginaActualPendientes = 1;
let pendientesFiltrados = [];

function filtrarPendientes() {
    const search = document.getElementById('searchPendiente')?.value.toLowerCase().trim() || '';
    const fechaDesde = document.getElementById('fechaDesdePend')?.value || '';
    const fechaHasta = document.getElementById('fechaHastaPend')?.value || '';
    const porPagina = parseInt(document.getElementById('pendientesPorPagina')?.value || 10);
    
    const items = document.querySelectorAll('.pendiente-item');
    pendientesFiltrados = [];
    
    items.forEach(item => {
        const cliente = item.dataset.cliente || '';
        const numero = item.dataset.numero || '';
        const fecha = item.dataset.fecha || '';
        
        let show = true;
        
        // Filtrar por búsqueda
        if (search && !cliente.includes(search) && !numero.includes(search)) {
            show = false;
        }
        
        // Filtrar por fecha desde
        if (fechaDesde && show && fecha < fechaDesde) {
            show = false;
        }
        
        // Filtrar por fecha hasta
        if (fechaHasta && show && fecha > fechaHasta) {
            show = false;
        }
        
        if (show) {
            pendientesFiltrados.push(item);
        }
        item.style.display = 'none'; // Ocultar todos primero
    });
    
    // Actualizar contador
    const contador = document.getElementById('contadorPendientes');
    if (contador) {
        contador.textContent = `(${pendientesFiltrados.length} de ${items.length})`;
    }
    
    // Mostrar página 1 de los filtrados
    paginaActualPendientes = 1;
    mostrarPaginaPendientes();
}

function mostrarPaginaPendientes() {
    const porPagina = parseInt(document.getElementById('pendientesPorPagina')?.value || 10);
    const totalPaginasPend = Math.ceil(pendientesFiltrados.length / porPagina);
    
    if (paginaActualPendientes < 1) paginaActualPendientes = 1;
    if (paginaActualPendientes > totalPaginasPend) paginaActualPendientes = totalPaginasPend || 1;
    
    const inicio = (paginaActualPendientes - 1) * porPagina;
    const fin = inicio + porPagina;
    
    // Ocultar todos y mostrar solo los de la página actual
    pendientesFiltrados.forEach((item, index) => {
        item.style.display = (index >= inicio && index < fin) ? '' : 'none';
    });
    
    // Actualizar info
    const infoEl = document.getElementById('infoPendientes');
    if (infoEl && pendientesFiltrados.length > 0) {
        infoEl.textContent = `Mostrando ${inicio + 1} - ${Math.min(fin, pendientesFiltrados.length)} de ${pendientesFiltrados.length}`;
    } else if (infoEl) {
        infoEl.textContent = 'Sin resultados';
    }
    
    const paginaEl = document.getElementById('paginaActualPend');
    if (paginaEl) {
        paginaEl.textContent = `${paginaActualPendientes} / ${totalPaginasPend || 1}`;
    }
    
    // Habilitar/deshabilitar botones
    const btnPrev = document.getElementById('btnPrevPend');
    const btnNext = document.getElementById('btnNextPend');
    if (btnPrev) btnPrev.disabled = paginaActualPendientes <= 1;
    if (btnNext) btnNext.disabled = paginaActualPendientes >= totalPaginasPend;
}

function cambiarPaginaPendientes(pagina) {
    paginaActualPendientes = pagina;
    mostrarPaginaPendientes();
}

// Inicializar filtrado de pendientes al cargar
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelectorAll('.pendiente-item').length > 0) {
        filtrarPendientes();
    }
});
</script>

<!-- Modal para ver comprobante -->
<div id="modalComprobante" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; justify-content: center; align-items: center; padding: 20px;">
    <div style="background: white; border-radius: 16px; width: 100%; max-width: 1000px; max-height: 90%; overflow: hidden; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <div style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
            <h5 style="margin: 0; font-weight: 600;"><i class="fas fa-file-alt me-2"></i>Comprobante de Pago</h5>
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
// Event listeners para cerrar modal de comprobante
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalComprobante();
});
document.getElementById('modalComprobante')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalComprobante();
});
</script>

</body>
</html>
