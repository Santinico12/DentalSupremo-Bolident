<?php
/**
 * Portal del Paciente: Verificación y Firma Digital de Historia Clínica Odontológica
 * Dental Supremo - Cochabamba, Bolivia
 */
date_default_timezone_set('America/La_Paz');
session_start();

require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/models/Client.php';
require_once __DIR__ . '/../src/models/HistoriaClinica.php';

$clientModel = new Client($pdo);
$historiaModel = new HistoriaClinica($pdo);

// Soporte de acceso por token seguro (desde celular/WhatsApp) o por ID (con sesión o token)
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$clienteId = intval($_GET['id'] ?? $_POST['cliente_id'] ?? 0);

$historia = null;
if (!empty($token)) {
    $historia = $historiaModel->getByToken($token);
    if ($historia) {
        $clienteId = intval($historia['cliente_id']);
    }
} elseif ($clienteId > 0) {
    // Si viene ID y está logueado como admin/doctor, o si no requiere login estricto
    $historia = $historiaModel->getByCliente($clienteId);
}

if (!$historia || !$clienteId) {
    http_response_code(404);
    die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Ficha no encontrada</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body class="bg-light p-4"><div class="container text-center py-5"><h2 class="text-danger">⚠️ Enlace no válido o expirado</h2><p class="text-muted">No se pudo encontrar la historia clínica solicitada. Por favor solicita un nuevo enlace en recepción.</p></div></body></html>');
}

$cliente = $clientModel->getById($clienteId);
if (!$cliente) {
    die('Paciente no encontrado');
}

