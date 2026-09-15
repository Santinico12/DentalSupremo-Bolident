<?php
/**
 * API Server-Side Pagination - Clientes Dental Supremo
 * Procesa peticiones DataTables Server-Side con respuestas ultra-rápidas JSON
 */
date_default_timezone_set('America/La_Paz');
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Client.php';

$clientModel = new Client($pdo);

$draw = intval($_POST['draw'] ?? $_GET['draw'] ?? 1);
$start = intval($_POST['start'] ?? $_GET['start'] ?? 0);
$length = intval($_POST['length'] ?? $_GET['length'] ?? 15);

// Obtener parámetro de búsqueda de DataTables
$search = '';
if (isset($_POST['search']['value'])) {
    $search = trim($_POST['search']['value']);
} elseif (isset($_GET['search']['value'])) {
    $search = trim($_GET['search']['value']);
} elseif (isset($_POST['search']) && is_string($_POST['search'])) {
    $search = trim($_POST['search']);
}

$orderColIndex = intval($_POST['order'][0]['column'] ?? $_GET['order'][0]['column'] ?? 0);
$orderDir = $_POST['order'][0]['dir'] ?? $_GET['order'][0]['dir'] ?? 'asc';

$clientes = $clientModel->getPaginated($start, $length, $search, $orderColIndex, $orderDir);
$counts = $clientModel->getPaginatedCounts($search);

$data = [];

foreach ($clientes as $c) {
    $iniciales = strtoupper(substr($c['nombre'], 0, 1));
    $nombreEsc = htmlspecialchars($c['nombre'], ENT_QUOTES);
    $telefonoEsc = htmlspecialchars($c['telefono'], ENT_QUOTES);
    $cleanPhone = preg_replace('/[^0-9]/', '', $c['telefono']);
    
    // Columna 0: Nombre + Avatar
    $colNombre = '
        <div class="client-name">
            <div class="client-avatar">' . $iniciales . '</div>
            <div class="client-info">
                <h6>' . htmlspecialchars($c['nombre']) . '</h6>
            </div>
        </div>';
        
    // Columna 1: Teléfono Badge
    $colTelefono = '
        <span class="phone-badge">
            <i class="fas fa-phone-alt"></i> ' . htmlspecialchars($c['telefono']) . '
        </span>';
        
    // Columna 2: Botones de Acción
    $colAcciones = '
        <div class="action-buttons justify-content-end">
            <button class="btn-action btn-edit" onclick="editClient(' . $c['id'] . ', \'' . $nombreEsc . '\', \'' . $telefonoEsc . '\')" title="Editar Paciente">
                <i class="fas fa-pen"></i>
            </button>
            <a href="https://wa.me/' . $cleanPhone . '" target="_blank" class="btn-action btn-whatsapp" title="Enviar WhatsApp">
                <i class="fab fa-whatsapp"></i>
            </a>
            <a href="historia_clinica.php?id=' . $c['id'] . '" class="btn-action btn-historia" title="Historia Clínica">
                <i class="fas fa-file-medical"></i>
            </a>
            <a href="odontograma.php?cliente_id=' . $c['id'] . '" class="btn-action btn-odontograma" title="Odontograma">
                <i class="fas fa-tooth"></i>
            </a>
            <button class="btn-action btn-historial" onclick="openHistorial(' . $c['id'] . ', \'' . $nombreEsc . '\', \'' . $telefonoEsc . '\')" title="Historial de Citas">
                <i class="fas fa-history"></i>
            </button>
            <button class="btn-action btn-delete" onclick="deleteClient(' . $c['id'] . ')" title="Eliminar">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>';

    // Matriz 2D para DataTables con propiedades adicionales para sincronización móvil
    $data[] = [
        $colNombre,
        $colTelefono,
        $colAcciones,
        'DT_RowId' => 'cliente_' . $c['id'],
        'raw_id' => $c['id'],
        'raw_nombre' => $c['nombre'],
        'raw_telefono' => $c['telefono']
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => (int)$counts['recordsTotal'],
    'recordsFiltered' => (int)$counts['recordsFiltered'],
    'data' => $data
], JSON_UNESCAPED_UNICODE);
?>
