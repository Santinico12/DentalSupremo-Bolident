<?php
/**
 * API para obtener datos de historia clínica de un paciente
 * Usado en el calendario para mostrar info rápida
 */
date_default_timezone_set('America/La_Paz');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$clienteId = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
$citaId = isset($_GET['cita_id']) ? intval($_GET['cita_id']) : 0;

if (!$clienteId && !$citaId) {
    echo json_encode(['success' => false, 'error' => 'ID requerido']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Client.php';
require_once '../src/models/HistoriaClinica.php';
require_once '../src/models/Evolucion.php';
require_once '../src/models/ArchivoClinico.php';

// Si viene cita_id, obtener el cliente_id
if ($citaId && !$clienteId) {
    $stmt = $pdo->prepare("SELECT cliente_id FROM citas WHERE id = :id");
    $stmt->execute([':id' => $citaId]);
    $cita = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cita) {
        $clienteId = $cita['cliente_id'];
    }
}

if (!$clienteId) {
    echo json_encode(['success' => false, 'error' => 'Cliente no encontrado']);
    exit();
}

$clientModel = new Client($pdo);
$historiaModel = new HistoriaClinica($pdo);
$evolucionModel = new Evolucion($pdo);
$archivoModel = new ArchivoClinico($pdo);

$cliente = $clientModel->getById($clienteId);
$historia = $historiaModel->getByCliente($clienteId);
$evoluciones = $evolucionModel->getByCliente($clienteId, 5); // Últimas 5
$archivos = $archivoModel->getByCliente($clienteId);
$alertas = $historiaModel->getAlertasMedicas($clienteId);

echo json_encode([
    'success' => true,
    'cliente_id' => $clienteId,
    'cliente' => $cliente,
    'historia' => $historia,
    'evoluciones' => $evoluciones,
    'archivos' => array_slice($archivos, 0, 4), // Solo mostrar 4 archivos
    'alertas' => $alertas,
    'total_evoluciones' => count($evolucionModel->getByCliente($clienteId)),
    'total_archivos' => count($archivos)
]);
?>
