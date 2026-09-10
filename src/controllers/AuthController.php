<?php
require_once __DIR__ . '/../models/User.php';

// No necesitas session_start() aquí, ya que se llama en login.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $userModel = new User($pdo); // Asegúrate de que $pdo esté definido en login.php
    $user = $userModel->login($username, $password);

    if ($user) {
        $_SESSION['user'] = $user;
        header('Location: calendario.php');
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>