<?php
/**
 * API para guardar nuevo tratamiento
 */
date_default_timezone_set('America/La_Paz');
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['nombre']) || !isset($data['precio'])) {
    echo json_encode(['success' => false, 'error' => 'Nombre y precio son requeridos']);
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Tratamiento.php';

$tratamientoModel = new Tratamiento($pdo);

try {
    // Generar código automático
    $codigo = 'CUST' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    $id = $tratamientoModel->crear([
        'codigo' => $codigo,
        'nombre' => $data['nombre'],
        'descripcion' => $data['descripcion'] ?? '',
        'precio' => floatval($data['precio']),
        'categoria' => $data['categoria'] ?? 'Personalizado'
    ]);
    
    // Devolver el tratamiento creado
    $tratamiento = $tratamientoModel->getById($id);
    
    echo json_encode([
        'success' => true,
        'tratamiento' => $tratamiento,
        'message' => 'Tratamiento creado correctamente'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
}
?>
