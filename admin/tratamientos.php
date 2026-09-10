<?php
/**
 * Gestión de Tratamientos
 * CRUD completo para catálogo de servicios dentales
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Tratamiento.php';

$tratamientoModel = new Tratamiento($pdo);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $tratamientoModel->crear([
            'codigo' => !empty($_POST['codigo']) ? trim($_POST['codigo']) : null,
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'precio' => floatval($_POST['precio']),
            'categoria' => $_POST['categoria'] ?? 'General'
        ]);
        $_SESSION['message'] = 'Tratamiento creado correctamente';
        $_SESSION['message_type'] = 'success';
    }
    elseif ($action === 'actualizar' && isset($_POST['id'])) {
        $tratamientoModel->actualizar($_POST['id'], [
            'codigo' => !empty($_POST['codigo']) ? trim($_POST['codigo']) : null,
            'nombre' => $_POST['nombre'],
            'descripcion' => $_POST['descripcion'] ?? '',
            'precio' => floatval($_POST['precio']),
            'categoria' => $_POST['categoria'] ?? 'General'
        ]);
        // Handle activo toggle from modal
        if (isset($_POST['activo_state'])) {
            if ($_POST['activo_state'] === '1') {
                $tratamientoModel->activar($_POST['id']);
            } else {
                $tratamientoModel->desactivar($_POST['id']);
            }
        }
        $_SESSION['message'] = 'Tratamiento actualizado correctamente';
        $_SESSION['message_type'] = 'success';
    }
    elseif ($action === 'desactivar' && isset($_POST['id'])) {
        $tratamientoModel->desactivar($_POST['id']);
        $_SESSION['message'] = 'Tratamiento desactivado';
        $_SESSION['message_type'] = 'warning';
    }
    elseif ($action === 'activar' && isset($_POST['id'])) {
        $tratamientoModel->activar($_POST['id']);
        $_SESSION['message'] = 'Tratamiento activado';
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: tratamientos.php');
    exit();
}

// Obtener todos los tratamientos (incluyendo inactivos para gestión)
$sql = "SELECT * FROM tratamientos ORDER BY categoria, nombre";
$tratamientos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$categorias = $tratamientoModel->getCategorias();

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #003B73; --primary-dark: #062846; --accent: #2998EC; }
    body { background: linear-gradient(135deg, #F4F9FD 0%, #E8F2FA 100%); }
    .page-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 25px 30px;
        color: white;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; gap: 12px; }
    .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-header { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: all 0.2s; }
    .btn-header:hover { transform: translateY(-1px); }
    .btn-new { background: white; color: var(--primary); }
    .btn-new:hover { background: #f0f0f0; }
    .btn-print { background: rgba(255,255,255,0.2); color: white; }
    .btn-back { background: rgba(255,255,255,0.2); color: white; }

    /* Filtros */
    .filters-bar {
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .filter-group { display: flex; align-items: center; gap: 8px; }
    .filter-group label { font-weight: 600; font-size: 0.85rem; color: #555; }
    .filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }
    .search-box { flex: 1; min-width: 200px; position: relative; }
    .search-box input { width: 100%; padding: 10px 15px 10px 40px; border: 2px solid #e9ecef; border-radius: 8px; }
    .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #888; }

    /* Tabla */
    .table-container {
        background: white;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        overflow-x: auto;
    }
    .table { width: 100%; border-collapse: collapse; min-width: 800px; }
    .table th { background: #f8f9fa; padding: 14px; text-align: left; font-weight: 700; color: #555; font-size: 0.85rem; border-bottom: 2px solid #eee; }
    .table td { padding: 14px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .table tr:hover { background: #fafafa; }
    .table tr.inactive { opacity: 0.5; }

    .badge-cat { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: #e9ecef; }
    .badge-activo { background: #d4edda; color: #155724; }
    .badge-inactivo { background: #f8d7da; color: #721c24; }

    .precio { font-weight: 700; color: var(--primary); }
    .codigo { font-family: monospace; background: #f0f0f0; padding: 2px 8px; border-radius: 4px; }

    .btn-action { width: 32px; height: 32px; border-radius: 6px; border: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; margin: 0 2px; transition: all 0.2s; }
    .btn-edit { background: #e3f2fd; color: #1976d2; }
    .btn-delete { background: #ffebee; color: #c62828; }
    .btn-action:hover { transform: scale(1.1); }

    /* Toggle switch */
    .toggle-group { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; }
    .toggle-group label { font-weight: 600; color: #555; font-size: 0.9rem; }
    .toggle-switch { position: relative; width: 50px; height: 26px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
        position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
        background: #ccc; border-radius: 26px; transition: 0.3s;
    }
    .toggle-slider::before {
        content: ''; position: absolute; height: 20px; width: 20px; left: 3px; bottom: 3px;
        background: white; border-radius: 50%; transition: 0.3s;
    }
    .toggle-switch input:checked + .toggle-slider { background: #28a745; }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(24px); }
    .toggle-status { font-size: 0.8rem; font-weight: 600; margin-left: 10px; }

    /* Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 9999; padding: 20px; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: white; border-radius: 16px; padding: 30px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h4 { margin: 0; font-weight: 700; }
    .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1rem; }
    .form-control:focus { border-color: var(--primary); outline: none; }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn-save { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }

    /* Stats */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .stat-card { background: white; border-radius: 12px; padding: 15px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    .stat-number { font-size: 1.8rem; font-weight: 800; color: var(--primary); }
    .stat-label { color: #666; font-size: 0.8rem; }

    @media print {
        .page-header, .filters-bar, .btn-action, .modal-overlay { display: none !important; }
        .table-container { box-shadow: none; padding: 0; }
    }

    @media (max-width: 768px) {
        .page-container { padding: 10px; }

        /* Header compact */
        .page-header { padding: 14px 16px; border-radius: 12px; margin-bottom: 15px; gap: 10px; }
        .page-header h1 { font-size: 1.1rem; gap: 8px; }
        .btn-header { padding: 7px 12px; font-size: 0.78rem; gap: 5px; }
        .btn-header .btn-text { display: none; }

        /* Stats */
        .stats-row { grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 15px; }
        .stat-card { padding: 10px 6px; border-radius: 10px; }
        .stat-number { font-size: 1.2rem; }
        .stat-label { font-size: 0.68rem; }

        /* Filters stack */
        .filters-bar { flex-direction: column; gap: 8px; padding: 12px; }
        .search-box { width: 100%; min-width: 0; }
        .filter-group { width: 100%; }
        .filter-group select { width: 100%; }

        /* Hide table, show cards */
        .desktop-table { display: none; }
        .mobile-trat-cards { display: block !important; }

        /* Table fallback */
        .table-container { padding: 12px; border-radius: 12px; }

        /* Modal responsive */
        .modal-box { padding: 20px; border-radius: 14px; margin: 10px; }
        .form-control { padding: 10px 12px; font-size: 0.9rem; }
        .modal-footer { flex-direction: column; }
        .modal-footer button { width: 100%; justify-content: center; }
    }

    /* Mobile treatment cards */
    .mobile-trat-cards { display: none; }

    .m-trat-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 10px;
        border-left: 4px solid var(--primary);
        transition: box-shadow 0.2s;
    }
    .m-trat-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.08); }
    .m-trat-card.inactive { opacity: 0.5; }

    .m-trat-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 6px;
    }
    .m-trat-name { font-weight: 700; font-size: 0.92rem; color: #333; }
    .m-trat-desc { font-size: 0.75rem; color: #888; margin-top: 2px; }
    .m-trat-code { font-family: monospace; font-size: 0.7rem; background: #e9ecef; padding: 1px 6px; border-radius: 4px; color: #555; }

    .m-trat-precio { font-weight: 800; font-size: 1.1rem; color: var(--primary); flex-shrink: 0; }

    .m-trat-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
    }
    .m-trat-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    .m-trat-actions { display: flex; gap: 4px; }
    .m-trat-actions .btn-action { width: 30px; height: 30px; border-radius: 6px; font-size: 0.78rem; }
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-tooth"></i> Gestión de Tratamientos</h1>
        <div class="header-actions">
            <button class="btn-header btn-new" onclick="abrirModal()">
                <i class="fas fa-plus"></i> <span class="btn-text">Nuevo Tratamiento</span>
            </button>
            <button class="btn-header btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> <span class="btn-text">Imprimir</span>
            </button>
            <a href="presupuestos.php" class="btn-header btn-back">
                <i class="fas fa-arrow-left"></i> <span class="btn-text">Presupuestos</span>
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?>" style="background: <?php echo $_SESSION['message_type'] === 'success' ? '#d4edda' : '#fff3cd'; ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <?php echo $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($tratamientos, fn($t) => $t['activo'])); ?></div>
            <div class="stat-label">Tratamientos Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count($categorias); ?></div>
            <div class="stat-label">Categorías</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">Bs <?php 
                $precios = array_column(array_filter($tratamientos, fn($t) => $t['activo']), 'precio');
                echo count($precios) ? number_format(array_sum($precios) / count($precios), 0) : 0;
            ?></div>
            <div class="stat-label">Precio Promedio</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="filters-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchTrat" placeholder="Buscar tratamiento..." oninput="filtrarTabla()">
        </div>
        <div class="filter-group">
            <label>Categoría:</label>
            <select id="filterCategoria" onchange="filtrarTabla()">
                <option value="">Todas</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Estado:</label>
            <select id="filterEstado" onchange="filtrarTabla()">
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
                <option value="">Todos</option>
            </select>
        </div>
    </div>

    <!-- Tabla -->
    <div class="table-container">
        <div class="desktop-table">
        <table class="table" id="tablaTratamientos">
            <thead>
                <tr>
                    <th>Tratamiento</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tratamientos as $t): ?>
                <tr class="trat-row <?php echo $t['activo'] ? '' : 'inactive'; ?>" 
                    data-nombre="<?php echo strtolower($t['nombre']); ?>" 
                    data-categoria="<?php echo $t['categoria']; ?>"
                    data-activo="<?php echo $t['activo']; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($t['nombre']); ?></strong>
                        <?php if ($t['descripcion']): ?>
                        <div style="font-size: 0.8rem; color: #888;"><?php echo htmlspecialchars($t['descripcion']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-cat"><?php echo $t['categoria']; ?></span></td>
                    <td class="precio">Bs <?php echo number_format($t['precio'], 2); ?></td>
                    <td>
                        <span class="badge-cat <?php echo $t['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>">
                            <?php echo $t['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn-action btn-edit" onclick='editarTratamiento(<?php echo json_encode($t); ?>)' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($t['activo']): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Desactivar este tratamiento?')">
                            <input type="hidden" name="action" value="desactivar">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="btn-action btn-delete" title="Desactivar">
                                <i class="fas fa-ban"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Mobile cards -->
        <div class="mobile-trat-cards">
            <?php foreach ($tratamientos as $t): ?>
            <div class="m-trat-card trat-row <?php echo $t['activo'] ? '' : 'inactive'; ?>"
                 data-nombre="<?php echo strtolower($t['nombre']); ?>"
                 data-categoria="<?php echo $t['categoria']; ?>"
                 data-activo="<?php echo $t['activo']; ?>">
                <div class="m-trat-top">
                    <div>
                        <div class="m-trat-name"><?php echo htmlspecialchars($t['nombre']); ?></div>
                        <?php if ($t['descripcion']): ?>
                        <div class="m-trat-desc"><?php echo htmlspecialchars($t['descripcion']); ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="m-trat-precio">Bs <?php echo number_format($t['precio'], 2); ?></span>
                </div>
                <div class="m-trat-bottom">
                    <div class="m-trat-meta">
                        <span class="badge-cat"><?php echo $t['categoria']; ?></span>
                        <span class="badge-cat <?php echo $t['activo'] ? 'badge-activo' : 'badge-inactivo'; ?>" style="font-size:0.68rem;">
                            <?php echo $t['activo'] ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </div>
                    <div class="m-trat-actions">
                        <button class="btn-action btn-edit" onclick='editarTratamiento(<?php echo json_encode($t); ?>)' title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($t['activo']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Desactivar?')">
                            <input type="hidden" name="action" value="desactivar">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="btn-action btn-delete" title="Desactivar">
                                <i class="fas fa-ban"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalTratamiento">
    <div class="modal-box">
        <div class="modal-header">
            <h4 id="modalTitle"><i class="fas fa-tooth me-2"></i>Nuevo Tratamiento</h4>
            <button type="button" class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>
        <form method="POST" id="formTratamiento">
            <input type="hidden" name="action" id="formAction" value="crear">
            <input type="hidden" name="id" id="formId">
            <input type="hidden" name="codigo" id="inputCodigo">
            
            <div class="form-group">
                <label>Nombre del Tratamiento *</label>
                <input type="text" name="nombre" id="inputNombre" class="form-control" required placeholder="Ej: Limpieza Dental Básica">
            </div>
            <div class="form-group">
                <label>Precio (Bs) *</label>
                <input type="number" name="precio" id="inputPrecio" class="form-control" step="0.01" required placeholder="Ej: 150.00">
            </div>
            <div class="form-group">
                <label>Categoría</label>
                <select name="categoria" id="inputCategoria" class="form-control">
                    <option value="Prevención">Prevención</option>
                    <option value="Restauración">Restauración</option>
                    <option value="Endodoncia">Endodoncia</option>
                    <option value="Cirugía">Cirugía</option>
                    <option value="Prótesis">Prótesis</option>
                    <option value="Ortodoncia">Ortodoncia</option>
                    <option value="Estética">Estética</option>
                    <option value="Diagnóstico">Diagnóstico</option>
                    <option value="Implantes">Implantes</option>
                    <option value="Personalizado">Personalizado</option>
                </select>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" id="inputDescripcion" class="form-control" rows="2" placeholder="Descripción breve..."></textarea>
            </div>

            <div class="toggle-group" id="toggleEstadoGroup" style="display: none;">
                <label><i class="fas fa-power-off" style="margin-right: 5px;"></i> Estado del tratamiento</label>
                <div style="display: flex; align-items: center;">
                    <label class="toggle-switch">
                        <input type="checkbox" id="inputActivo" name="activo" value="1" checked>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="toggle-status" id="toggleStatusText" style="color: #28a745;">Activo</span>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fas fa-save me-1"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-tooth me-2"></i>Nuevo Tratamiento';
    document.getElementById('formAction').value = 'crear';
    document.getElementById('formId').value = '';
    document.getElementById('inputCodigo').value = '';
    document.getElementById('formTratamiento').reset();
    document.getElementById('toggleEstadoGroup').style.display = 'none';
    document.getElementById('modalTratamiento').classList.add('show');
}

function editarTratamiento(t) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Tratamiento';
    document.getElementById('formAction').value = 'actualizar';
    document.getElementById('formId').value = t.id;
    document.getElementById('inputCodigo').value = t.codigo;
    document.getElementById('inputNombre').value = t.nombre;
    document.getElementById('inputPrecio').value = t.precio;
    document.getElementById('inputCategoria').value = t.categoria;
    document.getElementById('inputDescripcion').value = t.descripcion || '';
    
    // Show toggle and set state
    document.getElementById('toggleEstadoGroup').style.display = 'flex';
    const checkbox = document.getElementById('inputActivo');
    checkbox.checked = t.activo == 1;
    updateToggleText();
    
    document.getElementById('modalTratamiento').classList.add('show');
}

function cerrarModal() {
    document.getElementById('modalTratamiento').classList.remove('show');
}

function filtrarTabla() {
    const search = document.getElementById('searchTrat').value.toLowerCase();
    const categoria = document.getElementById('filterCategoria').value;
    const estado = document.getElementById('filterEstado').value;
    
    document.querySelectorAll('.trat-row').forEach(row => {
        const nombre = row.dataset.nombre;
        const cat = row.dataset.categoria;
        const activo = row.dataset.activo;
        
        let show = true;
        if (search && !nombre.includes(search)) show = false;
        if (categoria && cat !== categoria) show = false;
        if (estado !== '' && activo !== estado) show = false;
        
        row.style.display = show ? '' : 'none';
    });
}

// Cerrar modal con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModal(); });
document.getElementById('modalTratamiento').addEventListener('click', function(e) { if (e.target === this) cerrarModal(); });

// Filtrar al cargar (mostrar solo activos por defecto)
filtrarTabla();

// Toggle status text update
function updateToggleText() {
    const checkbox = document.getElementById('inputActivo');
    const text = document.getElementById('toggleStatusText');
    if (checkbox.checked) {
        text.textContent = 'Activo';
        text.style.color = '#28a745';
    } else {
        text.textContent = 'Inactivo';
        text.style.color = '#dc3545';
    }
}
document.getElementById('inputActivo').addEventListener('change', updateToggleText);

// Handle form submit to set correct action based on toggle
document.getElementById('formTratamiento').addEventListener('submit', function(e) {
    const action = document.getElementById('formAction').value;
    if (action === 'actualizar') {
        const isActivo = document.getElementById('inputActivo').checked;
        // After updating, also activate/deactivate
        const id = document.getElementById('formId').value;
        // We embed the activo state in a hidden field
        let activoInput = document.getElementById('inputActivoHidden');
        if (!activoInput) {
            activoInput = document.createElement('input');
            activoInput.type = 'hidden';
            activoInput.id = 'inputActivoHidden';
            activoInput.name = 'activo_state';
            this.appendChild(activoInput);
        }
        activoInput.value = isActivo ? '1' : '0';
    }
});
</script>

</body>
</html>
