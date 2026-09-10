<?php
/**
 * Endpoint para guardar/actualizar condiciones del odontograma
 * Recibe JSON: { cliente_id, diente, superficie, condicion, notas }
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
require_once '../src/models/Odontograma.php';

// Obtener datos JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
if (!isset($data['cliente_id']) || !isset($data['diente']) || !isset($data['condicion'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$clienteId = (int)$data['cliente_id'];
$diente = htmlspecialchars($data['diente']);
$superficie = isset($data['superficie']) ? htmlspecialchars($data['superficie']) : 'completo';
$condicion = htmlspecialchars($data['condicion']);
$notas = isset($data['notas']) ? htmlspecialchars($data['notas']) : '';

// Validar que el diente sea válido (11-48, 51-85)
if (!preg_match('/^[1-8][1-8]$/', $diente)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de diente inválido']);
    exit();
}

$odontogramaModel = new Odontograma($pdo);

try {
    // Si la condición es "sano", eliminar el registro
    if ($condicion === 'sano' || $condicion === 'limpiar') {
        $result = $odontogramaModel->eliminar($clienteId, $diente, $superficie);
        echo json_encode([
            'success' => true,
            'message' => 'Diente marcado como sano',
            'accion' => 'eliminado'
        ]);
    } else {
        // Guardar o actualizar
        $result = $odontogramaModel->guardar($clienteId, $diente, $superficie, $condicion, $notas);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Condición guardada correctamente',
                'accion' => 'guardado'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se pudo guardar la condición'
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
