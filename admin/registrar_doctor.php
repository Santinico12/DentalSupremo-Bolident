<?php
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Doctor.php';

$doctorModel = new Doctor($pdo);
$doctores = $doctorModel->getAll();
$catalogoEspecialidades = $doctorModel->getAllEspecialidades();

$mensaje = '';
$tipo_mensaje = '';

// Procesar formulario de nuevo doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo_pais = trim($_POST['codigo_pais'] ?? '+591');
    $telefono_num = trim($_POST['telefono'] ?? '');
    $especialidadesSeleccionadas = $_POST['especialidades'] ?? [];
    
    $telefono = null;
    if (!empty($telefono_num)) {
        if (strpos($telefono_num, '+') === 0) {
            $telefono = $telefono_num;
        } else {
            $telefono = $codigo_pais . preg_replace('/\D/', '', $telefono_num);
        }
    }
    
    if (empty($nombre)) {
        $mensaje = 'El nombre del doctor es obligatorio.';
        $tipo_mensaje = 'danger';
    } else {
        try {
            $doctorModel->create($nombre, 'activo', $telefono, $especialidadesSeleccionadas);
            $mensaje = 'Doctor registrado correctamente con sus especialidades.';
            $tipo_mensaje = 'success';
            $doctores = $doctorModel->getAll();
        } catch (Exception $e) {
            $mensaje = 'Error al registrar el doctor: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Procesar formulario de edición de doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'editar') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo_pais = trim($_POST['codigo_pais'] ?? '+591');
    $telefono_num = trim($_POST['telefono'] ?? '');
    $estado = in_array($_POST['estado'] ?? '', ['activo', 'inactivo']) ? $_POST['estado'] : 'activo';
    $especialidadesSeleccionadas = $_POST['especialidades'] ?? [];

    $telefono = null;
    if (!empty($telefono_num)) {
        if (strpos($telefono_num, '+') === 0) {
            $telefono = $telefono_num;
        } else {
            $telefono = $codigo_pais . preg_replace('/\D/', '', $telefono_num);
        }
    }

    if ($doctor_id <= 0 || empty($nombre)) {
        $mensaje = 'Datos incompletos para actualizar el doctor.';
        $tipo_mensaje = 'danger';
    } else {
        try {
            $doctorModel->update($doctor_id, $nombre, $estado, $telefono, $especialidadesSeleccionadas);
            $mensaje = 'Doctor y especialidades actualizados correctamente.';
            $tipo_mensaje = 'success';
            $doctores = $doctorModel->getAll();
        } catch (Exception $e) {
            $mensaje = 'Error al actualizar el doctor: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
        }
    }
}

// Procesar eliminación de doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'eliminar') {
    $doctor_id = $_POST['doctor_id'] ?? '';
    
    try {
        $doctorModel->delete((int)$doctor_id);
        $mensaje = 'Doctor eliminado correctamente.';
        $tipo_mensaje = 'success';
        $doctores = $doctorModel->getAll();
    } catch (Exception $e) {
        $mensaje = 'Error al eliminar el doctor: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Procesar cambio de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado') {
    $doctor_id = $_POST['doctor_id'] ?? '';
    
    try {
        $doctorModel->toggleEstado((int)$doctor_id);
        $mensaje = 'Estado del doctor actualizado correctamente.';
        $tipo_mensaje = 'success';
        $doctores = $doctorModel->getAll();
    } catch (Exception $e) {
        $mensaje = 'Error al actualizar el estado: ' . $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

require_once '../templates/header_general.php';
?>

<style>
    :root {
        --primary-color: #003B73;
        --secondary-color: #2998EC;
        --accent-teal: #2998EC;
        --whatsapp-green: #25D366;
        --border-color: #E2E8F0;
    }

    .page-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 24px 30px;
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: 0 4px 15px rgba(0, 59, 115, 0.12);
    }

    .page-header h1 {
        font-size: 1.6rem;
        font-weight: 800;
        margin: 0 0 4px 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 0.92rem;
    }

    .card-custom {
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        margin-bottom: 24px;
        background: white;
    }

    .card-custom .card-header {
        background: #F8FAFC;
        border-bottom: 1px solid var(--border-color);
        padding: 16px 20px;
        border-radius: 12px 12px 0 0;
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-custom .card-body {
        padding: 22px;
    }

    .especialidades-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 10px;
        padding: 12px;
        background: #F8FAFC;
        border: 1px solid var(--border-color);
        border-radius: 8px;
    }

    .esp-checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.88rem;
        cursor: pointer;
        user-select: none;
        padding: 4px 6px;
        border-radius: 4px;
        transition: background 0.2s ease;
    }

    .esp-checkbox-item:hover {
        background: rgba(0, 168, 150, 0.08);
    }

    .badge-esp {
        background: #E0F2FE;
        color: #0369A1;
        border: 1px solid #BAE6FD;
        font-size: 0.74rem;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 12px;
        display: inline-block;
        margin: 2px 2px;
    }

    .badge-tel {
        background: rgba(37, 211, 102, 0.12);
        color: #0F766E;
        font-weight: 700;
        font-size: 0.82rem;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }
    .badge-tel:hover {
        background: rgba(37, 211, 102, 0.25);
        color: #064E3B;
    }

    .badge-activo { background-color: #10B981; color: white; }
    .badge-inactivo { background-color: #94A3B8; color: white; }

    .btn-action-sm {
        padding: 5px 10px;
        font-size: 0.82rem;
        font-weight: 600;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border: none;
    }
</style>

<div class="container py-3">
    <!-- Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1><i class="fas fa-user-md"></i> Gestión de Doctores & Especialidades</h1>
                <p>Administra el equipo odontológico, especialidades médicas y números de contacto de la Clínica Dentality</p>
            </div>
            <a href="dashboard.php" class="btn btn-light btn-sm fw-bold">
                <i class="fas fa-arrow-left me-1"></i> Volver al Panel
            </a>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Formulario de Registro -->
    <div class="card-custom">
        <div class="card-header">
            <span><i class="fas fa-user-plus me-2 text-primary"></i> Registrar Nuevo Doctor/a</span>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="crear">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nombre" class="form-label fw-bold text-dark small">
                            <i class="fas fa-user me-1 text-primary"></i> Nombre Completo del Doctor/a:
                        </label>
                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Dra. Valeria Montaño" required>
                    </div>
                    <div class="col-md-6">
                        <label for="telefono" class="form-label fw-bold text-dark small">
                            <i class="fab fa-whatsapp me-1 text-success"></i> Celular / WhatsApp:
                        </label>
                        <div class="input-group">
                            <select class="form-select" id="codigo_pais" name="codigo_pais" style="max-width: 140px;" required>
                                <option value="+591" selected>🇧🇴 +591</option>
                                <option value="+54">🇦🇷 +54 Argentina</option>
                                <option value="+55">🇧🇷 +55 Brasil</option>
                                <option value="+56">🇨🇱 +56 Chile</option>
                                <option value="+57">🇨🇴 +57 Colombia</option>
                                <option value="+593">🇪🇨 +593 Ecuador</option>
                                <option value="+595">🇵🇾 +595 Paraguay</option>
                                <option value="+51">🇵🇪 +51 Perú</option>
                                <option value="+598">🇺🇾 +598 Uruguay</option>
                                <option value="+58">🇻🇪 +58 Venezuela</option>
                                <option value="+52">🇲🇽 +52 México</option>
                                <option value="+1">🇺🇸 +1 EE.UU/Canadá</option>
                                <option value="+34">🇪🇸 +34 España</option>
                            </select>
                            <input type="tel" class="form-control" id="telefono" name="telefono" placeholder="Ej: 71234567">
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Prefijo predeterminado: Bolivia (+591)</small>
                    </div>
                </div>

                <!-- Selección de Especialidades Múltiples -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-dark small">
                        <i class="fas fa-tooth me-1 text-primary"></i> Especialidades que atiende (puedes marcar varias):
                    </label>
                    
                    <?php if (!empty($catalogoEspecialidades)): ?>
                        <div class="especialidades-grid">
                            <?php foreach ($catalogoEspecialidades as $esp): ?>
                                <label class="esp-checkbox-item">
                                    <input type="checkbox" name="especialidades[]" value="<?php echo (int)$esp['id']; ?>" class="form-check-input mt-0">
                                    <span><?php echo htmlspecialchars($esp['nombre']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border small py-2">
                            <i class="fas fa-info-circle me-1"></i> Catálogo cargando automáticamente desde la base de datos.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary fw-bold" style="background-color: var(--primary-color); border: none;">
                        <i class="fas fa-save me-1"></i> Guardar Doctor/a
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Doctores -->
    <div class="card-custom">
        <div class="card-header">
            <span><i class="fas fa-list me-2 text-primary"></i> Doctores Registrados (<?php echo count($doctores); ?>)</span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($doctores)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 28%;">Doctor/a</th>
                                <th style="width: 20%;">WhatsApp / Teléfono</th>
                                <th style="width: 29%;">Especialidades</th>
                                <th style="width: 8%;">Estado</th>
                                <th style="width: 15%; text-align: right;" class="pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctores as $doctor): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($doctor['nombre']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($doctor['telefono'])): ?>
                                            <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $doctor['telefono']); ?>" target="_blank" class="badge-tel" title="Abrir chat en WhatsApp">
                                                <i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($doctor['telefono']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small"><em>Sin número</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $listaEsp = !empty($doctor['especialidades']) ? explode(',', $doctor['especialidades']) : ['Odontología General'];
                                        foreach ($listaEsp as $espItem): 
                                            $espItem = trim($espItem);
                                            if (!empty($espItem)):
                                        ?>
                                            <span class="badge-esp"><?php echo htmlspecialchars($espItem); ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $doctor['estado']; ?> px-2 py-1">
                                            <?php echo ucfirst($doctor['estado']); ?>
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <div class="d-inline-flex gap-1">
                                            <!-- Botón Editar Modal -->
                                            <button type="button" class="btn btn-primary btn-action-sm" onclick="abrirModalEditar(<?php echo htmlspecialchars(json_encode($doctor)); ?>)" title="Editar doctor y especialidades">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <!-- Botón Cambiar Estado -->
                                            <form method="POST" action="" style="margin: 0; display: inline;">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="doctor_id" value="<?php echo (int)$doctor['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-action-sm" title="<?php echo $doctor['estado'] === 'activo' ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo $doctor['estado'] === 'activo' ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                            </form>

                                            <!-- Botón Eliminar -->
                                            <form method="POST" action="" style="margin: 0; display: inline;" onsubmit="return confirm('¿Está seguro de eliminar este doctor?');">
                                                <input type="hidden" name="action" value="eliminar">
                                                <input type="hidden" name="doctor_id" value="<?php echo (int)$doctor['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-action-sm" title="Eliminar">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-user-md fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">No hay doctores registrados aún.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Edición de Doctor -->
<div class="modal fade" id="modalEditarDoctor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="doctor_id" id="edit_doctor_id">

                <div class="modal-header text-white" style="background: var(--primary-color);">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-edit me-2"></i> Editar Doctor/a</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Nombre Completo:</label>
                            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Celular / WhatsApp:</label>
                            <div class="input-group">
                                <select class="form-select" id="edit_codigo_pais" name="codigo_pais" style="max-width: 140px;" required>
                                    <option value="+591" selected>🇧🇴 +591</option>
                                    <option value="+54">🇦🇷 +54 Argentina</option>
                                    <option value="+55">🇧🇷 +55 Brasil</option>
                                    <option value="+56">🇨🇱 +56 Chile</option>
                                    <option value="+57">🇨🇴 +57 Colombia</option>
                                    <option value="+593">🇪🇨 +593 Ecuador</option>
                                    <option value="+595">🇵🇾 +595 Paraguay</option>
                                    <option value="+51">🇵🇪 +51 Perú</option>
                                    <option value="+598">🇺🇾 +598 Uruguay</option>
                                    <option value="+58">🇻🇪 +58 Venezuela</option>
                                    <option value="+52">🇲🇽 +52 México</option>
                                    <option value="+1">🇺🇸 +1 EE.UU/Canadá</option>
                                    <option value="+34">🇪🇸 +34 España</option>
                                </select>
                                <input type="tel" class="form-control" name="telefono" id="edit_telefono" placeholder="Ej: 71234567">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Estado:</label>
                        <select class="form-select" name="estado" id="edit_estado">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Especialidades que atiende:</label>
                        <div class="especialidades-grid" id="edit_especialidades_container">
                            <?php foreach ($catalogoEspecialidades as $esp): ?>
                                <label class="esp-checkbox-item">
                                    <input type="checkbox" name="especialidades[]" value="<?php echo (int)$esp['id']; ?>" id="edit_esp_<?php echo (int)$esp['id']; ?>" class="form-check-input mt-0 edit-esp-checkbox">
                                    <span><?php echo htmlspecialchars($esp['nombre']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold" style="background-color: var(--primary-color);">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function abrirModalEditar(doctor) {
    document.getElementById('edit_doctor_id').value = doctor.id;
    document.getElementById('edit_nombre').value = doctor.nombre || '';
    document.getElementById('edit_estado').value = doctor.estado || 'activo';

    // Separar código de país del número de teléfono
    let tel = (doctor.telefono || '').trim();
    let codSelect = document.getElementById('edit_codigo_pais');
    let telInput = document.getElementById('edit_telefono');

    if (tel) {
        let matched = false;
        // Revisar si empieza con alguno de los prefijos conocidos
        for (let opt of codSelect.options) {
            if (opt.value && tel.startsWith(opt.value)) {
                codSelect.value = opt.value;
                telInput.value = tel.substring(opt.value.length).trim();
                matched = true;
                break;
            }
        }
        if (!matched) {
            // Si empieza con 591 sin el +
            if (tel.startsWith('591') && tel.length > 8) {
                codSelect.value = '+591';
                telInput.value = tel.substring(3).trim();
            } else {
                codSelect.value = '+591';
                telInput.value = tel.replace(/^\+/, '');
            }
        }
    } else {
        codSelect.value = '+591';
        telInput.value = '';
    }

    // Desmarcar todos los checkboxes
    document.querySelectorAll('.edit-esp-checkbox').forEach(cb => cb.checked = false);

    // Marcar los que tiene asignados
    if (doctor.especialidades_ids) {
        const ids = doctor.especialidades_ids.split(',').map(s => s.trim());
        ids.forEach(id => {
            const cb = document.getElementById('edit_esp_' + id);
            if (cb) cb.checked = true;
        });
    }

    const modal = new bootstrap.Modal(document.getElementById('modalEditarDoctor'));
    modal.show();
}
</script>
</body>
</html>
