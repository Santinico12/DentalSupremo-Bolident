<?php
/**
 * Eliminar Presupuesto
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user']) || !isset($_GET['id'])) {
    header('Location: presupuestos.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Presupuesto.php';

$presupuestoModel = new Presupuesto($pdo);
$result = $presupuestoModel->eliminar($_GET['id']);

if ($result) {
    $_SESSION['message'] = 'Presupuesto eliminado correctamente';
    $_SESSION['message_type'] = 'success';
} else {
    $_SESSION['message'] = 'Error al eliminar el presupuesto';
    $_SESSION['message_type'] = 'danger';
}

header('Location: presupuestos.php');
exit();
?>
