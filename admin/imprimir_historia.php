<?php
/**
 * Impresión / PDF Oficial de Historia Clínica Odontológica
 * Reproducción de alta fidelidad del formulario oficial de Bolivia
 * Dra. Tatiana Ruiz - Cochabamba, Bolivia
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user']) && empty($_GET['token'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../src/config/db.php';
require_once __DIR__ . '/../src/models/Client.php';
require_once __DIR__ . '/../src/models/HistoriaClinica.php';

$clientModel = new Client($pdo);
$historiaModel = new HistoriaClinica($pdo);

$clienteId = intval($_GET['id'] ?? $_GET['cliente_id'] ?? 0);
$token = trim($_GET['token'] ?? '');

$historia = null;
if (!empty($token)) {
    $historia = $historiaModel->getByToken($token);
    if ($historia) $clienteId = intval($historia['cliente_id']);
} elseif ($clienteId > 0) {
    $historia = $historiaModel->getByCliente($clienteId);
}

if (!$clienteId || !$historia) {
    die('Historia clínica no encontrada.');
}

$cliente = $clientModel->getById($clienteId);
if (!$cliente) {
    die('Paciente no encontrado.');
}

// Procesar patologías seleccionadas
$patologias = [];
if (!empty($historia['patologias_personales'])) {
    $patologias = is_array($historia['patologias_personales']) 
        ? $historia['patologias_personales'] 
        : json_decode($historia['patologias_personales'], true);
}
if (!is_array($patologias)) $patologias = [];

function checkMark($condition) {
    return $condition ? '&#9746;' : '&#9744;';
}

function hasPatologia($patologias, $key) {
    return in_array($key, $patologias);
}

// Separar nombre si es posible
$nombreCompleto = trim($cliente['nombre']);
$partesNombre = explode(' ', $nombreCompleto);
$apPaterno = '';
$apMaterno = '';
$nombres = $nombreCompleto;
if (count($partesNombre) >= 3) {
    $apPaterno = $partesNombre[0];
    $apMaterno = $partesNombre[1];
    $nombres = implode(' ', array_slice($partesNombre, 2));
} elseif (count($partesNombre) === 2) {
    $apPaterno = $partesNombre[0];
    $nombres = $partesNombre[1];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historia Clínica Odontológica — <?php echo htmlspecialchars($cliente['nombre']); ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 10mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            background: #525659;
            color: #000;
            padding: 15px;
            font-size: 10.5px;
            line-height: 1.25;
        }

        .sheet-container {
            width: 195mm;
            min-height: 275mm;
            margin: 0 auto;
            background: #fff;
            padding: 10mm 12mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            position: relative;
        }

        /* Barra de Control de Impresión en Pantalla */
        .print-toolbar {
            width: 195mm;
            margin: 0 auto 12px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #1e293b;
            color: white;
            padding: 10px 16px;
            border-radius: 8px;
        }

        .btn-print {
            background: #16a34a;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back {
            background: #475569;
            color: white;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
        }

        /* Encabezado Oficial */
        .header-table {
            width: 100%;
            margin-bottom: 6px;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
        }

        .title-main {
            text-align: center;
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 0.5px;
            color: #000;
        }

        /* Estructura de Tablas con Bordes Oficiales */
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            border: 1px solid #000;
        }

        .form-table th, .form-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            font-size: 9.5px;
            vertical-align: middle;
        }

        .form-table th {
            background: #f0f0f0;
            text-align: left;
            font-weight: 800;
            font-size: 9.5px;
        }

        .sec-title {
            font-weight: 900;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.2px;
        }

        .sec-header-row {
            background: #fff;
            border-bottom: 1px solid #000;
            font-weight: 800;
        }

        .check-box {
            font-size: 12px;
            margin-right: 2px;
            vertical-align: middle;
        }

        .data-val {
            font-weight: 600;
            color: #000;
        }

        .dotted-line {
            border-bottom: 1px dotted #555;
            min-height: 14px;
            display: inline-block;
        }

        .text-box-area {
            border: 1px solid #000;
            min-height: 48px;
            padding: 4px;
            margin-bottom: 5px;
            font-size: 9.5px;
            background: #fff;
        }

        .text-box-area .title {
            font-weight: 900;
            text-transform: uppercase;
            font-size: 9.5px;
            margin-bottom: 2px;
        }

        .text-box-area .content {
            font-size: 9px;
            line-height: 1.3;
        }

        /* Declaración Jurada y Firma */
        .declaration-box {
            margin-top: 6px;
            font-size: 9px;
            line-height: 1.3;
        }

        .legal-stmt {
            text-align: justify;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .signature-table td {
            border: none;
            padding: 2px 4px;
            vertical-align: bottom;
            font-size: 9.5px;
        }

        .signature-space {
            text-align: center;
            height: 55px;
            vertical-align: bottom;
        }

        .signature-img {
            max-height: 48px;
            max-width: 180px;
            display: block;
            margin: 0 auto;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .print-toolbar {
                display: none !important;
            }
            .sheet-container {
                width: 100%;
                box-shadow: none;
                padding: 0;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<!-- Barra superior no imprimible -->
<div class="print-toolbar">
    <div>
        <strong>Formato Oficial: Historia Clínica Odontológica</strong>
        <span style="opacity: 0.8; font-size: 12px; margin-left: 8px;">(Listo para imprimir o exportar a PDF)</span>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="historia_clinica.php?id=<?php echo $clienteId; ?>" class="btn-back">⬅ Volver al Panel</a>
        <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
    </div>
</div>

<div class="sheet-container">

    <!-- 1. Encabezado con Escudo y Emblema -->
    <table class="header-table">
        <tr>
            <td style="width: 60px; text-align: left;">
                <!-- Escudo de Bolivia (SVG vectorial para máxima nitidez en impresión) -->
                <svg width="50" height="42" viewBox="0 0 100 80">
                    <circle cx="50" cy="40" r="32" fill="#d4af37" stroke="#8c6d1f" stroke-width="2"/>
                    <rect x="25" y="22" width="50" height="7" fill="#dc2626"/>
                    <rect x="25" y="29" width="50" height="7" fill="#facc15"/>
                    <rect x="25" y="36" width="50" height="7" fill="#16a34a"/>
                    <text x="50" y="58" font-size="10" font-weight="bold" text-anchor="middle" fill="#000">BOLIVIA</text>
                </svg>
            </td>
            <td>
                <h1 class="title-main">HISTORIA CLÍNICA ODONTOLÓGICA</h1>
            </td>
            <td style="width: 60px; text-align: right;">
                <!-- Emblema Odontológico -->
                <svg width="45" height="45" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="44" fill="none" stroke="#6B1D49" stroke-width="4"/>
                    <path d="M30,35 Q30,70 50,85 Q70,70 70,35 Q50,45 30,35 Z" fill="#6B1D49"/>
                    <line x1="50" y1="20" x2="50" y2="80" stroke="#fff" stroke-width="4"/>
                </svg>
            </td>
        </tr>
    </table>

    <!-- 2. DATOS PERSONALES -->
    <table class="form-table">
        <tr>
            <td colspan="4">
                <span class="sec-title">DATOS PERSONALES</span>
                <span style="margin-left: 20px;">Codificación: <strong class="data-val"><?php echo htmlspecialchars($historia['codificacion'] ?: '—'); ?></strong></span>
                <span style="margin-left: 30px;">N. H.C. <strong class="data-val"><?php echo htmlspecialchars($historia['numero_hc'] ?: ('HC-' . str_pad($clienteId, 5, '0', STR_PAD_LEFT))); ?></strong></span>
            </td>
            <td style="width: 50px; text-align: center;">
                Edad: <strong class="data-val"><?php echo $historia['edad'] ? $historia['edad'] : '—'; ?></strong>
            </td>
            <td style="width: 65px; text-align: center;">
                Sexo: <strong class="data-val"><?php echo htmlspecialchars($historia['sexo'] ?: '—'); ?></strong>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="width: 30%;">
                Apellido Paterno: <strong class="data-val"><?php echo htmlspecialchars($apPaterno ?: '—'); ?></strong>
            </td>
            <td colspan="2" style="width: 30%;">
                Apellido Materno: <strong class="data-val"><?php echo htmlspecialchars($apMaterno ?: '—'); ?></strong>
            </td>
            <td colspan="2">
                Nombres: <strong class="data-val"><?php echo htmlspecialchars($nombres ?: $nombreCompleto); ?></strong>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                Lugar y Fecha de Nacimiento: 
                <strong class="data-val">
                    <?php echo htmlspecialchars($historia['lugar_nacimiento'] ?: 'Cochabamba'); ?>, 
                    <?php echo $historia['fecha_nacimiento'] ? date('d/m/Y', strtotime($historia['fecha_nacimiento'])) : '—'; ?>
                </strong>
            </td>
            <td style="width: 22%;">
                Ocupación: <strong class="data-val"><?php echo htmlspecialchars($historia['ocupacion'] ?: '—'); ?></strong>
            </td>
            <td colspan="2">
                Dirección: <strong class="data-val"><?php echo htmlspecialchars($historia['direccion'] ?: '—'); ?></strong>
            </td>
            <td>
                Teléfono-celular: <strong class="data-val"><?php echo htmlspecialchars($cliente['telefono']); ?></strong>
            </td>
        </tr>
    </table>

    <!-- 3. ANTECEDENTES PATOLÓGICOS Y FAMILIARES -->
    <table class="form-table">
        <tr>
            <td>
                <span class="sec-title">ANTECEDENTES PATOLÓGICOS Y FAMILIARES:</span>
                <span class="data-val" style="margin-left: 8px;">
                    <?php echo htmlspecialchars($historia['antecedentes_familiares'] ?: 'No refiere antecedentes patológicos familiares relevantes.'); ?>
                </span>
            </td>
        </tr>
    </table>

    <!-- 4. ANTECEDENTES PATOLÓGICOS Y PERSONALES -->
    <table class="form-table">
        <tr>
            <th colspan="4" class="sec-title">ANTECEDENTES PATOLÓGICOS Y PERSONALES:</th>
        </tr>
        <tr>
            <td style="width: 25%;">
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'anemia')); ?></span> Anemia
            </td>
            <td style="width: 25%;">
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'cardiopatias')); ?></span> Cardiopatías
            </td>
            <td style="width: 25%;">
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'chagas')); ?></span> Chagas
            </td>
            <td style="width: 25%;">
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'enf_gastrica')); ?></span> Enf. Gástrica
            </td>
        </tr>
        <tr>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'hepatitis')); ?></span> Hepatitis
            </td>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'tuberculosis')); ?></span> Tuberculosis
            </td>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'asma')); ?></span> Asma
            </td>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'diabetes')); ?></span> Diabetes
            </td>
        </tr>
        <tr>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'epilepsia')); ?></span> Epilepsia
            </td>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'hipertension')); ?></span> Hipertensión
            </td>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'vih')); ?></span> VIH
            </td>
            <td>
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'problemas_renales')); ?></span> Problemas Renales
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="check-box"><?php echo checkMark(hasPatologia($patologias, 'problemas_coagulacion')); ?></span> Problemas de Coagulación sanguínea
            </td>
            <td colspan="2">
                Otros: <strong class="data-val"><?php echo htmlspecialchars($historia['enfermedades_sistemicas'] ?: 'Ninguno'); ?></strong>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                Alergias: <strong class="data-val"><?php echo htmlspecialchars($historia['alergias'] ?: 'Ninguna conocida'); ?></strong>
            </td>
            <td colspan="2">
                Embarazo: <span class="check-box"><?php echo checkMark(!empty($historia['embarazo'])); ?></span> Sí &nbsp;&nbsp; <span class="check-box"><?php echo checkMark(empty($historia['embarazo'])); ?></span> No
            </td>
        </tr>
        <tr>
            <td colspan="2">
                ¿Está en tratamiento médico?: <strong class="data-val"><?php echo htmlspecialchars($historia['en_tratamiento_medico'] ?: 'No'); ?></strong>
            </td>
            <td colspan="2">
                ¿Toma algún medicamento?: <strong class="data-val"><?php echo htmlspecialchars($historia['toma_medicamento'] ?: ($historia['medicamentos_actuales'] ?: 'No')); ?></strong>
            </td>
        </tr>
        <tr>
            <td colspan="4">
                ¿Tuvo alguna hemorragia después de una extracción dental?:
                &nbsp;&nbsp; 
                <span class="check-box"><?php echo checkMark(($historia['hemorragia_extraccion'] ?? '') === 'No' || empty($historia['hemorragia_extraccion'])); ?></span> No &nbsp;&nbsp;
                <span class="check-box"><?php echo checkMark(($historia['hemorragia_extraccion'] ?? '') === 'Mediata'); ?></span> Mediata &nbsp;&nbsp;
                <span class="check-box"><?php echo checkMark(($historia['hemorragia_extraccion'] ?? '') === 'Inmediata'); ?></span> Inmediata
            </td>
        </tr>
    </table>

    <!-- 5. EXAMEN EXTRA ORAL & EXAMEN INTRA ORAL (2 Columnas) -->
    <table class="form-table">
        <tr>
            <th style="width: 50%;" class="sec-title">EXAMEN EXTRA ORAL</th>
            <th style="width: 50%;" class="sec-title">EXAMEN INTRA ORAL</th>
        </tr>
        <tr>
            <td>
                A.T.M.: <strong class="data-val"><?php echo htmlspecialchars($historia['atm'] ?: 'Normal / Sin dolor ni ruidos'); ?></strong>
            </td>
            <td>
                Labios: <strong class="data-val"><?php echo htmlspecialchars($historia['labios'] ?: 'Aparentemente normales'); ?></strong>
            </td>
        </tr>
        <tr>
            <td>
                Ganglios Linfáticos: <strong class="data-val"><?php echo htmlspecialchars($historia['ganglios_linfaticos'] ?: 'No palpables / Normales'); ?></strong>
            </td>
            <td>
                Lengua: <strong class="data-val"><?php echo htmlspecialchars($historia['lengua'] ?: 'Normal / Sin lesiones'); ?></strong>
            </td>
        </tr>
        <tr>
            <td>
                Respirador: &nbsp;
                <span class="check-box"><?php echo checkMark(($historia['tipo_respirador'] ?? '') === 'Nasal' || empty($historia['tipo_respirador'])); ?></span> Nasal &nbsp;
                <span class="check-box"><?php echo checkMark(($historia['tipo_respirador'] ?? '') === 'Bucal'); ?></span> Bucal &nbsp;
                <span class="check-box"><?php echo checkMark(($historia['tipo_respirador'] ?? '') === 'Buco nasal'); ?></span> Buco nasal
            </td>
            <td>
                Paladar: <strong class="data-val"><?php echo htmlspecialchars($historia['paladar'] ?: 'Normal'); ?></strong>
            </td>
        </tr>
        <tr>
            <td rowspan="4" style="vertical-align: top;">
                Otros: <strong class="data-val"><?php echo htmlspecialchars($historia['examen_extraoral_otros'] ?: 'Sin particularidad'); ?></strong>
            </td>
            <td>
                Piso de la Boca: <strong class="data-val"><?php echo htmlspecialchars($historia['piso_boca'] ?: 'Normal'); ?></strong>
            </td>
        </tr>
        <tr>
            <td>
                Mucosa Yugal: <strong class="data-val"><?php echo htmlspecialchars($historia['mucosa_yugal'] ?: 'Normal'); ?></strong>
            </td>
        </tr>
        <tr>
            <td>
                Encías: <strong class="data-val"><?php echo htmlspecialchars($historia['encias'] ?: 'Normales'); ?></strong>
            </td>
        </tr>
        <tr>
            <td>
                Utiliza Prótesis dental: &nbsp;
                <span class="check-box"><?php echo checkMark(!empty($historia['usa_protesis'])); ?></span> Sí &nbsp;&nbsp;&nbsp;
                <span class="check-box"><?php echo checkMark(empty($historia['usa_protesis'])); ?></span> No
            </td>
        </tr>
    </table>

    <!-- 6. ANTECEDENTES BUCODENTALES -->
    <table class="form-table">
        <tr>
            <th colspan="2" class="sec-title">ANTECEDENTES BUCODENTALES</th>
        </tr>
        <tr>
            <td style="width: 50%;">
                Fecha de la última visita al Odontólogo: 
                <strong class="data-val">
                    <?php echo $historia['ultima_visita_dentista'] ? date('d/m/Y', strtotime($historia['ultima_visita_dentista'])) : 'No recuerda / Mayor a 1 año'; ?>
                </strong>
            </td>
            <td>
                Hábitos: &nbsp;
                <span class="check-box"><?php echo checkMark(!empty($historia['habitos_fuma'])); ?></span> Fuma &nbsp;&nbsp;
                <span class="check-box"><?php echo checkMark(!empty($historia['habitos_bebe'])); ?></span> Bebe &nbsp;&nbsp;
                Otros: <strong class="data-val"><?php echo htmlspecialchars($historia['habitos_otros'] ?: ($historia['habitos'] ?: 'Ninguno')); ?></strong>
            </td>
        </tr>
    </table>

    <!-- 7. ANTECEDENTES DE HIGIENE ORAL -->
    <table class="form-table">
        <tr>
            <th colspan="3" class="sec-title">ANTECEDENTES DE HIGIENE ORAL</th>
        </tr>
        <tr>
            <td style="width: 30%;">
                Usa Cepillo dental: 
                <span class="check-box"><?php echo checkMark(!empty($historia['usa_cepillo'])); ?></span> Sí 
                <span class="check-box"><?php echo checkMark(empty($historia['usa_cepillo'])); ?></span> No
            </td>
            <td style="width: 30%;">
                Utiliza Hilo dental: 
                <span class="check-box"><?php echo checkMark(!empty($historia['usa_hilo'])); ?></span> Sí 
                <span class="check-box"><?php echo checkMark(empty($historia['usa_hilo'])); ?></span> No
            </td>
            <td>
                Utiliza enjuague Bucal: 
                <span class="check-box"><?php echo checkMark(!empty($historia['usa_enjuague'])); ?></span> Sí 
                <span class="check-box"><?php echo checkMark(empty($historia['usa_enjuague'])); ?></span> No
            </td>
        </tr>
        <tr>
            <td>
                Frecuencia de cepillado: <strong class="data-val"><?php echo htmlspecialchars($historia['frecuencia_cepillado'] ?: '2 a 3 veces al día'); ?></strong>
            </td>
            <td colspan="2">
                Durante el cepillado dental sangran las encías: 
                &nbsp;&nbsp;
                <span class="check-box"><?php echo checkMark(!empty($historia['sangrado_encias'])); ?></span> Sí &nbsp;&nbsp;&nbsp;
                <span class="check-box"><?php echo checkMark(empty($historia['sangrado_encias'])); ?></span> No
            </td>
        </tr>
        <tr>
            <td>
                Higiene dental: &nbsp;
                <span class="check-box"><?php echo checkMark(($historia['nivel_higiene_bucal'] ?? '') === 'Buena'); ?></span> Buena &nbsp;
                <span class="check-box"><?php echo checkMark(($historia['nivel_higiene_bucal'] ?? '') === 'Regular'); ?></span> Regular &nbsp;
                <span class="check-box"><?php echo checkMark(($historia['nivel_higiene_bucal'] ?? '') === 'Mala'); ?></span> Mala
            </td>
            <td colspan="2">
                ¿Ha tenido algún problema grave en un tratamiento dental anterior?: 
                <strong class="data-val"><?php echo htmlspecialchars($historia['problema_grave_dental_anterior'] ?: 'No refiere'); ?></strong>
            </td>
        </tr>
    </table>

    <!-- 8. OBSERVACIONES -->
    <div class="text-box-area">
        <div class="title">OBSERVACIONES:</div>
        <div class="content"><?php echo nl2br(htmlspecialchars($historia['observaciones'] ?: 'Sin observaciones particulares.')); ?></div>
    </div>

    <!-- 9. MOTIVO DE CONSULTA -->
    <div class="text-box-area">
        <div class="title">MOTIVO DE CONSULTA:</div>
        <div class="content"><?php echo nl2br(htmlspecialchars($historia['motivo_consulta'] ?: 'Evaluación y control odontológico general.')); ?></div>
    </div>

    <!-- 10. EXAMEN CLÍNICO -->
    <div class="text-box-area">
        <div class="title">EXAMEN CLÍNICO:</div>
        <div class="content"><?php echo nl2br(htmlspecialchars($historia['examen_clinico'] ?: 'Paciente normotípico, colaborador en la consulta. Tejidos blandos en buen estado aparente.')); ?></div>
    </div>

    <!-- 11. DIAGNOSTICO -->
    <div class="text-box-area">
        <div class="title">DIAGNOSTICO:</div>
        <div class="content"><?php echo nl2br(htmlspecialchars($historia['diagnostico'] ?: 'Evaluación clínica inicial en curso.')); ?></div>
    </div>

    <!-- 12. PLAN DE TRATAMIENTO -->
    <div class="text-box-area">
        <div class="title">PLAN DE TRATAMIENTO:</div>
        <div class="content"><?php echo nl2br(htmlspecialchars($historia['plan_tratamiento'] ?: 'Profilaxis dental y plan preventivo / restaurador.')); ?></div>
    </div>

    <!-- 13. DECLARACIÓN JURADA Y FIRMA -->
    <div class="declaration-box">
        <p class="legal-stmt">
            Declaro ciertos todos los datos relativos a mi historia clínica, no habiendo omitido ningún aspecto de interés o que me hubiera sido cuestionado.
        </p>

        <table class="signature-table">
            <tr>
                <td style="width: 55%;">
                    Nombre del paciente: <strong class="data-val"><?php echo htmlspecialchars($cliente['nombre']); ?></strong>
                </td>
                <td style="width: 45%; text-align: right;">
                    CI: <strong class="data-val"><?php echo htmlspecialchars($historia['ci'] ?: '—'); ?></strong>
                </td>
            </tr>
            <tr>
                <td class="signature-space">
                    <div style="font-size: 9px; color: #555; margin-bottom: 2px;">Firma del Paciente:</div>
                    <?php if (!empty($historia['firma_paciente'])): ?>
                        <img src="<?php echo $historia['firma_paciente']; ?>" class="signature-img" alt="Firma">
                    <?php else: ?>
                        <div style="height: 35px; border-bottom: 1px dotted #000; width: 220px; margin: 0 auto;"></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: right; vertical-align: bottom;">
                    Fecha: <strong class="data-val"><?php echo !empty($historia['fecha_firma']) ? date('d/m/Y', strtotime($historia['fecha_firma'])) : date('d/m/Y'); ?></strong>
                </td>
            </tr>
        </table>
    </div>

</div>

</body>
</html>
