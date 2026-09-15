<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Client.php';

$clientModel = new Client($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $telefono = trim($_POST['telefono']);

    // Consultar el cliente anterior para verificar si cambió el teléfono
    $clientePrevio = $clientModel->getById($id);
    $telefonoPrevio = $clientePrevio ? trim($clientePrevio['telefono']) : '';
    
    // Solo si el teléfono es diferente (y no está vacío)
    $telefonoCambio = (!empty($telefono) && $telefono !== $telefonoPrevio);

    if ($clientModel->update($id, $nombre, $telefono)) {
        $_SESSION['message'] = "Cliente actualizado correctamente.";
        $_SESSION['message_type'] = "success";

        // Enviar mensaje SOLO cuando se modifique el número de teléfono
        if ($telefonoCambio) {
            try {
                $updateMsg = "🦷 *Dental Supremo - Actualización de Contacto* ✨\n\n"
                           . "¡Hola *{$nombre}*! 👋\n"
                           . "Hemos actualizado con éxito tu número de contacto para tus atenciones y citas odontológicas en *Dental Supremo*.\n\n"
                           . "Recuerda que desde este número dispones de nuestro Asistente Virtual 24/7 para consultar y confirmar tus citas en cualquier momento. ¡Cuidamos de tu sonrisa! 😊✨";

                $botApiUrl = getenv('BOT_API_URL') ? rtrim(getenv('BOT_API_URL'), '/') . '/send-welcome' : 'https://dentalsupremo-bot.onrender.com/api/send-welcome';
                $postData = json_encode([
                    'clienteId' => $id,
                    'telefono' => $telefono,
                    'pacienteNombre' => $nombre,
                    'mensaje' => $updateMsg
                ]);

                $ch = curl_init($botApiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                @curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {}
        }
    } else {
        $_SESSION['message'] = "Error al actualizar el cliente.";
        $_SESSION['message_type'] = "danger";
    }

    header('Location: lista_clientes.php');
    exit();
}
?>