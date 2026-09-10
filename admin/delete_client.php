<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Client.php';

$clientModel = new Client($pdo);

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    if ($clientModel->delete($id)) {
        $_SESSION['message'] = "Cliente eliminado correctamente.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error al eliminar el cliente.";
        $_SESSION['message_type'] = "danger";
    }

    header('Location: lista_clientes.php');
    exit();
}
?>