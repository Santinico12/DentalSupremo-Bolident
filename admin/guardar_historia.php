<?php
/**
 * Guardar Historia Clínica (Ficha Médica Odontológica Oficial)
 * Procesa todos los campos del formato oficial de Bolivia y firma digital
 */
date_default_timezone_set('America/La_Paz');
session_start();

$isAjax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'No autorizado']);
        exit();
    }
    header('Location: lista_clientes.php');
    exit();
}

$clienteId = intval($_POST['cliente_id'] ?? 0);
if (!$clienteId) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'ID de paciente requerido']);
        exit();
    }
    header('Location: lista_clientes.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/HistoriaClinica.php';

$historiaModel = new HistoriaClinica($pdo);

// Procesar patologías personales (checkboxes)
$patologiasArray = [];
if (isset($_POST['patologias']) && is_array($_POST['patologias'])) {
    $patologiasArray = array_map('trim', $_POST['patologias']);
}

// Subida o captura de firma digital si viene en la petición
$firmaBase64 = null;
if (!empty($_POST['firma_paciente'])) {
    $firmaBase64 = trim($_POST['firma_paciente']);
}

$data = [
    'ci' => trim($_POST['ci'] ?? ''),
    'numero_hc' => trim($_POST['numero_hc'] ?? ''),
    'codificacion' => trim($_POST['codificacion'] ?? ''),
    'fecha_nacimiento' => !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null,
    'lugar_nacimiento' => trim($_POST['lugar_nacimiento'] ?? ''),
    'edad' => !empty($_POST['edad']) ? intval($_POST['edad']) : null,
    'sexo' => !empty($_POST['sexo']) ? $_POST['sexo'] : null,
    'ocupacion' => trim($_POST['ocupacion'] ?? ''),
    'direccion' => trim($_POST['direccion'] ?? ''),
    'email' => trim($_POST['email'] ?? ''),
    
    // Contacto de emergencia
    'contacto_emergencia_nombre' => trim($_POST['contacto_emergencia_nombre'] ?? ''),
    'contacto_emergencia_telefono' => trim($_POST['contacto_emergencia_telefono'] ?? ''),
    'contacto_emergencia_parentesco' => trim($_POST['contacto_emergencia_parentesco'] ?? ''),
    
    // Antecedentes
    'grupo_sanguineo' => trim($_POST['grupo_sanguineo'] ?? ''),
    'alergias' => trim($_POST['alergias'] ?? ''),
    'enfermedades_sistemicas' => trim($_POST['enfermedades_sistemicas'] ?? ''),
    'antecedentes_familiares' => trim($_POST['antecedentes_familiares'] ?? ''),
    'patologias_personales' => $patologiasArray,
    
    // Preguntas médicas específicas
    'en_tratamiento_medico' => trim($_POST['en_tratamiento_medico'] ?? ''),
    'toma_medicamento' => trim($_POST['toma_medicamento'] ?? ''),
    'hemorragia_extraccion' => trim($_POST['hemorragia_extraccion'] ?? 'No'),
    'medicamentos_actuales' => trim($_POST['medicamentos_actuales'] ?? ''),
    'cirugias_previas' => trim($_POST['cirugias_previas'] ?? ''),
    'hospitalizaciones' => trim($_POST['hospitalizaciones'] ?? ''),
    'ultima_visita_dentista' => !empty($_POST['ultima_visita_dentista']) ? $_POST['ultima_visita_dentista'] : null,
    'experiencia_anestesia' => trim($_POST['experiencia_anestesia'] ?? ''),
    
    // Examen Extra Oral
    'atm' => trim($_POST['atm'] ?? ''),
    'ganglios_linfaticos' => trim($_POST['ganglios_linfaticos'] ?? ''),
    'tipo_respirador' => trim($_POST['tipo_respirador'] ?? 'Nasal'),
    'examen_extraoral_otros' => trim($_POST['examen_extraoral_otros'] ?? ''),
    
    // Examen Intra Oral
    'labios' => trim($_POST['labios'] ?? ''),
    'lengua' => trim($_POST['lengua'] ?? ''),
    'paladar' => trim($_POST['paladar'] ?? ''),
    'piso_boca' => trim($_POST['piso_boca'] ?? ''),
    'mucosa_yugal' => trim($_POST['mucosa_yugal'] ?? ''),
    'encias' => trim($_POST['encias'] ?? ''),
    'usa_protesis' => isset($_POST['usa_protesis']) ? intval($_POST['usa_protesis']) : 0,
    
    // Hábitos
    'habitos' => trim($_POST['habitos'] ?? ''),
    'habitos_fuma' => isset($_POST['habitos_fuma']) ? 1 : 0,
    'habitos_bebe' => isset($_POST['habitos_bebe']) ? 1 : 0,
    'habitos_otros' => trim($_POST['habitos_otros'] ?? ''),
    
    // Higiene bucal
    'higiene_bucal' => trim($_POST['higiene_bucal'] ?? ''),
    'usa_cepillo' => isset($_POST['usa_cepillo']) ? intval($_POST['usa_cepillo']) : 1,
    'usa_hilo' => isset($_POST['usa_hilo']) ? intval($_POST['usa_hilo']) : 0,
    'usa_enjuague' => isset($_POST['usa_enjuague']) ? intval($_POST['usa_enjuague']) : 0,
    'frecuencia_cepillado' => trim($_POST['frecuencia_cepillado'] ?? ''),
    'sangrado_encias' => isset($_POST['sangrado_encias']) ? intval($_POST['sangrado_encias']) : 0,
    'nivel_higiene_bucal' => trim($_POST['nivel_higiene_bucal'] ?? 'Buena'),
    'problema_grave_dental_anterior' => trim($_POST['problema_grave_dental_anterior'] ?? ''),
    
    // Otros
    'embarazo' => isset($_POST['embarazo']) ? 1 : 0,
    'lactancia' => isset($_POST['lactancia']) ? 1 : 0,
    'observaciones' => trim($_POST['observaciones'] ?? ''),
    
    // Secciones clínicas
    'motivo_consulta' => trim($_POST['motivo_consulta'] ?? ''),
    'examen_clinico' => trim($_POST['examen_clinico'] ?? ''),
    'diagnostico' => trim($_POST['diagnostico'] ?? ''),
    'plan_tratamiento' => trim($_POST['plan_tratamiento'] ?? '')
];

// Si viene firma en la petición
if (!empty($firmaBase64)) {
    $data['firma_paciente'] = $firmaBase64;
    $data['fecha_firma'] = date('Y-m-d H:i:s');
}

$resultado = $historiaModel->guardar($clienteId, $data);

// Si se envió solo la firma (acción específica de firma)
if (isset($_POST['solo_firma']) && !empty($firmaBase64)) {
    $resultado = $historiaModel->guardarFirma($clienteId, $firmaBase64, $data['ci']);
}

if ($isAjax) {
    header('Content-Type: application/json');
    if ($resultado) {
        echo json_encode([
            'ok' => true,
            'mensaje' => 'Historia clínica y ficha médica guardadas exitosamente.',
            'fecha_firma' => !empty($data['fecha_firma']) ? date('d/m/Y H:i') : null
        ]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar la historia clínica.']);
    }
    exit();
}

if ($resultado) {
    $_SESSION['message'] = 'Historia Clínica Odontológica actualizada correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al guardar la Historia Clínica';
    $_SESSION['message_type'] = 'danger';
}

header('Location: historia_clinica.php?id=' . $clienteId);
exit();
?>
