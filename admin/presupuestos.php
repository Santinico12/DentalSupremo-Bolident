<?php
/**
 * Lista de Presupuestos con Paginación
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Presupuesto.php';

$presupuestoModel = new Presupuesto($pdo);

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
    $whereClauses[] = "p.fecha >= :desde";
    $params[':desde'] = $fechaDesde;
}
if ($fechaHasta) {
    $whereClauses[] = "p.fecha <= :hasta";
    $params[':hasta'] = $fechaHasta;
}

$whereSQL = count($whereClauses) > 0 ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Contar total de presupuestos (con filtro)
$sqlCount = "SELECT COUNT(*) FROM presupuestos p $whereSQL";
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$totalPresupuestos = $stmtCount->fetchColumn();
$totalPaginas = ceil($totalPresupuestos / $porPagina);

// Obtener presupuestos paginados
$sql = "SELECT p.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono 
        FROM presupuestos p 
        LEFT JOIN clientes c ON p.cliente_id = c.id 
        $whereSQL
        ORDER BY p.fecha DESC, p.id DESC 
        LIMIT $porPagina OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$presupuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $presupuestoModel->getEstadisticas();

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #003B73; --primary-dark: #062846; --accent: #2998EC; }
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
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.6rem; display: flex; align-items: center; gap: 12px; }
    .btn-nuevo {
        background: white;
        color: var(--primary);
        padding: 12px 24px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .btn-nuevo:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.2); color: var(--primary); }

    /* Stats Cards */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .stat-number { font-size: 2rem; font-weight: 800; color: var(--primary); }
    .stat-label { color: #666; font-size: 0.85rem; margin-top: 5px; }

    /* Tabla */
    .table-container {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
    .table-title { font-weight: 700; font-size: 1.1rem; color: #333; }
    
    .table { width: 100%; border-collapse: collapse; }
    .table th { background: #f8f9fa; padding: 14px; text-align: left; font-weight: 700; color: #555; font-size: 0.85rem; border-bottom: 2px solid #eee; }
    .table td { padding: 14px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .table tr:hover { background: #fafafa; }

    .badge-estado {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-borrador { background: #ffc107; color: #000; }
    .badge-enviado { background: #17a2b8; color: #fff; }
    .badge-aprobado { background: #28a745; color: #fff; }
    .badge-rechazado { background: #dc3545; color: #fff; }
    .badge-vencido { background: #6c757d; color: #fff; }
    .badge-pagado { background: linear-gradient(135deg, #28a745, #20c997); color: #fff; }

    .monto { font-weight: 700; color: #28a745; font-size: 1rem; }
    
    .btn-action {
        width: 36px; height: 36px;
        border-radius: 8px;
        border: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        margin: 0 2px;
    }
    .btn-view { background: #e3f2fd; color: #1976d2; }
    .btn-pdf { background: #ffebee; color: #c62828; }
    .btn-delete { background: #fce4ec; color: #c2185b; }
    .btn-action:hover { transform: scale(1.1); }

    .empty-state { text-align: center; padding: 60px 20px; color: #888; }
    .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.3; }

    /* Pagination */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
        flex-wrap: wrap;
        gap: 10px;
    }
    .pagination-info { color: #666; font-size: 0.9rem; }
    .pagination-btns { display: flex; gap: 5px; }
    .pagination-btns a, .pagination-btns span {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        text-decoration: none;
        color: #333;
        font-size: 0.85rem;
    }
    .pagination-btns .active-page {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .page-container { padding: 10px; }

        /* Header compact */
        .page-header { padding: 14px 16px; border-radius: 12px; margin-bottom: 15px; flex-direction: row; gap: 10px; }
        .page-header h1 { font-size: 1.1rem; gap: 8px; }
        .page-header h1 i { font-size: 1rem; }
        .header-btns-mobile { display: flex; gap: 5px; }
        .btn-nuevo { padding: 7px 12px; border-radius: 8px; font-size: 0.75rem; gap: 5px; }
        .btn-nuevo span.btn-text { display: none; }

        /* Stats compact */
        .stats-row { grid-template-columns: repeat(2, 1fr); gap: 8px; margin-bottom: 15px; }
        .stat-card { padding: 12px 8px; border-radius: 10px; }
        .stat-number { font-size: 1.3rem; }
        .stat-label { font-size: 0.72rem; margin-top: 2px; }

        /* Table container */
        .table-container { padding: 12px; border-radius: 12px; }
        .table-header { gap: 10px; margin-bottom: 12px; }
        .table-title { font-size: 0.95rem; }

        /* Filters stack */
        .filters-row { flex-direction: column !important; gap: 8px !important; width: 100%; }
        .filters-row > * { width: 100% !important; }
        .filters-row input[type="text"] { width: 100% !important; }
        .filters-row select { width: 100%; }
        .filters-row .date-filters { display: flex; gap: 6px; }
        .filters-row .date-filters > div { flex: 1; }
        .filters-row .date-filters input { width: 100%; }

        /* Hide table, show cards */
        .desktop-table { display: none; }
        .mobile-pres-cards { display: block !important; }

        /* Pagination */
        .pagination-container { justify-content: center; }
        .pagination-info { width: 100%; text-align: center; font-size: 0.8rem; }
        .pagination-btns { justify-content: center; flex-wrap: wrap; }
        .pagination-btns a, .pagination-btns span { padding: 6px 10px; font-size: 0.8rem; }
    }

    /* Mobile presupuesto cards */
    .mobile-pres-cards { display: none; }

    .m-pres-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 10px;
        border-left: 4px solid var(--primary);
        transition: box-shadow 0.2s;
    }
    .m-pres-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.08); }
    .m-pres-card.estado-pagado { border-left-color: #28a745; }
    .m-pres-card.estado-aprobado { border-left-color: #28a745; }
    .m-pres-card.estado-borrador { border-left-color: #ffc107; }
    .m-pres-card.estado-enviado { border-left-color: #17a2b8; }
    .m-pres-card.estado-rechazado { border-left-color: #dc3545; }
    .m-pres-card.estado-vencido { border-left-color: #6c757d; }

    .m-pres-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .m-pres-num { font-weight: 700; font-size: 0.9rem; color: #333; }
    .m-pres-cliente { font-size: 0.82rem; color: #555; margin-top: 2px; }
    .m-pres-telefono { font-size: 0.72rem; color: #888; }

    .m-pres-badge {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        flex-shrink: 0;
    }

    .m-pres-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 6px;
    }

    .m-pres-meta { display: flex; gap: 12px; align-items: center; }
    .m-pres-date { font-size: 0.78rem; color: #6c757d; display: flex; align-items: center; gap: 4px; }
    .m-pres-total { font-weight: 800; font-size: 1.05rem; color: #28a745; }

    .m-pres-actions { display: flex; gap: 4px; }
    .m-pres-actions .btn-action { width: 32px; height: 32px; border-radius: 8px; font-size: 0.8rem; }
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Presupuestos</h1>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="crear_presupuesto.php" class="btn-nuevo">
                <i class="fas fa-plus"></i> <span class="btn-text">Nuevo Presupuesto</span>
            </a>
            <a href="pagos.php" class="btn-nuevo" style="background: #28a745; color: white;">
                <i class="fas fa-money-bill-wave"></i> <span class="btn-text">Pagos</span>
            </a>
            <a href="dashboard.php" class="btn-nuevo" style="background: rgba(255,255,255,0.2); color: white;">
                <i class="fas fa-arrow-left"></i> <span class="btn-text">Volver</span>
            </a>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Presupuestos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['aprobados'] ?? 0; ?></div>
            <div class="stat-label">Aprobados</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['borradores'] ?? 0; ?></div>
            <div class="stat-label">Borradores</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">Bs <?php echo number_format($stats['monto_aprobado'] ?? 0, 0); ?></div>
            <div class="stat-label">Monto Aprobado</div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <div class="table-title"><i class="fas fa-list me-2"></i>Lista de Presupuestos <span style="font-weight: 400; font-size: 0.85rem; color: #888;">(<?php echo $totalPresupuestos; ?> total)</span></div>
            <div class="filters-row" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <div style="position: relative; flex: 1; min-width: 180px;">
                    <input type="text" id="searchPresupuesto" placeholder="Buscar..." 
                           style="padding: 10px 15px 10px 40px; border: 2px solid #e9ecef; border-radius: 8px; width: 100%; font-size: 0.9rem;"
                           oninput="filtrarPresupuestos()">
                    <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                </div>
                <select id="filterEstado" style="padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px;" onchange="filtrarPresupuestos()">
                    <option value="">Todos</option>
                    <option value="borrador">Borrador</option>
                    <option value="enviado">Enviado</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="parcial">Pago Parcial</option>
                    <option value="pagado">Pagado</option>
                    <option value="rechazado">Rechazado</option>
                </select>
                <div class="date-filters" style="display: flex; align-items: center; gap: 5px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; color: #666;">Desde:</label>
                        <input type="date" id="fechaDesde" value="<?php echo $fechaDesde; ?>" 
                               style="padding: 8px 10px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.85rem;" onchange="filtrarPorFecha()">
                    </div>
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label style="font-size: 0.8rem; color: #666;">Hasta:</label>
                        <input type="date" id="fechaHasta" value="<?php echo $fechaHasta; ?>" 
                               style="padding: 8px 10px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.85rem;" onchange="filtrarPorFecha()">
                    </div>
                </div>
                <?php if ($fechaDesde || $fechaHasta): ?>
                <a href="presupuestos.php" style="padding: 8px 12px; background: #dc3545; color: white; border-radius: 6px; text-decoration: none; font-size: 0.8rem;">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
                <select onchange="cambiarPorPagina(this.value)" style="padding: 10px 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.85rem;">
                    <option value="10" <?php echo $porPagina == 10 ? 'selected' : ''; ?>>10/pág</option>
                    <option value="25" <?php echo $porPagina == 25 ? 'selected' : ''; ?>>25/pág</option>
                    <option value="50" <?php echo $porPagina == 50 ? 'selected' : ''; ?>>50/pág</option>
                    <option value="100" <?php echo $porPagina == 100 ? 'selected' : ''; ?>>100/pág</option>
                </select>
            </div>
        </div>

        <?php if (empty($presupuestos)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <h4>No hay presupuestos</h4>
            <p>Crea tu primer presupuesto haciendo clic en "Nuevo Presupuesto"</p>
        </div>
        <?php else: ?>
        <div class="desktop-table">
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Paciente</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Pago</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="presupuestosBody">
                <?php foreach ($presupuestos as $pres): 
                    $montoPagado = floatval($pres['monto_pagado']);
                    $totalPres = floatval($pres['total']);
                    $saldoPend = $totalPres - $montoPagado;
                    $pctPagado = $totalPres > 0 ? ($montoPagado / $totalPres) * 100 : 0;
                    $esParcial = $montoPagado > 0 && $montoPagado < $totalPres;
                    $estadoFiltro = $esParcial ? 'parcial' : $pres['estado'];
                ?>
                <tr class="presupuesto-row" 
                    data-cliente="<?php echo strtolower(htmlspecialchars($pres['cliente_nombre'])); ?>"
                    data-telefono="<?php echo htmlspecialchars($pres['cliente_telefono']); ?>"
                    data-numero="<?php echo strtolower(htmlspecialchars($pres['numero'])); ?>"
                    data-estado="<?php echo $pres['estado']; ?>"
                    data-estado-pago="<?php echo $estadoFiltro; ?>">
                    <td><strong><?php echo htmlspecialchars($pres['numero']); ?></strong></td>
                    <td>
                        <?php echo htmlspecialchars($pres['cliente_nombre']); ?>
                        <div style="font-size: 0.8rem; color: #888;">
                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($pres['cliente_telefono']); ?>
                        </div>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></td>
                    <td class="monto">Bs <?php echo number_format($pres['total'], 2); ?></td>
                    <td>
                        <?php if ($pres['estado'] === 'pagado'): ?>
                        <!-- Completamente pagado -->
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                            <span style="font-size: 0.82rem; font-weight: 700; color: #28a745;">Pagado</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #888;">Bs <?php echo number_format($montoPagado, 2); ?></div>
                        <?php elseif ($esParcial): ?>
                        <!-- Pago parcial -->
                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <span style="font-size: 0.78rem; font-weight: 700; color: #d97706;">Parcial <?php echo number_format($pctPagado, 0); ?>%</span>
                        </div>
                        <div style="width: 80px; height: 5px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo min(100, $pctPagado); ?>%; background: linear-gradient(90deg, #f59e0b, #d97706); border-radius: 3px;"></div>
                        </div>
                        <div style="font-size: 0.72rem; color: #888; margin-top: 2px;">Bs <?php echo number_format($montoPagado, 2); ?> / <?php echo number_format($totalPres, 2); ?></div>
                        <?php elseif (in_array($pres['estado'], ['aprobado', 'enviado'])): ?>
                        <!-- Sin pagos aún -->
                        <span style="font-size: 0.78rem; color: #aaa;"><i class="fas fa-minus-circle me-1"></i>Sin pago</span>
                        <?php else: ?>
                        <span style="font-size: 0.78rem; color: #ccc;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-estado badge-<?php echo $pres['estado']; ?>">
                            <?php 
                            $estadoTexto = [
                                'borrador' => 'Borrador',
                                'enviado' => 'Enviado',
                                'aprobado' => 'Aprobado',
                                'rechazado' => 'Rechazado',
                                'vencido' => 'Vencido',
                                'pagado' => 'Pagado'
                            ];
                            echo $estadoTexto[$pres['estado']] ?? ucfirst($pres['estado']); 
                            ?>
                        </span>
                    </td>
                    <td>
                        <a href="ver_presupuesto.php?id=<?php echo $pres['id']; ?>" class="btn-action btn-view" title="Ver">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="generar_presupuesto_pdf.php?id=<?php echo $pres['id']; ?>" target="_blank" class="btn-action btn-pdf" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                        <button class="btn-action btn-delete" onclick="eliminar(<?php echo $pres['id']; ?>)" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="mobile-pres-cards">
            <?php foreach ($presupuestos as $pres): 
                $badgeColors = [
                    'borrador' => 'background:#fff3cd;color:#856404;',
                    'enviado' => 'background:#d1ecf1;color:#0c5460;',
                    'aprobado' => 'background:#d4edda;color:#155724;',
                    'rechazado' => 'background:#f8d7da;color:#721c24;',
                    'vencido' => 'background:#e2e3e5;color:#383d41;',
                    'pagado' => 'background:#d4edda;color:#155724;'
                ];
                $badgeStyle = $badgeColors[$pres['estado']] ?? 'background:#e9ecef;color:#333;';
                $estadoTexto = [
                    'borrador' => 'Borrador', 'enviado' => 'Enviado', 'aprobado' => 'Aprobado',
                    'rechazado' => 'Rechazado', 'vencido' => 'Vencido', 'pagado' => 'Pagado'
                ];
                $mMontoPagado = floatval($pres['monto_pagado']);
                $mTotalPres = floatval($pres['total']);
                $mPctPagado = $mTotalPres > 0 ? ($mMontoPagado / $mTotalPres) * 100 : 0;
                $mEsParcial = $mMontoPagado > 0 && $mMontoPagado < $mTotalPres;
                $mEstadoFiltro = $mEsParcial ? 'parcial' : $pres['estado'];
            ?>
            <div class="m-pres-card estado-<?php echo $pres['estado']; ?> presupuesto-row"
                 data-cliente="<?php echo strtolower(htmlspecialchars($pres['cliente_nombre'])); ?>"
                 data-telefono="<?php echo htmlspecialchars($pres['cliente_telefono']); ?>"
                 data-numero="<?php echo strtolower(htmlspecialchars($pres['numero'])); ?>"
                 data-estado="<?php echo $pres['estado']; ?>"
                 data-estado-pago="<?php echo $mEstadoFiltro; ?>">
                <div class="m-pres-top">
                    <div>
                        <div class="m-pres-num"><?php echo htmlspecialchars($pres['numero']); ?></div>
                        <div class="m-pres-cliente"><?php echo htmlspecialchars($pres['cliente_nombre']); ?></div>
                        <div class="m-pres-telefono"><i class="fas fa-phone" style="font-size:0.65rem;"></i> <?php echo htmlspecialchars($pres['cliente_telefono']); ?></div>
                    </div>
                    <span class="m-pres-badge" style="<?php echo $badgeStyle; ?>"><?php echo $estadoTexto[$pres['estado']] ?? ucfirst($pres['estado']); ?></span>
                </div>
                <!-- Info de pago en mobile -->
                <?php if ($pres['estado'] === 'pagado'): ?>
                <div style="display: flex; align-items: center; gap: 6px; margin: 6px 0; padding: 6px 10px; background: #f0fdf4; border-radius: 8px;">
                    <i class="fas fa-check-circle" style="color: #28a745; font-size: 0.75rem;"></i>
                    <span style="font-size: 0.78rem; font-weight: 700; color: #28a745;">Pagado completamente</span>
                    <span style="font-size: 0.75rem; color: #888; margin-left: auto;">Bs <?php echo number_format($mMontoPagado, 2); ?></span>
                </div>
                <?php elseif ($mEsParcial): ?>
                <div style="margin: 6px 0; padding: 8px 10px; background: #fffbeb; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #d97706;"><i class="fas fa-clock me-1"></i>Pago parcial <?php echo number_format($mPctPagado, 0); ?>%</span>
                        <span style="font-size: 0.72rem; color: #dc3545; font-weight: 600;">Debe: Bs <?php echo number_format($mTotalPres - $mMontoPagado, 2); ?></span>
                    </div>
                    <div style="width: 100%; height: 5px; background: #e9ecef; border-radius: 3px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo min(100, $mPctPagado); ?>%; background: linear-gradient(90deg, #f59e0b, #d97706); border-radius: 3px;"></div>
                    </div>
                    <div style="font-size: 0.72rem; color: #888; margin-top: 3px;">Pagado: Bs <?php echo number_format($mMontoPagado, 2); ?> de Bs <?php echo number_format($mTotalPres, 2); ?></div>
                </div>
                <?php endif; ?>
                <div class="m-pres-bottom">
                    <div class="m-pres-meta">
                        <span class="m-pres-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></span>
                        <span class="m-pres-total">Bs <?php echo number_format($pres['total'], 2); ?></span>
                    </div>
                    <div class="m-pres-actions">
                        <a href="ver_presupuesto.php?id=<?php echo $pres['id']; ?>" class="btn-action btn-view" title="Ver"><i class="fas fa-eye"></i></a>
                        <a href="generar_presupuesto_pdf.php?id=<?php echo $pres['id']; ?>" target="_blank" class="btn-action btn-pdf" title="PDF"><i class="fas fa-file-pdf"></i></a>
                        <button class="btn-action btn-delete" onclick="eliminar(<?php echo $pres['id']; ?>)" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $porPagina, $totalPresupuestos); ?> de <?php echo $totalPresupuestos; ?>
            </div>
            <div class="pagination-btns">
                <?php if ($pagina > 1): ?>
                <a href="?p=1&pp=<?php echo $porPagina; ?><?php echo $fechaDesde ? '&desde='.$fechaDesde : ''; ?><?php echo $fechaHasta ? '&hasta='.$fechaHasta : ''; ?>">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?p=<?php echo $pagina - 1; ?>&pp=<?php echo $porPagina; ?><?php echo $fechaDesde ? '&desde='.$fechaDesde : ''; ?><?php echo $fechaHasta ? '&hasta='.$fechaHasta : ''; ?>">
                    <i class="fas fa-angle-left"></i>
                </a>
                <?php endif; ?>
                
                <span class="active-page">
                    <?php echo $pagina; ?> / <?php echo $totalPaginas; ?>
                </span>
                
                <?php if ($pagina < $totalPaginas): ?>
                <a href="?p=<?php echo $pagina + 1; ?>&pp=<?php echo $porPagina; ?><?php echo $fechaDesde ? '&desde='.$fechaDesde : ''; ?><?php echo $fechaHasta ? '&hasta='.$fechaHasta : ''; ?>">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?p=<?php echo $totalPaginas; ?>&pp=<?php echo $porPagina; ?><?php echo $fechaDesde ? '&desde='.$fechaDesde : ''; ?><?php echo $fechaHasta ? '&hasta='.$fechaHasta : ''; ?>">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>
</div>

<script>
function eliminar(id) {
    if (confirm('¿Estás seguro de eliminar este presupuesto?')) {
        window.location.href = 'eliminar_presupuesto.php?id=' + id;
    }
}

function filtrarPresupuestos() {
    const search = document.getElementById('searchPresupuesto').value.toLowerCase().trim();
    const estado = document.getElementById('filterEstado').value;
    
    document.querySelectorAll('.presupuesto-row').forEach(row => {
        const cliente = row.dataset.cliente || '';
        const telefono = row.dataset.telefono || '';
        const numero = row.dataset.numero || '';
        const rowEstado = row.dataset.estado || '';
        const rowEstadoPago = row.dataset.estadoPago || rowEstado;
        
        let show = true;
        
        // Filtrar por búsqueda (cliente, teléfono o número)
        if (search && !cliente.includes(search) && !telefono.includes(search) && !numero.includes(search)) {
            show = false;
        }
        
        // Filtrar por estado (incluye filtro 'parcial')
        if (estado) {
            if (estado === 'parcial') {
                if (rowEstadoPago !== 'parcial') show = false;
            } else {
                if (rowEstado !== estado) show = false;
            }
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function cambiarPorPagina(valor) {
    const desde = document.getElementById('fechaDesde').value;
    const hasta = document.getElementById('fechaHasta').value;
    let url = '?p=1&pp=' + valor;
    if (desde) url += '&desde=' + desde;
    if (hasta) url += '&hasta=' + hasta;
    window.location.href = url;
}

function filtrarPorFecha() {
    const desde = document.getElementById('fechaDesde').value;
    const hasta = document.getElementById('fechaHasta').value;
    let url = '?p=1&pp=<?php echo $porPagina; ?>';
    if (desde) url += '&desde=' + desde;
    if (hasta) url += '&hasta=' + hasta;
    window.location.href = url;
}
</script>

</body>
</html>

