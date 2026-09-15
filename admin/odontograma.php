<?php
/**
 * Odontograma Digital Anatómico Interactivo - Dental Supremo
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    $_SESSION['message'] = 'Cliente no especificado';
    $_SESSION['message_type'] = 'danger';
    header('Location: lista_clientes.php');
    exit();
}

$clienteId = (int)$_GET['cliente_id'];

require_once '../src/config/db.php';
require_once '../src/models/Odontograma.php';

$odontogramaModel = new Odontograma($pdo);

$stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $_SESSION['message'] = 'Cliente no encontrado';
    $_SESSION['message_type'] = 'danger';
    header('Location: lista_clientes.php');
    exit();
}

$odontograma = $odontogramaModel->getByClienteIndexado($clienteId);
$condiciones = Odontograma::getCondiciones();
$dientesAdulto = Odontograma::getDientesAdulto();
$dientesPediatrico = Odontograma::getDientesPediatrico();

require_once '../templates/header_general.php';
?>

<!-- Dependencies -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #003B73;
        --primary-gradient: linear-gradient(135deg, #003B73 0%, #2998EC 100%);
        --accent-teal: #2998EC;
        --accent-blue: #2563EB;
        --accent-red: #EF4444;
        --accent-gold: #F59E0B;
        --accent-purple: #8B5CF6;
        --accent-green: #10B981;
        --bg-main: #F4F9FD;
        --card-bg: #FFFFFF;
        --text-dark: #0F172A;
        --text-muted: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.04);
        --shadow-md: 0 10px 25px rgba(15, 76, 110, 0.08);
        --shadow-lg: 0 20px 40px rgba(15, 76, 110, 0.12);
        --radius-lg: 16px;
        --radius-md: 10px;
    }

    body {
        background-color: var(--bg-main);
        font-family: 'Plus Jakarta Sans', sans-serif;
        color: var(--text-dark);
    }

    .odonto-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 10px 20px 40px 20px;
    }

    /* Header Card */
    .odonto-header {
        background: var(--primary-gradient);
        border-radius: var(--radius-lg);
        padding: 20px 26px;
        color: white;
        margin-bottom: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        box-shadow: var(--shadow-md);
        position: relative;
        overflow: hidden;
    }

    .odonto-header h2 {
        margin: 0;
        font-weight: 800;
        font-size: 1.45rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .paciente-info {
        background: rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(10px);
        padding: 8px 18px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .paciente-avatar {
        width: 40px;
        height: 40px;
        background: white;
        color: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.1rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }

    /* KPI Summary Row */
    .kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }

    .kpi-chip {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: var(--shadow-sm);
    }

    .kpi-chip-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .kpi-chip-val {
        font-size: 1.25rem;
        font-weight: 800;
        line-height: 1;
        color: var(--text-dark);
    }

    .kpi-chip-lbl {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    /* Dentition Tabs & Mode Selector */
    .toolbar-card {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 18px 22px;
        margin-bottom: 22px;
        box-shadow: var(--shadow-sm);
    }

    .toolbar-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 14px;
        margin-bottom: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid var(--border-color);
    }

    .nav-dentition {
        display: flex;
        background: #F1F5F9;
        padding: 4px;
        border-radius: 12px;
        gap: 4px;
    }

    .btn-tab-dent {
        padding: 8px 18px;
        border-radius: 8px;
        border: none;
        background: transparent;
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-tab-dent.active {
        background: white;
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }

    .mode-selector {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-mode {
        padding: 7px 14px;
        border-radius: 8px;
        border: 1.5px solid var(--border-color);
        background: white;
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-mode.active {
        border-color: var(--primary);
        background: rgba(15, 76, 110, 0.08);
        color: var(--primary);
    }

    /* Conditions Palette Grid */
    .condiciones-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 10px;
    }

    .condicion-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border: 1.5px solid var(--border-color);
        border-radius: var(--radius-md);
        background: white;
        cursor: pointer;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 700;
        font-size: 0.82rem;
        color: var(--text-dark);
    }

    .condicion-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
        border-color: var(--accent-teal);
    }

    .condicion-btn.active {
        border-color: var(--primary);
        background: rgba(15, 76, 110, 0.06);
        box-shadow: 0 0 0 3px rgba(15, 76, 110, 0.15);
    }

    .condicion-badge-dot {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        flex-shrink: 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Dental Diagram Viewport */
    .dental-diagram-card {
        background: white;
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-color);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
        overflow-x: auto;
    }

    .diagram-title {
        text-align: center;
        margin-bottom: 20px;
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .jaw-section {
        background: #F8FAFC;
        border-radius: 14px;
        padding: 16px;
        margin-bottom: 18px;
        border: 1px solid #F1F5F9;
    }

    .jaw-label {
        text-align: center;
        font-weight: 800;
        color: var(--primary);
        font-size: 0.8rem;
        letter-spacing: 1px;
        margin-bottom: 12px;
        text-transform: uppercase;
    }

    .teeth-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
    }

    .quadrant-divider {
        width: 3px;
        height: 80px;
        background: var(--primary-gradient);
        margin: 0 10px;
        border-radius: 3px;
        flex-shrink: 0;
    }

    /* Realistic 5-Surface FDI Tooth Component */
    .tooth-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        transition: transform 0.2s ease;
        user-select: none;
    }

    .tooth-wrapper:hover {
        transform: translateY(-4px);
    }

    .tooth-number-badge {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--primary);
        background: #F1F5F9;
        padding: 2px 8px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .tooth-anatomical-container {
        position: relative;
        width: 54px;
        height: 74px;
        background: #FFFFFF;
        border-radius: 10px;
        border: 1.5px solid var(--border-color);
        padding: 4px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: space-between;
        transition: all 0.2s ease;
        box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }

    .tooth-wrapper:hover .tooth-anatomical-container {
        border-color: var(--accent-teal);
        box-shadow: 0 6px 14px rgba(0, 168, 150, 0.18);
    }

    /* Crown Silhouette Representation */
    .tooth-silhouette {
        width: 24px;
        height: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.85;
    }

    /* FDI 5-Surface SVG Box */
    .fdi-svg-box {
        width: 42px;
        height: 42px;
        cursor: pointer;
    }

    .fdi-surface {
        fill: #FFFFFF;
        stroke: #CBD5E1;
        stroke-width: 1.5;
        transition: fill 0.2s ease, stroke 0.2s ease;
    }

    .fdi-surface:hover {
        fill: rgba(0, 168, 150, 0.25) !important;
        stroke: var(--accent-teal);
    }

    /* Condition Overlays */
    .tooth-overlay-icon {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        z-index: 10;
    }

    .tooth-overlay-icon.extraccion {
        color: #DC2626;
        font-size: 2rem;
        text-shadow: 0 0 4px rgba(255,255,255,0.8);
    }

    .tooth-overlay-icon.ausente {
        color: #64748B;
        font-size: 1.8rem;
        opacity: 0.7;
    }

    .tooth-overlay-icon.implante {
        color: #0284C7;
        font-size: 1.4rem;
    }

    /* Responsive Mobile */
    @media (max-width: 991px) {
        .teeth-row {
            overflow-x: auto;
            justify-content: flex-start;
            padding-bottom: 8px;
        }
    }

    @media (max-width: 768px) {
        .odonto-container { padding: 10px; }
        .odonto-header { flex-direction: column; text-align: center; }
        .paciente-info { width: 100%; justify-content: center; }
        .quadrant-divider { height: 60px; margin: 0 4px; }
        .tooth-anatomical-container { width: 44px; height: 64px; }
        .fdi-svg-box { width: 34px; height: 34px; }
    }
