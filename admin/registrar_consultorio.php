<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Consultorio.php';

$consultorioModel = new Consultorio($pdo);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nombre = trim($_POST['nombre'] ?? '');
        $color = trim($_POST['color'] ?? '#6B1D49');
        if (!empty($nombre)) {
            $consultorioModel->create($nombre, $color);
            $_SESSION['message'] = "Consultorio registrado exitosamente.";
            $_SESSION['message_type'] = "success";
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $color = trim($_POST['color'] ?? '#6B1D49');
        if ($id > 0 && !empty($nombre)) {
            $consultorioModel->update($id, $nombre, $color);
            $_SESSION['message'] = "Consultorio actualizado exitosamente.";
            $_SESSION['message_type'] = "success";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $consultorioModel->delete($id);
            $_SESSION['message'] = "Consultorio eliminado exitosamente.";
            $_SESSION['message_type'] = "success";
        }
    }
    
    header('Location: registrar_consultorio.php');
    exit();
}

// Obtener todos los consultorios
$consultorios = $consultorioModel->getAll();

// Cargar la plantilla general (Sidebar Navbar + FontAwesome + Plus Jakarta Sans)
require_once '../templates/header_general.php';
?>

<!-- Dependencies extra si son requeridas -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --primary: #6B1D49;
        --primary-gradient: linear-gradient(135deg, #6B1D49 0%, #C47D9F 100%);
        --primary-dark: #531438;
        --accent-teal: #C47D9F;
        --accent-green: #10B981;
        --danger: #EF4444;
        --warning: #F59E0B;
        --bg-main: #FDF8FA;
        --card-bg: #FFFFFF;
        --text-dark: #1E293B;
        --text-muted: #64748B;
        --border-color: #E2E8F0;
        --shadow-sm: 0 2px 8px rgba(15, 76, 110, 0.06);
        --shadow-md: 0 4px 16px rgba(15, 76, 110, 0.1);
        --shadow-hover: 0 8px 24px rgba(0, 168, 150, 0.15);
    }

    body {
        background-color: var(--bg-main);
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .main-container {
        padding: 25px 30px;
        max-width: 1300px;
        margin: 0 auto;
    }

    /* Header Principal */
    .page-header {
        background: var(--primary-gradient);
        border-radius: 18px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
        color: white;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .logo-container {
        position: relative;
    }

    .logo {
        width: 75px;
        height: 75px;
        border-radius: 16px;
        object-fit: cover;
        border: 3px solid rgba(255, 255, 255, 0.9);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        background: white;
    }

    .header-text h1 {
        margin: 0;
        font-size: 1.8rem;
        color: white;
        font-weight: 800;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .header-text p {
        margin: 6px 0 0 0;
        color: rgba(255, 255, 255, 0.88);
        font-size: 0.95rem;
        font-weight: 500;
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        color: white;
        padding: 10px 20px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.9rem;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .btn-back:hover {
        background: white;
        color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Cards */
    .form-card, .consultorios-section {
        background: var(--card-bg);
        border-radius: 18px;
        padding: 28px;
        margin-bottom: 25px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: var(--text-dark);
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .section-title i {
        color: var(--accent-teal);
        margin-right: 10px;
    }

    /* Form Inputs */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 8px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group label i {
        color: var(--primary);
    }

    .form-control {
        padding: 12px 16px;
        border: 2px solid var(--border-color);
        border-radius: 12px;
        transition: all 0.25s ease;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .form-control:focus {
        border-color: var(--accent-teal);
        box-shadow: 0 0 0 4px rgba(0, 168, 150, 0.12);
    }

    .color-preview-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .color-picker-input {
        width: 60px;
        height: 48px;
        padding: 4px;
        border-radius: 10px;
        cursor: pointer;
        border: 2px solid var(--border-color);
    }

    .color-display {
        flex: 1;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 0.95rem;
        letter-spacing: 0.5px;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        text-shadow: 0 1px 2px rgba(0,0,0,0.4);
    }

    .color-presets {
        display: flex;
        gap: 8px;
        margin-top: 10px;
        flex-wrap: wrap;
    }

    .preset-chip {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid white;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .preset-chip:hover {
        transform: scale(1.15);
    }

    .btn-submit {
        background: var(--primary-gradient);
        color: white;
        padding: 14px 28px;
        border: none;
        border-radius: 12px;
        font-weight: 800;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.25s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(15, 76, 110, 0.2);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
        color: white;
    }

    /* Consultorios Grid */
    .consultorios-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }

    .consultorio-card {
        background: white;
        border-radius: 16px;
        padding: 22px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        border-left: 6px solid var(--primary);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }

    .consultorio-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-hover);
    }

    .consultorio-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 18px;
    }

    .consultorio-badge {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.4rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        flex-shrink: 0;
    }

    .consultorio-info h3 {
        margin: 0;
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text-dark);
    }

    .consultorio-info p {
        margin: 4px 0 0 0;
        font-size: 0.85rem;
        color: var(--text-muted);
        font-weight: 600;
    }

    .consultorio-actions {
        display: flex;
        gap: 10px;
    }

    .btn-action {
        flex: 1;
        padding: 9px 14px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }

    .btn-edit {
        background: rgba(15, 76, 110, 0.08);
        color: var(--primary);
    }

    .btn-edit:hover {
        background: var(--primary);
        color: white;
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.08);
        color: var(--danger);
    }

    .btn-delete:hover {
        background: var(--danger);
        color: white;
    }

    .consultorios-count-badge {
        background: var(--primary-gradient);
        color: white;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 800;
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 4rem;
        color: var(--accent-teal);
        margin-bottom: 15px;
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .main-container {
            padding: 15px;
        }

        .page-header {
            padding: 20px;
        }

        .header-content {
            flex-direction: column;
            text-align: center;
        }

        .header-text h1 {
            font-size: 1.4rem;
            justify-content: center;
        }

        .consultorios-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="main-container">
    <!-- Header Banner -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1><i class="fas fa-clinic-medical"></i> Gestión de Consultorios</h1>
                <p>Administra y personaliza las salas y consultorios de tu clínica Dra. Tatiana Ruiz</p>
            </div>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Dashboard
        </a>
    </div>

    <!-- Mensajes de Notificación -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-radius: 12px; font-weight: 600;">
            <i class="fas <?php echo ($_SESSION['message_type'] ?? 'info') === 'success' ? 'fa-check-circle' : 'fa-info-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($_SESSION['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Formulario de Nuevo Consultorio -->
    <div class="form-card">
        <div class="section-title">
            <span><i class="fas fa-plus-circle"></i> Nuevo Consultorio</span>
        </div>
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="create">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nombre">
                            <i class="fas fa-door-open"></i>
                            Nombre del Consultorio
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="nombre" 
                               name="nombre" 
                               placeholder="Ej: Consultorio 1, Sala Odontopediátrica, etc."
                               required>
                        <div class="invalid-feedback">
                            Por favor, ingrese el nombre del consultorio.
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="color">
                            <i class="fas fa-palette"></i>
                            Color Identificador en Calendario
                        </label>
                        <div class="color-preview-wrapper">
                            <input type="color" 
                                   class="form-control color-picker-input" 
                                   id="color" 
                                   name="color" 
                                   value="#970f6eff"
                                   onchange="updateColorDisplay(this.value)"
                                   required>
                            <div class="color-display" id="colorDisplay" style="background-color: #970f6eff;">
                                #970f6eff
                            </div>
                        </div>
                        <div class="color-presets">
                            <span class="preset-chip" style="background: #970f6eff;" onclick="selectPresetColor('#970f6eff')" title="Fucsia"></span>
                            <span class="preset-chip" style="background: #00A896;" onclick="selectPresetColor('#00A896')" title="Teal Esmeralda"></span>
                            <span class="preset-chip" style="background: #10B981;" onclick="selectPresetColor('#10B981')" title="Verde Menta"></span>
                            <span class="preset-chip" style="background: #8B5CF6;" onclick="selectPresetColor('#8B5CF6')" title="Púrpura"></span>
                            <span class="preset-chip" style="background: #F59E0B;" onclick="selectPresetColor('#F59E0B')" title="Ámbar"></span>
                            <span class="preset-chip" style="background: #EF4444;" onclick="selectPresetColor('#EF4444')" title="Rojo Carmesí"></span>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit mt-2">
                <i class="fas fa-save"></i>
                Registrar Consultorio
            </button>
        </form>
    </div>

    <!-- Lista de Consultorios -->
    <div class="consultorios-section">
        <div class="section-title">
            <span>
                <i class="fas fa-list"></i>
                Consultorios Registrados
            </span>
            <?php if (!empty($consultorios)): ?>
                <span class="consultorios-count-badge"><?php echo count($consultorios); ?> Activos</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($consultorios)): ?>
            <div class="consultorios-grid">
                <?php foreach ($consultorios as $consultorio): ?>
                    <div class="consultorio-card" style="border-left-color: <?php echo htmlspecialchars($consultorio['color']); ?>;">
                        <div class="consultorio-header">
                            <div class="consultorio-badge" style="background-color: <?php echo htmlspecialchars($consultorio['color']); ?>;">
                                <i class="fas fa-clinic-medical"></i>
                            </div>
                            <div class="consultorio-info">
                                <h3><?php echo htmlspecialchars($consultorio['nombre']); ?></h3>
                                <p>
                                    <i class="fas fa-palette me-1"></i>
                                    Código: <?php echo htmlspecialchars($consultorio['color']); ?>
                                </p>
                            </div>
                        </div>

                        <div class="consultorio-actions">
                            <button type="button" 
                                    class="btn-action btn-edit" 
                                    onclick="editarConsultorio(<?php echo $consultorio['id']; ?>, '<?php echo htmlspecialchars($consultorio['nombre'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($consultorio['color'], ENT_QUOTES); ?>')">
                                <i class="fas fa-edit"></i>
                                Editar
                            </button>
                            <button type="button" 
                                    class="btn-action btn-delete" 
                                    onclick="eliminarConsultorio(<?php echo $consultorio['id']; ?>, '<?php echo htmlspecialchars($consultorio['nombre'], ENT_QUOTES); ?>')">
                                <i class="fas fa-trash-alt"></i>
                                Eliminar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-clinic-medical"></i>
                <h5 class="fw-bold text-dark">No hay consultorios registrados</h5>
                <p>Crea el primer consultorio usando el formulario de arriba.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para Editar Consultorio -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; box-shadow: var(--shadow-md);">
            <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                <h5 class="modal-title fw-bold" id="editModalLabel">
                    <i class="fas fa-edit me-2"></i>
                    Editar Consultorio
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="form-group">
                        <label for="edit_nombre">
                            <i class="fas fa-door-open"></i>
                            Nombre del Consultorio
                        </label>
                        <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_color">
                            <i class="fas fa-palette"></i>
                            Color Identificador
                        </label>
                        <div class="color-preview-wrapper">
                            <input type="color" 
                                   class="form-control color-picker-input" 
                                   id="edit_color" 
                                   name="color"
                                   onchange="updateEditColorDisplay(this.value)"
                                   required>
                            <div class="color-display" id="editColorDisplay">
                                #6B1D49
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary px-4" style="background: var(--primary); border: none; border-radius: 10px; font-weight: 700;">
                        <i class="fas fa-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form oculto para eliminar -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="delete_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateColorDisplay(color) {
        const display = document.getElementById('colorDisplay');
        display.style.backgroundColor = color;
        display.textContent = color.toUpperCase();
    }

    function selectPresetColor(color) {
        document.getElementById('color').value = color;
        updateColorDisplay(color);
    }

    function updateEditColorDisplay(color) {
        const display = document.getElementById('editColorDisplay');
        display.style.backgroundColor = color;
        display.textContent = color.toUpperCase();
    }

    function editarConsultorio(id, nombre, color) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_color').value = color;
        updateEditColorDisplay(color);
        
        const modal = new bootstrap.Modal(document.getElementById('editModal'));
        modal.show();
    }

    function eliminarConsultorio(id, nombre) {
        if (confirm('¿Está seguro de eliminar el consultorio "' + nombre + '"?\n\nEsta acción afectará a las citas y cronogramas asociados.')) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
</script>
</body>
</html>
