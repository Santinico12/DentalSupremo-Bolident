<?php
ob_start();

session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Consultorio.php';

$appointmentModel = new Appointment($pdo);
$consultorioModel = new Consultorio($pdo);
$consultorios = $consultorioModel->getAll();

// Definir parámetros de búsqueda
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');
    $consultorio_id = $_POST['consultorio_id'] ?? '';
    $estado_filtro = $_POST['estado'] ?? '';
} else {
    // Por defecto cargar el mes en curso para mayor comodidad del usuario
    $fecha_inicio = date('Y-m-01');
    $fecha_fin = date('Y-m-d');
    $consultorio_id = '';
    $estado_filtro = '';
}

$citas = [];
if (!empty($fecha_inicio) && !empty($fecha_fin)) {
    $start_dt = $fecha_inicio . ' 00:00:00';
    $end_dt = $fecha_fin . ' 23:59:59';

    if ($consultorio_id === "") {
        $citas = $appointmentModel->getByDateRange($start_dt, $end_dt);
    } else {
        $citas = $appointmentModel->getByDateRangeAndConsultorio($start_dt, $end_dt, $consultorio_id);
    }
    
    // Mapear colores de consultorios
    if (!empty($citas)) {
        $consultorios_colors = [];
        foreach ($consultorios as $cons) {
            $consultorios_colors[$cons['id']] = $cons['color'];
        }
        
        foreach ($citas as &$cita) {
            if (!isset($cita['consultorio_color']) || empty($cita['consultorio_color'])) {
                $cita['consultorio_color'] = $consultorios_colors[$cita['consultorio_id']] ?? '#003B73';
            }
        }
        unset($cita);
    }
    
    // Filtrar por estado si se seleccionó uno específico
    if ($estado_filtro !== '' && $estado_filtro !== 'todos') {
        $citas = array_values(array_filter($citas, function($cita) use ($estado_filtro) {
            $estado = isset($cita['estado']) ? strtolower($cita['estado']) : 'activo';
            return $estado === $estado_filtro;
        }));
    }
}

// Calcular estadísticas
$total_citas = count($citas);
$total_confirmadas = 0;
$total_canceladas = 0;
$total_pospuestas = 0;
$total_activas = 0;
$duracion_total = 0;
$citas_por_consultorio = [];

foreach ($citas as $cita) {
    $estado = isset($cita['estado']) ? strtolower($cita['estado']) : 'activo';
    if ($estado === 'confirmado') $total_confirmadas++;
    elseif ($estado === 'cancelado') $total_canceladas++;
    elseif ($estado === 'pospuesto') $total_pospuestas++;
    else $total_activas++;
    
    $duracion_total += (int)$cita['duracion_estimada'];
    
    $cons_id = $cita['consultorio_id'];
    if (!isset($citas_por_consultorio[$cons_id])) {
        $citas_por_consultorio[$cons_id] = [
            'nombre' => $cita['consultorio_nombre'],
            'cantidad' => 0,
            'color' => $cita['consultorio_color'] ?? '#003B73'
        ];
    }
    $citas_por_consultorio[$cons_id]['cantidad']++;
}

require_once '../templates/header_general.php';
?>

