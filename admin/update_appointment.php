<?php
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit();
    }
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Appointment.php';

$appointmentModel = new Appointment($pdo);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $fechaInput = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
    $horaInput = isset($_POST['hora']) ? trim($_POST['hora']) : '';
    
    // Si la fecha y hora vienen por separado
    if (!empty($horaInput) && strlen($fechaInput) <= 10) {
        $fechaCompleta = $fechaInput . ' ' . $horaInput;
    } else {
        $fechaCompleta = $fechaInput;
    }

    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $duracion_estimada = isset($_POST['duracion_estimada']) ? (int)$_POST['duracion_estimada'] : 30;
    $consultorio_id = isset($_POST['consultorio_id']) ? (int)$_POST['consultorio_id'] : 1;
    $doctor_id = (!empty($_POST['doctor_id']) && $_POST['doctor_id'] !== '0') ? (int)$_POST['doctor_id'] : null;

    if ($id > 0 && !empty($fechaCompleta)) {
        // Verificar si hay conflicto de horario excluyendo la cita actual
        $conflictCheck = $appointmentModel->checkConflict($consultorio_id, $fechaCompleta, $duracion_estimada, $id);
        if ($conflictCheck['conflict']) {
            $msgError = "Conflicto de horario: Ya existe " . ($conflictCheck['type'] === 'cita' ? 'una cita' : 'un evento') . 
                        " ('" . $conflictCheck['nombre'] . "', " . $conflictCheck['hora_inicio'] . " - " . $conflictCheck['hora_fin'] . 
                        ") en ese consultorio a esa hora.";
            $_SESSION['message'] = $msgError;
            $_SESSION['message_type'] = "danger";
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msgError]);
                exit();
            }
            
            $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'dashboard.php';
            header("Location: $redirect");
            exit();
        }

        if ($appointmentModel->update($id, $fechaCompleta, $descripcion, $duracion_estimada, $consultorio_id, $doctor_id)) {
            $_SESSION['message'] = "Cita y horario actualizados correctamente.";
            $_SESSION['message_type'] = "success";
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Cita actualizada correctamente']);
                exit();
            }
        } else {
            $_SESSION['message'] = "Error al actualizar los datos de la cita.";
            $_SESSION['message_type'] = "danger";
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error al guardar cambios']);
                exit();
            }
        }
    } else {
        $_SESSION['message'] = "Datos de la cita no válidos.";
        $_SESSION['message_type'] = "warning";
        
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit();
        }
    }

    $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 'dashboard.php';
    header("Location: $redirect");
    exit();
}
?>