// Procesar firma vía AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $firmaBase64 = trim($_POST['firma_paciente'] ?? '');
    $ci = trim($_POST['ci'] ?? '');

    if (empty($firmaBase64) || strlen($firmaBase64) < 100) {
        echo json_encode(['ok' => false, 'error' => 'Por favor realiza tu firma en el recuadro antes de confirmar.']);
        exit;
    }

    $guardado = $historiaModel->guardarFirma($clienteId, $firmaBase64, $ci);
    if ($guardado) {
        echo json_encode([
            'ok' => true,
            'mensaje' => '¡Firma registrada exitosamente!',
            'fecha_firma' => date('d/m/Y H:i')
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al registrar la firma.']);
    }
    exit;
}

// Decodificar patologías personales
$patologias = [];
if (!empty($historia['patologias_personales'])) {
    $patologias = is_array($historia['patologias_personales']) 
        ? $historia['patologias_personales'] 
        : json_decode($historia['patologias_personales'], true);
}
if (!is_array($patologias)) $patologias = [];

$nombresPatologias = [
    'anemia' => 'Anemia',
    'cardiopatias' => 'Cardiopatías',
    'chagas' => 'Chagas',
    'asma' => 'Asma',
    'diabetes' => 'Diabetes',
    'problemas_renales' => 'Problemas Renales',
    'enf_gastrica' => 'Enf. Gástrica',
    'hepatitis' => 'Hepatitis',
    'tuberculosis' => 'Tuberculosis',
    'epilepsia' => 'Epilepsia',
    'hipertension' => 'Hipertensión',
    'vih' => 'VIH',
    'problemas_coagulacion' => 'Problemas de Coagulación',
    'otros' => 'Otras patologías'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Firma de Historia Clínica — <?php echo htmlspecialchars($cliente['nombre']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #003B73;
            --primary-dark: #062846;
            --accent: #2998EC;
            --accent-gold: #D4AF37;
            --bg: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --border: #E2E8F0;
            --success: #16A34A;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: linear-gradient(135deg, #FAF5F7 0%, #F1E5EC 100%);
            color: var(--text-main);
            min-height: 100vh;
            padding: 16px 12px 40px 12px;
            display: flex;
            justify-content: center;
        }

        .portal-wrapper {
            width: 100%;
            max-width: 760px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Cabecera institucional */
        .clinic-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 16px;
            padding: 22px 20px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 59, 115, 0.3);
            position: relative;
            overflow: hidden;
        }

        .clinic-header::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 140px;
            height: 140px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }

        .clinic-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .clinic-title {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 4px;
        }

        .clinic-sub {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* Banner de Instrucción */
        .instruction-banner {
            background: #FFFFFF;
            border-left: 4px solid var(--accent);
            border-radius: 12px;
            padding: 14px 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            font-size: 0.88rem;
            color: #334155;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .instruction-banner i {
            color: var(--primary);
            font-size: 1.25rem;
            margin-top: 2px;
        }

        /* Tarjetas de Datos */
        .section-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .section-header h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .data-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 16px;
        }

        @media (max-width: 540px) {
            .data-grid { grid-template-columns: 1fr; }
        }

        .data-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .data-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .data-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .badge-tag {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            background: #F1F5F9;
            color: #334155;
            margin: 2px;
        }

        .badge-tag.danger {
            background: #FEE2E2;
            color: #991B1B;
        }

        .badge-tag.success {
            background: #DCFCE7;
            color: #166534;
        }

        /* Declaración Legal */
        .legal-declaration-box {
            background: #FDF4F8;
            border: 1px solid #F3D0E2;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }

        .legal-text {
            font-size: 0.92rem;
            font-style: italic;
            color: #4A1533;
            line-height: 1.5;
            margin-bottom: 12px;
            font-weight: 500;
        }

        /* Pad de Firma Digital */
        .signature-section {
            background: #FFFFFF;
            border: 2px solid var(--accent);
            border-radius: 16px;
            padding: 20px 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 59, 115, 0.15);
            text-align: center;
        }

        .signature-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .signature-subtitle {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .canvas-container {
            width: 100%;
            height: 190px;
            background: #FFFFFF;
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            position: relative;
            touch-action: none;
            cursor: crosshair;
            margin-bottom: 12px;
            overflow: hidden;
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.03);
        }

        .canvas-container.active {
            border-color: var(--primary);
            border-style: solid;
        }

        #signatureCanvas {
            width: 100%;
            height: 100%;
            display: block;
        }

        .signature-line-guide {
            position: absolute;
            bottom: 30px;
            left: 10%;
            right: 10%;
            height: 1px;
            border-bottom: 1px dashed #E2E8F0;
            pointer-events: none;
        }

        .signature-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #94A3B8;
            font-size: 0.85rem;
            pointer-events: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-signature-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn-custom {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-clear {
            background: #F1F5F9;
            color: #475569;
        }
        .btn-clear:hover { background: #E2E8F0; }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            flex: 1;
            box-shadow: 0 4px 12px rgba(0, 59, 115, 0.35);
        }
        .btn-submit:hover { opacity: 0.95; transform: translateY(-1px); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Estado ya firmado */
        .signed-success-card {
            background: #F0FDF4;
            border: 2px solid #86EFAC;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
        }

        .signed-success-card i.fa-check-circle {
            font-size: 3rem;
            color: var(--success);
            margin-bottom: 10px;
        }

        .signed-img {
            max-width: 240px;
            max-height: 100px;
            border: 1px solid #CBD5E1;
            background: white;
            border-radius: 8px;
            padding: 6px;
            margin: 12px auto;
            display: block;
        }

        .footer-note {
            text-align: center;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="portal-wrapper">

    <!-- Cabecera de la Clínica -->
    <div class="clinic-header">
        <div class="clinic-icon">
            <i class="fas fa-tooth"></i>
        </div>
        <h1 class="clinic-title">Clínica Dental Supremo</h1>
        <p class="clinic-sub">Historia Clínica Odontológica & Verificación de Datos</p>
    </div>

    <!-- Banner Explicativo -->
    <div class="instruction-banner">
        <i class="fas fa-shield-alt"></i>
        <div>
            <strong>Estimado/a <?php echo htmlspecialchars($cliente['nombre']); ?>:</strong>
            <p class="mb-0 mt-1" style="font-size: 0.84rem; line-height: 1.4;">
                Por favor revisa detenidamente tus datos personales, antecedentes de salud bucal y diagnóstico registrado. Al final de la página podrás estampar tu firma digital con tu dedo o lápiz táctil para confirmar tu conformidad.
            </p>
        </div>
    </div>

    <!-- 1. Datos Personales -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-user-circle"></i> 1. Datos Personales</h3>
            <span class="badge-tag">N° H.C: <?php echo htmlspecialchars($historia['numero_hc'] ?? 'HC-' . $clienteId); ?></span>
        </div>
        <div class="data-grid">
            <div class="data-item">
                <span class="data-label">Paciente</span>
                <span class="data-value"><?php echo htmlspecialchars($cliente['nombre']); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Cédula de Identidad (C.I.)</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['ci'] ?: 'Por confirmar al pie'); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Celular / WhatsApp</span>
                <span class="data-value"><?php echo htmlspecialchars($cliente['telefono']); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Edad / Sexo</span>
                <span class="data-value">
                    <?php echo htmlspecialchars($historia['edad'] ? $historia['edad'] . ' años' : 'No registrada'); ?> | 
                    <?php echo htmlspecialchars($historia['sexo'] === 'F' ? 'Femenino' : ($historia['sexo'] === 'M' ? 'Masculino' : 'Otro')); ?>
                </span>
            </div>
            <div class="data-item">
                <span class="data-label">Fecha y Lugar de Nacimiento</span>
                <span class="data-value">
                    <?php echo htmlspecialchars($historia['fecha_nacimiento'] ? date('d/m/Y', strtotime($historia['fecha_nacimiento'])) : 'No registrada'); ?>
                    <?php if (!empty($historia['lugar_nacimiento'])) echo ' (' . htmlspecialchars($historia['lugar_nacimiento']) . ')'; ?>
                </span>
            </div>
            <div class="data-item">
                <span class="data-label">Ocupación</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['ocupacion'] ?: 'No especificada'); ?></span>
            </div>
            <div class="data-item" style="grid-column: 1 / -1;">
                <span class="data-label">Dirección</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['direccion'] ?: 'No registrada'); ?></span>
            </div>
        </div>
    </div>

    <!-- 2. Antecedentes Médicos y Patologías -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-heartbeat"></i> 2. Antecedentes Médicos & Patológicos</h3>
        </div>

        <div style="margin-bottom: 12px;">
            <span class="data-label">Patologías Personales Declaradas:</span>
            <div style="margin-top: 6px;">
                <?php if (!empty($patologias)): ?>
                    <?php foreach ($patologias as $patKey): ?>
                        <span class="badge-tag danger"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($nombresPatologias[$patKey] ?? ucfirst($patKey)); ?></span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="badge-tag success"><i class="fas fa-check me-1"></i>Sin patologías sistémicas declaradas</span>
                <?php endif; ?>
                <?php if (!empty($historia['embarazo'])): ?>
                    <span class="badge-tag danger"><i class="fas fa-baby me-1"></i>Embarazada</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="data-grid">
            <div class="data-item">
                <span class="data-label">Alergias</span>
                <span class="data-value" style="color: <?php echo !empty($historia['alergias']) ? '#DC2626' : 'inherit'; ?>;">
                    <?php echo htmlspecialchars($historia['alergias'] ?: 'Ninguna conocida'); ?>
                </span>
            </div>
            <div class="data-item">
                <span class="data-label">¿Toma Medicamentos?</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['toma_medicamento'] ?: ($historia['medicamentos_actuales'] ?: 'No')); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">¿En Tratamiento Médico?</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['en_tratamiento_medico'] ?: 'No'); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Hemorragia Post-Extracción</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['hemorragia_extraccion'] ?: 'No'); ?></span>
            </div>
            <?php if (!empty($historia['antecedentes_familiares'])): ?>
            <div class="data-item" style="grid-column: 1 / -1;">
                <span class="data-label">Antecedentes Familiares</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['antecedentes_familiares']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 3. Examen Odontológico e Higiene -->
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-tooth"></i> 3. Examen Clínico Odontológico</h3>
        </div>
        <div class="data-grid">
            <div class="data-item">
                <span class="data-label">A.T.M. (Mandíbula)</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['atm'] ?: 'Normal / Sin alteración'); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Tipo de Respirador</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['tipo_respirador'] ?: 'Nasal'); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Higiene Bucal General</span>
                <span class="data-value"><?php echo htmlspecialchars($historia['nivel_higiene_bucal'] ?: 'Buena'); ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Sangrado de Encías</span>
                <span class="data-value"><?php echo !empty($historia['sangrado_encias']) ? 'Sí durante el cepillado' : 'No'; ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Prótesis Dental</span>
                <span class="data-value"><?php echo !empty($historia['usa_protesis']) ? 'Sí utiliza prótesis' : 'No utiliza'; ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Hábitos</span>
                <span class="data-value">
                    <?php 
                    $habs = [];
                    if (!empty($historia['habitos_fuma'])) $habs[] = 'Fuma';
                    if (!empty($historia['habitos_bebe'])) $habs[] = 'Bebe';
                    if (!empty($historia['habitos_otros'])) $habs[] = $historia['habitos_otros'];
                    echo !empty($habs) ? htmlspecialchars(implode(', ', $habs)) : 'Sin hábitos de riesgo';
                    ?>
                </span>
            </div>
        </div>
    </div>

    <!-- 4. Diagnóstico y Plan de Tratamiento (Si está registrado) -->
    <?php if (!empty($historia['motivo_consulta']) || !empty($historia['diagnostico']) || !empty($historia['plan_tratamiento'])): ?>
    <div class="section-card">
        <div class="section-header">
            <h3><i class="fas fa-stethoscope"></i> 4. Diagnóstico & Plan de Tratamiento</h3>
        </div>
        <div class="data-grid">
            <?php if (!empty($historia['motivo_consulta'])): ?>
            <div class="data-item" style="grid-column: 1 / -1;">
                <span class="data-label">Motivo de Consulta</span>
                <span class="data-value"><?php echo nl2br(htmlspecialchars($historia['motivo_consulta'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($historia['diagnostico'])): ?>
            <div class="data-item" style="grid-column: 1 / -1;">
                <span class="data-label">Diagnóstico Odontológico</span>
                <span class="data-value"><?php echo nl2br(htmlspecialchars($historia['diagnostico'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($historia['plan_tratamiento'])): ?>
            <div class="data-item" style="grid-column: 1 / -1;">
                <span class="data-label">Plan de Tratamiento Propuesto</span>
                <span class="data-value"><?php echo nl2br(htmlspecialchars($historia['plan_tratamiento'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Declaración Jurada Legal -->
    <div class="legal-declaration-box">
        <i class="fas fa-file-contract text-primary fa-lg mb-2"></i>
        <p class="legal-text">
            "Declaro ciertos todos los datos relativos a mi historia clínica, no habiendo omitido ningún aspecto de interés o que me hubiera sido cuestionado."
        </p>
        <span class="text-muted small">Ley de Salud y Normativa Odontológica de Bolivia</span>
    </div>

    <!-- Estado de Firma o Sección para Firmar -->
    <?php if (!empty($historia['firma_paciente'])): ?>
        <!-- Ficha ya firmada -->
        <div class="signed-success-card" id="cardFirmado">
            <i class="fas fa-check-circle"></i>
            <h3 class="fw-bold text-success mb-1">¡Historia Clínica Firmada!</h3>
            <p class="text-muted small mb-2">
                Documento verificado y suscrito por el paciente el 
                <strong><?php echo date('d/m/Y H:i', strtotime($historia['fecha_firma'] ?? 'now')); ?></strong>
            </p>
            <div class="data-item my-2">
                <span class="data-label">Firma Registrada:</span>
                <img src="<?php echo $historia['firma_paciente']; ?>" class="signed-img" alt="Firma del Paciente">
            </div>
            <p class="small text-muted mb-0">C.I.: <strong><?php echo htmlspecialchars($historia['ci'] ?: 'Registrado'); ?></strong></p>
            <div class="mt-3">
                <button type="button" class="btn-custom btn-clear btn-sm" onclick="habilitarRefirma()">
                    <i class="fas fa-pen-nib me-1"></i> Volver a firmar
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recuadro Interactivo de Firma (Visible si no está firmada o si desea refirmar) -->
    <div class="signature-section" id="seccionFirma" style="<?php echo !empty($historia['firma_paciente']) ? 'display: none;' : ''; ?>">
        <h3 class="signature-title"><i class="fas fa-pen-nib me-1"></i> Firma Digital del Paciente</h3>
        <p class="signature-subtitle">Dibuja tu firma en el recuadro blanco usando tu dedo o lápiz táctil.</p>

        <!-- Cédula de Identidad -->
        <div style="max-width: 280px; margin: 0 auto 14px auto; text-align: left;">
            <label class="data-label" for="inputCI">Cédula de Identidad (C.I.):</label>
            <input type="text" id="inputCI" class="form-control" style="width: 100%; padding: 10px 12px; border: 2px solid var(--border); border-radius: 8px; font-weight: 600;" placeholder="Ej: 7984512 CBBA" value="<?php echo htmlspecialchars($historia['ci'] ?? ''); ?>">
        </div>

        <!-- Canvas de Firma -->
        <div class="canvas-container" id="canvasContainer">
            <canvas id="signatureCanvas"></canvas>
            <div class="signature-line-guide"></div>
            <div class="signature-placeholder" id="signaturePlaceholder">
                <i class="fas fa-signature"></i> Firma aquí con tu dedo
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="btn-signature-group">
            <button type="button" class="btn-custom btn-clear" onclick="limpiarFirma()">
                <i class="fas fa-eraser"></i> Limpiar
            </button>
            <button type="button" class="btn-custom btn-submit" id="btnGuardarFirma" onclick="enviarFirma()">
                <i class="fas fa-check-circle"></i> Confirmar y Firmar Ficha
            </button>
        </div>
    </div>

    <div class="footer-note">
        Clínica Dental Supremo &copy; <?php echo date('Y'); ?> — Cochabamba, Bolivia
    </div>

</div>

<script>
let canvas = document.getElementById('signatureCanvas');
let ctx = canvas.getContext('2d');
let isDrawing = false;
let hasSigned = false;
let lastX = 0;
let lastY = 0;

function resizeCanvas() {
    const container = document.getElementById('canvasContainer');
    if (!container) return;
    const rect = container.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1E293B';
}

window.addEventListener('resize', resizeCanvas);
window.addEventListener('DOMContentLoaded', resizeCanvas);

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    if (e.touches && e.touches.length > 0) {
        return {
            x: e.touches[0].clientX - rect.left,
            y: e.touches[0].clientY - rect.top
        };
    }
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
}

function startDrawing(e) {
    isDrawing = true;
    hasSigned = true;
    const placeholder = document.getElementById('signaturePlaceholder');
    if (placeholder) placeholder.style.display = 'none';
    document.getElementById('canvasContainer').classList.add('active');
    const pos = getPos(e);
    lastX = pos.x;
    lastY = pos.y;
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
}

function draw(e) {
    if (!isDrawing) return;
    e.preventDefault();
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    lastX = pos.x;
    lastY = pos.y;
}

function stopDrawing() {
    isDrawing = false;
}

// Mouse events
canvas.addEventListener('mousedown', startDrawing);
canvas.addEventListener('mousemove', draw);
window.addEventListener('mouseup', stopDrawing);

// Touch events for mobile/tablet
canvas.addEventListener('touchstart', (e) => {
    e.preventDefault();
    startDrawing(e);
}, { passive: false });

canvas.addEventListener('touchmove', (e) => {
    e.preventDefault();
    draw(e);
}, { passive: false });

window.addEventListener('touchend', stopDrawing);

function limpiarFirma() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSigned = false;
    const placeholder = document.getElementById('signaturePlaceholder');
    if (placeholder) placeholder.style.display = 'flex';
    document.getElementById('canvasContainer').classList.remove('active');
}

function habilitarRefirma() {
    const card = document.getElementById('cardFirmado');
    const seccion = document.getElementById('seccionFirma');
    if (card) card.style.display = 'none';
    if (seccion) {
        seccion.style.display = 'block';
        resizeCanvas();
    }
}

async function enviarFirma() {
    if (!hasSigned) {
        alert('Por favor realiza tu firma en el recuadro antes de confirmar.');
        return;
    }

    const ciVal = document.getElementById('inputCI').value.trim();
    if (!ciVal) {
        if (!confirm('¿Deseas confirmar la firma sin registrar tu número de Cédula de Identidad?')) {
            document.getElementById('inputCI').focus();
            return;
        }
    }

    const btn = document.getElementById('btnGuardarFirma');
    btn.disabled = true;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando firma...';

    const dataUrl = canvas.toDataURL('image/png');

    try {
        const formData = new FormData();
        formData.append('token', '<?php echo htmlspecialchars($token ?: ($historia['firma_token'] ?? '')); ?>');
        formData.append('cliente_id', '<?php echo $clienteId; ?>');
        formData.append('ci', ciVal);
        formData.append('firma_paciente', dataUrl);

        const res = await fetch('firmar_ficha.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.ok) {
            alert('✅ ' + data.mensaje);
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'No se pudo registrar la firma'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (e) {
        alert('Error de conexión: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}
</script>

</body>
</html>
