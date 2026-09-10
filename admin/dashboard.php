<?php
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Consultorio.php';
require_once '../src/models/Doctor.php';

$appointmentModel = new Appointment($pdo);
$consultorioModel = new Consultorio($pdo);
$doctorModel = new Doctor($pdo);

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    if ($appointmentModel->delete($id)) {
        $_SESSION['message'] = "Cita eliminada correctamente.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error al eliminar la cita.";
        $_SESSION['message_type'] = "danger";
    }
    header('Location: dashboard.php');
    exit();
}

// Determinar filtros
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'hoy';
$consultorio_filtro = isset($_GET['consultorio']) ? (int)$_GET['consultorio'] : 0;

// Obtener citas según el filtro de tiempo
switch ($filtro) {
    case 'hoy':
        $citas = $appointmentModel->getToday();
        $tituloFiltro = 'Citas de Hoy';
        $iconoFiltro = 'fa-calendar-day';
        break;
    case 'manana':
        $citas = $appointmentModel->getTomorrow();
        $tituloFiltro = 'Citas de Mañana';
        $iconoFiltro = 'fa-calendar-plus';
        break;
    case 'semana':
        $citas = $appointmentModel->getThisWeek();
        $tituloFiltro = 'Citas de Esta Semana';
        $iconoFiltro = 'fa-calendar-week';
        break;
    default:
        $citas = $appointmentModel->getToday();
        $tituloFiltro = 'Citas de Hoy';
        $iconoFiltro = 'fa-calendar-day';
}

// Filtrar por consultorio si está seleccionado
if ($consultorio_filtro > 0) {
    $citas = array_filter($citas, function($cita) use ($consultorio_filtro) {
        return $cita['consultorio_id'] == $consultorio_filtro;
    });
}

$user = $_SESSION['user'];
$consultorios = $consultorioModel->getAll();
$doctores = $doctorModel->getActivos();

// Obtener recordatorios para mañana y para hoy (tanto pendientes como ya enviados)
$fechaManana = date('Y-m-d', strtotime('+1 day'));
$fechaHoy = date('Y-m-d');

$recordatoriosManana = $appointmentModel->getCitasParaRecordatorios($fechaManana);
$recordatoriosHoy = $appointmentModel->getCitasParaRecordatorios($fechaHoy);

$totalManana = count($recordatoriosManana);
$totalHoy = count($recordatoriosHoy);
$totalRecordatorios = $totalManana + $totalHoy;

// Contar los que están pendientes de envío
$pendientesManana = count(array_filter($recordatoriosManana, function($r) { return empty($r['recordatorio_enviado']); }));
$pendientesHoy = count(array_filter($recordatoriosHoy, function($r) { return empty($r['recordatorio_enviado']); }));
$totalPendientes = $pendientesManana + $pendientesHoy;

