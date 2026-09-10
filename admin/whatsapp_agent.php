<?php
ob_start();
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Doctor.php';
require_once '../src/models/Client.php';
require_once '../src/models/Appointment.php';

// Endpoint AJAX para generar la agenda del doctor
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_doctor_agenda') {
    header('Content-Type: application/json; charset=utf-8');
    $doctorId = (int)($_GET['doctor_id'] ?? 0);
    $fecha = trim($_GET['fecha'] ?? date('Y-m-d'));
    
    $doctorModel = new Doctor($pdo);
    $doc = $doctorModel->getById($doctorId);
    
    if (!$doc) {
        echo json_encode(['ok' => false, 'error' => 'Doctor no encontrado']);
        exit();
    }
    
    $stmtCitas = $pdo->prepare("
        SELECT c.id, c.fecha, c.finDeCita, c.descripcion, cl.nombre AS paciente_nombre, cl.telefono AS paciente_telefono, cons.nombre AS consultorio_nombre
        FROM citas c
        JOIN clientes cl ON c.cliente_id = cl.id
        JOIN consultorios cons ON c.consultorio_id = cons.id
        WHERE c.doctor_id = ? AND DATE(c.fecha) = ? AND (c.estado IS NULL OR c.estado IN ('activo', 'confirmado', 'pospuesto'))
        ORDER BY c.fecha ASC
    ");
    $stmtCitas->execute([$doctorId, $fecha]);
    $citas = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);
    
    $dt = new DateTime($fecha);
    $dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
    $meses = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
    $diaSemana = $dias[$dt->format('l')] ?? $dt->format('l');
    $diaNum = $dt->format('d');
    $mesNom = $meses[$dt->format('m')] ?? $dt->format('m');
    $fechaTexto = "{$diaSemana} {$diaNum} de {$mesNom}";
    
    $msg = "📋 *AGENDA DE PACIENTES - {$doc['nombre']}* 🩺\n";
    $msg .= "📅 *Fecha:* {$fechaTexto}\n";
    $msg .= "🏥 *Dentality - Gestión Odontológica*\n\n";
    
    if (empty($citas)) {
        $msg .= "✨ _No tienes pacientes agendados para esta fecha._";
    } else {
        $msg .= "Tienes *" . count($citas) . " paciente(s)* programado(s):\n\n";
        foreach ($citas as $i => $c) {
            $h = date('H:i', strtotime($c['fecha']));
            $p = $c['paciente_nombre'];
            $motivo = !empty($c['descripcion']) ? $c['descripcion'] : 'Consulta Odontológica';
            $cons = $c['consultorio_nombre'];
            $idx = $i + 1;
            $msg .= "{$idx}. ⏰ *{$h}* — 👤 *{$p}* ({$cons})\n   📝 _Motivo:_ {$motivo}\n\n";
        }
        $msg .= "¡Que tengas una excelente jornada clínica! 🦷✨";
    }
    
    echo json_encode([
        'ok' => true,
        'doctor' => $doc,
        'fecha_texto' => $fechaTexto,
        'total_citas' => count($citas),
        'mensaje' => $msg,
        'telefono' => $doc['telefono'] ?? ''
    ]);
    exit();
}

$doctorModel = new Doctor($pdo);
$doctoresActivos = $doctorModel->getActivos();

$stmtClientes = $pdo->query("SELECT id, nombre, telefono FROM clientes WHERE telefono IS NOT NULL AND TRIM(telefono) != '' ORDER BY nombre ASC");
$clientesConTel = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);

// Métricas de hoy
$hoyStr = date('Y-m-d');
$stmtCitasHoy = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE DATE(fecha) = ? AND (estado IN ('activo','confirmado') OR estado IS NULL)");
$stmtCitasHoy->execute([$hoyStr]);
$totalCitasHoy = (int)$stmtCitasHoy->fetchColumn();

$stmtConfirmadasHoy = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE DATE(fecha) = ? AND estado = 'confirmado'");
$stmtConfirmadasHoy->execute([$hoyStr]);
$totalConfirmadasHoy = (int)$stmtConfirmadasHoy->fetchColumn();

$appointmentModel = new Appointment($pdo);
$recordatoriosManana = $appointmentModel->getCitasPendientesRecordatorio(date('Y-m-d', strtotime('+1 day')));
$totalRecordatoriosPendientes = count($recordatoriosManana);

require_once '../templates/header_general.php';
?>

