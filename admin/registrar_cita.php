<?php
session_start();
require_once '../src/config/db.php';
require_once '../src/models/Client.php';
require_once '../src/models/Consultorio.php';
require_once '../src/models/Appointment.php';
require_once '../src/models/Doctor.php';
require_once '../src/models/Event.php';

date_default_timezone_set('America/La_Paz'); // Cambia 'America/La_Paz' por tu zona horaria

$clientModel = new Client($pdo);
$consultorioModel = new Consultorio($pdo);
$doctorModel = new Doctor($pdo);
$appointmentModel = new Appointment($pdo);

$clientes = $clientModel->getAll();
$consultorios = $consultorioModel->getAll();
$doctores = $doctorModel->getActivos();

// Capturar los parámetros enviados desde el cronograma
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';
$consultorio_id = isset($_GET['consultorio_id']) ? $_GET['consultorio_id'] : '';

// Validar que la fecha y el consultorio estén presentes
$error = '';
if (empty($fecha) || empty($consultorio_id)) {
    $error = 'Debe seleccionar una fecha y un consultorio para registrar una cita.';
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
    $fechaInput = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
    $horaInput = isset($_POST['hora']) ? trim($_POST['hora']) : '';
    
    if (!empty($horaInput) && strlen($fechaInput) <= 10) {
        $fechaCompleta = $fechaInput . ' ' . $horaInput;
    } else {
        $fechaCompleta = $fechaInput;
    }

    $descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
    $descripcion = preg_replace("/[\r\n]+/u", ' ', (string)$descripcion);
    $descripcion = preg_replace('/\s{2,}/u', ' ', trim($descripcion));
    $duracion_estimada = isset($_POST['duracion_estimada']) ? (int)$_POST['duracion_estimada'] : 30;
    $consultorio_id = isset($_POST['consultorio_id']) ? (int)$_POST['consultorio_id'] : 1;
    $doctor_id = (!empty($_POST['doctor_id']) && $_POST['doctor_id'] !== '0') ? (int)$_POST['doctor_id'] : null;

    if (empty($cliente_id) || empty($fechaCompleta)) {
        $error = 'Por favor seleccione el cliente y la fecha/hora de la cita.';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit();
        }
    } else {
        $conflictCheck = $appointmentModel->checkConflict($consultorio_id, $fechaCompleta, $duracion_estimada);
        if ($conflictCheck['conflict']) {
            $error = "Conflicto de horario: Ya existe " . ($conflictCheck['type'] === 'cita' ? 'una cita' : 'un evento') . 
                     " ('" . $conflictCheck['nombre'] . "', " . $conflictCheck['hora_inicio'] . " - " . $conflictCheck['hora_fin'] . 
                     ") en el consultorio seleccionado. Por favor elija otro horario.";
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit();
            }
        } else {
            $appointmentModel->create($cliente_id, $fechaCompleta, $descripcion, $duracion_estimada, $consultorio_id, $doctor_id);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Cita registrada correctamente']);
                exit();
            }
            header('Location: calendario.php?mensaje=Cita registrada correctamente');
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Cita</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/registrar_cita.css">
    
    <style>
        :root {
            --primary-color: #6B1D49;
            --secondary-color: #C47D9F;
        }
        
        body {
            background-color: #FDF8FA;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .container {
            max-width: 800px;
            margin-top: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem;
        }
        
        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 0.5rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(196, 162, 126, 0.25);
        }
        
        .btn-registrar {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-registrar:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-volver {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-right: auto;
        }
        
        .btn-volver:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0">
                    <i class="fas fa-calendar-plus me-2"></i>
                    Registrar Cita
                </h2>
            </div>
            <div class="card-body">

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registro-cita">
            <div class="mb-3">
                <label for="cliente_id" class="form-label">Cliente:</label>
                <select id="cliente_id" name="cliente_id" class="form-select select2" required>
                    <option value="" selected disabled>Seleccione un cliente</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= $cliente['id']; ?>"><?= htmlspecialchars($cliente['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Campo oculto para la fecha -->
            <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Campo oculto para el consultorio -->
            <input type="hidden" name="consultorio_id" value="<?= htmlspecialchars($consultorio_id, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Mostrar la fecha seleccionada -->
            <div class="mb-3">
                <label for="fecha_mostrada" class="form-label">Fecha seleccionada:</label>
                <input type="text" id="fecha_mostrada" class="form-control" value="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>

            <!-- Mostrar el consultorio seleccionado -->
            <div class="mb-3">
                <label for="consultorio_mostrado" class="form-label">Consultorio seleccionado:</label>
                <select id="consultorio_mostrado" class="form-select" disabled>
                    <?php foreach ($consultorios as $consultorio): ?>
                        <option value="<?= $consultorio['id']; ?>" <?= $consultorio['id'] == $consultorio_id ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($consultorio['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción:</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="3" required></textarea>
            </div>

            <div class="mb-3">
                <label for="duracion_estimada" class="form-label">Duración:</label>
                <select id="duracion_estimada" name="duracion_estimada" class="form-select" required>
                    <option value="15">15 minutos</option>
                    <option value="30">30 minutos</option>
                    <option value="45">45 minutos</option>
                    <option value="60">1 hora</option>
                    <option value="75">1 hora y 15 minutos</option>
                    <option value="90">1 hora y 30 minutos</option>
                    <option value="105">1 hora y 45 minutos</option>
                    <option value="120">2 horas</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="doctor_id" class="form-label">Doctor (Opcional):</label>
                <select id="doctor_id" name="doctor_id" class="form-select select2">
                    <option value="">Sin asignar</option>
                    <?php foreach ($doctores as $doctor): ?>
                        <option value="<?= $doctor['id']; ?>"><?= htmlspecialchars($doctor['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            </form>
            <div class="button-container">
                <a href="calendario.php" class="btn-volver">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
                <button type="submit" form="registro-cita" class="btn-registrar" <?= empty($fecha) ? 'disabled' : ''; ?>>
                    <i class="fas fa-save me-2"></i>Registrar
                </button>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        $('#cliente_id').select2({
            placeholder: "Seleccione un cliente",
            allowClear: true,
            width: '100%' // Ajusta el ancho al contenedor
        });
    });
</script>
</body>
</html>
