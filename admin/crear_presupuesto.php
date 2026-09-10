<?php
/**
 * Crear Nuevo Presupuesto
 * Con búsqueda de clientes y tratamientos
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Presupuesto.php';
require_once '../src/models/Tratamiento.php';
require_once '../src/models/Client.php';

$presupuestoModel = new Presupuesto($pdo);
$tratamientoModel = new Tratamiento($pdo);
$clientModel = new Client($pdo);

$clientes = $clientModel->getAll();
$tratamientos = $tratamientoModel->getAll();

// Obtener doctores si existe la tabla
$doctores = [];
try {
    $doctores = $pdo->query("SELECT id, nombre FROM doctores WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = $_POST['cliente_id'] ?? null;
    $doctorId = $_POST['doctor_id'] ?? null;
    $notas = $_POST['notas'] ?? '';
    $items = $_POST['items'] ?? [];

    if ($clienteId && !empty($items)) {
        // Filter only valid items (must have descripcion and cantidad)
        $validItems = array_filter($items, function($item) {
            return !empty($item['descripcion']) && isset($item['cantidad']) && $item['cantidad'] >= 1 && $item['precio'] > 0;
        });

        if (empty($validItems)) {
            $_SESSION['message'] = 'Debe agregar al menos un tratamiento válido con cantidad y precio';
            $_SESSION['message_type'] = 'danger';
            header('Location: crear_presupuesto.php');
            exit();
        }

        try {
            $presupuestoId = $presupuestoModel->crear([
                'cliente_id' => $clienteId,
                'doctor_id' => $doctorId ?: null,
                'notas' => $notas
            ]);

            foreach ($validItems as $item) {
                $presupuestoModel->agregarItem($presupuestoId, [
                    'tratamiento_id' => $item['tratamiento_id'] ?: null,
                    'descripcion' => $item['descripcion'],
                    'diente' => $item['diente'] ?? null,
                    'cantidad' => $item['cantidad'] ?? 1,
                    'precio_unitario' => $item['precio']
                ]);
            }

            if (!empty($_POST['descuento'])) {
                $presupuestoModel->aplicarDescuento($presupuestoId, floatval($_POST['descuento']));
            }

            $_SESSION['message'] = 'Presupuesto creado exitosamente';
            $_SESSION['message_type'] = 'success';
            header('Location: ver_presupuesto.php?id=' . $presupuestoId);
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = 'Error al registrar presupuesto: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            header('Location: crear_presupuesto.php');
            exit();
        }
    }
}

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #6B1D49; --primary-dark: #531438; --accent: #C47D9F; }
    body { background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%); }
    .page-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 25px 30px;
        color: white;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; gap: 12px; }
    .btn-volver { background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; }
    .btn-volver:hover { background: rgba(255,255,255,0.3); color: white; }

    .form-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-card h5 { margin: 0 0 20px 0; font-weight: 700; color: #333; display: flex; align-items: center; gap: 10px; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .form-group { margin-bottom: 15px; position: relative; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1rem; transition: all 0.2s; }
    .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(196,162,126,0.15); }
    
    /* Buscador de clientes */
    .search-container { position: relative; }
    .search-input { padding-right: 40px; }
    .search-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #888; }
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 2px solid var(--primary);
        border-top: none;
        border-radius: 0 0 10px 10px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .search-results.show { display: block; }
    .search-result-item {
        padding: 12px 15px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background 0.2s;
    }
    .search-result-item:hover { background: rgba(196,162,126,0.1); }
    .search-result-item:last-child { border-bottom: none; }
    .result-name { font-weight: 600; color: #333; }
    .result-phone { font-size: 0.85rem; color: #888; }
    .selected-client {
        background: rgba(196,162,126,0.1);
        border: 2px solid var(--primary);
        border-radius: 10px;
        padding: 12px 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .selected-client .client-info { display: flex; align-items: center; gap: 10px; }
    .selected-client .avatar { width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; }
    .selected-client .btn-change { background: none; border: none; color: var(--primary); cursor: pointer; font-weight: 600; }

    /* Items Table */
    .items-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .items-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 700; font-size: 0.85rem; }
    .items-table td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    .items-table input, .items-table select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
    .items-table .col-trat { width: 35%; }
    .items-table .col-diente { width: 10%; }
    .items-table .col-cant { width: 10%; }
    .items-table .col-precio { width: 15%; }
    .items-table .col-subtotal { width: 15%; text-align: right; font-weight: 700; }
    .items-table .col-action { width: 5%; text-align: center; }

    /* Buscador de tratamientos */
    .trat-search-wrapper { position: relative; }
    .trat-search { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; }
    .trat-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid var(--primary);
        border-radius: 0 0 8px 8px;
        max-height: 200px;
        overflow-y: auto;
        z-index: 100;
        display: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .trat-dropdown.show { display: block; }
    .trat-option {
        padding: 10px;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        border-bottom: 1px solid #eee;
    }
    .trat-option:hover { background: rgba(196,162,126,0.1); }
    .trat-name { font-weight: 600; }
    .trat-price { color: var(--primary); font-weight: 700; }
    .trat-option.new-trat { background: #e8f4fd; border-bottom: none; color: #0066cc; }
    .trat-option.new-trat:hover { background: #d0e8f9; }

    .btn-add-item { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn-add-item:hover { background: var(--primary-dark); }
    .btn-new-trat { background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; margin-left: 10px; }
    .btn-new-trat:hover { background: #138496; }
    .btn-remove { background: #dc3545; color: white; border: none; width: 30px; height: 30px; border-radius: 6px; cursor: pointer; }

    /* Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 9999; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 450px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); animation: modalIn 0.3s ease; }
    @keyframes modalIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h4 { margin: 0; color: #333; font-weight: 700; }
    .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888; }
    .modal-close:hover { color: #333; }
    .modal-body .form-group { margin-bottom: 15px; }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
    .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
    .btn-save { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }

    /* Totales */
    .totales-box { background: #f8f9fa; border-radius: 12px; padding: 20px; margin-top: 20px; }
    .total-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 1rem; }
    .total-row.final { font-size: 1.3rem; font-weight: 800; color: var(--primary); border-top: 2px solid #ddd; padding-top: 15px; margin-top: 10px; }

    .btn-submit { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; padding: 15px 40px; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(196,162,126,0.4); }

    /* Clases para desktop/móvil */
    .mobile-only { display: none; }
    .desktop-only { display: block; }
    
    .trat-select {
        padding: 12px;
        font-size: 1rem;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        background: white;
        width: 100%;
    }
    .trat-select:focus {
        border-color: var(--primary);
        outline: none;
    }

    @media (max-width: 768px) {
        /* Intercambiar visibilidad en móvil */
        .mobile-only { display: block; }
        .desktop-only { display: none; }
        
        .page-container { padding: 10px; }
        
        /* Header compact - icon only buttons */
        .page-header { 
            flex-direction: row; 
            padding: 14px 16px;
            gap: 10px;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        .page-header h1 { font-size: 1.05rem; gap: 8px; }
        .page-header > div { gap: 6px; }
        .btn-volver {
            padding: 8px 10px;
            font-size: 0;
            border-radius: 6px;
        }
        .btn-volver i { font-size: 0.85rem; margin: 0 !important; }
        
        /* Form cards */
        .form-card { 
            padding: 14px; 
            margin-bottom: 12px;
            border-radius: 12px;
        }
        .form-card h5 {
            font-size: 0.95rem;
            margin-bottom: 12px;
        }
        
        /* Form inputs */
        .form-grid { 
            grid-template-columns: 1fr; 
            gap: 10px;
        }
        .form-control {
            padding: 11px 12px;
            font-size: 0.92rem;
            border-radius: 8px;
        }

        /* Patient selector on mobile */
        .selected-client {
            padding: 10px 12px;
            border-radius: 8px;
        }
        .selected-client .avatar { width: 36px; height: 36px; font-size: 0.85rem; }
        .selected-client .result-name { font-size: 0.9rem; }
        .selected-client .btn-change { font-size: 0.8rem; padding: 5px 8px; }

        /* Search results mobile */
        .search-results { max-height: 200px; border-radius: 0 0 8px 8px; }
        .search-result-item { padding: 10px 12px; }
        
        /* ====== TRATAMIENTOS - CARD FORMAT ====== */
        .items-table {
            display: block;
        }
        .items-table thead {
            display: none;
        }
        .items-table tbody {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .items-table .item-row {
            display: block;
            background: #fff;
            border: 2px solid #e9ecef;
            border-left: 4px solid var(--primary);
            border-radius: 12px;
            padding: 14px;
            position: relative;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
        }
        .items-table .item-row:hover {
            border-color: var(--primary);
        }
        .items-table td {
            display: block;
            padding: 6px 0;
            text-align: left;
            min-width: unset;
            border: none;
        }
        .items-table td::before {
            content: attr(data-label);
            display: block;
            font-weight: 700;
            font-size: 0.68rem;
            color: #999;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td input,
        .items-table td .trat-search,
        .items-table td .trat-select {
            width: 100%;
            padding: 10px 12px;
            font-size: 0.92rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        /* Delete button top-right */
        .items-table td:last-child {
            position: absolute;
            top: 8px;
            right: 8px;
            padding: 0;
        }
        .items-table td:last-child::before { display: none; }
        .btn-remove { width: 28px; height: 28px; font-size: 0.75rem; border-radius: 6px; }

        /* Subtotal bar */
        .items-table .col-subtotal {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            text-align: center;
            font-size: 1rem;
            margin-top: 6px;
            width: 100%;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .items-table .col-subtotal::before { 
            color: rgba(255,255,255,0.85); 
            display: inline;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        .items-table .col-subtotal span { font-weight: 800; font-size: 1.1rem; }
        
        /* Mobile select */
        .trat-select {
            font-size: 0.92rem !important;
            padding: 10px !important;
        }
        .trat-search {
            font-size: 0.92rem;
            padding: 10px;
        }
        .trat-dropdown {
            position: fixed;
            left: 5%;
            right: 5%;
            max-height: 50vh;
            z-index: 9999;
        }
        
        /* Buttons full width */
        .btn-add-item, .btn-new-trat {
            padding: 12px 15px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin: 4px 0;
            border-radius: 10px;
        }
        .btn-new-trat { margin-left: 0; }
        
        /* Totals */
        .totales-box {
            padding: 14px;
            border-radius: 10px;
        }
        .total-row { font-size: 0.9rem; }
        .total-row.final { font-size: 1.1rem; }
        .totales-box input[type="number"] {
            width: 90px !important;
            padding: 6px 8px !important;
        }
        
        /* Submit */
        .btn-submit {
            width: 100%;
            justify-content: center;
            padding: 14px;
            font-size: 1rem;
            border-radius: 12px;
        }
        
        /* Notes textarea */
        .form-card textarea {
            min-height: 60px;
        }
        
        /* Modal responsive */
        .modal-box {
            width: 95%;
            padding: 18px;
            margin: 10px;
            border-radius: 14px;
        }
        .modal-header h4 { font-size: 1rem; }
        .modal-body .form-control { padding: 10px 12px; font-size: 0.9rem; }
        .modal-footer { flex-direction: column; gap: 8px; }
        .modal-footer button { width: 100%; display: flex; align-items: center; justify-content: center; }
    }
    
    @media (max-width: 480px) {
        .page-header h1 { font-size: 0.92rem; }
        .page-header h1 i { font-size: 0.85rem; }
        .form-card h5 { font-size: 0.88rem; }
        .stat-number { font-size: 1rem; }
        .btn-submit { font-size: 0.92rem; padding: 12px; }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice-dollar"></i> Nuevo Presupuesto</h1>
        <div style="display: flex; gap: 10px;">
            <a href="tratamientos.php" class="btn-volver" style="background: #17a2b8;"><i class="fas fa-tooth me-2"></i>Tratamientos</a>
            <a href="presupuestos.php" class="btn-volver"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        </div>
    </div>

    <form method="POST" id="formPresupuesto">
        <!-- Datos generales -->
        <div class="form-card">
            <h5><i class="fas fa-user"></i> Datos del Paciente</h5>
            <div class="form-grid">
                <div class="form-group">
                    <label>Buscar Paciente *</label>
                    <div id="clienteSelector">
                        <div class="search-container">
                            <input type="text" id="searchCliente" class="form-control search-input" placeholder="Escriba nombre o teléfono..." autocomplete="off">
                            <i class="fas fa-search search-icon"></i>
                            <div id="clienteResults" class="search-results"></div>
                        </div>
                    </div>
                    <div id="clienteSelected" class="selected-client" style="display: none;">
                        <div class="client-info">
                            <div class="avatar" id="clienteAvatar">J</div>
                            <div>
                                <div class="result-name" id="clienteNombre">Juan Pérez</div>
                                <div class="result-phone" id="clienteTelefono">+591 12345678</div>
                            </div>
                        </div>
                        <button type="button" class="btn-change" onclick="cambiarCliente()"><i class="fas fa-exchange-alt"></i> Cambiar</button>
                    </div>
                    <input type="hidden" name="cliente_id" id="clienteIdInput" required>
                </div>
                <div class="form-group">
                    <label>Doctor</label>
                    <select name="doctor_id" class="form-control">
                        <option value="">Sin asignar</option>
                        <?php foreach ($doctores as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Items del presupuesto -->
        <div class="form-card">
            <h5><i class="fas fa-tooth"></i> Tratamientos</h5>
            
            <table class="items-table" id="itemsTable">
                <thead>
                    <tr>
                        <th class="col-trat">Tratamiento (buscar)</th>
                        <th class="col-diente">Diente (Opcional)</th>
                        <th class="col-cant">Cant.</th>
                        <th class="col-precio">Precio Unit.</th>
                        <th class="col-subtotal">Subtotal</th>
                        <th class="col-action"></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <tr class="item-row" data-index="0">
                        <td data-label="Tratamiento">
                            <!-- Buscador para desktop -->
                            <div class="trat-search-wrapper desktop-only">
                                <input type="text" class="trat-search" placeholder="Buscar tratamiento..." oninput="buscarTratamiento(this, 0)" onfocus="mostrarTratamientos(0)">
                                <div class="trat-dropdown" id="tratDropdown-0"></div>
                            </div>
                            <!-- Select para móvil -->
                            <select class="trat-select mobile-only form-control" onchange="seleccionarTratamientoSelect(this, 0)">
                                <option value="">Seleccionar tratamiento...</option>
                                <?php foreach ($tratamientos as $t): ?>
                                <option value="<?php echo $t['id']; ?>" data-precio="<?php echo $t['precio']; ?>"><?php echo htmlspecialchars($t['nombre']); ?> - Bs <?php echo number_format($t['precio'], 2); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="items[0][tratamiento_id]" class="tratamiento-id-input">
                            <input type="hidden" name="items[0][descripcion]" class="descripcion-input">
                        </td>
                        <td data-label="Diente (Opcional)"><input type="text" name="items[0][diente]" placeholder="Ej: 18"></td>
                        <td data-label="Cantidad"><input type="number" name="items[0][cantidad]" value="1" min="1" class="cantidad-input" onchange="calcularSubtotal(0)" required></td>
                        <td data-label="Precio Unitario"><input type="number" name="items[0][precio]" step="0.01" class="precio-input" onchange="calcularSubtotal(0)" placeholder="Bs"></td>
                        <td class="col-subtotal" data-label="Subtotal"><span id="subtotal-0">Bs 0.00</span></td>
                        <td><button type="button" class="btn-remove" onclick="eliminarFila(this)"><i class="fas fa-times"></i></button></td>
                    </tr>
                </tbody>
            </table>

            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="btn-add-item" onclick="agregarFila()">
                    <i class="fas fa-plus me-2"></i>Agregar Tratamiento
                </button>
                <button type="button" class="btn-new-trat" onclick="abrirModalTratamiento()">
                    <i class="fas fa-plus-circle me-2"></i>Crear Tratamiento Nuevo
                </button>
            </div>

            <div class="totales-box">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span id="subtotalGeneral">Bs 0.00</span>
                </div>
                <div class="total-row">
                    <span>Descuento (Bs):</span>
                    <input type="number" name="descuento" id="descuentoInput" value="0" min="0" step="0.01" style="width: 100px; padding: 5px; border: 1px solid #ddd; border-radius: 5px;" onchange="calcularTotal()" placeholder="Bs">
                </div>
                <div class="total-row final">
                    <span>TOTAL:</span>
                    <span id="totalFinal">Bs 0.00</span>
                </div>
            </div>
        </div>

        <!-- Notas -->
        <div class="form-card">
            <h5><i class="fas fa-sticky-note"></i> Notas Adicionales</h5>
            <textarea name="notas" class="form-control" rows="3" placeholder="Observaciones, condiciones de pago, etc."></textarea>
        </div>

        <div style="text-align: center; margin-top: 20px;">
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Guardar Presupuesto
            </button>
        </div>
    </form>
</div>

<!-- Modal para crear nuevo tratamiento -->
<div class="modal-overlay" id="modalTratamiento">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-plus-circle me-2"></i>Nuevo Tratamiento</h4>
            <button type="button" class="modal-close" onclick="cerrarModalTratamiento()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Nombre del Tratamiento *</label>
                <input type="text" id="nuevoTratNombre" class="form-control" placeholder="Ej: Blanqueamiento LED">
            </div>
            <div class="form-group">
                <label>Precio (Bs) *</label>
                <input type="number" id="nuevoTratPrecio" class="form-control" step="0.01" placeholder="Ej: 500.00">
            </div>
            <div class="form-group">
                <label>Categoría</label>
                <select id="nuevoTratCategoria" class="form-control">
                    <option value="Personalizado">Personalizado</option>
                    <option value="Prevención">Prevención</option>
                    <option value="Restauración">Restauración</option>
                    <option value="Endodoncia">Endodoncia</option>
                    <option value="Cirugía">Cirugía</option>
                    <option value="Prótesis">Prótesis</option>
                    <option value="Ortodoncia">Ortodoncia</option>
                    <option value="Estética">Estética</option>
                    <option value="Diagnóstico">Diagnóstico</option>
                    <option value="Implantes">Implantes</option>
                </select>
            </div>
            <div class="form-group">
                <label>Descripción (opcional)</label>
                <textarea id="nuevoTratDescripcion" class="form-control" rows="2" placeholder="Descripción breve..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-cancel" onclick="cerrarModalTratamiento()">Cancelar</button>
            <button type="button" class="btn-save" onclick="guardarNuevoTratamiento()">
                <i class="fas fa-save me-1"></i> Guardar
            </button>
        </div>
    </div>
</div>

<script>
// Datos de clientes y tratamientos
const clientes = <?php echo json_encode($clientes); ?>;
const tratamientos = <?php echo json_encode($tratamientos); ?>;
let filaIndex = 1;
let clienteSeleccionado = null;

// ========== BÚSQUEDA DE CLIENTES ==========
const searchInput = document.getElementById('searchCliente');
const resultsDiv = document.getElementById('clienteResults');

searchInput.addEventListener('input', function() {
    const query = this.value.toLowerCase().trim();
    if (query.length < 2) {
        resultsDiv.classList.remove('show');
        return;
    }
    
    const filtrados = clientes.filter(c => 
        c.nombre.toLowerCase().includes(query) || 
        c.telefono.includes(query)
    ).slice(0, 10);
    
    if (filtrados.length === 0) {
        resultsDiv.innerHTML = '<div class="search-result-item"><em>No se encontraron resultados</em></div>';
    } else {
        resultsDiv.innerHTML = filtrados.map(c => `
            <div class="search-result-item" onmousedown="seleccionarCliente(${c.id}, '${c.nombre.replace(/'/g, "\\'")}', '${c.telefono}')">
                <div class="result-name">${c.nombre}</div>
                <div class="result-phone"><i class="fas fa-phone me-1"></i>${c.telefono}</div>
            </div>
        `).join('');
    }
    resultsDiv.classList.add('show');
});

// Cerrar resultados cuando se hace clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
        resultsDiv.classList.remove('show');
    }
});

function seleccionarCliente(id, nombre, telefono) {
    clienteSeleccionado = { id, nombre, telefono };
    document.getElementById('clienteIdInput').value = id;
    document.getElementById('clienteNombre').textContent = nombre;
    document.getElementById('clienteTelefono').textContent = telefono;
    document.getElementById('clienteAvatar').textContent = nombre.charAt(0).toUpperCase();
    
    document.getElementById('clienteSelector').style.display = 'none';
    document.getElementById('clienteSelected').style.display = 'flex';
    resultsDiv.classList.remove('show');
}

function cambiarCliente() {
    clienteSeleccionado = null;
    document.getElementById('clienteIdInput').value = '';
    document.getElementById('clienteSelector').style.display = 'block';
    document.getElementById('clienteSelected').style.display = 'none';
    searchInput.value = '';
    searchInput.focus();
}

// ========== BÚSQUEDA DE TRATAMIENTOS ==========
function buscarTratamiento(input, index) {
    const query = input.value.toLowerCase().trim();
    const dropdown = document.getElementById(`tratDropdown-${index}`);
    
    if (query.length < 1) {
        mostrarTratamientos(index);
        return;
    }
    
    const filtrados = tratamientos.filter(t => 
        t.nombre.toLowerCase().includes(query) || 
        t.codigo.toLowerCase().includes(query) ||
        (t.categoria && t.categoria.toLowerCase().includes(query))
    );
    
    renderTratamientos(dropdown, filtrados, index);
    dropdown.classList.add('show');
}

function mostrarTratamientos(index) {
    const dropdown = document.getElementById(`tratDropdown-${index}`);
    renderTratamientos(dropdown, tratamientos.slice(0, 15), index);
    dropdown.classList.add('show');
}

function renderTratamientos(dropdown, lista, index) {
    if (lista.length === 0) {
        dropdown.innerHTML = '<div class="trat-option"><em>Sin resultados</em></div>';
    } else {
        dropdown.innerHTML = lista.map(t => `
            <div class="trat-option" onmousedown="seleccionarTratamiento(${t.id}, '${t.nombre.replace(/'/g, "\\'")}', ${t.precio}, ${index})">
                <span class="trat-name">${t.nombre}</span>
                <span class="trat-price">Bs ${parseFloat(t.precio).toFixed(2)}</span>
            </div>
        `).join('');
    }
}

function seleccionarTratamiento(id, nombre, precio, index) {
    const row = document.querySelector(`.item-row[data-index="${index}"]`);
    row.querySelector('.trat-search').value = nombre;
    row.querySelector('.tratamiento-id-input').value = id;
    row.querySelector('.descripcion-input').value = nombre;
    row.querySelector('.precio-input').value = precio;
    
    const dropdown = document.getElementById(`tratDropdown-${index}`);
    dropdown.classList.remove('show');
    
    calcularSubtotal(index);
}

// Selección desde combobox móvil
function seleccionarTratamientoSelect(select, index) {
    const row = document.querySelector(`.item-row[data-index="${index}"]`);
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const id = selectedOption.value;
        const nombre = selectedOption.text.split(' - Bs')[0];
        const precio = selectedOption.dataset.precio;
        
        row.querySelector('.tratamiento-id-input').value = id;
        row.querySelector('.descripcion-input').value = nombre;
        row.querySelector('.precio-input').value = precio;
        
        calcularSubtotal(index);
    }
}

// Cerrar dropdowns al hacer clic fuera
document.addEventListener('click', function(e) {
    if (!e.target.closest('.trat-search-wrapper')) {
        document.querySelectorAll('.trat-dropdown').forEach(d => d.classList.remove('show'));
    }
});

// ========== CÁLCULOS ==========
function calcularSubtotal(index) {
    const row = document.querySelector(`.item-row[data-index="${index}"]`);
    if (!row) return;
    
    const cantidad = parseFloat(row.querySelector('.cantidad-input').value) || 0;
    const precio = parseFloat(row.querySelector('.precio-input').value) || 0;
    const subtotal = cantidad * precio;
    document.getElementById(`subtotal-${index}`).textContent = 'Bs ' + subtotal.toFixed(2);
    calcularTotal();
}

function calcularTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const cant = parseFloat(row.querySelector('.cantidad-input').value) || 0;
        const precio = parseFloat(row.querySelector('.precio-input').value) || 0;
        subtotal += cant * precio;
    });
    
    // Descuento en monto fijo (Bs)
    let descuentoMonto = parseFloat(document.getElementById('descuentoInput').value) || 0;
    
    // Asegurar que el descuento no sea mayor al subtotal
    if (descuentoMonto > subtotal) {
        descuentoMonto = subtotal;
        document.getElementById('descuentoInput').value = subtotal.toFixed(2);
    }
    
    const total = subtotal - descuentoMonto;
    
    document.getElementById('subtotalGeneral').textContent = 'Bs ' + subtotal.toFixed(2);
    document.getElementById('totalFinal').textContent = 'Bs ' + total.toFixed(2);
}

function agregarFila() {
    const tbody = document.getElementById('itemsBody');
    
    // Generar opciones del select para móvil
    let optionsHtml = '<option value="">Seleccionar tratamiento...</option>';
    tratamientos.forEach(t => {
        optionsHtml += `<option value="${t.id}" data-precio="${t.precio}">${t.nombre} - Bs ${parseFloat(t.precio).toFixed(2)}</option>`;
    });
    
    const html = `
        <tr class="item-row" data-index="${filaIndex}">
            <td data-label="Tratamiento">
                <!-- Buscador para desktop -->
                <div class="trat-search-wrapper desktop-only">
                    <input type="text" class="trat-search" placeholder="Buscar tratamiento..." oninput="buscarTratamiento(this, ${filaIndex})" onfocus="mostrarTratamientos(${filaIndex})">
                    <div class="trat-dropdown" id="tratDropdown-${filaIndex}"></div>
                </div>
                <!-- Select para móvil -->
                <select class="trat-select mobile-only form-control" onchange="seleccionarTratamientoSelect(this, ${filaIndex})">
                    ${optionsHtml}
                </select>
                <input type="hidden" name="items[${filaIndex}][tratamiento_id]" class="tratamiento-id-input">
                <input type="hidden" name="items[${filaIndex}][descripcion]" class="descripcion-input">
            </td>
            <td data-label="Diente (Opcional)"><input type="text" name="items[${filaIndex}][diente]" placeholder="Ej: 18"></td>
            <td data-label="Cantidad"><input type="number" name="items[${filaIndex}][cantidad]" value="1" min="1" class="cantidad-input" onchange="calcularSubtotal(${filaIndex})" required></td>
            <td data-label="Precio Unitario"><input type="number" name="items[${filaIndex}][precio]" step="0.01" class="precio-input" onchange="calcularSubtotal(${filaIndex})" placeholder="Bs"></td>
            <td class="col-subtotal" data-label="Subtotal"><span id="subtotal-${filaIndex}">Bs 0.00</span></td>
            <td><button type="button" class="btn-remove" onclick="eliminarFila(this)"><i class="fas fa-times"></i></button></td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', html);
    filaIndex++;
}

function eliminarFila(btn) {
    if (document.querySelectorAll('.item-row').length > 1) {
        btn.closest('tr').remove();
        calcularTotal();
    }
}

// Validación antes de enviar
document.getElementById('formPresupuesto').addEventListener('submit', function(e) {
    if (!document.getElementById('clienteIdInput').value) {
        e.preventDefault();
        alert('Por favor seleccione un paciente');
        return;
    }
    
    // Validar que cada fila tenga tratamiento y cantidad
    let valid = true;
    let firstInvalid = null;
    const rows = document.querySelectorAll('.item-row');
    
    rows.forEach(row => {
        const descInput = row.querySelector('.descripcion-input');
        const searchInput = row.querySelector('.trat-search');
        const selectInput = row.querySelector('.trat-select');
        const cantInput = row.querySelector('.cantidad-input');
        const precioInput = row.querySelector('.precio-input');
        
        // Auto-fill descripcion from search input if needed
        if (!descInput.value && searchInput && searchInput.value) {
            descInput.value = searchInput.value;
        }
        
        // Check tratamiento is selected
        if (!descInput.value) {
            valid = false;
            if (!firstInvalid) firstInvalid = row;
            // Highlight the field
            if (searchInput) searchInput.style.borderColor = '#dc3545';
            if (selectInput) selectInput.style.borderColor = '#dc3545';
        } else {
            if (searchInput) searchInput.style.borderColor = '';
            if (selectInput) selectInput.style.borderColor = '';
        }
        
        // Check cantidad
        if (!cantInput.value || parseInt(cantInput.value) < 1) {
            valid = false;
            if (!firstInvalid) firstInvalid = row;
            cantInput.style.borderColor = '#dc3545';
        } else {
            cantInput.style.borderColor = '';
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert('Por favor complete los campos obligatorios: Tratamiento y Cantidad en cada fila');
        if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
});

// ========== MODAL NUEVO TRATAMIENTO ==========
function abrirModalTratamiento() {
    document.getElementById('modalTratamiento').classList.add('show');
    document.getElementById('nuevoTratNombre').focus();
}

function cerrarModalTratamiento() {
    document.getElementById('modalTratamiento').classList.remove('show');
    // Limpiar campos
    document.getElementById('nuevoTratNombre').value = '';
    document.getElementById('nuevoTratPrecio').value = '';
    document.getElementById('nuevoTratDescripcion').value = '';
    document.getElementById('nuevoTratCategoria').value = 'Personalizado';
}

function guardarNuevoTratamiento() {
    const nombre = document.getElementById('nuevoTratNombre').value.trim();
    const precio = parseFloat(document.getElementById('nuevoTratPrecio').value);
    const categoria = document.getElementById('nuevoTratCategoria').value;
    const descripcion = document.getElementById('nuevoTratDescripcion').value.trim();
    
    if (!nombre) {
        alert('Por favor ingrese el nombre del tratamiento');
        return;
    }
    if (!precio || precio <= 0) {
        alert('Por favor ingrese un precio válido');
        return;
    }
    
    // Enviar al servidor
    fetch('guardar_tratamiento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre, precio, categoria, descripcion })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Agregar al array local de tratamientos
            tratamientos.push(data.tratamiento);
            
            // Agregar una nueva fila con este tratamiento
            agregarFila();
            const ultimaFila = document.querySelector(`.item-row[data-index="${filaIndex - 1}"]`);
            if (ultimaFila) {
                ultimaFila.querySelector('.trat-search').value = nombre;
                ultimaFila.querySelector('.descripcion-input').value = nombre;
                ultimaFila.querySelector('.tratamiento-id-input').value = data.tratamiento.id;
                ultimaFila.querySelector('.precio-input').value = precio;
                calcularSubtotal(filaIndex - 1);
            }
            
            cerrarModalTratamiento();
            alert('✓ Tratamiento "' + nombre + '" creado y agregado al presupuesto');
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(err => {
        alert('Error de conexión');
        console.error(err);
    });
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalTratamiento();
    }
});

// Cerrar modal al hacer clic fuera
document.getElementById('modalTratamiento').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalTratamiento();
    }
});
</script>

</body>
</html>
