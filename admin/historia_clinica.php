<?php
/**
 * Historia Clínica del Paciente
 * Vista principal con tabs: Ficha Médica, Evoluciones, Odontograma, Archivos
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: lista_clientes.php');
    exit();
}

$clienteId = intval($_GET['id']);

require_once '../src/config/db.php';
require_once '../src/models/Client.php';
require_once '../src/models/HistoriaClinica.php';
require_once '../src/models/Evolucion.php';
require_once '../src/models/ArchivoClinico.php';
require_once '../src/models/Presupuesto.php';

$clientModel = new Client($pdo);
$historiaModel = new HistoriaClinica($pdo);
$evolucionModel = new Evolucion($pdo);
$archivoModel = new ArchivoClinico($pdo);
$presupuestoModel = new Presupuesto($pdo);

$cliente = $clientModel->getById($clienteId);
if (!$cliente) {
    $_SESSION['message'] = 'Paciente no encontrado';
    header('Location: lista_clientes.php');
    exit();
}

$historia = $historiaModel->getByCliente($clienteId);
if (!$historia) {
    // Si aún no existe, inicializarla para que tenga token y número de HC de inmediato
    $tokenInicial = bin2hex(random_bytes(16));
    $numeroHc = 'HC-' . str_pad($clienteId, 5, '0', STR_PAD_LEFT);
    $historiaModel->guardar($clienteId, [
        'cliente_id' => $clienteId,
        'numero_hc' => $numeroHc,
        'firma_token' => $tokenInicial
    ]);
    $historia = $historiaModel->getByCliente($clienteId);
}
if ($historia && empty($historia['firma_token'])) {
    $nuevoToken = bin2hex(random_bytes(16));
    $pdo->prepare("UPDATE historia_clinica SET firma_token = ? WHERE id = ?")->execute([$nuevoToken, $historia['id']]);
    $historia['firma_token'] = $nuevoToken;
}

$patologiasSeleccionadas = [];
if (!empty($historia['patologias_personales'])) {
    $decoded = json_decode($historia['patologias_personales'], true);
    if (is_array($decoded)) {
        $patologiasSeleccionadas = $decoded;
    }
}

// Construir enlace para que el paciente firme desde su dispositivo
$firmaToken = $historia['firma_token'] ?? '';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir = dirname($_SERVER['PHP_SELF']);
$linkFirma = $protocol . $host . rtrim($dir, '/\\') . '/firmar_ficha.php?token=' . urlencode($firmaToken);

$evoluciones = $evolucionModel->getByCliente($clienteId);
$archivos = $archivoModel->getByCliente($clienteId);
$presupuestos = $presupuestoModel->getByCliente($clienteId);
$alertas = $historiaModel->getAlertasMedicas($clienteId);

// Obtener doctores para el formulario
$doctores = $pdo->query("SELECT id, nombre FROM doctores WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #003B73; --primary-dark: #062846; --accent: #2998EC; }
    body { background: linear-gradient(135deg, #F4F9FD 0%, #f3e6ed 100%); }
    .page-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    
    /* Header del paciente */
    .patient-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 25px;
        color: white;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .patient-info { display: flex; align-items: center; gap: 20px; }
    .patient-avatar { width: 70px; height: 70px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; }
    .patient-name { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .patient-phone { opacity: 0.9; font-size: 0.95rem; }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-header { padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.2); color: white; border: none; cursor: pointer; }
    .btn-header:hover { background: rgba(255,255,255,0.3); color: white; }

    /* Alertas médicas */
    .alertas-box { margin-bottom: 20px; }
    .alerta { padding: 12px 15px; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
    .alerta-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .alerta-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
    .alerta-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }

    /* Tabs */
    .tabs-container { margin-bottom: 20px; }
    .tabs-nav { display: flex; gap: 5px; background: white; padding: 5px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); flex-wrap: wrap; }
    .tab-btn { padding: 12px 20px; border: none; background: transparent; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; color: #666; }
    .tab-btn:hover { background: #f0f0f0; }
    .tab-btn.active { background: var(--primary); color: white; }
    .tab-btn .badge { background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; }
    .tab-btn.active .badge { background: rgba(255,255,255,0.3); }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Cards */
    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .card-title { font-weight: 700; font-size: 1.1rem; color: #333; display: flex; align-items: center; gap: 10px; margin: 0; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.95rem; }
    .form-control:focus { border-color: var(--primary); outline: none; }

    /* Timeline de evoluciones */
    .timeline { position: relative; padding-left: 30px; }
    .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
    .timeline-item { position: relative; padding-bottom: 25px; }
    .timeline-item::before { content: ''; position: absolute; left: -24px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); border: 3px solid white; box-shadow: 0 0 0 2px var(--primary); }
    .timeline-date { font-size: 0.85rem; color: #888; margin-bottom: 5px; }
    .timeline-card { background: #f8f9fa; border-radius: 10px; padding: 15px; border-left: 3px solid var(--primary); }
    .timeline-title { font-weight: 700; color: #333; margin-bottom: 8px; }
    .timeline-content { color: #666; font-size: 0.9rem; }
    .timeline-content p { margin: 4px 0; }
    .timeline-doctor { font-size: 0.85rem; color: var(--primary); margin-top: 8px; }

    /* Archivos */
    .archivos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
    .archivo-card { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; }
    .archivo-icon { font-size: 3rem; color: var(--primary); margin-bottom: 10px; }
    .archivo-nombre { font-weight: 600; font-size: 0.9rem; word-break: break-word; }
    .archivo-fecha { font-size: 0.8rem; color: #888; margin-top: 5px; }

    /* Botones */
    .btn { padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: var(--primary-dark); }
    .btn-success { background: #28a745; color: white; }
    .btn-sm { padding: 6px 12px; font-size: 0.85rem; }

    /* Empty state */
    .empty-state { text-align: center; padding: 40px; color: #888; }
    .empty-state i { font-size: 3rem; opacity: 0.3; margin-bottom: 15px; }

    /* Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 9999; padding: 20px; transition: background 0.3s; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: white; border-radius: 16px; padding: 25px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h4 { margin: 0; font-weight: 700; }
    .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888; }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

    @media (max-width: 768px) {
        html, body {
            overflow-x: hidden;
            max-width: 100%;
        }
        .page-container {
            padding: 10px 8px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Header del paciente en móvil */
        .patient-header {
            padding: 16px 14px;
            flex-direction: column;
            align-items: stretch;
            gap: 14px;
            border-radius: 14px;
        }
        .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
        }
        .patient-avatar {
            width: 48px;
            height: 48px;
            min-width: 48px;
            font-size: 1.25rem;
        }
        .patient-name {
            font-size: 1.15rem;
            line-height: 1.25;
            word-break: break-word;
        }
        .patient-phone {
            font-size: 0.85rem;
            margin-top: 3px;
        }

        /* Botones de acción del header: cuadrícula responsive de 2 columnas */
        .header-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            width: 100%;
        }
        .header-actions .btn-header {
            padding: 9px 8px;
            font-size: 0.78rem;
            justify-content: center;
            text-align: center;
            border-radius: 8px;
            width: 100%;
            white-space: nowrap;
            box-sizing: border-box;
        }
        /* Botón 'Firmar en Pantalla' ocupa 2 columnas */
        .header-actions .btn-header:nth-child(3) {
            grid-column: span 2;
        }

        /* Tabs de navegación */
        .tabs-nav {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            padding: 6px;
            width: 100%;
            border-radius: 10px;
        }
        .tab-btn {
            padding: 10px 6px;
            font-size: 0.82rem;
            justify-content: center;
            text-align: center;
            min-width: 0;
            border-radius: 8px;
        }
        .tab-btn i {
            font-size: 0.85rem;
        }
        .tab-btn .badge {
            font-size: 0.7rem;
            padding: 1px 6px;
        }

        /* Tarjetas de contenido */
        .card {
            padding: 16px 14px;
            border-radius: 12px;
            margin-bottom: 14px;
            box-sizing: border-box;
            overflow: hidden;
            word-break: break-word;
        }
        .card-header {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .card-title {
            font-size: 1.02rem;
            line-height: 1.3;
        }

        /* Formularios y cuadrícula */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr !important;
            gap: 12px;
            width: 100%;
        }
        .form-grid .form-group {
            width: 100% !important;
            margin-bottom: 0;
            grid-column: 1 / -1 !important;
        }
        .form-control {
            width: 100% !important;
            font-size: 0.95rem;
            padding: 10px 12px;
            box-sizing: border-box;
        }

        /* Catálogo de Patologías en móvil */
        .patologias-grid {
            grid-template-columns: 1fr !important;
            gap: 8px;
            width: 100%;
        }
        .patologia-item {
            padding: 10px 12px;
            font-size: 0.88rem;
            width: 100%;
            box-sizing: border-box;
        }

        /* Examen estomatognático en móvil */
        .exam-columns {
            grid-template-columns: 1fr !important;
            gap: 14px;
            width: 100%;
        }
        .exam-box {
            padding: 14px 12px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Opciones inline de hábitos e higiene */
        .inline-checks {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            width: 100%;
            box-sizing: border-box;
        }
        .inline-checks label {
            width: 100%;
        }

        /* Sección de firma en móvil */
        .firma-card-body {
            flex-direction: column;
            align-items: stretch;
            gap: 16px;
            width: 100%;
        }
        .firma-preview-container {
            width: 100%;
            min-width: 0;
            padding: 15px 10px;
            box-sizing: border-box;
        }
        .firma-preview-img {
            max-width: 100%;
            height: auto;
            max-height: 100px;
        }
        .firma-card-body .btn {
            width: 100%;
            justify-content: center;
            text-align: center;
            padding: 12px 14px;
            font-size: 0.88rem;
            box-sizing: border-box;
        }

        /* Botón guardar ficha médica */
        #formHistoriaClinica button[type="submit"] {
            width: 100% !important;
            max-width: 100% !important;
            padding: 14px 20px !important;
            font-size: 1rem !important;
            box-sizing: border-box;
        }

        /* Modales */
        .modal-box {
            width: 95% !important;
            max-width: 95% !important;
            padding: 16px 14px !important;
            margin: 10px auto;
            border-radius: 12px;
        }
        .signature-pad-canvas {
            height: 180px !important;
            width: 100% !important;
        }
        .modal-footer {
            flex-direction: column;
            width: 100%;
            gap: 8px;
        }
        .modal-footer .btn {
            width: 100%;
            justify-content: center;
        }

        /* Presupuestos: hide table, show cards */
        .presupuestos-table { display: none; }
        .presupuestos-cards { display: block !important; }
    }

    /* Presupuesto cards (mobile) */
    .presupuestos-cards { display: none; }

    .pres-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 10px;
        border-left: 4px solid var(--primary);
        transition: box-shadow 0.2s;
    }

    .pres-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.08); }

    .pres-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .pres-card-number {
        font-weight: 700;
        font-size: 0.95rem;
        color: #333;
    }

    .pres-card-estado {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .pres-card-estado.aprobado { background: rgba(196,162,126,0.15); color: var(--primary-dark); }
    .pres-card-estado.pagado { background: rgba(40,167,69,0.12); color: #28a745; }
    .pres-card-estado.pendiente { background: rgba(255,193,7,0.12); color: #d4a106; }
    .pres-card-estado.cancelado { background: rgba(220,53,69,0.12); color: #dc3545; }
    .pres-card-estado.borrador { background: rgba(108,117,125,0.12); color: #6c757d; }

    .pres-card-body {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pres-card-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .pres-card-date {
        font-size: 0.8rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .pres-card-total {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--primary);
    }

    .pres-card-action {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.85rem;
        transition: background 0.2s;
    }

    .pres-card-action:hover { background: var(--primary-dark); color: white; }

    /* Patologías Grid & Badges */
    .patologias-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 10px; margin-top: 10px; }
    .patologia-item { display: flex; align-items: center; gap: 9px; background: #fff; border: 1.5px solid #e2d1db; padding: 9px 14px; border-radius: 8px; font-size: 0.88rem; cursor: pointer; transition: all 0.2s; user-select: none; }
    .patologia-item:hover { background: #fbf2f6; border-color: var(--primary); }
    .patologia-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
    .patologia-item.checked { background: #f5e4ed; border-color: var(--primary); font-weight: 600; color: var(--primary-dark); }

    /* Examen columnas */
    .exam-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 850px) { .exam-columns { grid-template-columns: 1fr; } }
    .exam-box { background: #fcfcfc; border: 1.5px solid #ebdbe3; border-radius: 12px; padding: 18px; }
    .exam-box-title { font-size: 0.95rem; font-weight: 700; color: var(--primary); margin-top: 0; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #f0dfe7; display: flex; align-items: center; gap: 8px; }

    /* Radio / Check groups inline */
    .inline-checks { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; padding: 6px 0; }
    .inline-checks label { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; margin: 0; cursor: pointer; font-size: 0.9rem; color: #444; }
    .inline-checks input[type="radio"], .inline-checks input[type="checkbox"] { accent-color: var(--primary); width: 17px; height: 17px; }

    /* Firma box */
    .firma-card-body { display: flex; gap: 25px; align-items: center; flex-wrap: wrap; }
    .firma-preview-container { border: 2px dashed #b892a7; border-radius: 12px; padding: 15px; background: #faf5f8; text-align: center; min-width: 250px; min-height: 130px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
    .firma-preview-img { max-height: 110px; max-width: 230px; object-fit: contain; }
    .signature-pad-canvas { border: 2px dashed #003B73; border-radius: 8px; background: #ffffff; cursor: crosshair; touch-action: none; width: 100%; height: 210px; }
</style>

<div class="page-container">
    <!-- Header del paciente -->
    <div class="patient-header">
        <div class="patient-info">
            <div class="patient-avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
            <div>
                <h1 class="patient-name"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
                <div class="patient-phone"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($cliente['telefono']); ?></div>
            </div>
        </div>
        <div class="header-actions">
            <a href="imprimir_historia.php?id=<?php echo $clienteId; ?>" target="_blank" class="btn-header" style="background: rgba(255,255,255,0.25);" title="Imprimir o exportar PDF formato oficial">
                <i class="fas fa-print"></i> Formato Oficial
            </a>
            <button type="button" class="btn-header" onclick="abrirModalCompartirFirma()" style="background: #25D366; color: white;" title="Enviar enlace para firma en el celular del paciente">
                <i class="fab fa-whatsapp"></i> Enlace Paciente
            </button>
            <button type="button" class="btn-header" onclick="abrirModalFirmaDoctor()" style="background: #17a2b8; color: white;" title="Abrir recuadro táctil para firmar en este equipo o tablet">
                <i class="fas fa-signature"></i> Firmar en Pantalla
            </button>
            <a href="odontograma.php?cliente_id=<?php echo $clienteId; ?>" class="btn-header">
                <i class="fas fa-tooth"></i> Odontograma
            </a>
            <a href="lista_clientes.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alerta alerta-<?php echo $_SESSION['message_type'] ?? 'info'; ?>" style="margin-bottom: 15px;">
        <i class="fas fa-info-circle me-2"></i> <?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message'], $_SESSION['message_type']); ?>
    </div>
    <?php endif; ?>

    <!-- Alertas médicas -->
    <?php if (!empty($alertas)): ?>
    <div class="alertas-box">
        <?php foreach ($alertas as $alerta): ?>
        <div class="alerta alerta-<?php echo $alerta['tipo']; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($alerta['texto']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabs de navegación -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="cambiarTab('ficha')">
                <i class="fas fa-notes-medical"></i> Ficha Médica
            </button>
            <button class="tab-btn" onclick="cambiarTab('evoluciones')">
                <i class="fas fa-history"></i> Evoluciones
                <span class="badge"><?php echo count($evoluciones); ?></span>
            </button>
            <button class="tab-btn" onclick="cambiarTab('archivos')">
                <i class="fas fa-images"></i> Archivos
                <span class="badge"><?php echo count($archivos); ?></span>
            </button>
            <button class="tab-btn" onclick="cambiarTab('presupuestos')">
                <i class="fas fa-file-invoice-dollar"></i> Presupuestos
                <span class="badge"><?php echo count($presupuestos); ?></span>
            </button>
        </div>
    </div>

    <!-- Tab: Ficha Médica -->
    <div id="tab-ficha" class="tab-content active">
        <form method="POST" action="guardar_historia.php" id="formHistoriaClinica">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <!-- 1. Identificación y Datos Personales -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-id-card text-primary"></i> 1. Identificación y Datos Personales</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Cédula de Identidad (C.I.)</label>
                        <input type="text" name="ci" class="form-control" value="<?php echo htmlspecialchars($historia['ci'] ?? ''); ?>" placeholder="Ej: 8472910 LP">
                    </div>
                    <div class="form-group">
                        <label>Nº Historia Clínica</label>
                        <input type="text" name="numero_hc" class="form-control" value="<?php echo htmlspecialchars($historia['numero_hc'] ?? ('HC-' . str_pad($clienteId, 5, '0', STR_PAD_LEFT))); ?>" placeholder="Ej: HC-00045">
                    </div>
                    <div class="form-group">
                        <label>Codificación</label>
                        <input type="text" name="codificacion" class="form-control" value="<?php echo htmlspecialchars($historia['codificacion'] ?? ''); ?>" placeholder="Ej: COD-BO-2026">
                    </div>
                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" id="campo_fecha_nac" class="form-control" value="<?php echo $historia['fecha_nacimiento'] ?? ''; ?>" onchange="calcularEdadDesdeFecha(this.value)">
                    </div>
                    <div class="form-group">
                        <label>Edad (Años)</label>
                        <input type="number" name="edad" id="campo_edad" class="form-control" value="<?php echo $historia['edad'] ?? ''; ?>" min="0" max="125" placeholder="Calculado auto">
                    </div>
                    <div class="form-group">
                        <label>Sexo</label>
                        <select name="sexo" class="form-control">
                            <option value="">Seleccionar...</option>
                            <option value="F" <?php echo ($historia['sexo'] ?? '') === 'F' ? 'selected' : ''; ?>>Femenino</option>
                            <option value="M" <?php echo ($historia['sexo'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="Otro" <?php echo ($historia['sexo'] ?? '') === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Lugar de Nacimiento</label>
                        <input type="text" name="lugar_nacimiento" class="form-control" value="<?php echo htmlspecialchars($historia['lugar_nacimiento'] ?? ''); ?>" placeholder="Ciudad / Departamento">
                    </div>
                    <div class="form-group">
                        <label>Ocupación / Profesión</label>
                        <input type="text" name="ocupacion" class="form-control" value="<?php echo htmlspecialchars($historia['ocupacion'] ?? ''); ?>" placeholder="Ej: Estudiante, Ingeniero, etc.">
                    </div>
                    <div class="form-group">
                        <label>Email de Contacto</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($historia['email'] ?? ''); ?>" placeholder="paciente@correo.com">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Dirección de Domicilio</label>
                        <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($historia['direccion'] ?? ''); ?>" placeholder="Zona, calle, número o referencia">
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0e6ec;">
                    <h4 style="font-size: 0.95rem; color: #555; margin-bottom: 12px;"><i class="fas fa-phone-alt me-1"></i> Contacto de Emergencia</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" name="contacto_emergencia_nombre" class="form-control" value="<?php echo htmlspecialchars($historia['contacto_emergencia_nombre'] ?? ''); ?>" placeholder="Nombre de contacto">
                        </div>
                        <div class="form-group">
                            <label>Teléfono de Contacto</label>
                            <input type="text" name="contacto_emergencia_telefono" class="form-control" value="<?php echo htmlspecialchars($historia['contacto_emergencia_telefono'] ?? ''); ?>" placeholder="Número de llamada">
                        </div>
                        <div class="form-group">
                            <label>Parentesco / Relación</label>
                            <input type="text" name="contacto_emergencia_parentesco" class="form-control" value="<?php echo htmlspecialchars($historia['contacto_emergencia_parentesco'] ?? ''); ?>" placeholder="Ej: Madre, Padre, Esposo(a), Hijo(a)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Antecedentes Patológicos (Familiares y Personales) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-heartbeat text-danger"></i> 2. Antecedentes Patológicos (Familiares y Personales)</h3>
                </div>
                
                <div class="form-group">
                    <label>Antecedentes Familiares de Relevancia</label>
                    <textarea name="antecedentes_familiares" class="form-control" rows="2" placeholder="Diabetes, cardiopatías, hipertensión, cáncer u otras afecciones en padres, abuelos o hermanos..."><?php echo htmlspecialchars($historia['antecedentes_familiares'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top: 18px;">
                    <label style="font-weight: 700; color: #333; display: block; margin-bottom: 4px;">
                        <i class="fas fa-clipboard-check text-primary me-1"></i> Patologías Personales (Marque las que presente o haya presentado):
                    </label>
                    <div class="patologias-grid">
                        <?php
                        $catalogoPatologias = [
                            'anemia' => 'Anemia',
                            'cardiopatias' => 'Cardiopatías',
                            'chagas' => 'Chagas',
                            'asma' => 'Asma',
                            'diabetes' => 'Diabetes',
                            'problemas_renales' => 'Problemas Renales',
                            'enf_gastrica' => 'Enfermedad Gástrica / Úlcera',
                            'hepatitis' => 'Hepatitis',
                            'tuberculosis' => 'Tuberculosis',
                            'epilepsia' => 'Epilepsia',
                            'hipertension' => 'Hipertensión Arterial',
                            'vih' => 'VIH / SIDA',
                            'problemas_coagulacion' => 'Problemas de Coagulación',
                            'otros' => 'Otras Afecciones'
                        ];
                        foreach ($catalogoPatologias as $clave => $nombre):
                            $checked = in_array($clave, $patologiasSeleccionadas);
                        ?>
                        <label class="patologia-item <?php echo $checked ? 'checked' : ''; ?>">
                            <input type="checkbox" name="patologias[]" value="<?php echo $clave; ?>" <?php echo $checked ? 'checked' : ''; ?> onchange="this.parentElement.classList.toggle('checked', this.checked)">
                            <span><?php echo $nombre; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 20px;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>¿Se encuentra actualmente bajo algún tratamiento médico?</label>
                        <input type="text" name="en_tratamiento_medico" class="form-control" value="<?php echo htmlspecialchars($historia['en_tratamiento_medico'] ?? ''); ?>" placeholder="Indique si está en tratamiento y el motivo">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>¿Toma algún medicamento actualmente? (Detalle nombres y dosis):</label>
                        <textarea name="toma_medicamento" class="form-control" rows="2" placeholder="Nombres de medicamentos, anticoagulantes, antihipertensivos, antibióticos, etc."><?php echo htmlspecialchars($historia['toma_medicamento'] ?? ($historia['medicamentos_actuales'] ?? '')); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Hemorragia anormal en extracciones dentales previas</label>
                        <select name="hemorragia_extraccion" class="form-control">
                            <option value="No" <?php echo ($historia['hemorragia_extraccion'] ?? 'No') === 'No' ? 'selected' : ''; ?>>No</option>
                            <option value="Inmediata" <?php echo ($historia['hemorragia_extraccion'] ?? '') === 'Inmediata' ? 'selected' : ''; ?>>Sí - Inmediata</option>
                            <option value="Mediata" <?php echo ($historia['hemorragia_extraccion'] ?? '') === 'Mediata' ? 'selected' : ''; ?>>Sí - Mediata (tardía)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Grupo Sanguíneo</label>
                        <select name="grupo_sanguineo" class="form-control">
                            <option value="">Desconocido</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $gs): ?>
                            <option value="<?php echo $gs; ?>" <?php echo ($historia['grupo_sanguineo'] ?? '') === $gs ? 'selected' : ''; ?>><?php echo $gs; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><i class="fas fa-exclamation-triangle text-danger me-1"></i> Alergias Conocidas (Medicamentos, anestésicos, látex, etc.)</label>
                        <textarea name="alergias" class="form-control" rows="2" placeholder="Penicilina, anestesia local, analgésicos, látex, etc."><?php echo htmlspecialchars($historia['alergias'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Cirugías Previas</label>
                        <input type="text" name="cirugias_previas" class="form-control" value="<?php echo htmlspecialchars($historia['cirugias_previas'] ?? ''); ?>" placeholder="Cirugías que haya tenido">
                    </div>
                    <div class="form-group">
                        <label>Hospitalizaciones Previas</label>
                        <input type="text" name="hospitalizaciones" class="form-control" value="<?php echo htmlspecialchars($historia['hospitalizaciones'] ?? ''); ?>" placeholder="Causas de hospitalización">
                    </div>
                </div>

                <div class="inline-checks" style="margin-top: 10px; background: #fff5f8; border-radius: 8px; padding: 12px 15px;">
                    <label>
                        <input type="checkbox" name="embarazo" value="1" <?php echo ($historia['embarazo'] ?? 0) ? 'checked' : ''; ?>>
                        <i class="fas fa-baby me-1 text-primary"></i> Paciente embarazada
                    </label>
                    <label>
                        <input type="checkbox" name="lactancia" value="1" <?php echo ($historia['lactancia'] ?? 0) ? 'checked' : ''; ?>>
                        <i class="fas fa-female me-1 text-primary"></i> En período de lactancia
                    </label>
                </div>
            </div>

            <!-- 3. Examen Estomatognático (Extraoral e Intraoral) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-stethoscope text-primary"></i> 3. Examen Estomatognático (Extraoral e Intraoral)</h3>
                </div>
                <div class="exam-columns">
                    <!-- Columna Extraoral -->
                    <div class="exam-box">
                        <div class="exam-box-title">
                            <i class="fas fa-head-side-mask"></i> Examen Extraoral
                        </div>
                        <div class="form-group">
                            <label>Articulación Temporomandibular (ATM)</label>
                            <input type="text" name="atm" class="form-control" value="<?php echo htmlspecialchars($historia['atm'] ?? 'Aparentemente normal'); ?>" placeholder="Normal, chasquido, dolor, subluxación...">
                        </div>
                        <div class="form-group">
                            <label>Ganglios Linfáticos</label>
                            <input type="text" name="ganglios_linfaticos" class="form-control" value="<?php echo htmlspecialchars($historia['ganglios_linfaticos'] ?? 'No palpables'); ?>" placeholder="No palpables, inflamados, indurados...">
                        </div>
                        <div class="form-group">
                            <label>Tipo de Respirador</label>
                            <select name="tipo_respirador" class="form-control">
                                <option value="Nasal" <?php echo ($historia['tipo_respirador'] ?? 'Nasal') === 'Nasal' ? 'selected' : ''; ?>>Nasal</option>
                                <option value="Bucal" <?php echo ($historia['tipo_respirador'] ?? '') === 'Bucal' ? 'selected' : ''; ?>>Bucal</option>
                                <option value="Mixto" <?php echo ($historia['tipo_respirador'] ?? '') === 'Mixto' ? 'selected' : ''; ?>>Mixto</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Otros Hallazgos Extraorales</label>
                            <textarea name="examen_extraoral_otros" class="form-control" rows="2" placeholder="Facies, asimetrías, lesiones en piel..."><?php echo htmlspecialchars($historia['examen_extraoral_otros'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Columna Intraoral -->
                    <div class="exam-box">
                        <div class="exam-box-title">
                            <i class="fas fa-teeth-open"></i> Examen Intraoral
                        </div>
                        <div class="form-group">
                            <label>Labios</label>
                            <input type="text" name="labios" class="form-control" value="<?php echo htmlspecialchars($historia['labios'] ?? 'Aparentemente normales'); ?>" placeholder="Normales, secos, queilitis...">
                        </div>
                        <div class="form-group">
                            <label>Lengua</label>
                            <input type="text" name="lengua" class="form-control" value="<?php echo htmlspecialchars($historia['lengua'] ?? 'Aparentemente normal'); ?>" placeholder="Normal, saburral, geográfica...">
                        </div>
                        <div class="form-group">
                            <label>Paladar (Duro y Blando)</label>
                            <input type="text" name="paladar" class="form-control" value="<?php echo htmlspecialchars($historia['paladar'] ?? 'Aparentemente normal'); ?>" placeholder="Normal, ojival, fisurado...">
                        </div>
                        <div class="form-group">
                            <label>Piso de Boca</label>
                            <input type="text" name="piso_boca" class="form-control" value="<?php echo htmlspecialchars($historia['piso_boca'] ?? 'Aparentemente normal'); ?>" placeholder="Normal, torus, ránula...">
                        </div>
                        <div class="form-group">
                            <label>Mucosa Yugal</label>
                            <input type="text" name="mucosa_yugal" class="form-control" value="<?php echo htmlspecialchars($historia['mucosa_yugal'] ?? 'Normal'); ?>" placeholder="Normal, aftas, línea alba...">
                        </div>
                        <div class="form-group">
                            <label>Encías</label>
                            <input type="text" name="encias" class="form-control" value="<?php echo htmlspecialchars($historia['encias'] ?? 'Sanas'); ?>" placeholder="Sanas, gingivitis, periodontitis...">
                        </div>
                        <div class="form-group">
                            <label>¿Usa Prótesis Dental?</label>
                            <select name="usa_protesis" class="form-control">
                                <option value="0" <?php echo ($historia['usa_protesis'] ?? 0) == 0 ? 'selected' : ''; ?>>No</option>
                                <option value="1" <?php echo ($historia['usa_protesis'] ?? 0) == 1 ? 'selected' : ''; ?>>Sí - Prótesis Removible</option>
                                <option value="2" <?php echo ($historia['usa_protesis'] ?? 0) == 2 ? 'selected' : ''; ?>>Sí - Prótesis Fija</option>
                                <option value="3" <?php echo ($historia['usa_protesis'] ?? 0) == 3 ? 'selected' : ''; ?>>Sí - Prótesis Total</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Higiene Bucodental y Hábitos -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-smile-beam text-primary"></i> 4. Higiene Bucodental y Hábitos</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Elementos de Higiene Bucal</label>
                        <div class="inline-checks">
                            <label><input type="checkbox" name="usa_cepillo" value="1" <?php echo ($historia['usa_cepillo'] ?? 1) ? 'checked' : ''; ?>> Cepillo</label>
                            <label><input type="checkbox" name="usa_hilo" value="1" <?php echo ($historia['usa_hilo'] ?? 0) ? 'checked' : ''; ?>> Hilo Dental</label>
                            <label><input type="checkbox" name="usa_enjuague" value="1" <?php echo ($historia['usa_enjuague'] ?? 0) ? 'checked' : ''; ?>> Enjuague</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Frecuencia de Cepillado</label>
                        <select name="frecuencia_cepillado" class="form-control">
                            <option value="">Seleccionar...</option>
                            <option value="1 vez al día" <?php echo ($historia['frecuencia_cepillado'] ?? '') === '1 vez al día' ? 'selected' : ''; ?>>1 vez al día</option>
                            <option value="2 veces al día" <?php echo ($historia['frecuencia_cepillado'] ?? '') === '2 veces al día' ? 'selected' : ''; ?>>2 veces al día</option>
                            <option value="3 o más veces al día" <?php echo ($historia['frecuencia_cepillado'] ?? '') === '3 o más veces al día' ? 'selected' : ''; ?>>3 o más veces al día</option>
                            <option value="Ocasional" <?php echo ($historia['frecuencia_cepillado'] ?? '') === 'Ocasional' ? 'selected' : ''; ?>>Ocasional</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sangrado de Encías al Cepillarse</label>
                        <select name="sangrado_encias" class="form-control">
                            <option value="0" <?php echo ($historia['sangrado_encias'] ?? 0) == 0 ? 'selected' : ''; ?>>No presenta sangrado</option>
                            <option value="1" <?php echo ($historia['sangrado_encias'] ?? 0) == 1 ? 'selected' : ''; ?>>Sí presenta sangrado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nivel de Higiene Bucal Evaluado</label>
                        <select name="nivel_higiene_bucal" class="form-control">
                            <option value="Buena" <?php echo ($historia['nivel_higiene_bucal'] ?? 'Buena') === 'Buena' ? 'selected' : ''; ?>>Buena</option>
                            <option value="Regular" <?php echo ($historia['nivel_higiene_bucal'] ?? '') === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                            <option value="Mala" <?php echo ($historia['nivel_higiene_bucal'] ?? '') === 'Mala' ? 'selected' : ''; ?>>Mala</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hábitos de Consumo</label>
                        <div class="inline-checks">
                            <label><input type="checkbox" name="habitos_fuma" value="1" <?php echo ($historia['habitos_fuma'] ?? 0) ? 'checked' : ''; ?>> <i class="fas fa-smoking me-1"></i> Fuma tabaco</label>
                            <label><input type="checkbox" name="habitos_bebe" value="1" <?php echo ($historia['habitos_bebe'] ?? 0) ? 'checked' : ''; ?>> <i class="fas fa-wine-bottle me-1"></i> Consume alcohol</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Otros Hábitos (Bruxismo, onicofagia, otros)</label>
                        <input type="text" name="habitos_otros" class="form-control" value="<?php echo htmlspecialchars($historia['habitos_otros'] ?? ($historia['habitos'] ?? '')); ?>" placeholder="Ej: Bruxismo nocturno, morder bolígrafos...">
                    </div>
                    <div class="form-group">
                        <label>Última Visita al Odontólogo</label>
                        <input type="date" name="ultima_visita_dentista" class="form-control" value="<?php echo $historia['ultima_visita_dentista'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Experiencia con Anestesia Dental</label>
                        <input type="text" name="experiencia_anestesia" class="form-control" value="<?php echo htmlspecialchars($historia['experiencia_anestesia'] ?? ''); ?>" placeholder="Normal, mareos, hipotensión, dolor...">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Problemas o Complicaciones Dentales Previas Graves</label>
                        <textarea name="problema_grave_dental_anterior" class="form-control" rows="2" placeholder="Infecciones severas, alvéolo seco, dificultad de cicatrización..."><?php echo htmlspecialchars($historia['problema_grave_dental_anterior'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- 5. Consulta Clínica, Diagnóstico y Tratamiento -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-notes-medical text-primary"></i> 5. Consulta Clínica, Diagnóstico y Plan de Tratamiento</h3>
                </div>
                <div class="form-group">
                    <label style="font-weight: 700;">Motivo de Consulta (Palabras del Paciente):</label>
                    <textarea name="motivo_consulta" class="form-control" rows="2" placeholder="Refiere dolor en molar, revisión periódica, limpieza, etc."><?php echo htmlspecialchars($historia['motivo_consulta'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label style="font-weight: 700;">Examen Clínico Odontológico:</label>
                    <textarea name="examen_clinico" class="form-control" rows="3" placeholder="Hallazgos clínicos observados en la cavidad oral, piezas con caries, movilidad..."><?php echo htmlspecialchars($historia['examen_clinico'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label style="font-weight: 700;">Diagnóstico Clínico Presuntivo / Definitivo:</label>
                    <textarea name="diagnostico" class="form-control" rows="2" placeholder="Diagnóstico odontológico establecido..."><?php echo htmlspecialchars($historia['diagnostico'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label style="font-weight: 700;">Plan de Tratamiento Sugerido:</label>
                    <textarea name="plan_tratamiento" class="form-control" rows="2" placeholder="Profilaxis, restauraciones, endodoncia, prótesis, extracciones..."><?php echo htmlspecialchars($historia['plan_tratamiento'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Observaciones Adicionales</label>
                    <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas internas sobre el paciente o el caso..."><?php echo htmlspecialchars($historia['observaciones'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- 6. Declaración Jurada y Firma Digital del Paciente -->
            <div class="card" style="border-left: 5px solid var(--primary);">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-file-signature text-primary"></i> 6. Declaración Jurada y Firma Digital del Paciente</h3>
                </div>
                
                <div style="background: #F4F9FD; border: 1px solid #ead3e1; border-radius: 10px; padding: 15px; margin-bottom: 20px; font-size: 0.88rem; line-height: 1.5; color: #4a2839;">
                    <i class="fas fa-balance-scale me-1 text-primary"></i>
                    <strong>Declaración Legal:</strong> Declaro bajo juramento que los datos personales y antecedentes de salud consignados en la presente historia clínica son verídicos y fidedignos, no habiendo omitido información relevante sobre mi estado de salud o tratamientos en curso. Asimismo, autorizo al profesional odontólogo tratante a realizar los exámenes clínicos, radiológicos y procedimientos odontológicos pertinentes a mi diagnóstico y tratamiento.
                </div>

                <div class="firma-card-body">
                    <div class="firma-preview-container">
                        <?php if (!empty($historia['firma_paciente'])): ?>
                            <div style="margin-bottom: 8px;">
                                <span class="badge" style="background: #28a745; color: white; padding: 6px 12px; font-size: 0.82rem; border-radius: 20px;">
                                    <i class="fas fa-check-circle me-1"></i> Firmado Digitalmente
                                </span>
                            </div>
                            <img src="<?php echo $historia['firma_paciente']; ?>" alt="Firma del Paciente" class="firma-preview-img" id="imgFirmaPreview">
                            <div style="font-size: 0.78rem; color: #777; margin-top: 6px;" id="lblFechaFirma">
                                <i class="fas fa-calendar-alt me-1"></i> <?php echo !empty($historia['fecha_firma']) ? date('d/m/Y H:i', strtotime($historia['fecha_firma'])) : 'Registrado'; ?>
                            </div>
                        <?php else: ?>
                            <i class="fas fa-file-signature" style="font-size: 3rem; color: #d69db8; margin-bottom: 10px;"></i>
                            <div style="color: #888; font-size: 0.9rem; font-weight: 600;">Pendiente de Firma</div>
                            <div style="color: #aaa; font-size: 0.78rem;">El paciente aún no ha firmado esta ficha</div>
                        <?php endif; ?>
                    </div>

                    <div style="flex: 1; min-width: 250px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 1rem; color: #333;">Acciones de Firma:</h4>
                        <p style="font-size: 0.88rem; color: #666; margin-bottom: 15px;">
                            Puede registrar la firma digital directamente en pantalla (usando tablet o ratón en consulta) o enviar un enlace seguro para que el paciente firme desde su teléfono celular.
                        </p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" class="btn" style="background: #17a2b8; color: white;" onclick="abrirModalFirmaDoctor()">
                                <i class="fas fa-pen-fancy me-1"></i> <?php echo !empty($historia['firma_paciente']) ? 'Cambiar / Re-firmar en Pantalla' : 'Firmar Ahora en Pantalla'; ?>
                            </button>
                            <button type="button" class="btn" style="background: #25D366; color: white;" onclick="abrirModalCompartirFirma()">
                                <i class="fab fa-whatsapp me-1"></i> Compartir Enlace al Paciente
                            </button>
                            <a href="imprimir_historia.php?id=<?php echo $clienteId; ?>" target="_blank" class="btn" style="background: #6c757d; color: white;">
                                <i class="fas fa-print me-1"></i> Imprimir Formato Oficial
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón Guardar Historia Clínica -->
            <div style="text-align: center; margin: 30px 0 20px 0;">
                <button type="submit" class="btn btn-primary" style="padding: 14px 40px; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(0, 59, 115,0.35);">
                    <i class="fas fa-save me-2"></i> Guardar Ficha Médica Completa
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Evoluciones -->
    <div id="tab-evoluciones" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Historial de Atenciones</h3>
                <button class="btn btn-primary" onclick="abrirModalEvolucion()">
                    <i class="fas fa-plus"></i> Nueva Evolución
                </button>
            </div>

            <?php if (empty($evoluciones)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h4>Sin evoluciones registradas</h4>
                <p>Registra la primera nota de evolución clínica</p>
            </div>
            <?php else: ?>
            <div class="timeline">
                <?php foreach ($evoluciones as $evo): ?>
                <div class="timeline-item">
                    <div class="timeline-date">
                        <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y H:i', strtotime($evo['fecha_atencion'])); ?>
                    </div>
                    <div class="timeline-card">
                        <div class="timeline-title"><?php echo htmlspecialchars($evo['motivo_consulta']); ?></div>
                        <div class="timeline-content">
                            <?php if ($evo['diagnostico']): ?>
                            <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($evo['diagnostico']); ?></p>
                            <?php endif; ?>
                            <p><strong>Tratamiento:</strong> <?php echo htmlspecialchars($evo['tratamiento_realizado']); ?></p>
                            <?php if ($evo['dientes_tratados']): ?>
                            <p><strong>Dientes:</strong> <?php echo htmlspecialchars($evo['dientes_tratados']); ?></p>
                            <?php endif; ?>
                            <?php if ($evo['indicaciones_paciente']): ?>
                            <p><strong>Indicaciones:</strong> <?php echo htmlspecialchars($evo['indicaciones_paciente']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($evo['doctor_nombre']): ?>
                        <div class="timeline-doctor"><i class="fas fa-user-md me-1"></i><?php echo $evo['doctor_nombre']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Archivos -->
    <div id="tab-archivos" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-images"></i> Archivos Clínicos</h3>
                <button class="btn btn-primary" onclick="abrirModalArchivo()">
                    <i class="fas fa-upload"></i> Subir Archivo
                </button>
            </div>

            <?php if (empty($archivos)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h4>Sin archivos</h4>
                <p>Sube radiografías, fotos intraorales o documentos</p>
            </div>
            <?php else: ?>
            <div class="archivos-grid">
                <?php foreach ($archivos as $archivo): ?>
                <div class="archivo-card">
                    <?php
                    $iconos = [
                        'radiografia' => 'fa-x-ray',
                        'foto_intraoral' => 'fa-camera',
                        'foto_extraoral' => 'fa-portrait',
                        'documento' => 'fa-file-pdf',
                        'otro' => 'fa-file'
                    ];
                    $icono = $iconos[$archivo['tipo']] ?? 'fa-file';
                    ?>
                    <div class="archivo-icon"><i class="fas <?php echo $icono; ?>"></i></div>
                    <div class="archivo-nombre"><?php echo htmlspecialchars($archivo['descripcion'] ?: $archivo['nombre_archivo']); ?></div>
                    <div class="archivo-fecha"><?php echo date('d/m/Y', strtotime($archivo['created_at'])); ?></div>
                    <div class="d-flex justify-content-center gap-1 mt-2 flex-wrap">
                        <button onclick="verArchivoClinico('<?php echo $archivo['ruta_archivo']; ?>', '<?php echo $archivo['extension']; ?>')" class="btn btn-sm btn-primary" title="Ver Archivo">
                            <i class="fas fa-eye"></i> Ver
                        </button>
                        <button onclick="abrirModalRenombrarArchivo(<?php echo $archivo['id']; ?>, '<?php echo htmlspecialchars($archivo['descripcion'] ?: $archivo['nombre_archivo'], ENT_QUOTES); ?>', '<?php echo $archivo['tipo']; ?>', '<?php echo $archivo['fecha_toma']; ?>')" class="btn btn-sm btn-warning text-white" title="Renombrar / Editar">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button onclick="eliminarArchivoClinico(<?php echo $archivo['id']; ?>, <?php echo $clienteId; ?>)" class="btn btn-sm btn-danger" title="Eliminar Archivo">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Presupuestos -->
    <div id="tab-presupuestos" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Presupuestos del Paciente</h3>
                <a href="crear_presupuesto.php?cliente=<?php echo $clienteId; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Presupuesto
                </a>
            </div>

            <?php if (empty($presupuestos)): ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <h4>Sin presupuestos</h4>
                <p>Crea un presupuesto para este paciente</p>
            </div>
            <?php else: ?>
            <div class="presupuestos-table">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Número</th>
                        <th style="padding: 12px; text-align: left;">Fecha</th>
                        <th style="padding: 12px; text-align: right;">Total</th>
                        <th style="padding: 12px; text-align: center;">Estado</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presupuestos as $pres): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px;"><strong><?php echo $pres['numero']; ?></strong></td>
                        <td style="padding: 12px;"><?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></td>
                        <td style="padding: 12px; text-align: right; font-weight: 700; color: var(--primary);">Bs <?php echo number_format($pres['total'], 2); ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; background: #e9ecef;"><?php echo ucfirst($pres['estado']); ?></span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="ver_presupuesto.php?id=<?php echo $pres['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Mobile cards -->
            <div class="presupuestos-cards">
                <?php foreach ($presupuestos as $pres): ?>
                <div class="pres-card">
                    <div class="pres-card-header">
                        <span class="pres-card-number"><i class="fas fa-file-invoice" style="color:var(--primary);margin-right:5px;"></i><?php echo $pres['numero']; ?></span>
                        <span class="pres-card-estado <?php echo strtolower($pres['estado']); ?>"><?php echo ucfirst($pres['estado']); ?></span>
                    </div>
                    <div class="pres-card-body">
                        <div class="pres-card-info">
                            <span class="pres-card-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></span>
                            <span class="pres-card-total">Bs <?php echo number_format($pres['total'], 2); ?></span>
                        </div>
                        <a href="ver_presupuesto.php?id=<?php echo $pres['id']; ?>" class="pres-card-action" title="Ver presupuesto">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Nueva Evolución -->
<div class="modal-overlay" id="modalEvolucion">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-plus-circle me-2"></i>Nueva Evolución Clínica</h4>
            <button class="modal-close" onclick="cerrarModalEvolucion()">&times;</button>
        </div>
        <form method="POST" action="guardar_evolucion.php">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" class="form-control">
                    <option value="">Sin asignar</option>
                    <?php foreach ($doctores as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Motivo de Consulta *</label>
                <input type="text" name="motivo_consulta" class="form-control" required placeholder="Ej: Dolor en muela 36">
            </div>
            <div class="form-group">
                <label>Diagnóstico</label>
                <textarea name="diagnostico" class="form-control" rows="2" placeholder="Diagnóstico clínico..."></textarea>
            </div>
            <div class="form-group">
                <label>Tratamiento Realizado *</label>
                <textarea name="tratamiento_realizado" class="form-control" rows="3" required placeholder="Describir el procedimiento realizado..."></textarea>
            </div>
            <div class="form-group">
                <label>Dientes Tratados</label>
                <input type="text" name="dientes_tratados" class="form-control" placeholder="Ej: 36, 37">
            </div>
            <div class="form-group">
                <label>Indicaciones al Paciente</label>
                <textarea name="indicaciones_paciente" class="form-control" rows="2" placeholder="Recomendaciones post-tratamiento..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalEvolucion()">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Subir Archivo -->
<div class="modal-overlay" id="modalArchivo">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-upload me-2"></i>Subir Archivo</h4>
            <button class="modal-close" onclick="cerrarModalArchivo()">&times;</button>
        </div>
        <form method="POST" action="subir_archivo_clinico.php" enctype="multipart/form-data">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="form-group">
                <label>Tipo de Archivo *</label>
                <select name="tipo" class="form-control" required>
                    <option value="radiografia">Radiografía</option>
                    <option value="foto_intraoral">Foto Intraoral</option>
                    <option value="foto_extraoral">Foto Extraoral</option>
                    <option value="documento">Documento</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Archivo * <small style="color: #888;">(Solo PDF, JPG, PNG, GIF, WebP - Máx 5MB)</small></label>
                <input type="file" name="archivo" class="form-control" required accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <input type="text" name="descripcion" class="form-control" placeholder="Ej: Radiografía periapical diente 36">
            </div>
            <div class="form-group">
                <label>Fecha de Toma</label>
                <input type="date" name="fecha_toma" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalArchivo()">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i> Subir</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Renombrar/Editar Archivo -->
<div class="modal-overlay" id="modalRenombrarArchivo">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-pen me-2"></i>Renombrar / Editar Archivo</h4>
            <button class="modal-close" onclick="cerrarModalRenombrarArchivo()">&times;</button>
        </div>
        <form method="POST" action="renombrar_archivo_clinico.php">
            <input type="hidden" name="id" id="edit_archivo_id">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="form-group">
                <label>Nombre / Descripción del Archivo *</label>
                <input type="text" name="descripcion" id="edit_archivo_descripcion" class="form-control" required placeholder="Ej: Radiografía muela 36">
            </div>

            <div class="form-group">
                <label>Tipo de Archivo</label>
                <select name="tipo" id="edit_archivo_tipo" class="form-control">
                    <option value="radiografia">Radiografía</option>
                    <option value="foto_intraoral">Foto Intraoral</option>
                    <option value="foto_extraoral">Foto Extraoral</option>
                    <option value="documento">Documento</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="form-group">
                <label>Fecha de Toma</label>
                <input type="date" name="fecha_toma" id="edit_archivo_fecha" class="form-control">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalRenombrarArchivo()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
// Cambiar tabs
function cambiarTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelector(`[onclick="cambiarTab('${tabId}')"]`).classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
}

// Modales
function abrirModalEvolucion() { document.getElementById('modalEvolucion').classList.add('show'); }
function cerrarModalEvolucion() { document.getElementById('modalEvolucion').classList.remove('show'); }
function abrirModalArchivo() { document.getElementById('modalArchivo').classList.add('show'); }
function cerrarModalArchivo() { document.getElementById('modalArchivo').classList.remove('show'); }
function abrirModalRenombrarArchivo(id, descripcion, tipo, fecha) {
    document.getElementById('edit_archivo_id').value = id;
    document.getElementById('edit_archivo_descripcion').value = descripcion;
    document.getElementById('edit_archivo_tipo').value = tipo || 'otro';
    document.getElementById('edit_archivo_fecha').value = fecha || '';
    document.getElementById('modalRenombrarArchivo').classList.add('show');
}
function cerrarModalRenombrarArchivo() {
    document.getElementById('modalRenombrarArchivo').classList.remove('show');
}
function eliminarArchivoClinico(id, clienteId) {
    if (confirm('¿Está seguro de eliminar este archivo clínico?\n\nEsta acción eliminará el archivo del servidor de forma permanente.')) {
        window.location.href = 'eliminar_archivo_clinico.php?id=' + id + '&cliente_id=' + clienteId;
    }
}

// Cerrar con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        cerrarModalEvolucion();
        cerrarModalArchivo();
        cerrarModalRenombrarArchivo();
        cerrarModalVerArchivo();
    }
});

// Cerrar al hacer clic fuera (fondo/overlay)
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            if (this.id === 'modalVerArchivo') cerrarModalVerArchivo();
            else if (this.id === 'modalEvolucion') cerrarModalEvolucion();
            else if (this.id === 'modalArchivo') cerrarModalArchivo();
            else this.classList.remove('show');
        }
    });
});

// Validación de archivos
document.querySelector('input[name="archivo"]')?.addEventListener('change', function(e) {
    const file = this.files[0];
    if (!file) return;
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    const extension = file.name.split('.').pop().toLowerCase();
    let errorMsg = '';
    
    if (!allowedExtensions.includes(extension)) {
        errorMsg = '❌ Tipo de archivo no permitido.\n\nSolo se aceptan: PDF, JPG, PNG, GIF, WebP';
    } else if (file.size > maxSize) {
        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
        errorMsg = `❌ El archivo es muy grande (${sizeMB} MB).\n\nEl tamaño máximo permitido es 5 MB`;
    }
    
    if (errorMsg) {
        alert(errorMsg);
        this.value = ''; // Limpiar el input
    }
});

// Ver Archivo Clínico en Modal (Diseño unificado con pagos.php)
function verArchivoClinico(ruta, extension) {
    const modal = document.getElementById('modalVerArchivo');
    const contenido = document.getElementById('contenidoVerArchivo');
    
    extension = extension.toLowerCase();
    
    if (extension === 'pdf') {
        contenido.innerHTML = `
            <iframe src="${ruta}" style="width: 100%; height: 75vh; border: none; border-radius: 8px;"></iframe>
            <div style="margin-top: 15px; text-align: center;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600;">
                    <i class="fas fa-download me-2"></i>Descargar PDF
                </a>
            </div>
        `;
    } else {
        contenido.innerHTML = `
            <img src="${ruta}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);" alt="Archivo Clínico">
            <div style="margin-top: 15px; text-align: center;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600;">
                    <i class="fas fa-download me-2"></i>Descargar Imagen
                </a>
            </div>
        `;
    }
    
    modal.classList.add('show');
}

function cerrarModalVerArchivo() {
    document.getElementById('modalVerArchivo').classList.remove('show');
    setTimeout(() => {
        document.getElementById('contenidoVerArchivo').innerHTML = '';
    }, 300);
}

// Asegurar cierre al hacer clic fuera para todos los modales
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const id = e.target.id;
        if (id === 'modalVerArchivo') cerrarModalVerArchivo();
        else if (id === 'modalEvolucion') cerrarModalEvolucion();
        else if (id === 'modalArchivo') cerrarModalArchivo();
        else if (id === 'modalFirmaDoctor') cerrarModalFirmaDoctor();
        else if (id === 'modalCompartirFirma') cerrarModalCompartirFirma();
        else e.target.classList.remove('show');
    }
});

// Función para calcular edad desde la fecha de nacimiento
function calcularEdadDesdeFecha(fechaStr) {
    if (!fechaStr) return;
    const nacimiento = new Date(fechaStr);
    const hoy = new Date();
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const mes = hoy.getMonth() - nacimiento.getMonth();
    if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }
    if (edad >= 0 && edad <= 125) {
        const campoEdad = document.getElementById('campo_edad');
        if (campoEdad) campoEdad.value = edad;
    }
}

// ----------------------------------------------------
// Lógica de Firma Digital en Pantalla (Canvas)
// ----------------------------------------------------
let canvasFirma = null;
let ctxFirma = null;
let dibujando = false;
let tieneTrazos = false;

function initCanvasFirma() {
    canvasFirma = document.getElementById('canvasFirmaDoctor');
    if (!canvasFirma) return;
    
    ctxFirma = canvasFirma.getContext('2d');
    
    // Ajustar resolución interna para que la firma sea nítida en pantallas Retina/móviles
    const rect = canvasFirma.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvasFirma.width = rect.width * dpr;
    canvasFirma.height = rect.height * dpr;
    ctxFirma.scale(dpr, dpr);
    
    ctxFirma.strokeStyle = '#000066';
    ctxFirma.lineWidth = 2.5;
    ctxFirma.lineCap = 'round';
    ctxFirma.lineJoin = 'round';

    function getPos(e) {
        const r = canvasFirma.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: clientX - r.left,
            y: clientY - r.top
        };
    }

    function empezarTrazo(e) {
        e.preventDefault();
        dibujando = true;
        const pos = getPos(e);
        ctxFirma.beginPath();
        ctxFirma.moveTo(pos.x, pos.y);
    }

    function dibujarTrazo(e) {
        if (!dibujando) return;
        e.preventDefault();
        tieneTrazos = true;
        const pos = getPos(e);
        ctxFirma.lineTo(pos.x, pos.y);
        ctxFirma.stroke();
    }

    function terminarTrazo(e) {
        if (!dibujando) return;
        e.preventDefault();
        dibujando = false;
    }

    // Eventos Mouse y Touch / Pointer
    canvasFirma.addEventListener('mousedown', empezarTrazo);
    canvasFirma.addEventListener('mousemove', dibujarTrazo);
    window.addEventListener('mouseup', terminarTrazo);

    canvasFirma.addEventListener('touchstart', empezarTrazo, { passive: false });
    canvasFirma.addEventListener('touchmove', dibujarTrazo, { passive: false });
    canvasFirma.addEventListener('touchend', terminarTrazo);
}

function abrirModalFirmaDoctor() {
    document.getElementById('modalFirmaDoctor').classList.add('show');
    setTimeout(() => {
        initCanvasFirma();
        limpiarFirmaDoctor();
    }, 150);
}

function cerrarModalFirmaDoctor() {
    document.getElementById('modalFirmaDoctor').classList.remove('show');
}

function limpiarFirmaDoctor() {
    if (!canvasFirma || !ctxFirma) return;
    const dpr = window.devicePixelRatio || 1;
    ctxFirma.save();
    ctxFirma.setTransform(1, 0, 0, 1, 0, 0);
    ctxFirma.clearRect(0, 0, canvasFirma.width, canvasFirma.height);
    ctxFirma.restore();
    tieneTrazos = false;
}

function guardarFirmaDoctor() {
    if (!tieneTrazos) {
        alert('Por favor realice su firma en el recuadro antes de guardar.');
        return;
    }
    
    const btnGuardar = document.getElementById('btnGuardarFirmaDoctor');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';

    const dataURL = canvasFirma.toDataURL('image/png');
    const ciVal = document.getElementById('doc_ci_firma')?.value || '';

    const formData = new FormData();
    formData.append('ajax', '1');
    formData.append('solo_firma', '1');
    formData.append('cliente_id', '<?php echo $clienteId; ?>');
    formData.append('firma_paciente', dataURL);
    formData.append('ci', ciVal);

    fetch('guardar_historia.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-check me-1"></i> Guardar Firma';
        
        if (res.ok) {
            alert('✅ Firma registrada y guardada exitosamente.');
            cerrarModalFirmaDoctor();
            window.location.reload();
        } else {
            alert('❌ Error al guardar firma: ' + (res.error || 'Intente nuevamente.'));
        }
    })
    .catch(err => {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fas fa-check me-1"></i> Guardar Firma';
        alert('❌ Error de conexión al guardar la firma.');
    });
}

// ----------------------------------------------------
// Compartir enlace de firma
// ----------------------------------------------------
function abrirModalCompartirFirma() {
    document.getElementById('modalCompartirFirma').classList.add('show');
}

function cerrarModalCompartirFirma() {
    document.getElementById('modalCompartirFirma').classList.remove('show');
}

function copiarEnlaceFirma() {
    const input = document.getElementById('inputLinkFirma');
    if (!input) return;
    
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value).then(() => {
        const btn = document.getElementById('btnCopiarEnlaceFirma');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i> ¡Copiado!';
        btn.style.background = '#28a745';
        setTimeout(() => {
            btn.innerHTML = orig;
            btn.style.background = 'var(--primary)';
        }, 2000);
    }).catch(() => {
        document.execCommand('copy');
        alert('Enlace copiado al portapapeles');
    });
}
</script>

<!-- Modal para Firma en Pantalla (Tablet / Doctor) -->
<div class="modal-overlay" id="modalFirmaDoctor">
    <div class="modal-box" style="max-width: 650px; width: 95%;">
        <div class="modal-header">
            <h4 style="color: var(--primary);"><i class="fas fa-signature me-2"></i>Firma Digital del Paciente</h4>
            <button class="modal-close" onclick="cerrarModalFirmaDoctor()">&times;</button>
        </div>
        <div>
            <div style="background: #F4F9FD; border: 1px solid #ebd3e0; border-radius: 8px; padding: 10px 15px; margin-bottom: 15px; font-size: 0.88rem; color: #555;">
                <strong>Paciente:</strong> <?php echo htmlspecialchars($cliente['nombre']); ?> | 
                <strong>Teléfono:</strong> <?php echo htmlspecialchars($cliente['telefono']); ?>
            </div>

            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.9rem;">C.I. / Documento de Identidad del Firmante:</label>
                <input type="text" id="doc_ci_firma" class="form-control" value="<?php echo htmlspecialchars($historia['ci'] ?? ''); ?>" placeholder="Ej: 8472910 LP">
            </div>

            <div style="margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                <label style="font-weight: 600; font-size: 0.9rem; color: #333;">
                    Firme en el recuadro (Stylus, Dedo o Ratón):
                </label>
                <button type="button" class="btn btn-sm" style="background: #f0f0f0; color: #555; border: 1px solid #ccc;" onclick="limpiarFirmaDoctor()">
                    <i class="fas fa-eraser me-1"></i> Limpiar
                </button>
            </div>

            <div style="border: 2px dashed var(--primary); border-radius: 10px; background: #ffffff; padding: 4px; box-shadow: inset 0 2px 6px rgba(0,0,0,0.05);">
                <canvas id="canvasFirmaDoctor" style="width: 100%; height: 210px; display: block; cursor: crosshair; touch-action: none; border-radius: 6px;"></canvas>
            </div>
            <div style="font-size: 0.78rem; color: #888; text-align: center; margin-top: 6px;">
                <i class="fas fa-info-circle me-1"></i> Al registrar su firma, el paciente valida que los datos y antecedentes consignados son fidedignos.
            </div>
        </div>
        <div class="modal-footer" style="margin-top: 20px;">
            <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalFirmaDoctor()">Cancelar</button>
            <button type="button" class="btn btn-primary" id="btnGuardarFirmaDoctor" onclick="guardarFirmaDoctor()">
                <i class="fas fa-check me-1"></i> Guardar Firma
            </button>
        </div>
    </div>
</div>

<!-- Modal para Compartir Enlace al Paciente -->
<div class="modal-overlay" id="modalCompartirFirma">
    <div class="modal-box" style="max-width: 580px; width: 95%;">
        <div class="modal-header">
            <h4 style="color: #25D366;"><i class="fab fa-whatsapp me-2"></i>Enviar Enlace para Firma Digital</h4>
            <button class="modal-close" onclick="cerrarModalCompartirFirma()">&times;</button>
        </div>
        <div>
            <p style="font-size: 0.92rem; color: #555; margin-bottom: 15px;">
                El paciente puede abrir este enlace desde su smartphone para revisar su ficha médica y firmar directamente con su dedo:
            </p>
            
            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.88rem;">Enlace Directo de Verificación y Firma:</label>
                <div style="display: flex; gap: 8px;">
                    <input type="text" id="inputLinkFirma" class="form-control" value="<?php echo htmlspecialchars($linkFirma); ?>" readonly style="background: #f8f9fa; font-size: 0.85rem;">
                    <button type="button" class="btn btn-primary" id="btnCopiarEnlaceFirma" onclick="copiarEnlaceFirma()" style="white-space: nowrap;">
                        <i class="fas fa-copy me-1"></i> Copiar
                    </button>
                </div>
            </div>

            <?php
            $telefonoLimpio = preg_replace('/\D/', '', $cliente['telefono']);
            if (strlen($telefonoLimpio) === 8 && !str_starts_with($telefonoLimpio, '591')) {
                $telefonoLimpio = '591' . $telefonoLimpio;
            }
            $mensajeWs = "Hola " . $cliente['nombre'] . ", le saludamos de la Clínica Dentality. Por favor ingrese al siguiente enlace para verificar sus datos y registrar su firma en su Ficha Odontológica Oficial: " . $linkFirma;
            $urlWhatsapp = "https://api.whatsapp.com/send?phone=" . $telefonoLimpio . "&text=" . urlencode($mensajeWs);
            ?>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 15px; margin-top: 20px; text-align: center;">
                <div style="font-weight: 600; color: #166534; margin-bottom: 10px;">
                    <i class="fab fa-whatsapp me-1"></i> Enviar Directamente por WhatsApp
                </div>
                <p style="font-size: 0.85rem; color: #15803d; margin-bottom: 12px;">
                    Número registrado: <strong><?php echo htmlspecialchars($cliente['telefono']); ?></strong>
                </p>
                <a href="<?php echo $urlWhatsapp; ?>" target="_blank" class="btn" style="background: #25D366; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);">
                    <i class="fab fa-whatsapp" style="font-size: 1.2rem;"></i> Abrir Chat de WhatsApp
                </a>
            </div>
        </div>
        <div class="modal-footer" style="margin-top: 20px;">
            <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalCompartirFirma()">Cerrar</button>
        </div>
    </div>
</div>

<!-- Modal para visualizar archivos clínicos (Ancho ampliado a 1000px) -->
<div class="modal-overlay" id="modalVerArchivo">
    <div class="modal-box" style="max-width: 1000px; width: 95%; max-height: 95vh; padding: 0; overflow: hidden;">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 15px 20px; margin-bottom: 0;">
            <h5 style="margin: 0; font-weight: 600; font-size: 1.1rem;"><i class="fas fa-file-medical me-2"></i>Visualizador de Archivo</h5>
            <button class="modal-close" onclick="cerrarModalVerArchivo()" style="color: white; border: none; background: rgba(255,255,255,0.2); width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 1; transition: 0.3s;">&times;</button>
        </div>
        <div id="contenidoVerArchivo" style="padding: 20px; overflow-y: auto; max-height: calc(95vh - 70px); text-align: center;">
            <!-- Contenido dinámico -->
        </div>
    </div>
</div>

</body>
</html>
