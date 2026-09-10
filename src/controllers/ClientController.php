<?php
require_once __DIR__ . '/../models/Client.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];

    $clientModel = new Client($pdo);
    $clientId = $clientModel->create($nombre, $telefono);

    if ($clientId) {
        header('Location: dashboard.php');
    } else {
        echo "Error al registrar el cliente.";
    }
}
?>