</style>

<div class="odonto-container">
    <!-- Header Banner -->
    <div class="odonto-header">
        <h2>
            <i class="fas fa-tooth"></i>
            Odontograma Digital Anatómico
        </h2>
        <div class="paciente-info">
            <div class="paciente-avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
            <div>
                <strong style="font-size: 1.05rem;"><?php echo htmlspecialchars($cliente['nombre']); ?></strong>
                <div style="font-size: 0.8rem; opacity: 0.9;"><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($cliente['telefono']); ?></div>
            </div>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="kpi-row">
        <div class="kpi-chip">
            <div class="kpi-chip-icon" style="background: rgba(15, 76, 110, 0.1); color: var(--primary);">
                <i class="fas fa-teeth"></i>
            </div>
            <div>
                <div class="kpi-chip-val" id="kpi-evaluados">0</div>
                <div class="kpi-chip-lbl">Dientes Evaluados</div>
            </div>
        </div>
        <div class="kpi-chip">
            <div class="kpi-chip-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-red);">
                <i class="fas fa-bacteria"></i>
            </div>
            <div>
                <div class="kpi-chip-val" id="kpi-caries">0</div>
                <div class="kpi-chip-lbl">Caries</div>
            </div>
        </div>
        <div class="kpi-chip">
            <div class="kpi-chip-icon" style="background: rgba(37, 99, 235, 0.1); color: var(--accent-blue);">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div>
                <div class="kpi-chip-val" id="kpi-obturaciones">0</div>
                <div class="kpi-chip-lbl">Obturaciones</div>
            </div>
        </div>
        <div class="kpi-chip">
            <div class="kpi-chip-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--accent-gold);">
                <i class="fas fa-crown"></i>
            </div>
            <div>
                <div class="kpi-chip-val" id="kpi-coronas">0</div>
                <div class="kpi-chip-lbl">Coronas / Prótesis</div>
            </div>
        </div>
    </div>

    <!-- Toolbar & Conditions Palette -->
    <div class="toolbar-card">
        <div class="toolbar-top">
            <!-- Dentition Tabs -->
            <div class="nav-dentition">
                <button type="button" class="btn-tab-dent active" id="tab-adulto" onclick="switchDentition('adulto')">
                    <i class="fas fa-user"></i> Dentición Adulto (32 Dientes)
                </button>
                <button type="button" class="btn-tab-dent" id="tab-pediatrico" onclick="switchDentition('pediatrico')">
                    <i class="fas fa-child"></i> Dentición Infantil (20 Dientes)
                </button>
            </div>

            <!-- Mode Selector -->
            <div class="mode-selector">
                <span class="fw-bold me-1" style="font-size: 0.82rem; color: var(--text-muted);">MODO APLICACIÓN:</span>
                <button type="button" class="btn-mode active" id="mode-superficie" onclick="setApplicationMode('superficie')">
                    <i class="fas fa-th-large me-1"></i> Superficie FDI
                </button>
                <button type="button" class="btn-mode" id="mode-completo" onclick="setApplicationMode('completo')">
                    <i class="fas fa-square me-1"></i> Diente Completo
                </button>
            </div>
        </div>

        <h6 class="fw-bold mb-3" style="font-size: 0.9rem; color: var(--text-dark);">
            <i class="fas fa-palette me-2" style="color: var(--primary);"></i>Selecciona el Diagnóstico / Tratamiento a Aplicar:
        </h6>

        <!-- Conditions Grid -->
        <div class="condiciones-grid">
            <?php foreach ($condiciones as $key => $cond): ?>
            <button type="button" class="condicion-btn" data-condicion="<?php echo $key; ?>">
                <span class="condicion-badge-dot" style="background: <?php echo $cond['color']; ?>; border: 1px solid rgba(0,0,0,0.15);"></span>
                <span><?php echo htmlspecialchars($cond['nombre']); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Dental Diagram Main Panel -->
    <div class="dental-diagram-card">
        <!-- Adult Viewport -->
        <div id="viewport-adulto">
            <div class="diagram-title">
                <i class="fas fa-teeth-open" style="color: var(--primary);"></i>
                Esquema Anatómico Dental Adulto (FDI 11 - 48)
            </div>

            <!-- Maxilar Superior -->
            <div class="jaw-section">
                <div class="jaw-label"><i class="fas fa-chevron-up me-1"></i> Maxilar Superior</div>
                <div class="teeth-row">
                    <?php foreach ($dientesAdulto['superior_derecho'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                    <div class="quadrant-divider"></div>
                    <?php foreach ($dientesAdulto['superior_izquierdo'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                </div>
            </div>

            <!-- Maxilar Inferior -->
            <div class="jaw-section mb-0">
                <div class="teeth-row mb-2">
                    <?php foreach ($dientesAdulto['inferior_derecho'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                    <div class="quadrant-divider"></div>
                    <?php foreach ($dientesAdulto['inferior_izquierdo'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                </div>
                <div class="jaw-label mb-0"><i class="fas fa-chevron-down me-1"></i> Maxilar Inferior</div>
            </div>
        </div>

        <!-- Pediatric Viewport -->
        <div id="viewport-pediatrico" style="display: none;">
            <div class="diagram-title">
                <i class="fas fa-child" style="color: var(--primary);"></i>
                Esquema Dental Infantil / Deciduo (FDI 51 - 85)
            </div>

            <!-- Maxilar Superior Infantil -->
            <div class="jaw-section">
                <div class="jaw-label"><i class="fas fa-chevron-up me-1"></i> Maxilar Superior Deciduo</div>
                <div class="teeth-row">
                    <?php foreach ($dientesPediatrico['superior_derecho'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                    <div class="quadrant-divider"></div>
                    <?php foreach ($dientesPediatrico['superior_izquierdo'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                </div>
            </div>

            <!-- Maxilar Inferior Infantil -->
            <div class="jaw-section mb-0">
                <div class="teeth-row mb-2">
                    <?php foreach ($dientesPediatrico['inferior_derecho'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                    <div class="quadrant-divider"></div>
                    <?php foreach ($dientesPediatrico['inferior_izquierdo'] as $num): renderToothComponent($num, $odontograma, $condiciones); endforeach; ?>
                </div>
                <div class="jaw-label mb-0"><i class="fas fa-chevron-down me-1"></i> Maxilar Inferior Deciduo</div>
            </div>
        </div>
    </div>

    <!-- Actions & Nav Bar -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <a href="lista_clientes.php" class="btn btn-secondary px-4 fw-bold" style="border-radius: 10px;">
            <i class="fas fa-arrow-left me-2"></i> Volver al Directorio
        </a>
        <div class="text-muted fw-semibold" style="font-size: 0.88rem;">
            <i class="fas fa-sync-alt me-1 text-success"></i> Los cambios en el odontograma se guardan automáticamente
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1080;"></div>

<?php
/**
 * Función Helper para Renderizar un Diente FDI Anatómico 5-Superficies
 */
function renderToothComponent($num, $odontograma, $condiciones) {
    // Determinar si hay condición completa del diente
    $keyCompleto = $num . '_completo';
    $condCompleto = isset($odontograma[$keyCompleto]) ? $odontograma[$keyCompleto]['condicion'] : null;

    // Colores de superficies específicas
    $surfV = getSurfaceColor($num, 'V', $odontograma, $condiciones, $condCompleto);
    $surfD = getSurfaceColor($num, 'D', $odontograma, $condiciones, $condCompleto);
    $surfL = getSurfaceColor($num, 'L', $odontograma, $condiciones, $condCompleto);
    $surfM = getSurfaceColor($num, 'M', $odontograma, $condiciones, $condCompleto);
    $surfO = getSurfaceColor($num, 'O', $odontograma, $condiciones, $condCompleto);

    // Icono overlay para extracción, ausente o implante
    $overlayIcon = '';
    if ($condCompleto === 'extraccion') {
        $overlayIcon = '<div class="tooth-overlay-icon extraccion"><i class="fas fa-times"></i></div>';
    } elseif ($condCompleto === 'ausente') {
        $overlayIcon = '<div class="tooth-overlay-icon ausente"><i class="fas fa-ban"></i></div>';
    } elseif ($condCompleto === 'implante') {
        $overlayIcon = '<div class="tooth-overlay-icon implante"><i class="fas fa-screwdriver"></i></div>';
    }

    echo '
    <div class="tooth-wrapper" data-diente="' . $num . '">
        <span class="tooth-number-badge">' . $num . '</span>
        <div class="tooth-anatomical-container" id="tooth-box-' . $num . '">
            ' . $overlayIcon . '
            <!-- Anatomical Crown SVG Icon -->
            <div class="tooth-silhouette">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#64748B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 3C5 3 4 5 4 8C4 11 5 13 6 15C7 17 7 21 9 21C11 21 11 18 12 18C13 18 13 21 15 21C17 21 17 17 18 15C19 13 20 11 20 8C20 5 19 3 17 3C15 3 14 4 12 4C10 4 9 3 7 3Z"/>
                </svg>
            </div>
            <!-- FDI 5-Surface SVG Box -->
            <svg class="fdi-svg-box" viewBox="0 0 50 50">
                <!-- Vestibular (V - Arriba) -->
                <polygon points="0,0 50,0 35,15 15,15" class="fdi-surface surface-v" data-surface="V" style="fill: ' . $surfV . ';" />
                <!-- Distal (D - Derecha) -->
                <polygon points="50,0 50,50 35,35 35,15" class="fdi-surface surface-d" data-surface="D" style="fill: ' . $surfD . ';" />
                <!-- Lingual/Palatino (L - Abajo) -->
                <polygon points="0,50 50,50 35,35 15,35" class="fdi-surface surface-l" data-surface="L" style="fill: ' . $surfL . ';" />
                <!-- Mesial (M - Izquierda) -->
                <polygon points="0,0 0,50 15,35 15,15" class="fdi-surface surface-m" data-surface="M" style="fill: ' . $surfM . ';" />
                <!-- Oclusal/Incisal (O - Centro) -->
                <rect x="15" y="15" width="20" height="20" class="fdi-surface surface-o" data-surface="O" style="fill: ' . $surfO . ';" />
            </svg>
        </div>
    </div>';
}

function getSurfaceColor($diente, $superficie, $odontograma, $condiciones, $condCompleto) {
    $key = $diente . '_' . $superficie;
    if (isset($odontograma[$key])) {
        $cKey = $odontograma[$key]['condicion'];
        if (isset($condiciones[$cKey])) return $condiciones[$cKey]['color'];
    }
    if ($condCompleto && isset($condiciones[$condCompleto])) {
        return $condiciones[$condCompleto]['color'];
    }
    return '#FFFFFF';
}
?>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function() {
    const clienteId = <?php echo $clienteId; ?>;
    let selectedCond = null;
    let applicationMode = 'superficie'; // 'superficie' o 'completo'
    const condiciones = <?php echo json_encode($condiciones); ?>;

    // Manejar selección de condición en la paleta
    document.querySelectorAll('.condicion-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.condicion-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedCond = this.dataset.condicion;
        });
    });

    // Activar primera condición 'caries' por defecto si el usuario hace clic directo
    document.querySelector('.condicion-btn[data-condicion="caries"]')?.click();

    // Cambiar Dentición Adulto / Infantil
    window.switchDentition = function(tipo) {
        document.querySelectorAll('.btn-tab-dent').forEach(b => b.classList.remove('active'));
        if (tipo === 'adulto') {
            document.getElementById('tab-adulto').classList.add('active');
            document.getElementById('viewport-adulto').style.display = 'block';
            document.getElementById('viewport-pediatrico').style.display = 'none';
        } else {
            document.getElementById('tab-pediatrico').classList.add('active');
            document.getElementById('viewport-adulto').style.display = 'none';
            document.getElementById('viewport-pediatrico').style.display = 'block';
        }
    };

    // Cambiar Modo de Aplicación (Superficie vs Completo)
    window.setApplicationMode = function(modo) {
        applicationMode = modo;
        document.querySelectorAll('.btn-mode').forEach(b => b.classList.remove('active'));
        if (modo === 'superficie') {
            document.getElementById('mode-superficie').classList.add('active');
        } else {
            document.getElementById('mode-completo').classList.add('active');
        }
    };

    // Event listener para superficies FDI y dientes
    document.addEventListener('click', function(e) {
        // Clic en una superficie específica del SVG FDI
        var surfEl = e.target.closest('.fdi-surface');
        var toothWrapper = e.target.closest('.tooth-wrapper');

        if (!toothWrapper) return;
        var diente = toothWrapper.dataset.diente;

        if (!selectedCond) {
            showToast('Selecciona primero una condición de la paleta', 'warning');
            return;
        }

        var superficieTarget = 'completo';
        if (applicationMode === 'superficie' && surfEl) {
            superficieTarget = surfEl.dataset.surface;
        }

        aplicarCondicion(diente, superficieTarget, selectedCond, toothWrapper, surfEl);
    });

    function aplicarCondicion(diente, superficie, condicion, toothWrapper, surfEl) {
        const color = condiciones[condicion] ? condiciones[condicion].color : '#FFFFFF';
        const box = toothWrapper.querySelector('.tooth-anatomical-container');

        if (superficie === 'completo') {
            // Aplicar a todas las superficies del SVG
            toothWrapper.querySelectorAll('.fdi-surface').forEach(s => {
                s.style.fill = (condicion === 'sano' || condicion === 'limpiar') ? '#FFFFFF' : color;
            });

            // Limpiar overlays previos
            box.querySelectorAll('.tooth-overlay-icon').forEach(el => el.remove());

            if (condicion === 'extraccion') {
                box.insertAdjacentHTML('afterbegin', '<div class="tooth-overlay-icon extraccion"><i class="fas fa-times"></i></div>');
            } else if (condicion === 'ausente') {
                box.insertAdjacentHTML('afterbegin', '<div class="tooth-overlay-icon ausente"><i class="fas fa-ban"></i></div>');
            } else if (condicion === 'implante') {
                box.insertAdjacentHTML('afterbegin', '<div class="tooth-overlay-icon implante"><i class="fas fa-screwdriver"></i></div>');
            }
        } else {
            // Aplicar a superficie específica
            if (surfEl) {
                surfEl.style.fill = (condicion === 'sano' || condicion === 'limpiar') ? '#FFFFFF' : color;
            }
        }

        // Guardar via AJAX
        fetch('guardar_odontograma.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                cliente_id: clienteId,
                diente: diente,
                superficie: superficie,
                condicion: condicion
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showToast(`Diente ${diente} (${superficie.toUpperCase()}): ${condiciones[condicion]?.nombre || 'Guardado'}`, 'success');
                recalcularEstadisticas();
            } else {
                showToast(data.message || 'Error al guardar', 'danger');
            }
        })
        .catch(() => showToast('Error de conexión', 'danger'));
    }

    function recalcularEstadisticas() {
        let evaluados = 0, caries = 0, obturaciones = 0, coronas = 0;
        document.querySelectorAll('.fdi-surface').forEach(s => {
            const fill = s.style.fill.toUpperCase();
            if (fill && fill !== '#FFFFFF' && fill !== 'RGB(255, 255, 255)') {
                evaluados++;
                if (fill.includes('FF4444') || fill.includes('EF4444') || fill.includes('255, 68, 68')) caries++;
                if (fill.includes('4A90D9') || fill.includes('2563EB') || fill.includes('74, 144, 217')) obturaciones++;
                if (fill.includes('FFD700') || fill.includes('F59E0B') || fill.includes('255, 215, 0')) coronas++;
            }
        });
        document.getElementById('kpi-evaluados').textContent = Math.ceil(evaluados / 2);
        document.getElementById('kpi-caries').textContent = caries;
        document.getElementById('kpi-obturaciones').textContent = obturaciones;
        document.getElementById('kpi-coronas').textContent = coronas;
    }

    function showToast(msg, tipo) {
        const cont = document.querySelector('.toast-container');
        const id = 'toast-' + Date.now();
        const bg = tipo === 'success' ? 'bg-success' : tipo === 'warning' ? 'bg-warning text-dark' : 'bg-danger';
        cont.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast align-items-center text-white ${bg} border-0 shadow-lg" role="alert">
                <div class="d-flex">
                    <div class="toast-body fw-semibold">${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        const el = document.getElementById(id);
        const toastObj = new bootstrap.Toast(el, { delay: 2200 });
        toastObj.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }

    // Calcular estadísticas iniciales
    setTimeout(recalcularEstadisticas, 300);
})();
</script>

</body>
</html>
