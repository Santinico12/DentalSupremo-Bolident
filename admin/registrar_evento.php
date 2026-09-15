<?php
require_once 'check_auth.php';
require_once '../src/config/db.php';
require_once '../src/models/Consultorio.php';
require_once '../src/models/Event.php';
require_once '../src/models/Appointment.php';

date_default_timezone_set('America/La_Paz');

$consultorioModel = new Consultorio($pdo);
$eventModel = new Event($pdo);
$appointmentModel = new Appointment($pdo);

$consultorios = $consultorioModel->getAll();

// Capturar parámetros desde el calendario
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';
$consultorio_id = isset($_GET['consultorio_id']) ? $_GET['consultorio_id'] : '';

$error = '';
if (empty($fecha) || empty($consultorio_id)) {
    $error = 'Debe seleccionar una fecha y un consultorio para registrar un evento.';
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : '';
    $fechaInput = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
    $horaInput = isset($_POST['hora']) ? trim($_POST['hora']) : '';
    
    if (!empty($horaInput) && strlen($fechaInput) <= 10) {
        $fechaCompleta = $fechaInput . ' ' . $horaInput;
    } else {
        $fechaCompleta = $fechaInput;
    }

    $duracion_estimada = isset($_POST['duracion_estimada']) ? (int)$_POST['duracion_estimada'] : 60;
    $consultorio_id = isset($_POST['consultorio_id']) ? (int)$_POST['consultorio_id'] : 0;
    $color = '#AEADAD';  // Color fijo para todos los eventos

    $error = '';

    if (empty($nombre)) {
        $error = 'El nombre del evento es obligatorio.';
    } elseif (empty($fechaCompleta)) {
        $error = 'La fecha es obligatoria.';
    } elseif (empty($consultorio_id)) {
        $error = 'El consultorio es obligatorio.';
    } else {
        try {
            // Normalizar descripción
            $descripcion = preg_replace("/[\r\n]+/u", ' ', (string)$descripcion);
            $descripcion = preg_replace('/\s{2,}/u', ' ', trim($descripcion));

            // Verificar disponibilidad de horario
            $horaInicio = date('H:i:s', strtotime($fechaCompleta));
            $horaFin = date('H:i:s', strtotime($horaInicio . " + $duracion_estimada minutes"));

            $eventos = $eventModel->getEventosByDateAndConsultorio(date('Y-m-d', strtotime($fechaCompleta)), $consultorio_id);
            
            $conflicto = false;
            foreach ($eventos as $evento) {
                $eventoInicio = date('H:i:s', strtotime($evento['fecha']));
                $eventoFin = date('H:i:s', strtotime($evento['finDeEvento']));

                if (($horaInicio < $eventoFin) && ($horaFin > $eventoInicio)) {
                    $conflicto = true;
                    break;
                }
            }
            
            // Verificar conflictos con citas si no hay conflicto con eventos
            if (!$conflicto) {
                $citas = $appointmentModel->getCitasByDateAndConsultorio(date('Y-m-d', strtotime($fechaCompleta)), $consultorio_id);
                
                foreach ($citas as $cita) {
                    // Ignorar citas canceladas y pospuestas
                    if (isset($cita['estado'])) {
                        $est = strtolower($cita['estado']);
                        if ($est === 'cancelado' || $est === 'pospuesto') { continue; }
                    }
                    
                    $citaInicio = date('H:i:s', strtotime($cita['fecha']));
                    $citaFin = date('H:i:s', strtotime($cita['finDeCita']));
                    
                    if (($horaInicio < $citaFin) && ($horaFin > $citaInicio)) {
                        $conflicto = true;
                        break;
                    }
                }
            }

            if ($conflicto) {
                $error = 'El intervalo de tiempo no está disponible. Existe una cita o evento programado en esa hora. Por favor, elija otro horario.';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $error]);
                    exit();
                }
            } else {
                $eventModel->create($nombre, $descripcion, $consultorio_id, $fechaCompleta, $duracion_estimada, $color);
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Evento registrado correctamente']);
                    exit();
                }
                header('Location: calendario.php?mensaje=Evento registrado correctamente');
                exit();
            }
        } catch (Exception $e) {
            $error = 'Error al registrar el evento: ' . $e->getMessage();
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Evento - Dental Supremo</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 y FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #003B73;
            --secondary-color: #2998EC;
            --primary-dark: #062846;
            --bg-main: #F4F9FD;
        }

        body {
            background-color: var(--bg-main);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #2D3748;
        }

        .container {
            max-width: 800px;
            margin-top: 2rem;
        }

        .card {
            border: none;
            box-shadow: 0 4px 15px rgba(15, 76, 110, 0.08);
            border-radius: 12px;
            margin-bottom: 1.5rem;
            overflow: hidden;
            background: #fff;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 1.2rem 1.5rem;
            border-bottom: none;
        }

        .card-title {
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
        }

        .card-body {
            padding: 1.8rem;
        }

        .form-label {
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            padding: 0.6rem 0.85rem;
            font-size: 0.92rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 168, 150, 0.15);
        }

        .form-control:disabled, .form-select:disabled {
            background-color: #F1F5F9;
            color: #64748B;
            opacity: 0.9;
        }

        .button-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 1.5rem;
            padding-top: 1.2rem;
            border-top: 1px solid #E2E8F0;
        }

        .btn-volver {
            background-color: #64748B;
            color: white;
            padding: 10px 22px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.92rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-volver:hover {
            background-color: #475569;
            color: white;
            transform: translateY(-1px);
        }

        .btn-registrar {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-registrar:hover {
            background-color: var(--secondary-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 168, 150, 0.25);
        }

        .btn-registrar:disabled {
            background-color: #94A3B8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 576px) {
            .container {
                padding: 12px;
                margin-top: 0.5rem;
            }
            .card-body {
                padding: 1.2rem;
            }
            .button-container {
                flex-direction: column;
                gap: 10px;
            }
            .btn-volver, .btn-registrar {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container py-3">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title mb-0">
                    <i class="fas fa-calendar-alt me-2"></i>
                    Registrar Evento
                </h2>
            </div>
            <div class="card-body">

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                        <i class="fas fa-exclamation-circle me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registro-evento">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">
                            <i class="fas fa-tag text-primary"></i> Nombre del Evento:
                        </label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="nombre" 
                            name="nombre" 
                            placeholder="Ej: Cierre de consultorio, Mantenimiento, Reunión clínica"
                            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                    </div>

                    <!-- Campo oculto para la fecha -->
                    <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Campo oculto para el consultorio -->
                    <input type="hidden" name="consultorio_id" value="<?= htmlspecialchars($consultorio_id, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row g-3 mb-3">
                        <!-- Mostrar la fecha seleccionada -->
                        <div class="col-md-6">
                            <label for="fecha_mostrada" class="form-label">
                                <i class="fas fa-calendar-day text-primary"></i> Fecha y Hora:
                            </label>
                            <input type="text" id="fecha_mostrada" class="form-control" value="<?= htmlspecialchars($fecha, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                        </div>

                        <!-- Mostrar el consultorio seleccionado -->
                        <div class="col-md-6">
                            <label for="consultorio_mostrado" class="form-label">
                                <i class="fas fa-clinic-medical text-primary"></i> Consultorio asignado:
                            </label>
                            <select id="consultorio_mostrado" class="form-select" disabled>
                                <?php foreach ($consultorios as $consultorio): ?>
                                    <option value="<?= $consultorio['id']; ?>" <?= $consultorio['id'] == $consultorio_id ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($consultorio['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="duracion_estimada" class="form-label">
                            <i class="fas fa-clock text-primary"></i> Duración estimada:
                        </label>
                        <select id="duracion_estimada" name="duracion_estimada" class="form-select" required>
                            <option value="15">15 minutos</option>
                            <option value="30">30 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60" selected>1 hora</option>
                            <option value="75">1 hora y 15 minutos</option>
                            <option value="90">1 hora y 30 minutos</option>
                            <option value="105">1 hora y 45 minutos</option>
                            <option value="120">2 horas</option>
                            <option value="180">3 horas</option>
                            <option value="240">4 horas</option>
                            <option value="480">8 horas (Día completo)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">
                            <i class="fas fa-align-left text-primary"></i> Descripción o Notas (Opcional):
                        </label>
                        <textarea 
                            id="descripcion" 
                            name="descripcion" 
                            class="form-control" 
                            rows="3"
                            placeholder="Detalles adicionales sobre el evento o motivo del bloqueo..."
                        ><?= htmlspecialchars($_POST['descripcion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </form>

                <div class="button-container">
                    <a href="calendario.php" class="btn-volver">
                        <i class="fas fa-arrow-left me-2"></i>Volver
                    </a>
                    <button type="submit" form="registro-evento" class="btn-registrar" <?= empty($fecha) ? 'disabled' : ''; ?>>
                        <i class="fas fa-save me-2"></i>Registrar Evento
                    </button>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
