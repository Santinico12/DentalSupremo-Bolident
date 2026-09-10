<?php
/**
 * Endpoint para marcar recordatorio como enviado
 * Recibe JSON: { "id": 123 }
 */
date_default_timezone_set('America/La_Paz');
session_start();

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';

// Obtener datos JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !is_numeric($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de cita inválido']);
    exit();
}

$appointmentModel = new Appointment($pdo);
$id = (int)$data['id'];

try {
    $result = $appointmentModel->marcarRecordatorioEnviado($id);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Recordatorio marcado como enviado'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No se pudo actualizar el registro'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
