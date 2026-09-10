<?php
/**
 * Eliminar Movimiento (Egreso o Ingreso General)
 * Elimina el registro y el comprobante del servidor si existe
 */
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    header('Location: caja.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Caja.php';

$cajaModel = new Caja($pdo);
$id = intval($_GET['id']);
$tipo = $_GET['tipo'];

if ($tipo === 'egreso') {
    $egreso = $cajaModel->getEgresoById($id);
    if ($egreso) {
        // Eliminar comprobante de disco si existe
        if (!empty($egreso['comprobante_ruta']) && file_exists($egreso['comprobante_ruta'])) {
            unlink($egreso['comprobante_ruta']);
        }
        $cajaModel->eliminarEgreso($id);
        $_SESSION['message'] = 'Egreso eliminado con éxito';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'El egreso no existe o ya fue eliminado';
        $_SESSION['message_type'] = 'danger';
    }
} elseif ($tipo === 'ingreso_otro') {
    $ingreso = $cajaModel->getOtroIngresoById($id);
    if ($ingreso) {
        // Eliminar comprobante de disco si existe
        if (!empty($ingreso['comprobante_ruta']) && file_exists($ingreso['comprobante_ruta'])) {
            unlink($ingreso['comprobante_ruta']);
        }
        $cajaModel->eliminarOtroIngreso($id);
        $_SESSION['message'] = 'Ingreso general eliminado con éxito';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'El ingreso no existe o ya fue eliminado';
        $_SESSION['message_type'] = 'danger';
    }
} else {
    $_SESSION['message'] = 'Tipo de movimiento no permitido para eliminación';
    $_SESSION['message_type'] = 'danger';
}

header('Location: caja.php');
exit();
?>