<!-- Dependencies: Bootstrap 5, FontAwesome 6, DataTables & Chart.js -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #003B73;
        --primary-gradient: linear-gradient(135deg, #003B73 0%, #2998EC 100%);
        --primary-dark: #062846;
        --accent-teal: #2998EC;
        --accent-green: #10B981;
        --accent-purple: #8B5CF6;
        --accent-blue: #0284C7;
        --danger: #EF4444;
        --warning: #F59E0B;
        --bg-main: #F4F9FD;
        --card-bg: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(15, 76, 110, 0.06);
        --shadow-md: 0 4px 16px rgba(15, 76, 110, 0.1);
        --shadow-hover: 0 8px 24px rgba(0, 168, 150, 0.15);
    }

    body {
        background-color: var(--bg-main);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .main-container {
        padding: 25px 30px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Header Principal */
    .page-header {
        background: var(--primary-gradient);
        border-radius: 18px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
        color: white;
    }

    .header-text h1 {
        margin: 0;
        font-size: 1.8rem;
        color: white;
        font-weight: 800;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .header-text p {
        margin: 6px 0 0 0;
        color: rgba(255, 255, 255, 0.88);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        color: white;
        padding: 10px 20px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.9rem;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .btn-back:hover {
        background: white;
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Tarjetas de Filtros */
    .filter-card {
        background: var(--card-bg);
        border-radius: 18px;
        padding: 25px 28px;
        margin-bottom: 25px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .filter-card h3 {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .filter-card h3 i {
        color: var(--accent-teal);
        margin-right: 8px;
    }

    .form-label {
        font-weight: 700;
        color: var(--text-dark);
        font-size: 0.85rem;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .form-label i {
        color: var(--primary);
    }

    .form-control, .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 10px 14px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.25s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--accent-teal);
        box-shadow: 0 0 0 4px rgba(0, 168, 150, 0.12);
    }

    .btn-preset {
        background: var(--bg-main);
        border: 1px solid var(--border-color);
        color: var(--text-dark);
        font-weight: 600;
        font-size: 0.82rem;
        padding: 5px 12px;
        border-radius: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .btn-preset:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .btn-search {
        background: var(--primary-gradient);
        color: white;
        padding: 12px 26px;
        border: none;
        border-radius: 12px;
        font-weight: 800;
        font-size: 1rem;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 14px rgba(15, 76, 110, 0.2);
    }

    .btn-search:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
        color: white;
    }

    /* KPI Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 22px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        border-left: 5px solid var(--primary);
        transition: all 0.25s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-hover);
    }

    .stat-card.primary { border-left-color: var(--primary); }
    .stat-card.success { border-left-color: var(--accent-green); }
    .stat-card.warning { border-left-color: var(--warning); }
    .stat-card.danger { border-left-color: var(--danger); }
    .stat-card.info { border-left-color: var(--accent-teal); }

    .stat-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .stat-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: white;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .stat-card.primary .stat-icon { background: var(--primary); }
    .stat-card.success .stat-icon { background: var(--accent-green); }
    .stat-card.warning .stat-icon { background: var(--warning); }
    .stat-card.danger .stat-icon { background: var(--danger); }
    .stat-card.info .stat-icon { background: var(--accent-teal); }

    .stat-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--text-dark);
        line-height: 1;
    }

    /* Gráficos */
    .charts-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .chart-card {
        background: var(--card-bg);
        border-radius: 18px;
        padding: 24px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .chart-card h4 {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .chart-card h4 i {
        color: var(--accent-teal);
    }

    /* Tabla */
    .table-card {
        background: var(--card-bg);
        border-radius: 18px;
        padding: 28px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        margin-bottom: 25px;
    }

    .table-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 22px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .table-card-header h3 {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .export-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-export-custom {
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        border: none;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        color: white;
        text-decoration: none;
    }

    .btn-export-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        color: white;
    }

    .btn-pdf { background: #EF4444; }
    .btn-excel { background: #10B981; }
    .btn-csv { background: #0284C7; }
    .btn-print { background: #64748B; }

    /* Estilos Tabla DataTables */
    #informesTable {
        width: 100% !important;
        border-collapse: separate;
        border-spacing: 0;
    }

    #informesTable thead th {
        background: var(--bg-main);
        color: var(--text-dark);
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.78rem;
        letter-spacing: 0.5px;
        padding: 14px 16px;
        border-bottom: 2px solid var(--border-color);
    }

    #informesTable tbody tr {
        transition: background 0.2s ease;
    }

    #informesTable tbody tr:hover {
        background: rgba(0, 168, 150, 0.04);
    }

    #informesTable tbody td {
        padding: 14px 16px;
        vertical-align: middle;
        font-size: 0.92rem;
        border-bottom: 1px solid var(--border-color);
    }

    .badge-estado {
        padding: 5px 12px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .badge-activo { background: #F1F5F9; color: #475569; }
    .badge-confirmado { background: #D1FAE5; color: #065F46; }
    .badge-pospuesto { background: #FEF3C7; color: #92400E; }
    .badge-cancelado { background: #FEE2E2; color: #991B1B; }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--accent-teal);
        margin-bottom: 15px;
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .main-container {
            padding: 15px;
        }

        .page-header {
            padding: 20px;
        }

        .header-text h1 {
            font-size: 1.4rem;
        }

        .charts-section {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="main-container">
    <!-- Header Banner -->
    <div class="page-header">
        <div class="header-text">
            <h1><i class="fas fa-chart-line"></i> Informes y Estadísticas Avanzadas</h1>
            <p>Analiza el rendimiento operativo de tu clínica Dentality con métricas detalladas</p>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Dashboard
        </a>
    </div>

    <!-- Card de Filtros de Búsqueda -->
    <div class="filter-card">
        <h3>
            <span><i class="fas fa-filter"></i> Filtros de Búsqueda</span>
            <div class="d-flex gap-1 flex-wrap">
                <button type="button" class="btn-preset" onclick="setPresetDate('este_mes')">Este Mes</button>
                <button type="button" class="btn-preset" onclick="setPresetDate('mes_anterior')">Mes Anterior</button>
                <button type="button" class="btn-preset" onclick="setPresetDate('hoy')">Hoy</button>
            </div>
        </h3>
        <form method="POST" action="" id="filterForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">
                        <i class="fas fa-calendar-alt"></i>
                        Fecha Inicio
                    </label>
                    <input type="date" 
                           class="form-control" 
                           id="fecha_inicio" 
                           name="fecha_inicio" 
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>" 
                           required>
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">
                        <i class="fas fa-calendar-check"></i>
                        Fecha Fin
                    </label>
                    <input type="date" 
                           class="form-control" 
                           id="fecha_fin" 
                           name="fecha_fin" 
                           value="<?php echo htmlspecialchars($fecha_fin); ?>" 
                           required>
                </div>
                <div class="col-md-3">
                    <label for="consultorio_id" class="form-label">
                        <i class="fas fa-clinic-medical"></i>
                        Consultorio
                    </label>
                    <select class="form-select" id="consultorio_id" name="consultorio_id">
                        <option value="">Todos los consultorios</option>
                        <?php foreach ($consultorios as $consultorio): ?>
                            <option value="<?php echo $consultorio['id']; ?>" 
                                    <?php echo ($consultorio_id !== '' && $consultorio_id == $consultorio['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($consultorio['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">
                        <i class="fas fa-info-circle"></i>
                        Estado de Cita
                    </label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="todos">Todos los estados</option>
                        <option value="activo" <?php echo $estado_filtro === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="confirmado" <?php echo $estado_filtro === 'confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                        <option value="pospuesto" <?php echo $estado_filtro === 'pospuesto' ? 'selected' : ''; ?>>Pospuesto</option>
                        <option value="cancelado" <?php echo $estado_filtro === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end mt-3">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i>
                        Generar Reporte
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Resumen de Métricas KPI -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-card-header">
                <span class="stat-label">Total de Citas</span>
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_citas; ?></div>
        </div>

        <div class="stat-card success">
            <div class="stat-card-header">
                <span class="stat-label">Confirmadas</span>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_confirmadas; ?></div>
        </div>

        <div class="stat-card warning">
            <div class="stat-card-header">
                <span class="stat-label">Pospuestas</span>
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_pospuestas; ?></div>
        </div>

        <div class="stat-card danger">
            <div class="stat-card-header">
                <span class="stat-label">Canceladas</span>
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $total_canceladas; ?></div>
        </div>

        <div class="stat-card info">
            <div class="stat-card-header">
                <span class="stat-label">Horas Atendidas</span>
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($duracion_total / 60, 1); ?>h</div>
        </div>
    </div>

    <!-- Sección de Gráficos Interactivos -->
    <?php if ($total_citas > 0): ?>
    <div class="charts-section">
        <!-- Gráfico de Estados -->
        <div class="chart-card">
            <h4>
                <i class="fas fa-chart-pie"></i>
                Distribución por Estado
            </h4>
            <div style="position: relative; height: 260px;">
                <canvas id="estadosChart"></canvas>
            </div>
        </div>

        <!-- Gráfico por Consultorio -->
        <div class="chart-card">
            <h4>
                <i class="fas fa-chart-bar"></i>
                Citas por Consultorio
            </h4>
            <div style="position: relative; height: 260px;">
                <canvas id="consultoriosChart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla Detallada -->
    <div class="table-card">
        <div class="table-card-header">
            <h3>
                <i class="fas fa-table"></i>
                Detalle de Citas
            </h3>
            <?php if (!empty($citas)): ?>
            <div class="export-buttons" id="exportButtonsContainer">
                <a href="generar_informe.php?fecha_inicio=<?php echo urlencode($fecha_inicio); ?>&fecha_fin=<?php echo urlencode($fecha_fin); ?>&consultorio_id=<?php echo urlencode($consultorio_id); ?>" 
                   class="btn-export-custom btn-pdf" 
                   target="_blank">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <button id="btnExcel" type="button" class="btn-export-custom btn-excel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button id="btnCsv" type="button" class="btn-export-custom btn-csv">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button id="btnPrint" type="button" class="btn-export-custom btn-print">
                    <i class="fas fa-print"></i> Imprimir
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($citas)): ?>
            <div class="table-responsive">
                <table id="informesTable" class="table align-middle">
                    <thead>
                        <tr>
                            <th>Paciente / Cliente</th>
                            <th>Fecha Cita</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th>Descripción</th>
                            <th>Consultorio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($citas as $cita): 
                            $estado = isset($cita['estado']) ? strtolower($cita['estado']) : 'activo';
                            $badge_class = 'badge-' . $estado;
                            $estado_icon = [
                                'activo' => 'fa-circle',
                                'confirmado' => 'fa-check-circle',
                                'pospuesto' => 'fa-clock',
                                'cancelado' => 'fa-times-circle'
                            ];
                        ?>
                            <tr>
                                <td><strong class="text-dark"><?php echo htmlspecialchars($cita['cliente_nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($cita['fecha']))); ?></td>
                                <td><span class="badge bg-light text-dark fw-semibold"><?php echo htmlspecialchars(date('H:i', strtotime($cita['fecha']))); ?></span></td>
                                <td><span class="badge bg-light text-dark fw-semibold"><?php echo htmlspecialchars(date('H:i', strtotime($cita['finDeCita']))); ?></span></td>
                                <td><?php echo (int)$cita['duracion_estimada']; ?> min</td>
                                <td>
                                    <span class="badge-estado <?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $estado_icon[$estado] ?? 'fa-circle'; ?>"></i>
                                        <?php echo ucfirst($estado); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($cita['descripcion'] ?? '-'); ?></td>
                                <td>
                                    <span style="display: inline-flex; align-items: center; gap: 6px; font-weight: 600;">
                                        <span style="width: 10px; height: 10px; border-radius: 50%; background-color: <?php echo htmlspecialchars($cita['consultorio_color'] ?? '#003B73'); ?>;"></span>
                                        <?php echo htmlspecialchars($cita['consultorio_nombre']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h5 class="fw-bold text-dark">No se encontraron citas en este período</h5>
                <p class="mt-2">Prueba ajustando las fechas de inicio y fin en el filtro superior.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    function setPresetDate(type) {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');

        let start, end;

        if (type === 'hoy') {
            start = `${yyyy}-${mm}-${dd}`;
            end = `${yyyy}-${mm}-${dd}`;
        } else if (type === 'este_mes') {
            start = `${yyyy}-${mm}-01`;
            end = `${yyyy}-${mm}-${dd}`;
        } else if (type === 'mes_anterior') {
            const prevMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDayPrevMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            
            const prevYyyy = prevMonth.getFullYear();
            const prevMm = String(prevMonth.getMonth() + 1).padStart(2, '0');
            const prevLastDd = String(lastDayPrevMonth.getDate()).padStart(2, '0');

            start = `${prevYyyy}-${prevMm}-01`;
            end = `${prevYyyy}-${prevMm}-${prevLastDd}`;
        }

        document.getElementById('fecha_inicio').value = start;
        document.getElementById('fecha_fin').value = end;
        document.getElementById('filterForm').submit();
    }

    $(document).ready(function() {
        var tableEl = $('#informesTable');
        if (tableEl.length) {
            var table = tableEl.DataTable({
                dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center mt-3"lip>',
                buttons: [
                    { 
                        extend: 'excel', 
                        title: 'Informes de Citas - Dentality - <?php echo date("d-m-Y"); ?>', 
                        exportOptions: { columns: [0,1,2,3,4,5,6,7] }, 
                        className: 'd-none' 
                    },
                    { 
                        extend: 'csv', 
                        title: 'Informes de Citas - Dentality - <?php echo date("d-m-Y"); ?>', 
                        exportOptions: { columns: [0,1,2,3,4,5,6,7] }, 
                        className: 'd-none' 
                    },
                    { 
                        extend: 'print', 
                        title: 'Informes de Citas - Dentality',
                        messageTop: '<h3>Período: <?php echo $fecha_inicio ? date("d/m/Y", strtotime($fecha_inicio)) . " al " . date("d/m/Y", strtotime($fecha_fin)) : ""; ?></h3>',
                        exportOptions: { columns: [0,1,2,3,4,5,6,7] }, 
                        className: 'd-none' 
                    }
                ],
                language: { 
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/es-ES.json',
                    search: "_INPUT_",
                    searchPlaceholder: "Buscar en los informes..."
                },
                pageLength: 10,
                responsive: true,
                order: [[1, 'desc'], [2, 'desc']],
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Todos"]]
            });

            $('#btnExcel').on('click', function() { table.button(0).trigger(); });
            $('#btnCsv').on('click', function()   { table.button(1).trigger(); });
            $('#btnPrint').on('click', function() { table.button(2).trigger(); });
        }

        // Gráficos con Chart.js
        <?php if ($total_citas > 0): ?>
        // Gráfico de Estados (Doughnut)
        const estadosCtx = document.getElementById('estadosChart');
        if (estadosCtx) {
            new Chart(estadosCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Activas', 'Confirmadas', 'Pospuestas', 'Canceladas'],
                    datasets: [{
                        data: [<?php echo $total_activas; ?>, <?php echo $total_confirmadas; ?>, <?php echo $total_pospuestas; ?>, <?php echo $total_canceladas; ?>],
                        backgroundColor: ['#64748B', '#10B981', '#F59E0B', '#EF4444'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: { size: 12, family: "'Plus Jakarta Sans', sans-serif", weight: '600' }
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de Consultorios (Bar)
        const consultoriosCtx = document.getElementById('consultoriosChart');
        if (consultoriosCtx) {
            new Chart(consultoriosCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(function($c) { return "'" . addslashes($c['nombre']) . "'"; }, $citas_por_consultorio)); ?>],
                    datasets: [{
                        label: 'Número de Citas',
                        data: [<?php echo implode(',', array_map(function($c) { return $c['cantidad']; }, $citas_por_consultorio)); ?>],
                        backgroundColor: [<?php echo implode(',', array_map(function($c) { return "'" . $c['color'] . "'"; }, $citas_por_consultorio)); ?>],
                        borderWidth: 0,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#F1F5F9' },
                            ticks: { stepSize: 1, font: { family: "'Plus Jakarta Sans', sans-serif" } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' } }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    });
</script>
</body>
</html>