<style>
    :root {
        --primary: #003B73;
        --primary-dark: #062846;
        --primary-gradient: linear-gradient(135deg, #003B73 0%, #2998EC 100%);
        --accent-teal: #2998EC;
        --accent-green: #10B981;
        --whatsapp-green: #25D366;
        --whatsapp-dark: #128C7E;
        --danger: #EF4444;
        --warning: #F59E0B;
        --bg-main: #F4F9FD;
        --border-color: #E2E8F0;
        --text-dark: #0F172A;
        --text-muted: #64748B;
        --radius-lg: 14px;
        --radius-md: 10px;
        --shadow-md: 0 4px 12px rgba(0, 59, 115, 0.08);
        --shadow-lg: 0 10px 25px rgba(0, 59, 115, 0.12);
    }

    .main-container {
        padding: 20px 24px;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
        box-sizing: border-box;
    }

    /* Header Banner */
    .header-card {
        background: var(--primary-gradient);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
        gap: 16px;
        flex-wrap: wrap;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 16px;
        flex: 1 1 300px;
        min-width: 0;
    }

    .header-icon-box {
        width: 52px;
        height: 52px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.7rem;
        backdrop-filter: blur(8px);
        flex-shrink: 0;
    }

    .header-title-box {
        min-width: 0;
    }

    .header-title-box h2 {
        font-size: 1.35rem;
        font-weight: 800;
        margin: 0 0 4px 0;
        letter-spacing: -0.3px;
        line-height: 1.25;
        word-break: break-word;
    }

    .header-title-box p {
        margin: 0;
        font-size: 0.86rem;
        opacity: 0.9;
        line-height: 1.3;
    }

    .header-actions-wrap {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .bot-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.82rem;
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(6px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        white-space: nowrap;
    }

    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #94A3B8;
        flex-shrink: 0;
    }

    .status-dot.connected { background: #22C55E; box-shadow: 0 0 8px #22C55E; }
    .status-dot.qr_ready { background: #F59E0B; box-shadow: 0 0 8px #F59E0B; }
    .status-dot.disconnected { background: #EF4444; }

    /* Métricas Rápidas */
    .metric-cards-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 20px;
    }

    .metric-box {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
        min-width: 0;
    }

    .metric-icon {
        width: 46px;
        height: 46px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .metric-info {
        min-width: 0;
    }

    .metric-info .label {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        display: block;
        margin-bottom: 2px;
        line-height: 1.2;
        word-break: break-word;
    }

    .metric-info .value {
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-dark);
        line-height: 1.2;
    }

    /* Grid Layout */
    .agent-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    /* Cards */
    .dashboard-card {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-md);
        padding: 22px;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .card-title-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
        gap: 8px;
        flex-wrap: wrap;
    }

    .card-title-box h3 {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
        line-height: 1.3;
    }

    /* QR Code Container */
    .qr-display-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: #F8FAFC;
        border-radius: var(--radius-md);
        border: 2px dashed var(--border-color);
        min-height: 220px;
        text-align: center;
        box-sizing: border-box;
        width: 100%;
    }

    .qr-img {
        max-width: 180px;
        width: 100%;
        height: auto;
        aspect-ratio: 1;
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-md);
        background: white;
        padding: 8px;
        box-sizing: border-box;
    }

    .connected-state-box {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 14px;
        text-align: center;
    }

    .connected-icon-badge {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: rgba(37, 211, 102, 0.15);
        color: var(--whatsapp-green);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
    }

    .btn-danger-outline {
        background: transparent;
        color: var(--danger);
        border: 1px solid var(--danger);
        border-radius: 8px;
        padding: 6px 12px;
        font-weight: 700;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-danger-outline:hover {
        background: var(--danger);
        color: white;
    }

    /* Form Controls Clínicos */
    .form-label-custom {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 4px;
        display: block;
    }

    .btn-custom-action {
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.86rem;
        padding: 10px 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s ease;
        border: none;
        white-space: normal;
        text-align: center;
        line-height: 1.3;
    }

    .btn-send-bot {
        background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        color: white;
        box-shadow: 0 2px 6px rgba(37, 211, 102, 0.3);
    }
    .btn-send-bot:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(37, 211, 102, 0.4);
        color: white;
    }

    /* Select2 personalizado estilo Dra. Tatiana Ruiz */
    .select2-container {
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }
    .select2-container .select2-selection--single {
        height: 38px !important;
        border: 1px solid var(--border-color) !important;
        border-radius: 8px !important;
        padding: 4px 8px !important;
        display: flex !important;
        align-items: center !important;
        background-color: #fff !important;
        width: 100% !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
        right: 8px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--text-dark) !important;
        font-size: 0.85rem !important;
        font-weight: 500 !important;
        padding-left: 2px !important;
        padding-right: 20px !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        white-space: nowrap !important;
    }
    .select2-dropdown {
        border: 1px solid var(--border-color) !important;
        border-radius: 8px !important;
        box-shadow: 0 10px 25px rgba(15, 76, 110, 0.15) !important;
        font-size: 0.85rem !important;
        z-index: 9999 !important;
        max-width: 100% !important;
    }
    .select2-search--dropdown {
        padding: 8px !important;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid var(--border-color) !important;
        border-radius: 6px !important;
        padding: 6px 10px !important;
        font-size: 0.84rem !important;
        outline: none !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--primary) !important;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: var(--primary) !important;
        color: white !important;
    }

    /* Badge de Chat Pausado */
    .paused-chat-item {
        background: #FEF3C7;
        border: 1px solid #FDE68A;
        border-radius: 8px;
        padding: 8px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        font-size: 0.82rem;
        margin-bottom: 6px;
        flex-wrap: wrap;
    }

    /* Input Group Responsive */
    .input-group-responsive {
        display: flex;
        width: 100%;
    }

    /* =========================================================================
       REGLAS RESPONSIVAS MÓVILES (TABLETS Y SMARTPHONES)
       ========================================================================= */
    @media (max-width: 992px) {
        .metric-cards-row {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        .agent-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
    }

    @media (max-width: 768px) {
        .main-container {
            padding: 12px 10px;
        }
        .header-card {
            padding: 16px;
            flex-direction: column;
            align-items: stretch;
            gap: 14px;
        }
        .header-left {
            gap: 12px;
        }
        .header-icon-box {
            width: 44px;
            height: 44px;
            font-size: 1.4rem;
        }
        .header-title-box h2 {
            font-size: 1.15rem;
        }
        .header-actions-wrap {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .header-actions-wrap > * {
            width: 100%;
            text-align: center;
            justify-content: center;
        }
        .dashboard-card {
            padding: 16px 14px;
            border-radius: 12px;
        }
    }

    @media (max-width: 576px) {
        .metric-cards-row {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 14px;
        }
        .metric-box {
            padding: 10px 8px;
            gap: 8px;
            border-radius: 8px;
        }
        .metric-icon {
            width: 36px;
            height: 36px;
            font-size: 1.1rem;
            border-radius: 8px;
        }
        .metric-info .label {
            font-size: 0.65rem;
            line-height: 1.15;
        }
        .metric-info .value {
            font-size: 1.1rem;
        }
        .card-title-box h3 {
            font-size: 0.95rem;
        }
        .input-group-responsive {
            flex-direction: column;
            gap: 8px;
        }
        .input-group-responsive > * {
            width: 100% !important;
            max-width: 100% !important;
            border-radius: 8px !important;
            margin: 0 !important;
        }
        .paused-chat-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
        }
        .paused-chat-item button {
            width: 100%;
            text-align: center;
            padding: 4px 8px;
        }
    }
</style>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="main-container">
    <!-- Header Principal -->
    <div class="header-card">
        <div class="header-left">
            <div class="header-icon-box">
                <i class="fab fa-whatsapp"></i>
            </div>
            <div class="header-title-box">
                <h2>Centro de WhatsApp & Agente Inteligente</h2>
                <p>Atención automatizada 24/7 y herramientas de comunicación clínica para Dentality</p>
            </div>
        </div>
        <div class="header-actions-wrap">
            <!-- Botón Interruptor Global de Modo IA vs Modo Manual -->
            <button type="button" class="btn btn-sm fw-bold shadow-sm" id="btnToggleModoBot" style="border-radius: 20px; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.4);" onclick="toggleModoBot()" title="Alternar entre IA automática o atención manual de recepción">
                <i class="fas fa-robot me-1" id="iconModoBot"></i> <span id="txtModoBot">IA Automática 24/7</span>
            </button>
            <div class="bot-status-pill" id="statusPill">
                <span class="status-dot disconnected" id="statusDot"></span>
                <span id="statusText">Verificando...</span>
            </div>
        </div>
    </div>

    <!-- Fila de Métricas Rápidas Operativas -->
    <div class="metric-cards-row">
        <div class="metric-box">
            <div class="metric-icon" style="background: rgba(15, 76, 110, 0.1); color: var(--primary);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="metric-info">
                <span class="label">Citas Programadas Hoy</span>
                <span class="value"><?php echo $totalCitasHoy; ?></span>
            </div>
        </div>

        <div class="metric-box">
            <div class="metric-icon" style="background: rgba(37, 211, 102, 0.15); color: var(--whatsapp-green);">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="metric-info">
                <span class="label">Confirmadas por WhatsApp</span>
                <span class="value"><?php echo $totalConfirmadasHoy; ?></span>
            </div>
        </div>

        <div class="metric-box">
            <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);">
                <i class="fas fa-bell"></i>
            </div>
            <div class="metric-info">
                <span class="label">Recordatorios Pendientes</span>
                <span class="value"><?php echo $totalRecordatoriosPendientes; ?></span>
            </div>
        </div>

        <div class="metric-box">
            <div class="metric-icon" style="background: rgba(99, 102, 241, 0.12); color: #6366F1;">
                <i class="fas fa-comments"></i>
            </div>
            <div class="metric-info">
                <span class="label">Mensajes Atendidos</span>
                <span class="value" id="metricMsgHandled">0</span>
            </div>
        </div>
    </div>

    <!-- Fila 1: Conexión WhatsApp y Enviar Agenda a Doctores -->
    <div class="agent-grid">
        
        <!-- CARD 1: Conexión WhatsApp & QR -->
        <div class="dashboard-card">
            <div class="card-title-box">
                <h3><i class="fab fa-whatsapp" style="color: var(--whatsapp-green);"></i> Conexión WhatsApp Dentality</h3>
                <button type="button" class="btn-danger-outline" id="btnLogout" onclick="logoutBot()" style="display: none;">
                    <i class="fas fa-sign-out-alt"></i> Desconectar
                </button>
            </div>

            <div class="qr-display-container" id="qrContainer">
                <div class="text-center py-3" id="qrLoading">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2" style="color: var(--primary);"></i>
                    <p class="text-muted fw-semibold mb-0 small">Verificando conexión con WhatsApp...</p>
                </div>
            </div>

            <div class="mt-2 pt-2 border-top d-flex flex-column gap-1">
                <button type="button" class="btn btn-outline-secondary btn-sm w-100 fw-semibold" onclick="reiniciarMemoriaBot()" style="font-size: 0.82rem;">
                    <i class="fas fa-broom text-primary me-1"></i> Reiniciar Memoria de Conversaciones IA
                </button>
                <div class="text-muted small text-center mt-1" style="font-size: 0.75rem;">
                    <i class="fas fa-shield-alt text-success me-1"></i> Sesión protegida y reconexión automática 24/7.
                </div>
            </div>
        </div>

        <!-- CARD 2: Enviar Agenda del Día al WhatsApp del Doctor -->
        <div class="dashboard-card">
            <div class="card-title-box">
                <h3><i class="fas fa-user-md" style="color: var(--primary);"></i> Enviar Agenda al Doctor</h3>
                <span class="badge bg-light text-dark fw-bold border">Notificación Médica</span>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-7">
                    <label class="form-label-custom">Seleccionar Doctor/a:</label>
                    <select class="form-select form-select-sm" id="selectDoctorAgenda" onchange="cargarPrevisualizacionAgenda()">
                        <option value="">-- Seleccionar Especialista --</option>
                        <?php foreach ($doctoresActivos as $doc): ?>
                            <option value="<?php echo $doc['id']; ?>" data-tel="<?php echo htmlspecialchars($doc['telefono'] ?? ''); ?>">
                                <?php echo htmlspecialchars($doc['nombre']); ?> (<?php echo htmlspecialchars($doc['especialidades'] ?? 'Odontología General'); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label-custom">Fecha:</label>
                    <select class="form-select form-select-sm" id="selectFechaAgenda" onchange="cargarPrevisualizacionAgenda()">
                        <option value="<?php echo date('Y-m-d'); ?>">Hoy (<?php echo date('d/m'); ?>)</option>
                        <option value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">Mañana (<?php echo date('d/m', strtotime('+1 day')); ?>)</option>
                    </select>
                </div>
            </div>

            <div class="mb-2">
                <label class="form-label-custom">Teléfono WhatsApp del Doctor:</label>
                <input type="text" class="form-control form-control-sm" id="inputTelDoctor" placeholder="Ej: 71234567 o +59171234567">
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Mensaje a Enviar:</label>
                <textarea class="form-control form-control-sm" id="textPreviewAgenda" rows="5" style="font-size: 0.8rem; font-family: monospace;" placeholder="Selecciona un doctor para generar la agenda..."></textarea>
            </div>

            <button type="button" class="btn btn-custom-action btn-send-bot w-100" id="btnEnviarAgendaDoctor" onclick="enviarAgendaAlDoctor()">
                <i class="fab fa-whatsapp"></i> <span>Enviar Agenda al WhatsApp del Doctor</span>
            </button>
        </div>

    </div>

    <!-- Fila 2: Mensajes Rápidos y Control de Intervención Humana -->
    <div class="agent-grid">

        <!-- CARD 3: Mensajes Rápidos y Plantillas Clínicas a Pacientes -->
        <div class="dashboard-card">
            <div class="card-title-box">
                <h3><i class="fas fa-paper-plane" style="color: var(--accent-teal);"></i> Mensajes Rápidos a Pacientes</h3>
                <span class="badge bg-light text-dark fw-bold border">Plantillas Clínicas</span>
            </div>

            <div class="mb-2">
                <label class="form-label-custom">Buscar Paciente Registrado:</label>
                <select class="form-select form-select-sm" id="selectPacienteMensaje" onchange="seleccionarPacientePlantilla()">
                    <option value="">-- Seleccionar o Escribir Manual --</option>
                    <?php foreach ($clientesConTel as $cli): ?>
                        <option value="<?php echo htmlspecialchars($cli['telefono']); ?>" data-nombre="<?php echo htmlspecialchars($cli['nombre']); ?>">
                            <?php echo htmlspecialchars($cli['nombre']); ?> (<?php echo htmlspecialchars($cli['telefono']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label-custom">Número Celular:</label>
                    <input type="text" class="form-control form-control-sm" id="inputTelPaciente" placeholder="Ej: 71234567">
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Plantilla Odontológica:</label>
                    <select class="form-select form-select-sm" id="selectPlantillaClinica" onchange="aplicarPlantillaClinica()">
                        <option value="">-- Elige plantilla --</option>
                        <option value="post_extraccion">🩹 Post-Extracción / Cirugía</option>
                        <option value="control_post">🦷 Control Post-Tratamiento</option>
                        <option value="presupuesto_pendiente">📋 Recordatorio Plan Dental</option>
                        <option value="aviso_turno">⏰ Aviso Turno Listo</option>
                        <option value="personalizado">✍️ Personalizado</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label-custom">Contenido del Mensaje:</label>
                <textarea class="form-control form-control-sm" id="textMensajePaciente" rows="4" style="font-size: 0.8rem;" placeholder="Escribe el mensaje o selecciona una plantilla..."></textarea>
            </div>

            <button type="button" class="btn btn-custom-action btn-send-bot w-100" id="btnEnviarMensajePaciente" onclick="enviarMensajeDirectoPaciente()">
                <i class="fab fa-whatsapp"></i> <span>Enviar WhatsApp al Paciente</span>
            </button>
        </div>

        <!-- CARD 4: Control de Intervención Humana & Chats Silenciados -->
        <div class="dashboard-card">
            <div class="card-title-box">
                <h3><i class="fas fa-user-shield" style="color: var(--warning);"></i> Intervención Humana / Silenciar Bot</h3>
                <span class="badge bg-light text-dark fw-bold border">Recepción</span>
            </div>

            <!-- Banner Explicativo -->
            <div class="alert alert-light border mb-2 py-2 px-3 small" style="border-radius: 8px; background: #F8FAFC;">
                <i class="fas fa-info-circle text-primary me-1"></i> <strong>Auto-Pausa Activa:</strong> Si la recepcionista escribe desde WhatsApp al paciente, la IA se silencia automáticamente por <strong>60 min</strong>.
                <div class="mt-1 text-muted" style="font-size: 0.74rem;">
                    👉 Comandos en el chat: <code>#pausa</code> o <code>#reanudar</code>
                </div>
            </div>

            <!-- 1. Buscar Paciente por Nombre/Teclado -->
            <div class="mb-2">
                <label class="form-label-custom">Buscar Paciente Registrado:</label>
                <select class="form-select form-select-sm" id="selectPacientePausar" onchange="seleccionarPacientePausar()">
                    <option value="">-- Buscar por Nombre o Teléfono --</option>
                    <?php foreach ($clientesConTel as $cli): ?>
                        <option value="<?php echo htmlspecialchars($cli['telefono']); ?>" data-nombre="<?php echo htmlspecialchars($cli['nombre']); ?>">
                            <?php echo htmlspecialchars($cli['nombre']); ?> (<?php echo htmlspecialchars($cli['telefono']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. O Silenciar por Número Directo con prefijo predeterminado (+591) -->
            <div class="mb-3">
                <label class="form-label-custom">O ingresar número de celular:</label>
                <div class="input-group input-group-sm input-group-responsive">
                    <select class="form-select" id="pausa_codigo_pais" style="max-width: 130px;">
                        <option value="+591" selected>🇧🇴 +591</option>
                        <option value="+54">🇦🇷 +54</option>
                        <option value="+55">🇧🇷 +55</option>
                        <option value="+56">🇨🇱 +56</option>
                        <option value="+57">🇨🇴 +57</option>
                        <option value="+593">🇪🇨 +593</option>
                        <option value="+595">🇵🇾 +595</option>
                        <option value="+51">🇵🇪 +51</option>
                        <option value="+598">🇺🇾 +598</option>
                        <option value="+58">🇻🇪 +58</option>
                        <option value="+52">🇲🇽 +52</option>
                        <option value="+1">🇺🇸 +1</option>
                        <option value="+34">🇪🇸 +34</option>
                    </select>
                    <input type="text" class="form-control" id="inputPausarTel" placeholder="Ej: 71234567">
                    <button type="button" class="btn btn-warning fw-bold text-nowrap" onclick="pausarChatManual()">
                        <i class="fas fa-pause me-1"></i> Silenciar 60m
                    </button>
                </div>
            </div>

            <!-- Lista de Chats en Pausa -->
            <label class="form-label-custom d-flex justify-content-between align-items-center mb-2">
                <span>Chats en Pausa (Atendidos por Recepción):</span>
                <div>
                    <span class="badge bg-secondary me-1" id="badgePausedCount">0 activos</span>
                    <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 fw-bold" style="font-size: 0.75rem;" onclick="reanudarTodosLosChats()" title="Reanudar IA para todos los chats pausados">
                        <i class="fas fa-play-circle me-1"></i> Reanudar Todos
                    </button>
                </div>
            </label>
            <div id="containerPausedChats" style="max-height: 140px; overflow-y: auto; padding-right: 4px;">
                <div class="text-muted text-center py-3 small">No hay chats pausados. La IA está activa para todos.</div>
            </div>
        </div>

    </div>
</div>

<script>
function getBotApiUrl() {
    var custom = localStorage.getItem('dentality_bot_url') || localStorage.getItem('tatianaruiz_bot_url') || localStorage.getItem('dra_bot_url');
    if (custom && custom.trim() !== '') return custom.trim().replace(/\/$/, '') + '/api';
    <?php if (!empty(getenv('BOT_API_URL'))): ?>
    return '<?php echo rtrim(getenv('BOT_API_URL'), '/'); ?>';
    <?php else: ?>
    return 'https://dentality-bot.onrender.com/api';
    <?php endif; ?>
}

let pollingInterval = null;
let currentAiEnabled = true;

// ============================================
// Funciones de Modo Global e Intervención Humana
// ============================================
async function toggleModoBot() {
    const nuevoModo = !currentAiEnabled;
    const confirmMsg = nuevoModo 
        ? '¿Deseas activar el Modo Automático con IA? (El bot responderá a todos los pacientes 24/7)'
        : '¿Deseas activar el Modo Manual de Recepción? (La IA se pausará y no responderá mensajes automáticamente)';
    
    if (!confirm(confirmMsg)) return;

    try {
        const res = await fetch(`${getBotApiUrl()}/bot-mode`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ aiEnabled: nuevoModo })
        });
        const data = await res.json();
        if (data.ok) {
            currentAiEnabled = data.aiEnabled;
            actualizarBotonModo(currentAiEnabled);
            alert(`✅ ${data.message}`);
            checkBotStatus();
        }
    } catch (e) {
        alert('Error al cambiar modo: ' + e.message);
    }
}

function actualizarBotonModo(aiEnabled) {
    const btn = document.getElementById('btnToggleModoBot');
    const txt = document.getElementById('txtModoBot');
    const icon = document.getElementById('iconModoBot');
    if (!btn || !txt || !icon) return;

    if (aiEnabled) {
        btn.style.background = 'rgba(34, 197, 94, 0.25)';
        btn.style.borderColor = 'rgba(34, 197, 94, 0.6)';
        txt.textContent = '🟢 IA Automática 24/7';
        icon.className = 'fas fa-robot me-1';
    } else {
        btn.style.background = 'rgba(245, 158, 11, 0.35)';
        btn.style.borderColor = 'rgba(245, 158, 11, 0.8)';
        txt.textContent = '🟡 Modo Manual Recepción';
        icon.className = 'fas fa-user-clock me-1';
    }
}

function seleccionarPacientePausar() {
    const select = document.getElementById('selectPacientePausar');
    const codSelect = document.getElementById('pausa_codigo_pais');
    const telInput = document.getElementById('inputPausarTel');
    if (!select || !select.value) return;

    let tel = select.value.trim();
    let matched = false;
    for (let opt of codSelect.options) {
        if (opt.value && tel.startsWith(opt.value)) {
            codSelect.value = opt.value;
            telInput.value = tel.substring(opt.value.length).trim();
            matched = true;
            break;
        }
    }
    if (!matched) {
        if (tel.startsWith('591') && tel.length > 8) {
            codSelect.value = '+591';
            telInput.value = tel.substring(3).trim();
        } else {
            codSelect.value = '+591';
            telInput.value = tel.replace(/^\+/, '');
        }
    }
}

async function pausarChatManual() {
    const cod = document.getElementById('pausa_codigo_pais').value.trim() || '+591';
    const num = document.getElementById('inputPausarTel').value.trim();
    if (!num) {
        alert('Por favor selecciona un paciente o ingresa el número de celular.');
        return;
    }
    const tel = num.startsWith('+') ? num : (cod + num.replace(/\D/g, ''));

    // Obtener nombre si fue seleccionado del dropdown
    const select = document.getElementById('selectPacientePausar');
    let pacienteNombre = null;
    if (select && select.selectedIndex > 0) {
        pacienteNombre = select.options[select.selectedIndex].getAttribute('data-nombre');
    }

    try {
        const res = await fetch(`${getBotApiUrl()}/pause-chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                telefono: tel, 
                durationMinutes: 60,
                pacienteNombre: pacienteNombre
            })
        });
        const data = await res.json();
        if (data.ok) {
            alert(`⏸️ IA silenciada para +${tel.replace(/\D/g, '')} durante 60 minutos.`);
            document.getElementById('inputPausarTel').value = '';
            $('#selectPacientePausar').val('').trigger('change');
            checkBotStatus();
        } else {
            alert('Error: ' + (data.error || 'No se pudo pausar'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function reanudarChatManual(tel) {
    try {
        const res = await fetch(`${getBotApiUrl()}/resume-chat`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ telefono: tel })
        });
        const data = await res.json();
        if (data.ok) {
            alert(`▶️ IA reanudada exitosamente para +${tel}.`);
            checkBotStatus();
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function reanudarTodosLosChats() {
    if (!confirm('¿Deseas reanudar la IA para todos los chats pausados?')) return;
    try {
        const res = await fetch(`${getBotApiUrl()}/clear-all-paused`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await res.json();
        if (data.ok) {
            alert('▶️ ¡Todos los chats han sido reactivados con IA exitosamente!');
            checkBotStatus();
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function reiniciarMemoriaBot(telefono = null) {
    const msg = telefono 
        ? `¿Deseas reiniciar la memoria de conversación para +${telefono}? (El bot olvidará la charla previa pero conservará su identidad y ficha médica)`
        : '¿Deseas reiniciar la memoria de todas las conversaciones de la IA? (El bot olvidará las charlas previas pero conservará los pacientes y citas)';
    if (!confirm(msg)) return;

    try {
        const res = await fetch(`${getBotApiUrl()}/reset-session`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ telefono: telefono })
        });
        const data = await res.json();
        if (data.ok) {
            alert('🧹 ' + data.message);
        } else {
            alert('Error: ' + (data.error || 'No se pudo reiniciar la memoria'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

function renderPausedChats(pausedList) {
    const container = document.getElementById('containerPausedChats');
    const badge = document.getElementById('badgePausedCount');
    if (!container) return;

    // Solo filtrar identificadores internos de Baileys que tengan @lid o sean mayores a 14 dígitos sin nombre
    const validList = Array.isArray(pausedList) ? pausedList.filter(c => {
        const str = String(c.phone || '');
        if (str.includes('@lid')) return false;
        const clean = str.replace(/\D/g, '');
        if (clean.length > 14 && !c.pacienteNombre && !clean.startsWith('591')) return false;
        return clean.length >= 6;
    }) : [];

    if (validList.length === 0) {
        badge.textContent = '0 activos';
        badge.className = 'badge bg-secondary';
        container.innerHTML = '<div class="text-muted text-center py-3 small">No hay chats pausados. La IA está activa para todos.</div>';
        return;
    }

    badge.textContent = `${validList.length} en pausa`;
    badge.className = 'badge bg-warning text-dark';

    container.innerHTML = validList.map(c => {
        const nombreHtml = c.pacienteNombre ? `<span class="fw-bold text-dark me-1">${c.pacienteNombre}</span>` : '';
        return `
            <div class="paused-chat-item">
                <div>
                    ${nombreHtml}<strong>+${c.phone}</strong>
                    <span class="text-muted ms-1" style="font-size: 0.75rem;">(⏳ ${c.remainingMinutes} min restantes)</span>
                </div>
                <button type="button" class="btn btn-sm btn-success py-0 px-2 fw-bold" style="font-size: 0.74rem;" onclick="reanudarChatManual('${c.phone}')">
                    <i class="fas fa-play me-1"></i> Reanudar IA
                </button>
            </div>
        `;
    }).join('');
}

// ============================================
// Funciones de Agenda para Doctores
// ============================================
async function cargarPrevisualizacionAgenda() {
    const docSelect = document.getElementById('selectDoctorAgenda');
    const fechaSelect = document.getElementById('selectFechaAgenda');
    const telInput = document.getElementById('inputTelDoctor');
    const previewText = document.getElementById('textPreviewAgenda');

    const doctorId = docSelect.value;
    const fecha = fechaSelect.value;

    if (!doctorId) {
        previewText.value = '';
        telInput.value = '';
        return;
    }

    const selectedOption = docSelect.options[docSelect.selectedIndex];
    const docTel = selectedOption.getAttribute('data-tel') || '';
    if (docTel) telInput.value = docTel;

    previewText.value = 'Cargando agenda del doctor...';

    try {
        const res = await fetch(`whatsapp_agent.php?ajax=get_doctor_agenda&doctor_id=${doctorId}&fecha=${fecha}`);
        const data = await res.json();
        if (data.ok) {
            previewText.value = data.mensaje;
            if (data.telefono && !telInput.value) telInput.value = data.telefono;
        } else {
            previewText.value = 'No se pudo cargar la agenda: ' + (data.error || 'Error desconocido');
        }
    } catch (e) {
        previewText.value = 'Error al consultar agenda: ' + e.message;
    }
}

async function enviarAgendaAlDoctor() {
    const tel = document.getElementById('inputTelDoctor').value.trim();
    const msg = document.getElementById('textPreviewAgenda').value.trim();
    const btn = document.getElementById('btnEnviarAgendaDoctor');

    if (!tel) {
        alert('Por favor ingresa o verifica el número de celular del doctor.');
        return;
    }
    if (!msg) {
        alert('No hay mensaje de agenda para enviar.');
        return;
    }

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Enviando por WhatsApp...</span>';

    try {
        const res = await fetch(`${getBotApiUrl()}/send-message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ telefono: tel, mensaje: msg })
        });
        const data = await res.json();
        if (data.exito || data.ok) {
            alert('✅ Agenda enviada exitosamente al WhatsApp del Doctor.');
        } else {
            alert('⚠️ No se pudo enviar: ' + (data.error || 'Verifica que el bot esté conectado.'));
        }
    } catch (e) {
        alert('❌ Error al enviar mensaje: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// ============================================
// Funciones de Plantillas Odontológicas
// ============================================
function seleccionarPacientePlantilla() {
    const select = document.getElementById('selectPacienteMensaje');
    const telInput = document.getElementById('inputTelPaciente');
    if (!select) return;
    const selectedOption = select.options[select.selectedIndex];
    
    if (select.value) {
        telInput.value = select.value;
    } else {
        telInput.value = '';
    }
    aplicarPlantillaClinica();
}

function aplicarPlantillaClinica() {
    const selectPlantilla = document.getElementById('selectPlantillaClinica').value;
    const selectPac = document.getElementById('selectPacienteMensaje');
    const selectedOption = selectPac.options[selectPac.selectedIndex];
    const nombre = selectedOption?.getAttribute('data-nombre') || 'Estimado/a Paciente';
    const textArea = document.getElementById('textMensajePaciente');

    switch (selectPlantilla) {
        case 'post_extraccion':
            textArea.value = `¡Hola *${nombre}*! 👋\n\nDesde la *Clínica Dentality* te compartimos las indicaciones importantes para tu recuperación:\n\n1️⃣ Mantén la gasa mordida firmemente por 30 a 45 minutos.\n2️⃣ No consumas alimentos calientes, duros ni bebas con pajilla hoy.\n3️⃣ Evita esfuerzos físicos y no te enjuagues la boca con fuerza.\n4️⃣ Toma tu medicación en el horario recetado.\n\nCualquier molestia o duda, escríbenos aquí mismo. ¡Que te recuperes pronto! 🦷✨`;
            break;
        case 'control_post':
            textArea.value = `¡Hola *${nombre}*! 👋\n\nEsperamos que te encuentres muy bien tras tu atención en *Dentality*. 🦷\n\n¿Cómo sientes tu mordida o la zona tratada? Recuerda mantener una buena higiene y cepillado suave. Si tienes alguna duda o molestia, déjanos saber. ¡Cuidamos de tu sonrisa! ✨`;
            break;
        case 'presupuesto_pendiente':
            textArea.value = `¡Hola *${nombre}*! 👋\n\nTe saludamos cordialmente de la *Clínica Dentality*. Queremos recordarte que tienes pendiente continuar con tu plan dental para cuidar de tu salud bucal.\n\n¿Te gustaría que te agendemos un horario disponible esta semana para continuar tu tratamiento? 🦷✨`;
            break;
        case 'aviso_turno':
            textArea.value = `¡Hola *${nombre}*! 👋\n\nTe avisamos que tu doctor en *Dentality* ya se encuentra listo para recibirte. Te esperamos con gusto en la clínica. 🦷✨`;
            break;
        case 'personalizado':
            textArea.value = `¡Hola *${nombre}*! 👋\n\n`;
            break;
    }
}

async function enviarMensajeDirectoPaciente() {
    const tel = document.getElementById('inputTelPaciente').value.trim();
    const msg = document.getElementById('textMensajePaciente').value.trim();
    const btn = document.getElementById('btnEnviarMensajePaciente');

    if (!tel) {
        alert('Por favor ingresa o selecciona el número de celular del paciente.');
        return;
    }
    if (!msg) {
        alert('Por favor escribe un mensaje antes de enviar.');
        return;
    }

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Enviando por WhatsApp...</span>';

    try {
        const res = await fetch(`${getBotApiUrl()}/send-message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ telefono: tel, mensaje: msg })
        });
        const data = await res.json();
        if (data.exito || data.ok) {
            alert('✅ Mensaje enviado exitosamente al paciente.');
        } else {
            alert('⚠️ No se pudo enviar: ' + (data.error || 'Verifica que el bot esté conectado.'));
        }
    } catch (e) {
        alert('❌ Error al enviar mensaje: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// ============================================
// Estado del Bot y Polling
// ============================================
async function checkBotStatus() {
    try {
        const res = await fetch(`${getBotApiUrl()}/status`, { cache: 'no-cache' });
        const data = await res.json();

        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const qrContainer = document.getElementById('qrContainer');
        const btnLogout = document.getElementById('btnLogout');
        const metricMsg = document.getElementById('metricMsgHandled');

        if (metricMsg && data.totalMessagesHandled !== undefined) {
            metricMsg.textContent = data.totalMessagesHandled;
        }

        if (data.aiEnabled !== undefined) {
            currentAiEnabled = data.aiEnabled;
            actualizarBotonModo(currentAiEnabled);
        }

        if (Array.isArray(data.pausedChats)) {
            renderPausedChats(data.pausedChats);
        }

        statusDot.className = 'status-dot';

        if (data.status === 'connected') {
            statusDot.classList.add('connected');
            statusText.textContent = `Conectado (+${data.connectedUser || 'Bot'})`;
            btnLogout.style.display = 'inline-flex';

            qrContainer.innerHTML = `
                <div class="connected-state-box">
                    <div class="connected-icon-badge">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <h5 class="fw-bold mb-1 text-dark">¡WhatsApp Vinculado!</h5>
                    <p class="text-muted small mb-0">Línea activa: <strong class="text-dark">+${data.connectedUser || ''}</strong></p>
                    <span class="badge bg-success py-1 px-3 mt-1">🟢 Operando 24/7</span>
                </div>
            `;
        } else if (data.status === 'qr_ready') {
            let qrSrc = data.qrCodeBase64 || data.qr;
            if (!qrSrc) {
                try {
                    const qrRes = await fetch(`${getBotApiUrl()}/qr`);
                    const qrData = await qrRes.json();
                    qrSrc = qrData.qr;
                } catch (qrErr) {}
            }

            if (qrSrc) {
                statusDot.classList.add('qr_ready');
                statusText.textContent = 'Escanear Código QR';
                btnLogout.style.display = 'none';

                qrContainer.innerHTML = `
                    <div class="text-center">
                        <img src="${qrSrc}" class="qr-img mb-2" alt="QR WhatsApp">
                        <p class="fw-bold mb-0 text-dark" style="font-size: 0.85rem;">Escanea con WhatsApp</p>
                        <small class="text-muted">Abre WhatsApp > Dispositivos vinculados</small>
                    </div>
                `;
            } else {
                statusDot.classList.add('qr_ready');
                statusText.textContent = 'Generando QR...';
                btnLogout.style.display = 'none';

                qrContainer.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2 text-warning"></i>
                        <p class="text-muted fw-semibold mb-0 small">Generando nuevo código QR...</p>
                    </div>
                `;
            }
        } else {
            statusDot.classList.add('disconnected');
            statusText.textContent = 'Desconectado';
            btnLogout.style.display = 'none';

            qrContainer.innerHTML = `
                <div class="text-center py-3">
                    <i class="fas fa-plug text-danger fa-2x mb-2"></i>
                    <p class="text-muted fw-semibold mb-0 small">Servicio desconectado</p>
                </div>
            `;
        }

    } catch (e) {
        document.getElementById('statusDot').className = 'status-dot disconnected';
        document.getElementById('statusText').textContent = 'Error de conexión';
        document.getElementById('qrContainer').innerHTML = `
            <div class="text-center py-3">
                <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                <p class="text-muted fw-bold mb-0 small">No se pudo contactar con el microservicio</p>
            </div>
        `;
    }
}

async function logoutBot() {
    if (!confirm('¿Deseas cerrar la sesión de WhatsApp del Bot? Se generará un nuevo QR para vincular.')) return;
    try {
        await fetch(`${getBotApiUrl()}/logout`, { method: 'POST' });
        checkBotStatus();
    } catch (e) {
        alert('Error al cerrar sesión: ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    checkBotStatus();
    pollingInterval = setInterval(checkBotStatus, 3000);
});
</script>

<!-- jQuery y Select2 para Búsqueda por Teclado -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#selectPacienteMensaje').select2({
        placeholder: "🔍 Escribe para buscar paciente por nombre o teléfono...",
        allowClear: true,
        width: '100%'
    }).on('change', function() {
        seleccionarPacientePlantilla();
    });

    $('#selectDoctorAgenda').select2({
        placeholder: "🔍 Seleccionar o buscar doctor/a...",
        allowClear: true,
        width: '100%'
    }).on('change', function() {
        cargarPrevisualizacionAgenda();
    });

    $('#selectPacientePausar').select2({
        placeholder: "🔍 Buscar paciente por nombre o teléfono...",
        allowClear: true,
        width: '100%'
    }).on('change', function() {
        seleccionarPacientePausar();
    });
});
</script>