require_once '../templates/header_general.php';
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root {
        --primary: #003B73;
        --primary-dark: #062846;
        --primary-light: #2998EC;
        --success: #28a745;
        --warning: #ffc107;
        --danger: #dc3545;
        --info: #17a2b8;
        --light: #F4F9FD;
        --dark: #343a40;
        --whatsapp: #25D366;
        --shadow: 0 2px 8px rgba(0, 59, 115, 0.08);
        --shadow-hover: 0 4px 16px rgba(0, 59, 115, 0.2);
        --shadow-lg: 0 8px 32px rgba(0, 59, 115, 0.12);
    }

    body {
        background: linear-gradient(135deg, #F4F9FD 0%, #f3e6ed 100%);
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .dashboard-container {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    /* ============================================
       WELCOME HEADER - Slim
       ============================================ */
    .welcome-card {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border-radius: 12px;
        padding: 14px 20px;
        color: white;
        box-shadow: var(--shadow-hover);
        margin-bottom: 18px;
        animation: slideDown 0.5s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .welcome-card .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.3rem;
        font-weight: 600;
    }

    .welcome-card .user-info i {
        font-size: 1.5rem;
    }

    .welcome-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .welcome-date {
        font-size: 0.85rem;
        opacity: 0.9;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* ============================================
       QUICK ACCESS BAR - Compact Icon Strip
       ============================================ */
    .quick-bar {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(95px, 1fr));
        gap: 8px;
        margin-bottom: 18px;
        width: 100%;
    }

    .quick-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 10px 8px;
        background: white;
        border-radius: 10px;
        box-shadow: var(--shadow);
        transition: all 0.25s ease;
        cursor: pointer;
        text-decoration: none;
        color: var(--dark);
        width: 100%;
        border: 2px solid transparent;
    }

    .quick-item:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-hover);
        border-color: var(--primary);
        color: var(--primary);
    }

    .quick-item i {
        font-size: 1.3rem;
        color: var(--primary);
    }

    .quick-item span {
        font-size: 0.68rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        white-space: nowrap;
    }

    /* ============================================
       FILTERS - Inline Compact Row
       ============================================ */
    .filters-row {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 18px;
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .filter-section {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .filter-divider {
        width: 1px;
        height: 28px;
        background: #dee2e6;
        flex-shrink: 0;
    }

    .filter-tag {
        font-size: 0.75rem;
        font-weight: 700;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .filter-tag i {
        color: var(--primary);
        font-size: 0.85rem;
    }

    .filter-btn {
        padding: 7px 14px;
        border: 2px solid var(--primary);
        background: white;
        color: var(--primary);
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        white-space: nowrap;
    }

    .filter-btn:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-1px);
    }

    .filter-btn.active {
        background: var(--primary);
        color: white;
        box-shadow: 0 3px 10px rgba(196, 162, 126, 0.35);
    }

    .chip {
        padding: 7px 14px;
        border-radius: 18px;
        font-weight: 600;
        font-size: 0.78rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
        border: 2px solid transparent;
        white-space: nowrap;
    }

    .chip:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow);
    }

    .chip.active {
        border: 2px solid;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
    }

    /* ============================================
       CITAS CONTAINER
       ============================================ */
    .citas-container {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .citas-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        color: white;
        padding: 14px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .citas-header h3 {
        margin: 0;
        font-size: 1.2rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .citas-count {
        background: rgba(255, 255, 255, 0.25);
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    /* ============================================
       CITAS GRID - Compact Cards
       ============================================ */
    .citas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 14px;
        padding: 16px;
        background: var(--light);
        max-height: calc(100vh - 280px);
        overflow-y: auto;
    }

    .citas-grid::-webkit-scrollbar {
        width: 6px;
    }

    .citas-grid::-webkit-scrollbar-track {
        background: transparent;
    }

    .citas-grid::-webkit-scrollbar-thumb {
        background: var(--primary-light);
        border-radius: 3px;
    }

    /* ============================================
       CITA CARD - Compact Design
       ============================================ */
    .cita-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--shadow);
        overflow: hidden;
        transition: all 0.25s ease;
        border-left: 4px solid var(--primary);
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: scale(0.97); }
        to { opacity: 1; transform: scale(1); }
    }

    .cita-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-hover);
    }

    /* Status-based left border */
    .cita-card[data-estado="confirmado"] { border-left-color: var(--success); }
    .cita-card[data-estado="pospuesto"] { border-left-color: var(--warning); }
    .cita-card[data-estado="cancelado"] { border-left-color: var(--danger); }

    .cita-header-card {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        padding: 12px 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }

    .cita-cliente {
        font-weight: 600;
        font-size: 1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        min-width: 0;
    }

    .cita-cliente i { font-size: 1.1rem; flex-shrink: 0; }

    .cita-cliente-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cita-whatsapp {
        background: #25D366;
        color: white;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        min-width: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
        transition: all 0.25s ease;
        text-decoration: none;
    }

    .cita-whatsapp:hover {
        transform: scale(1.12) rotate(8deg);
        box-shadow: 0 3px 10px rgba(37, 211, 102, 0.4);
        color: white;
    }

    .cita-body {
        padding: 12px 14px;
    }

    /* Compact info grid: 3 cols on wide, 2 on narrow */
    .cita-info-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 10px;
    }

    .cita-info-item {
        display: flex;
        align-items: center;
        gap: 7px;
        padding: 8px;
        background: var(--light);
        border-radius: 8px;
        transition: background 0.2s ease;
    }

    .cita-info-item:hover { background: #e9ecef; }

    .cita-info-item i {
        color: var(--primary);
        font-size: 0.95rem;
        width: 18px;
        min-width: 18px;
    }

    .cita-info-content { flex: 1; min-width: 0; }

    .cita-info-label {
        font-size: 0.62rem;
        color: #6c757d;
        margin: 0;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
        line-height: 1.2;
    }

    .cita-info-value {
        font-size: 0.82rem;
        color: var(--dark);
        margin: 0;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: 1.3;
    }

    /* Collapsible description */
    .cita-descripcion {
        background: linear-gradient(135deg, #fff8f0 0%, #fff3e0 100%);
        padding: 8px 10px;
        border-radius: 8px;
        margin-bottom: 10px;
        border-left: 3px solid var(--primary);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .cita-descripcion:hover { background: #fff3e0; }

    .cita-descripcion-label {
        font-size: 0.62rem;
        color: #6c757d;
        margin: 0 0 2px 0;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .cita-descripcion-text {
        margin: 0;
        color: var(--dark);
        line-height: 1.4;
        font-size: 0.82rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cita-descripcion.expanded .cita-descripcion-text {
        white-space: normal;
    }

    /* Compact actions bar */
    .cita-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-wrap: wrap;
    }

    .btn-estado {
        padding: 6px 8px;
        border: none;
        border-radius: 6px;
        font-size: 0.68rem;
        font-weight: 700;
        transition: all 0.2s ease;
        cursor: pointer;
        text-transform: uppercase;
        letter-spacing: 0.2px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        white-space: nowrap;
        flex: 1;
        min-width: 0;
    }

    .btn-estado i { font-size: 0.75rem; flex-shrink: 0; }

    .btn-estado.activo { background: #6c757d; color: white; }
    .btn-estado.activo:hover { background: #5a6268; transform: translateY(-1px); }

    .btn-estado.confirmado { background: var(--success); color: white; }
    .btn-estado.confirmado:hover { background: #218838; transform: translateY(-1px); }

    .btn-estado.pospuesto { background: var(--warning); color: var(--dark); }
    .btn-estado.pospuesto:hover { background: #e0a800; transform: translateY(-1px); }

    .btn-estado.cancelado { background: var(--danger); color: white; }
    .btn-estado.cancelado:hover { background: #c82333; transform: translateY(-1px); }

    /* Action buttons */
    .cita-actions-main {
        display: flex;
        gap: 4px;
        margin-top: 6px;
    }

    .btn-accion {
        padding: 7px 10px;
        border: 2px solid var(--primary);
        background: white;
        color: var(--primary);
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.75rem;
        transition: all 0.2s ease;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        white-space: nowrap;
        flex: 1;
    }

    .btn-accion i { font-size: 0.85rem; flex-shrink: 0; }

    .btn-accion:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-1px);
    }

    .btn-eliminar {
        background: var(--danger);
        color: white;
        border: 2px solid var(--danger);
    }

    .btn-eliminar:hover {
        background: #c82333;
        border-color: #c82333;
        color: white;
    }

    /* Empty state */
    .citas-empty {
        text-align: center;
        padding: 50px 20px;
        color: #6c757d;
    }

    .citas-empty i {
        font-size: 3.5rem;
        color: var(--primary);
        margin-bottom: 15px;
        opacity: 0.4;
        animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .citas-empty p {
        font-size: 1rem;
        margin: 0;
        font-weight: 600;
    }

    /* ============================================
       RECORDATORIOS SIDE PANEL (Drawer)
       ============================================ */
    .recordatorios-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        backdrop-filter: blur(2px);
    }

    .recordatorios-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .recordatorios-drawer {
        position: fixed;
        top: 0;
        right: -440px;
        width: 420px;
        max-width: 95vw;
        height: 100%;
        background: linear-gradient(180deg, #128C7E 0%, #075E54 100%);
        z-index: 1050;
        transition: right 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: -4px 0 25px rgba(0, 0, 0, 0.25);
        display: flex;
        flex-direction: column;
    }

    .recordatorios-drawer.active {
        right: 0;
    }

    .drawer-header {
        padding: 16px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        flex-shrink: 0;
    }

    .drawer-title {
        color: white;
        font-size: 1.12rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .drawer-title i {
        font-size: 1.35rem;
    }

    .drawer-badge {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .drawer-close {
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.95rem;
    }

    .drawer-close:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: rotate(90deg);
    }

    /* Tabs inside Recordatorios Drawer */
    .drawer-tabs {
        display: flex;
        background: rgba(0, 0, 0, 0.18);
        padding: 5px;
        border-radius: 10px;
        margin: 12px 14px 0 14px;
        gap: 6px;
        flex-shrink: 0;
    }

    .drawer-tab {
        flex: 1;
        padding: 8px 10px;
        border: none;
        background: transparent;
        color: rgba(255, 255, 255, 0.8);
        font-weight: 600;
        font-size: 0.82rem;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        transition: all 0.2s ease;
    }

    .drawer-tab:hover {
        color: white;
        background: rgba(255, 255, 255, 0.1);
    }

    .drawer-tab.active {
        background: white;
        color: #075E54;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .drawer-tab-badge {
        padding: 1px 7px;
        border-radius: 10px;
        font-size: 0.72rem;
        font-weight: 700;
        background: rgba(0, 0, 0, 0.25);
        color: white;
    }

    .drawer-tab.active .drawer-tab-badge {
        background: #25D366;
        color: white;
    }

    .drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 14px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .drawer-body::-webkit-scrollbar { width: 5px; }
    .drawer-body::-webkit-scrollbar-track { background: transparent; }
    .drawer-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 3px; }

    .banner-bot-actions {
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 6px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .btn-bot-send-all {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 8px 12px;
        font-weight: 700;
        font-size: 0.82rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        transition: all 0.2s ease;
        text-decoration: none;
        width: 100%;
    }

    .btn-bot-send-all:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 12px rgba(37, 211, 102, 0.4);
        color: white;
    }

    .btn-bot-send-all:disabled {
        opacity: 0.65;
        cursor: not-allowed;
        transform: none;
    }

    .recordatorio-item {
        background: rgba(255, 255, 255, 0.12);
        border-radius: 10px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        backdrop-filter: blur(5px);
        transition: all 0.25s ease;
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .recordatorio-item:hover {
        background: rgba(255, 255, 255, 0.18);
        transform: translateX(2px);
    }

    .recordatorio-item.enviado {
        opacity: 0.95;
        background: rgba(37, 211, 102, 0.12);
        border: 1px solid rgba(37, 211, 102, 0.35);
    }

    .recordatorio-item.enviado:hover {
        background: rgba(37, 211, 102, 0.20);
        border-color: rgba(37, 211, 102, 0.55);
    }

    .badge-status-enviado {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.68rem;
        font-weight: 700;
        background: #25D366;
        color: #075E54;
        padding: 2px 7px;
        border-radius: 12px;
        margin-left: 6px;
        vertical-align: middle;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }

    .btn-bot-send-single.btn-reenviar {
        background: #e8f8f5;
        color: #075E54;
        border: 1px solid rgba(7, 94, 84, 0.35);
    }

    .btn-bot-send-single.btn-reenviar:hover {
        background: #d1f2eb;
        color: #054c44;
    }

    .recordatorio-info {
        flex: 1;
        min-width: 0;
    }

    .recordatorio-nombre {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .recordatorio-detalle {
        font-size: 0.76rem;
        opacity: 0.9;
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .recordatorio-detalle span {
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }

    .recordatorio-actions {
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-shrink: 0;
    }

    .btn-bot-send-single {
        background: white;
        color: #075E54;
        border: none;
        border-radius: 6px;
        padding: 4px 8px;
        font-weight: 700;
        font-size: 0.73rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        transition: all 0.2s ease;
        text-decoration: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }

    .btn-bot-send-single:hover {
        background: #e8f8f5;
        color: #128C7E;
        transform: scale(1.02);
    }

    .btn-wa-manual {
        background: rgba(255, 255, 255, 0.18);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 6px;
        padding: 3px 7px;
        font-weight: 600;
        font-size: 0.70rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 3px;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-wa-manual:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .recordatorios-empty {
        text-align: center;
        padding: 35px 15px;
        opacity: 0.85;
        color: white;
    }

    .recordatorios-empty i {
        font-size: 2.2rem;
        margin-bottom: 8px;
        display: block;
        opacity: 0.7;
    }

    /* Floating toggle button */
    .recordatorios-fab {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1030;
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: white;
        border: none;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        transition: all 0.3s ease;
        animation: fabPulse 3s ease-in-out infinite;
    }

    .recordatorios-fab:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(37, 211, 102, 0.5);
    }

    @keyframes fabPulse {
        0%, 100% { box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4); }
        50% { box-shadow: 0 4px 25px rgba(37, 211, 102, 0.6); }
    }

    .fab-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: var(--danger);
        color: white;
        font-size: 0.7rem;
        font-weight: 700;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid white;
        animation: badgeBounce 2s ease-in-out infinite;
    }

    @keyframes badgeBounce {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 768px) {
        .dashboard-container { padding: 10px; }

        .welcome-card {
            padding: 12px 14px;
            margin-bottom: 12px;
        }

        .welcome-card .user-info {
            font-size: 1.1rem;
        }

        .welcome-date { display: none; }

        /* Hide quick bar on mobile */
        .quick-bar { display: none !important; }

        .filters-row {
            padding: 10px 12px;
            margin-bottom: 12px;
            gap: 8px;
        }

        .filter-divider { display: none; }
        .filter-tag { font-size: 0.7rem; }

        .filter-btn {
            padding: 6px 10px;
            font-size: 0.75rem;
        }

        .chip {
            padding: 6px 10px;
            font-size: 0.72rem;
        }

        .citas-header {
            padding: 12px 14px;
        }

        .citas-header h3 { font-size: 1.05rem; }

        .citas-grid {
            grid-template-columns: 1fr;
            padding: 10px;
            gap: 10px;
            max-height: none;
        }

        .cita-info-row {
            grid-template-columns: repeat(2, 1fr);
        }

        .cita-whatsapp {
            width: 40px;
            height: 40px;
            font-size: 1.3rem;
        }

        .recordatorios-drawer {
            width: 100%;
            right: -100%;
        }

        .recordatorios-fab {
            bottom: 16px;
            right: 16px;
            width: 50px;
            height: 50px;
            font-size: 1.3rem;
        }
    }

    @media (max-width: 576px) {
        .cita-info-row {
            grid-template-columns: 1fr;
        }

        .cita-actions {
            flex-direction: column;
        }

        .cita-actions > .cita-estados-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            width: 100%;
        }

        .cita-actions-main {
            width: 100%;
        }

        .filters-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .filter-section { width: 100%; }
    }

    @media (min-width: 1200px) {
        .citas-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

<div class="dashboard-container">
    <!-- Welcome Header - Slim -->
    <div class="welcome-card">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span>Bienvenido, <?php echo htmlspecialchars($user['username']); ?></span>
        </div>
        <div class="welcome-right">
            <span class="welcome-date">
                <i class="fas fa-calendar-alt"></i>
                <?php echo date('l, d M Y'); ?>
            </span>
            <a href="logout.php" class="btn btn-light btn-sm" style="font-size: 0.82rem; border-radius: 8px;">
                <i class="fas fa-sign-out-alt me-1"></i> Salir
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert" style="border-radius: 10px; font-size: 0.9rem;">
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Filters - Inline Row -->
    <div class="filters-row">
        <!-- Period Filter -->
        <div class="filter-section">
            <span class="filter-tag"><i class="fas fa-clock"></i> Período</span>
            <a href="?filtro=hoy<?php echo $consultorio_filtro > 0 ? '&consultorio=' . $consultorio_filtro : ''; ?>" 
               class="filter-btn <?php echo $filtro === 'hoy' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i> Hoy
            </a>
            <a href="?filtro=manana<?php echo $consultorio_filtro > 0 ? '&consultorio=' . $consultorio_filtro : ''; ?>" 
               class="filter-btn <?php echo $filtro === 'manana' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-plus"></i> Mañana
            </a>
            <a href="?filtro=semana<?php echo $consultorio_filtro > 0 ? '&consultorio=' . $consultorio_filtro : ''; ?>" 
               class="filter-btn <?php echo $filtro === 'semana' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i> Semana
            </a>
        </div>

        <div class="filter-divider"></div>

        <!-- Consultorio Filter -->
        <div class="filter-section">
            <span class="filter-tag"><i class="fas fa-clinic-medical"></i> Consultorio</span>
            <a href="?filtro=<?php echo $filtro; ?>" 
               class="chip <?php echo $consultorio_filtro === 0 ? 'active' : ''; ?>"
               style="background: #e9ecef; color: #495057; <?php echo $consultorio_filtro === 0 ? 'border-color: #495057;' : ''; ?>">
                <i class="fas fa-th-large"></i> Todos
            </a>
            <?php foreach ($consultorios as $consultorio): ?>
                <a href="?filtro=<?php echo $filtro; ?>&consultorio=<?php echo $consultorio['id']; ?>" 
                   class="chip <?php echo $consultorio_filtro === (int)$consultorio['id'] ? 'active' : ''; ?>"
                   style="background: <?php echo htmlspecialchars($consultorio['color']); ?>; 
                          color: white;
                          <?php echo $consultorio_filtro === (int)$consultorio['id'] ? 'border-color: ' . htmlspecialchars($consultorio['color']) . ';' : ''; ?>">
                    <i class="fas fa-door-open"></i>
                    <?php echo htmlspecialchars($consultorio['nombre']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Citas Container -->
    <div class="citas-container">
        <div class="citas-header">
            <h3>
                <i class="fas <?php echo $iconoFiltro; ?>"></i>
                <?php echo $tituloFiltro; ?>
                <?php if ($consultorio_filtro > 0): ?>
                    <span style="font-size: 0.85rem; font-weight: 500;">
                        - <?php 
                            $cons = array_filter($consultorios, function($c) use ($consultorio_filtro) {
                                return $c['id'] == $consultorio_filtro;
                            });
                            echo htmlspecialchars(reset($cons)['nombre']);
                        ?>
                    </span>
                <?php endif; ?>
            </h3>
            <?php if (!empty($citas)): ?>
                <span class="citas-count"><?php echo count($citas); ?> cita<?php echo count($citas) > 1 ? 's' : ''; ?></span>
            <?php endif; ?>
        </div>

        <?php if (!empty($citas)): ?>
            <div class="citas-grid">
                <?php foreach ($citas as $cita): ?>
                <?php
                // Preparar el mensaje personalizado para WhatsApp
                $nombre_cliente = htmlspecialchars($cita['cliente_nombre']);
                $hora_cita = date('H:i', strtotime($cita['fecha']));
                $fecha_cita = date('d/m/Y', strtotime($cita['fecha']));
                
                $mensaje_whatsapp = "Hola *" . $cita['cliente_nombre'] . "*,\n\n";
                $mensaje_whatsapp .= "Te hablamos de la clínica *Dentality*. Te recordamos que tienes una cita programada:\n\n";
                if ($fecha_cita === date('d/m/Y')) {
                    $mensaje_whatsapp .= "Fecha: hoy\n";
                } elseif ($fecha_cita === date('d/m/Y', strtotime('+1 day'))) {
                    $mensaje_whatsapp .= "Fecha: mañana\n";
                } else {
                    $mensaje_whatsapp .= "Fecha: {$fecha_cita}\n";
                }
                $mensaje_whatsapp .= "Hora: {$hora_cita}\n";
                $mensaje_whatsapp .= "¿Me confirmas tu asistencia por favor? \n\n";
                $mensaje_whatsapp .= "Si necesitas reprogramar, déjanos saber. ";
                
                $mensaje_encoded = urlencode($mensaje_whatsapp);
                $whatsapp_link = "https://wa.me/" . htmlspecialchars($cita['telefono']) . "?text=" . $mensaje_encoded;
                $estado_actual = strtolower(trim($cita['estado'] ?? 'activo'));
                ?>
                
                <div class="cita-card" data-estado="<?php echo htmlspecialchars($estado_actual); ?>">
                    <div class="cita-header-card">
                        <h4 class="cita-cliente">
                            <i class="fas fa-user-circle"></i>
                            <span class="cita-cliente-text"><?php echo htmlspecialchars($cita['cliente_nombre']); ?></span>
                        </h4>
                        <a href="<?php echo $whatsapp_link; ?>" 
                        target="_blank" 
                        class="cita-whatsapp"
                        title="Enviar recordatorio por WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>

                    <div class="cita-body">
                        <div class="cita-info-row">
                            <div class="cita-info-item">
                                <i class="fas fa-calendar"></i>
                                <div class="cita-info-content">
                                    <p class="cita-info-label">Fecha</p>
                                    <p class="cita-info-value"><?php echo htmlspecialchars(date('d/m/Y', strtotime($cita['fecha']))); ?></p>
                                </div>
                            </div>

                            <div class="cita-info-item">
                                <i class="fas fa-clock"></i>
                                <div class="cita-info-content">
                                    <p class="cita-info-label">Horario</p>
                                    <p class="cita-info-value">
                                        <?php echo htmlspecialchars(date('H:i', strtotime($cita['fecha']))); ?> - 
                                        <?php echo htmlspecialchars(date('H:i', strtotime($cita['finDeCita']))); ?>
                                    </p>
                                </div>
                            </div>

                            <div class="cita-info-item">
                                <i class="fas fa-clinic-medical"></i>
                                <div class="cita-info-content">
                                    <p class="cita-info-label">Consultorio</p>
                                    <p class="cita-info-value"><?php echo htmlspecialchars($cita['consultorio_nombre']); ?></p>
                                </div>
                            </div>
                            
                            <div class="cita-info-item">
                                <i class="fas fa-user-md"></i>
                                <div class="cita-info-content">
                                    <p class="cita-info-label">Doctor</p>
                                    <p class="cita-info-value"><?php echo htmlspecialchars($cita['doctor_nombre']); ?></p>
                                </div>
                            </div>
                            
                            <div class="cita-info-item">
                                <i class="fas fa-circle" style="color: <?php 
                                    echo match($estado_actual) {
                                        'confirmado' => 'var(--success)',
                                        'pospuesto' => 'var(--warning)',
                                        'cancelado' => 'var(--danger)',
                                        default => '#6c757d'
                                    };
                                ?>; font-size: 0.7rem;"></i>
                                <div class="cita-info-content">
                                    <p class="cita-info-label">Estado</p>
                                    <p class="cita-info-value"><?php echo htmlspecialchars($cita['estado']); ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($cita['descripcion'])): ?>
                        <div class="cita-descripcion" onclick="this.classList.toggle('expanded')">
                            <p class="cita-descripcion-label">Descripción <i class="fas fa-chevron-down" style="font-size: 0.55rem; margin-left: 4px;"></i></p>
                            <p class="cita-descripcion-text"><?php echo htmlspecialchars($cita['descripcion']); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="cita-actions">
                            <div class="cita-estados-row" style="display: flex; gap: 4px; flex: 1;">
                                <button type="button" class="btn-estado activo btn-cambiar-estado" 
                                        data-id="<?php echo (int)$cita['id']; ?>" 
                                        data-estado="activo">
                                    <i class="fas fa-circle"></i>
                                    <span>Activo</span>
                                </button>
                                <button type="button" class="btn-estado confirmado btn-cambiar-estado" 
                                        data-id="<?php echo (int)$cita['id']; ?>" 
                                        data-estado="confirmado">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Confirmar</span>
                                </button>
                                <button type="button" class="btn-estado pospuesto btn-cambiar-estado" 
                                        data-id="<?php echo (int)$cita['id']; ?>" 
                                        data-estado="pospuesto">
                                    <i class="fas fa-clock"></i>
                                    <span>Posponer</span>
                                </button>
                                <button type="button" class="btn-estado cancelado btn-cambiar-estado" 
                                        data-id="<?php echo (int)$cita['id']; ?>" 
                                        data-estado="cancelado">
                                    <i class="fas fa-times-circle"></i>
                                    <span>Cancelar</span>
                                </button>
                            </div>
                            <div class="cita-actions-main">
                                <button type="button" class="btn-accion btn-editar-cita" 
                                        data-id="<?php echo (int)$cita['id']; ?>" 
                                        data-cliente="<?php echo htmlspecialchars((string)$cita['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-fecha="<?php echo date('Y-m-d', strtotime($cita['fecha'])); ?>"
                                        data-hora="<?php echo date('H:i', strtotime($cita['fecha'])); ?>"
                                        data-duracion="<?php echo (int)($cita['duracion_estimada'] ?? 30); ?>"
                                        data-consultorio="<?php echo (int)$cita['consultorio_id']; ?>"
                                        data-doctor="<?php echo (int)($cita['doctor_id'] ?? 0); ?>"
                                        data-desc="<?php echo htmlspecialchars((string)($cita['descripcion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-edit"></i>
                                    <span>Editar / Reprogramar</span>
                                </button>
                                <form method="POST" style="margin: 0; flex: 1;" 
                                    onsubmit="return confirm('¿Está seguro de eliminar esta cita?');">
                                    <input type="hidden" name="delete_id" value="<?php echo htmlspecialchars($cita['id']); ?>">
                                    <button type="submit" class="btn-accion btn-eliminar" style="width: 100%;">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Eliminar</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="citas-empty">
                <i class="fas fa-calendar-times"></i>
                <p>No hay citas programadas para este período</p>
                <?php if ($consultorio_filtro > 0): ?>
                    <p style="font-size: 0.9rem; margin-top: 8px; color: #999;">
                        <a href="?filtro=<?php echo $filtro; ?>" style="color: var(--primary); text-decoration: none; font-weight: 600;">
                            <i class="fas fa-undo"></i> Ver todas las citas
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Floating Action Button for Recordatorios -->
<?php if ($totalRecordatorios > 0): ?>
<button class="recordatorios-fab" id="recordatoriosFab" onclick="toggleRecordatoriosDrawer()" title="Recordatorios WhatsApp">
    <i class="fab fa-whatsapp"></i>
    <span class="fab-badge" id="fabBadge" style="<?php echo $totalPendientes === 0 ? 'background: #28a745;' : ''; ?>">
        <?php echo $totalPendientes > 0 ? $totalPendientes : '✓'; ?>
    </span>
</button>

<!-- Recordatorios Side Panel (Drawer) -->
<div class="recordatorios-overlay" id="recordatoriosOverlay" onclick="toggleRecordatoriosDrawer()"></div>
<div class="recordatorios-drawer" id="recordatoriosDrawer">
    <div class="drawer-header">
        <div class="drawer-title">
            <i class="fab fa-whatsapp"></i>
            <span>Recordatorios WhatsApp</span>
        </div>
        <span class="drawer-badge" id="drawerBadgeTotal">
            <span id="contadorTotalPendientes"><?php echo $totalPendientes; ?></span> pendientes
        </span>
        <button class="drawer-close" onclick="toggleRecordatoriosDrawer()" title="Cerrar panel">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Pestañas Mañana / Hoy -->
    <div class="drawer-tabs">
        <button type="button" class="drawer-tab active" id="tabBtnManana" onclick="switchRecordatoriosTab('manana')">
            <i class="fas fa-calendar-day"></i>
            <span>Mañana</span>
            <span class="drawer-tab-badge" id="badgeTabManana"><?php echo $pendientesManana > 0 ? $pendientesManana : ($totalManana > 0 ? '✓' : '0'); ?></span>
        </button>
        <button type="button" class="drawer-tab" id="tabBtnHoy" onclick="switchRecordatoriosTab('hoy')">
            <i class="fas fa-clock"></i>
            <span>Hoy</span>
            <span class="drawer-tab-badge" id="badgeTabHoy"><?php echo $pendientesHoy > 0 ? $pendientesHoy : ($totalHoy > 0 ? '✓' : '0'); ?></span>
        </button>
    </div>

    <!-- Contenedor Pestaña 1: Mañana -->
    <div class="drawer-body" id="tabContentManana">
        <div class="banner-bot-actions">
            <button type="button" class="btn-bot-send-all" id="btnEnviarTodosManana" 
                    onclick="enviarTodosRecordatoriosBot('<?php echo $fechaManana; ?>', 'manana')"
                    <?php echo $totalManana === 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-robot"></i>
                <span><?php echo $pendientesManana > 0 ? "Enviar Pendientes con el Bot ({$pendientesManana})" : ($totalManana > 0 ? "Reenviar a Todos con el Bot ({$totalManana})" : "Enviar Todos con el Bot (Mañana)"); ?></span>
            </button>
            <div style="font-size: 0.72rem; opacity: 0.85; text-align: center; color: white;">
                Envía recordatorios automáticos por WhatsApp con IA a las citas de mañana.
            </div>
        </div>

        <?php if ($totalManana > 0): ?>
            <?php foreach ($recordatoriosManana as $rec): ?>
            <?php
            $nombre_rec = htmlspecialchars($rec['cliente_nombre']);
            $hora_rec = date('H:i', strtotime($rec['fecha']));
            $cons_rec = htmlspecialchars($rec['consultorio_nombre']);
            $doc_rec = htmlspecialchars($rec['doctor_nombre']);
            $motivo_rec = htmlspecialchars($rec['descripcion'] ?? 'Consulta Odontológica');
            $tel_rec = htmlspecialchars($rec['telefono']);
            $estaEnviado = !empty($rec['recordatorio_enviado']);
            $fechaEnvioStr = !empty($rec['fecha_recordatorio']) ? date('d/m/Y H:i', strtotime($rec['fecha_recordatorio'])) : '';
            
            $msg_rec = "Hola *" . $rec['cliente_nombre'] . "*,\n\n";
            $msg_rec .= "Te hablamos de la clínica *Dentality*. Te recordamos que tienes una cita programada para *mañana*:\n\n";
            $msg_rec .= "Hora: {$hora_rec}\n";
            $msg_rec .= "Consultorio: {$cons_rec}\n";
            $msg_rec .= "¿Me confirmas tu asistencia por favor?\n\n";
            $msg_rec .= "Si necesitas reprogramar, déjanos saber. ";
            
            $msg_encoded_rec = urlencode($msg_rec);
            $wa_link_rec = "https://wa.me/" . $tel_rec . "?text=" . $msg_encoded_rec;
            ?>
            <div class="recordatorio-item <?php echo $estaEnviado ? 'enviado' : ''; ?>" id="recordatorio-<?php echo $rec['id']; ?>" data-tab="manana" data-enviado="<?php echo $estaEnviado ? '1' : '0'; ?>">
                <div class="recordatorio-info">
                    <div class="recordatorio-nombre">
                        <i class="fas fa-user-circle" style="margin-right: 4px; font-size: 0.85rem;"></i>
                        <span><?php echo $nombre_rec; ?></span>
                        <span class="badge-status-enviado" style="<?php echo !$estaEnviado ? 'display: none;' : ''; ?>" title="<?php echo $fechaEnvioStr ? 'Enviado: ' . $fechaEnvioStr : 'Enviado'; ?>">
                            <i class="fas fa-check-double"></i> Enviado
                        </span>
                    </div>
                    <div class="recordatorio-detalle">
                        <span><i class="fas fa-clock"></i> <?php echo $hora_rec; ?></span>
                        <span><i class="fas fa-clinic-medical"></i> <?php echo $cons_rec; ?></span>
                        <span><i class="fas fa-user-md"></i> <?php echo $doc_rec; ?></span>
                    </div>
                </div>
                <div class="recordatorio-actions">
                    <button type="button" class="btn-bot-send-single <?php echo $estaEnviado ? 'btn-reenviar' : ''; ?>" 
                            onclick="enviarRecordatorioIndividualBot(<?php echo $rec['id']; ?>, '<?php echo $tel_rec; ?>', '<?php echo addslashes($rec['cliente_nombre']); ?>', '<?php echo $hora_rec; ?>', 'mañana', '<?php echo addslashes($cons_rec); ?>', '<?php echo addslashes($doc_rec); ?>', '<?php echo addslashes($motivo_rec); ?>', <?php echo (int)$rec['cliente_id']; ?>, this)"
                            title="<?php echo $estaEnviado ? 'Reenviar con Bot de WhatsApp' : 'Enviar con Bot de WhatsApp'; ?>">
                        <i class="fas <?php echo $estaEnviado ? 'fa-redo' : 'fa-robot'; ?>"></i>
                        <span><?php echo $estaEnviado ? 'Reenviar' : 'Bot'; ?></span>
                    </button>
                    <a href="<?php echo $wa_link_rec; ?>" 
                       target="_blank" 
                       class="btn-wa-manual"
                       onclick="marcarRecordatorioEnviado(<?php echo $rec['id']; ?>, this)"
                       title="Enviar manual por WhatsApp Web">
                        <i class="fab fa-whatsapp"></i>
                        <span>WA Web</span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="recordatorios-empty" id="emptyManana">
                <i class="fas fa-calendar-times"></i>
                <p style="font-weight: 600; margin: 0;">No hay citas para mañana</p>
                <span style="font-size: 0.78rem; opacity: 0.8;">No se encontraron citas programadas para esta fecha.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Contenedor Pestaña 2: Hoy -->
    <div class="drawer-body" id="tabContentHoy" style="display: none;">
        <div class="banner-bot-actions">
            <button type="button" class="btn-bot-send-all" id="btnEnviarTodosHoy" 
                    onclick="enviarTodosRecordatoriosBot('<?php echo $fechaHoy; ?>', 'hoy')"
                    <?php echo $totalHoy === 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-robot"></i>
                <span><?php echo $pendientesHoy > 0 ? "Enviar Pendientes con el Bot ({$pendientesHoy})" : ($totalHoy > 0 ? "Reenviar a Todos con el Bot ({$totalHoy})" : "Enviar Todos con el Bot (Hoy)"); ?></span>
            </button>
            <div style="font-size: 0.72rem; opacity: 0.85; text-align: center; color: white;">
                Envía recordatorios inmediatos por WhatsApp con IA a las citas de hoy.
            </div>
        </div>

        <?php if ($totalHoy > 0): ?>
            <?php foreach ($recordatoriosHoy as $rec): ?>
            <?php
            $nombre_rec = htmlspecialchars($rec['cliente_nombre']);
            $hora_rec = date('H:i', strtotime($rec['fecha']));
            $cons_rec = htmlspecialchars($rec['consultorio_nombre']);
            $doc_rec = htmlspecialchars($rec['doctor_nombre']);
            $motivo_rec = htmlspecialchars($rec['descripcion'] ?? 'Consulta Odontológica');
            $tel_rec = htmlspecialchars($rec['telefono']);
            $estaEnviado = !empty($rec['recordatorio_enviado']);
            $fechaEnvioStr = !empty($rec['fecha_recordatorio']) ? date('d/m/Y H:i', strtotime($rec['fecha_recordatorio'])) : '';
            
            $msg_rec = "Hola *" . $rec['cliente_nombre'] . "*,\n\n";
            $msg_rec .= "Te hablamos de la clínica *Dentality*. Te recordamos que tienes una cita programada para *HOY*:\n\n";
            $msg_rec .= "Hora: {$hora_rec}\n";
            $msg_rec .= "Consultorio: {$cons_rec}\n";
            $msg_rec .= "¿Me confirmas tu asistencia por favor?\n\n";
            $msg_rec .= "Si necesitas reprogramar, déjanos saber. ";
            
            $msg_encoded_rec = urlencode($msg_rec);
            $wa_link_rec = "https://wa.me/" . $tel_rec . "?text=" . $msg_encoded_rec;
            ?>
            <div class="recordatorio-item <?php echo $estaEnviado ? 'enviado' : ''; ?>" id="recordatorio-<?php echo $rec['id']; ?>" data-tab="hoy" data-enviado="<?php echo $estaEnviado ? '1' : '0'; ?>">
                <div class="recordatorio-info">
                    <div class="recordatorio-nombre">
                        <i class="fas fa-user-circle" style="margin-right: 4px; font-size: 0.85rem;"></i>
                        <span><?php echo $nombre_rec; ?></span>
                        <span class="badge-status-enviado" style="<?php echo !$estaEnviado ? 'display: none;' : ''; ?>" title="<?php echo $fechaEnvioStr ? 'Enviado: ' . $fechaEnvioStr : 'Enviado'; ?>">
                            <i class="fas fa-check-double"></i> Enviado
                        </span>
                    </div>
                    <div class="recordatorio-detalle">
                        <span><i class="fas fa-clock"></i> <?php echo $hora_rec; ?></span>
                        <span><i class="fas fa-clinic-medical"></i> <?php echo $cons_rec; ?></span>
                        <span><i class="fas fa-user-md"></i> <?php echo $doc_rec; ?></span>
                    </div>
                </div>
                <div class="recordatorio-actions">
                    <button type="button" class="btn-bot-send-single <?php echo $estaEnviado ? 'btn-reenviar' : ''; ?>" 
                            onclick="enviarRecordatorioIndividualBot(<?php echo $rec['id']; ?>, '<?php echo $tel_rec; ?>', '<?php echo addslashes($rec['cliente_nombre']); ?>', '<?php echo $hora_rec; ?>', 'HOY', '<?php echo addslashes($cons_rec); ?>', '<?php echo addslashes($doc_rec); ?>', '<?php echo addslashes($motivo_rec); ?>', <?php echo (int)$rec['cliente_id']; ?>, this)"
                            title="<?php echo $estaEnviado ? 'Reenviar con Bot de WhatsApp' : 'Enviar con Bot de WhatsApp'; ?>">
                        <i class="fas <?php echo $estaEnviado ? 'fa-redo' : 'fa-robot'; ?>"></i>
                        <span><?php echo $estaEnviado ? 'Reenviar' : 'Bot'; ?></span>
                    </button>
                    <a href="<?php echo $wa_link_rec; ?>" 
                       target="_blank" 
                       class="btn-wa-manual"
                       onclick="marcarRecordatorioEnviado(<?php echo $rec['id']; ?>, this)"
                       title="Enviar manual por WhatsApp Web">
                        <i class="fab fa-whatsapp"></i>
                        <span>WA Web</span>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="recordatorios-empty" id="emptyHoy">
                <i class="fas fa-calendar-times"></i>
                <p style="font-weight: 600; margin: 0;">No hay citas para hoy</p>
                <span style="font-size: 0.78rem; opacity: 0.8;">No se encontraron citas programadas para esta fecha.</span>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Modal para editar y reprogramar cita completa -->
<div class="modal fade" id="editCitaModalDash" tabindex="-1" aria-labelledby="editCitaLabelDash" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px; overflow: hidden; border: none; box-shadow: var(--shadow-hover);">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 18px 22px;">
                <h5 class="modal-title" id="editCitaLabelDash" style="font-weight: 700; font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-edit"></i>
                    Editar / Reprogramar Cita
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="update_appointment.php" method="POST" id="formEditCitaDash">
                <div class="modal-body" style="padding: 22px;">
                    <input type="hidden" name="id" id="edit_cita_id">
                    <input type="hidden" name="redirect" value="dashboard.php">
                    <div id="edit_dash_error_alert" class="alert alert-danger d-none mb-3" role="alert" style="border-radius: 8px; font-weight: 600; font-size: 0.9rem;"></div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-user me-1" style="color: var(--primary);"></i> Paciente
                        </label>
                        <input type="text" id="edit_cliente_nombre" class="form-control" readonly style="background-color: #f8f9fa; font-weight: 700; color: #212529;">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_fecha" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-calendar-alt me-1" style="color: var(--primary);"></i> Fecha de la Cita
                            </label>
                            <input type="date" name="fecha" id="edit_fecha" class="form-control" required style="border-radius: 8px;">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_hora" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clock me-1" style="color: var(--primary);"></i> Hora de Inicio
                            </label>
                            <input type="time" name="hora" id="edit_hora" class="form-control" required style="border-radius: 8px;">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_doctor_id" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-user-md me-1" style="color: var(--primary);"></i> Doctor Asignado
                            </label>
                            <select name="doctor_id" id="edit_doctor_id" class="form-select" style="border-radius: 8px;">
                                <option value="0">Sin asignar</option>
                                <?php foreach ($doctores as $doc): ?>
                                    <option value="<?php echo $doc['id']; ?>"><?php echo htmlspecialchars($doc['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_consultorio_id" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                                <i class="fas fa-clinic-medical me-1" style="color: var(--primary);"></i> Consultorio
                            </label>
                            <select name="consultorio_id" id="edit_consultorio_id" class="form-select" required style="border-radius: 8px;">
                                <?php foreach ($consultorios as $cons): ?>
                                    <option value="<?php echo $cons['id']; ?>"><?php echo htmlspecialchars($cons['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_duracion" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-stopwatch me-1" style="color: var(--primary);"></i> Duración Estimada
                        </label>
                        <select name="duracion_estimada" id="edit_duracion" class="form-select" required style="border-radius: 8px;">
                            <option value="15">15 min</option>
                            <option value="30">30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1 hora</option>
                            <option value="90">1 hora y media</option>
                            <option value="120">2 horas</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_descripcion" class="form-label" style="font-weight: 600; font-size: 0.88rem; color: #495057;">
                            <i class="fas fa-notes-medical me-1" style="color: var(--primary);"></i> Descripción / Notas del Tratamiento
                        </label>
                        <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="3" placeholder="Detalles de la cita..." style="border-radius: 8px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8f9fa; padding: 14px 22px;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--primary); border: none; border-radius: 8px; padding: 8px 20px; font-weight: 600;">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ============================================
// Recordatorios Drawer & Bot Functions
// ============================================
function getBotApiUrl() {
    var custom = localStorage.getItem('dentality_bot_url') || localStorage.getItem('tatianaruiz_bot_url');
    if (custom && custom.trim() !== '') return custom.trim().replace(/\/$/, '') + '/api';
    if (window.location.protocol === 'https:') {
        return 'https://dentality-bot.onrender.com/api';
    }
    return 'http://localhost:3001/api';
}

function toggleRecordatoriosDrawer() {
    var drawer = document.getElementById('recordatoriosDrawer');
    var overlay = document.getElementById('recordatoriosOverlay');
    if (!drawer || !overlay) return;
    
    var isActive = drawer.classList.contains('active');
    if (isActive) {
        drawer.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    } else {
        drawer.classList.add('active');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Close drawer with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var drawer = document.getElementById('recordatoriosDrawer');
        if (drawer && drawer.classList.contains('active')) {
            toggleRecordatoriosDrawer();
        }
    }
});

function switchRecordatoriosTab(tab) {
    var tabManana = document.getElementById('tabBtnManana');
    var tabHoy = document.getElementById('tabBtnHoy');
    var contentManana = document.getElementById('tabContentManana');
    var contentHoy = document.getElementById('tabContentHoy');
    
    if (tab === 'manana') {
        if (tabManana) tabManana.classList.add('active');
        if (tabHoy) tabHoy.classList.remove('active');
        if (contentManana) contentManana.style.display = 'flex';
        if (contentHoy) contentHoy.style.display = 'none';
    } else {
        if (tabHoy) tabHoy.classList.add('active');
        if (tabManana) tabManana.classList.remove('active');
        if (contentHoy) contentHoy.style.display = 'flex';
        if (contentManana) contentManana.style.display = 'none';
    }
}

function actualizarContadoresRecordatorios() {
    var itemsManana = document.querySelectorAll('#tabContentManana .recordatorio-item:not(.enviado)');
    var itemsHoy = document.querySelectorAll('#tabContentHoy .recordatorio-item:not(.enviado)');
    var totalMananaItems = document.querySelectorAll('#tabContentManana .recordatorio-item').length;
    var totalHoyItems = document.querySelectorAll('#tabContentHoy .recordatorio-item').length;
    
    var countManana = itemsManana.length;
    var countHoy = itemsHoy.length;
    var totalPendientes = countManana + countHoy;
    var totalCitas = totalMananaItems + totalHoyItems;
    
    var badgeManana = document.getElementById('badgeTabManana');
    var badgeHoy = document.getElementById('badgeTabHoy');
    var badgeTotal = document.getElementById('contadorTotalPendientes');
    var fabBadge = document.getElementById('fabBadge');
    var fab = document.getElementById('recordatoriosFab');
    var btnTodosManana = document.getElementById('btnEnviarTodosManana');
    var btnTodosHoy = document.getElementById('btnEnviarTodosHoy');
    
    if (badgeManana) badgeManana.textContent = countManana > 0 ? countManana : (totalMananaItems > 0 ? '✓' : '0');
    if (badgeHoy) badgeHoy.textContent = countHoy > 0 ? countHoy : (totalHoyItems > 0 ? '✓' : '0');
    if (badgeTotal) badgeTotal.textContent = totalPendientes;
    if (fabBadge) {
        fabBadge.textContent = totalPendientes > 0 ? totalPendientes : (totalCitas > 0 ? '✓' : '0');
        if (totalPendientes === 0) {
            fabBadge.style.background = '#28a745';
        } else {
            fabBadge.style.background = '#dc3545';
        }
    }
    
    if (btnTodosManana) {
        var spanM = btnTodosManana.querySelector('span');
        if (countManana > 0) {
            btnTodosManana.disabled = false;
            if (spanM) spanM.textContent = 'Enviar Pendientes con el Bot (' + countManana + ')';
        } else if (totalMananaItems > 0) {
            btnTodosManana.disabled = false;
            if (spanM) spanM.textContent = 'Reenviar a Todos con el Bot (' + totalMananaItems + ')';
        } else {
            btnTodosManana.disabled = true;
            if (spanM) spanM.textContent = 'Enviar Todos con el Bot (Mañana)';
        }
    }
    
    if (btnTodosHoy) {
        var spanH = btnTodosHoy.querySelector('span');
        if (countHoy > 0) {
            btnTodosHoy.disabled = false;
            if (spanH) spanH.textContent = 'Enviar Pendientes con el Bot (' + countHoy + ')';
        } else if (totalHoyItems > 0) {
            btnTodosHoy.disabled = false;
            if (spanH) spanH.textContent = 'Reenviar a Todos con el Bot (' + totalHoyItems + ')';
        } else {
            btnTodosHoy.disabled = true;
            if (spanH) spanH.textContent = 'Enviar Todos con el Bot (Hoy)';
        }
    }
    
    if (fab) {
        fab.style.display = totalCitas > 0 ? 'flex' : 'none';
    }
}

function marcarRecordatorioEnviadoVisualmente(id) {
    var item = document.getElementById('recordatorio-' + id);
    if (item) {
        item.classList.add('enviado');
        item.setAttribute('data-enviado', '1');
        
        var badge = item.querySelector('.badge-status-enviado');
        if (badge) {
            badge.style.display = 'inline-flex';
        } else {
            var nombreDiv = item.querySelector('.recordatorio-nombre');
            if (nombreDiv) {
                var span = document.createElement('span');
                span.className = 'badge-status-enviado';
                span.innerHTML = '<i class="fas fa-check-double"></i> Enviado';
                nombreDiv.appendChild(span);
            }
        }
        
        var botBtn = item.querySelector('.btn-bot-send-single');
        if (botBtn) {
            botBtn.classList.add('btn-reenviar');
            botBtn.disabled = false;
            botBtn.innerHTML = '<i class="fas fa-redo"></i> <span>Reenviar</span>';
            botBtn.title = 'Reenviar recordatorio con el Bot';
        }
    }
}

function marcarRecordatorioEnviado(id, element) {
    marcarRecordatorioEnviadoVisualmente(id);
    actualizarContadoresRecordatorios();
    
    fetch('marcar_recordatorio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id })
    }).catch(function(error) {
        console.error('Error al marcar recordatorio en BD:', error);
    });
}

async function enviarRecordatorioIndividualBot(citaId, telefono, pacienteNombre, hora, fechaLabel, consultorio, doctor, motivo, clienteId, btnElement) {
    if (!btnElement) return;
    
    var originalHtml = btnElement.innerHTML;
    btnElement.disabled = true;
    btnElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Enviando...</span>';
    
    var mensaje = '🦷 *RECORDATORIO DE CITA ODONTOLÓGICA - Dentality* ✨\n\n' +
                  '¡Hola *' + pacienteNombre + '*! 👋\n' +
                  'Te recordamos cordialmente que tienes una cita programada para el día *' + fechaLabel.toUpperCase() + '*:\n\n' +
                  '⏰ *Hora:* ' + hora + '\n' +
                  '🏥 *Consultorio:* ' + consultorio + '\n' +
                  '🩺 *Doctor/a:* ' + doctor + '\n' +
                  '📝 *Motivo / Tratamiento:* ' + motivo + '\n' +
                  '📍 *Ubicación:* Clínica Dentality, Av. Antezana 847 Edificio Torre Atlanta piso 6 oficina 4 , Cochabamba, Bolivia.\n\n' +
                  'Por favor, ayúdanos respondiendo a este mensaje:\n' +
                  '👉 Escribe *"Confirmo"* para confirmar tu asistencia.\n' +
                  '👉 O avísanos si necesitas *"Reprogramar"* tu horario.\n\n' +
                  '¡Te esperamos con gusto para cuidar de tu sonrisa! 🦷✨';
    
    try {
        var botUrl = getBotApiUrl();
        var res = await fetch(botUrl + '/send-reminder-single', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                citaId: citaId,
                telefono: telefono,
                mensaje: mensaje,
                clienteId: clienteId,
                pacienteNombre: pacienteNombre
            })
        });
        
        var data;
        var text = await res.text();
        try {
            data = JSON.parse(text);
        } catch (parseErr) {
            // Si /send-reminder-single aún no está activo, intentar fallback a /send-message
            try {
                var fallbackRes = await fetch(botUrl + '/send-message', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ telefono: telefono, mensaje: mensaje })
                });
                var fallbackText = await fallbackRes.text();
                data = JSON.parse(fallbackText);
            } catch (fbErr) {
                throw new Error('El microservicio de WhatsApp no respondió con JSON válido. Código: ' + res.status);
            }
        }
        
        if (data && (data.exito || data.ok)) {
            marcarRecordatorioEnviadoVisualmente(citaId);
            actualizarContadoresRecordatorios();
            // Asegurar persistencia en BD local
            fetch('marcar_recordatorio.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: citaId })
            }).catch(function(e){ console.warn('Error al guardar recordatorio en BD:', e); });
        } else {
            var errMsg = (data && data.error) ? data.error : 'Verifica que el bot de WhatsApp esté conectado escaneando el código QR.';
            alert('⚠️ No se pudo enviar por el bot: ' + errMsg);
            btnElement.disabled = false;
            btnElement.innerHTML = originalHtml;
        }
    } catch (err) {
        alert('❌ Error de conexión con el Bot de WhatsApp: ' + err.message + '\nVerifica que el bot esté activo.');
        btnElement.disabled = false;
        btnElement.innerHTML = originalHtml;
    }
}

async function enviarTodosRecordatoriosBot(fecha, tabType) {
    var labelFecha = tabType === 'manana' ? 'MAÑANA' : 'HOY';
    var containerId = tabType === 'manana' ? '#tabContentManana' : '#tabContentHoy';
    
    // Primero buscar pendientes
    var items = document.querySelectorAll(containerId + ' .recordatorio-item:not(.enviado)');
    var esReenvio = false;
    
    if (items.length === 0) {
        // Si no hay pendientes, seleccionar todos para reenvío
        items = document.querySelectorAll(containerId + ' .recordatorio-item');
        esReenvio = true;
    }
    
    if (items.length === 0) {
        alert('No hay citas registradas para enviar en ' + labelFecha + '.');
        return;
    }
    
    var promptMsg = esReenvio 
        ? '¿Deseas REENVIAR los recordatorios por WhatsApp con el Bot a las (' + items.length + ') citas de ' + labelFecha + '?' 
        : '¿Deseas enviar recordatorios automáticos por WhatsApp con el Bot a las (' + items.length + ') citas pendientes de ' + labelFecha + '?';
        
    if (!confirm(promptMsg)) return;
    
    var btn = tabType === 'manana' ? document.getElementById('btnEnviarTodosManana') : document.getElementById('btnEnviarTodosHoy');
    var originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Enviando con el Bot...</span>';
    }
    
    for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var singleBtn = item.querySelector('.btn-bot-send-single');
        if (singleBtn) {
            singleBtn.click();
            await new Promise(function(resolve) { setTimeout(resolve, 1500); });
        }
    }
    
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
    actualizarContadoresRecordatorios();
}

// ============================================
// Citas Functions
// ============================================
(function(){
    function actualizarEstadoCita(id, estado) {
        return fetch('update_estado_cita.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, estado: estado })
        }).then(r => r.json());
    }

    function actualizarDescripcionCita(id, descripcion) {
        return fetch('update_descripcion_cita.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, descripcion: descripcion })
        }).then(r => r.json());
    }

    // Cambiar estado
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.btn-cambiar-estado');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var estado = btn.getAttribute('data-estado');
        var originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        actualizarEstadoCita(id, estado).then(function(resp){
            if (resp && resp.success) { 
                location.reload(); 
            } else { 
                alert((resp && resp.message) || 'No se pudo actualizar el estado'); 
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }).catch(function(){ 
            btn.disabled = false;
            btn.innerHTML = originalHTML;
        });
    });

    function setSelectValue(selectEl, targetVal) {
        if (!selectEl) return;
        var valStr = String(targetVal);
        if (!selectEl.options) {
            selectEl.value = valStr;
            return;
        }
        for (var i = 0; i < selectEl.options.length; i++) {
            if (String(selectEl.options[i].value) === valStr) {
                selectEl.options[i].selected = true;
                selectEl.selectedIndex = i;
                break;
            }
        }
        selectEl.value = valStr;
        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // Abrir modal de edición y reprogramación de cita
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.btn-editar-cita, .btn-editar-desc');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        var cliente = btn.getAttribute('data-cliente') || '';
        var fecha = btn.getAttribute('data-fecha') || '';
        var hora = btn.getAttribute('data-hora') || '';
        var duracion = btn.getAttribute('data-duracion') || '30';
        var consultorio = btn.getAttribute('data-consultorio') || '1';
        var doctor = btn.getAttribute('data-doctor') || '0';
        var desc = btn.getAttribute('data-desc') || '';

        var idEl = document.getElementById('edit_cita_id');
        if (idEl) idEl.value = id;
        var cliEl = document.getElementById('edit_cliente_nombre');
        if (cliEl) cliEl.value = cliente;
        var fecEl = document.getElementById('edit_fecha');
        if (fecEl) fecEl.value = fecha;
        var horEl = document.getElementById('edit_hora');
        if (horEl) horEl.value = hora;
        setSelectValue(document.getElementById('edit_duracion'), duracion);
        setSelectValue(document.getElementById('edit_consultorio_id'), consultorio);
        setSelectValue(document.getElementById('edit_doctor_id'), doctor);
        
        var desEl = document.getElementById('edit_descripcion');
        if (desEl) desEl.value = desc;

        var alertBox = document.getElementById('edit_dash_error_alert');
        if (alertBox) alertBox.classList.add('d-none');

        var modalEl = document.getElementById('editCitaModalDash');
        if (modalEl) {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    });

    // Interceptar envío de edición para alertas instantáneas en el modal
    var formEditDash = document.getElementById('formEditCitaDash');
    if (formEditDash) {
        formEditDash.addEventListener('submit', function(e) {
            e.preventDefault();
            var alertBox = document.getElementById('edit_dash_error_alert');
            if (alertBox) alertBox.classList.add('d-none');

            var formData = new FormData(this);
            fetch('update_appointment.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.success) {
                    location.reload();
                } else {
                    if (alertBox) {
                        alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>' + (data.message || 'Error al guardar los cambios.');
                        alertBox.classList.remove('d-none');
                    } else {
                        alert(data.message || 'Error al guardar los cambios.');
                    }
                }
            })
            .catch(function() {
                if (alertBox) {
                    alertBox.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error de conexión. Intente nuevamente.';
                    alertBox.classList.remove('d-none');
                }
            });
        });
    }
})();
</script>

</body>
</html>
