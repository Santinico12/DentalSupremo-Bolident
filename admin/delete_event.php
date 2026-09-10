<?php
session_start();
require_once '../src/config/db.php';
require_once '../src/models/Event.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$evento_id = $input['evento_id'] ?? $input['id'] ?? null;

if (!$evento_id) {
    echo json_encode(['success' => false, 'message' => 'ID de evento no proporcionado']);
    exit();
}

try {
    $eventModel = new Event($pdo);
    
    if ($eventModel->delete((int)$evento_id)) {
        echo json_encode(['success' => true, 'message' => 'Evento eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el evento']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
