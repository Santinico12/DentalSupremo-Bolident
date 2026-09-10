<?php
/**
 * Historia Clínica del Paciente
 * Vista principal con tabs: Ficha Médica, Evoluciones, Odontograma, Archivos
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: lista_clientes.php');
    exit();
}

$clienteId = intval($_GET['id']);

require_once '../src/config/db.php';
require_once '../src/models/Client.php';
require_once '../src/models/HistoriaClinica.php';
require_once '../src/models/Evolucion.php';
require_once '../src/models/ArchivoClinico.php';
require_once '../src/models/Presupuesto.php';

$clientModel = new Client($pdo);
$historiaModel = new HistoriaClinica($pdo);
$evolucionModel = new Evolucion($pdo);
$archivoModel = new ArchivoClinico($pdo);
$presupuestoModel = new Presupuesto($pdo);

$cliente = $clientModel->getById($clienteId);
if (!$cliente) {
    $_SESSION['message'] = 'Paciente no encontrado';
    header('Location: lista_clientes.php');
    exit();
}

$historia = $historiaModel->getByCliente($clienteId);
$evoluciones = $evolucionModel->getByCliente($clienteId);
$archivos = $archivoModel->getByCliente($clienteId);
$presupuestos = $presupuestoModel->getByCliente($clienteId);
$alertas = $historiaModel->getAlertasMedicas($clienteId);

// Obtener doctores para el formulario
$doctores = $pdo->query("SELECT id, nombre FROM doctores WHERE estado = 'activo' ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #6B1D49; --primary-dark: #531438; --accent: #C47D9F; }
    body { background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%); }
    .page-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
    
    /* Header del paciente */
    .patient-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 25px;
        color: white;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .patient-info { display: flex; align-items: center; gap: 20px; }
    .patient-avatar { width: 70px; height: 70px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; }
    .patient-name { font-size: 1.5rem; font-weight: 700; margin: 0; }
    .patient-phone { opacity: 0.9; font-size: 0.95rem; }
    .header-actions { display: flex; gap: 10px; }
    .btn-header { padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.2); color: white; border: none; cursor: pointer; }
    .btn-header:hover { background: rgba(255,255,255,0.3); color: white; }

    /* Alertas médicas */
    .alertas-box { margin-bottom: 20px; }
    .alerta { padding: 12px 15px; border-radius: 8px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
    .alerta-danger { background: #f8d7da; color: #721c24; border-left: 4px solid #dc3545; }
    .alerta-warning { background: #fff3cd; color: #856404; border-left: 4px solid #ffc107; }
    .alerta-info { background: #d1ecf1; color: #0c5460; border-left: 4px solid #17a2b8; }

    /* Tabs */
    .tabs-container { margin-bottom: 20px; }
    .tabs-nav { display: flex; gap: 5px; background: white; padding: 5px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); flex-wrap: wrap; }
    .tab-btn { padding: 12px 20px; border: none; background: transparent; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; color: #666; }
    .tab-btn:hover { background: #f0f0f0; }
    .tab-btn.active { background: var(--primary); color: white; }
    .tab-btn .badge { background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; font-size: 0.8rem; }
    .tab-btn.active .badge { background: rgba(255,255,255,0.3); }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Cards */
    .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .card-title { font-weight: 700; font-size: 1.1rem; color: #333; display: flex; align-items: center; gap: 10px; margin: 0; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 6px; color: #555; font-size: 0.9rem; }
    .form-control { width: 100%; padding: 10px 12px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 0.95rem; }
    .form-control:focus { border-color: var(--primary); outline: none; }

    /* Timeline de evoluciones */
    .timeline { position: relative; padding-left: 30px; }
    .timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #e9ecef; }
    .timeline-item { position: relative; padding-bottom: 25px; }
    .timeline-item::before { content: ''; position: absolute; left: -24px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); border: 3px solid white; box-shadow: 0 0 0 2px var(--primary); }
    .timeline-date { font-size: 0.85rem; color: #888; margin-bottom: 5px; }
    .timeline-card { background: #f8f9fa; border-radius: 10px; padding: 15px; border-left: 3px solid var(--primary); }
    .timeline-title { font-weight: 700; color: #333; margin-bottom: 8px; }
    .timeline-content { color: #666; font-size: 0.9rem; }
    .timeline-content p { margin: 4px 0; }
    .timeline-doctor { font-size: 0.85rem; color: var(--primary); margin-top: 8px; }

    /* Archivos */
    .archivos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
    .archivo-card { background: #f8f9fa; border-radius: 10px; padding: 15px; text-align: center; }
    .archivo-icon { font-size: 3rem; color: var(--primary); margin-bottom: 10px; }
    .archivo-nombre { font-weight: 600; font-size: 0.9rem; word-break: break-word; }
    .archivo-fecha { font-size: 0.8rem; color: #888; margin-top: 5px; }

    /* Botones */
    .btn { padding: 10px 20px; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
    .btn-primary { background: var(--primary); color: white; }
    .btn-primary:hover { background: var(--primary-dark); }
    .btn-success { background: #28a745; color: white; }
    .btn-sm { padding: 6px 12px; font-size: 0.85rem; }

    /* Empty state */
    .empty-state { text-align: center; padding: 40px; color: #888; }
    .empty-state i { font-size: 3rem; opacity: 0.3; margin-bottom: 15px; }

    /* Modal */
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 9999; padding: 20px; transition: background 0.3s; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: white; border-radius: 16px; padding: 25px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .modal-header h4 { margin: 0; font-weight: 700; }
    .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #888; }
    .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

    @media (max-width: 768px) {
        .page-container { padding: 10px; }
        .patient-header { padding: 12px 15px; flex-direction: row; text-align: left; gap: 10px; align-items: center; flex-wrap: wrap; }
        .patient-info { flex-direction: row; gap: 10px; flex: 1; }
        .patient-avatar { width: 40px; height: 40px; min-width: 40px; font-size: 1.1rem; }
        .patient-name { font-size: 1rem; }
        .patient-phone { font-size: 0.8rem; }
        .header-actions { gap: 6px; }
        .btn-header { padding: 6px 10px; font-size: 0.78rem; }

        .tabs-nav { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; padding: 4px; }
        .tab-btn { padding: 10px 8px; font-size: 0.78rem; justify-content: center; text-align: center; min-width: 0; white-space: nowrap; }
        .tab-btn i { font-size: 0.85rem; }
        .tab-btn .badge { font-size: 0.7rem; padding: 1px 6px; }

        .card { padding: 15px; border-radius: 12px; }
        .card-header { flex-direction: column; gap: 10px; align-items: flex-start; }
        .card-title { font-size: 1rem; }

        .form-grid { grid-template-columns: 1fr; gap: 12px; }
        .form-grid .form-group[style*="grid-column: span 2"] { grid-column: span 1 !important; }

        .modal-box { padding: 15px; border-radius: 12px; }

        /* Presupuestos: hide table, show cards */
        .presupuestos-table { display: none; }
        .presupuestos-cards { display: block !important; }
    }

    /* Presupuesto cards (mobile) */
    .presupuestos-cards { display: none; }

    .pres-card {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 10px;
        border-left: 4px solid var(--primary);
        transition: box-shadow 0.2s;
    }

    .pres-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,0.08); }

    .pres-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .pres-card-number {
        font-weight: 700;
        font-size: 0.95rem;
        color: #333;
    }

    .pres-card-estado {
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .pres-card-estado.aprobado { background: rgba(196,162,126,0.15); color: var(--primary-dark); }
    .pres-card-estado.pagado { background: rgba(40,167,69,0.12); color: #28a745; }
    .pres-card-estado.pendiente { background: rgba(255,193,7,0.12); color: #d4a106; }
    .pres-card-estado.cancelado { background: rgba(220,53,69,0.12); color: #dc3545; }
    .pres-card-estado.borrador { background: rgba(108,117,125,0.12); color: #6c757d; }

    .pres-card-body {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pres-card-info {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .pres-card-date {
        font-size: 0.8rem;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .pres-card-total {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--primary);
    }

    .pres-card-action {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--primary);
        color: white;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        text-decoration: none;
        font-size: 0.85rem;
        transition: background 0.2s;
    }

    .pres-card-action:hover { background: var(--primary-dark); color: white; }
</style>

<div class="page-container">
    <!-- Header del paciente -->
    <div class="patient-header">
        <div class="patient-info">
            <div class="patient-avatar"><?php echo strtoupper(substr($cliente['nombre'], 0, 1)); ?></div>
            <div>
                <h1 class="patient-name"><?php echo htmlspecialchars($cliente['nombre']); ?></h1>
                <div class="patient-phone"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($cliente['telefono']); ?></div>
            </div>
        </div>
        <div class="header-actions">
            <a href="odontograma.php?cliente_id=<?php echo $clienteId; ?>" class="btn-header">
                <i class="fas fa-tooth"></i> Odontograma
            </a>
            <a href="lista_clientes.php" class="btn-header">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Alertas médicas -->
    <?php if (!empty($alertas)): ?>
    <div class="alertas-box">
        <?php foreach ($alertas as $alerta): ?>
        <div class="alerta alerta-<?php echo $alerta['tipo']; ?>">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($alerta['texto']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Tabs de navegación -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <button class="tab-btn active" onclick="cambiarTab('ficha')">
                <i class="fas fa-notes-medical"></i> Ficha Médica
            </button>
            <button class="tab-btn" onclick="cambiarTab('evoluciones')">
                <i class="fas fa-history"></i> Evoluciones
                <span class="badge"><?php echo count($evoluciones); ?></span>
            </button>
            <button class="tab-btn" onclick="cambiarTab('archivos')">
                <i class="fas fa-images"></i> Archivos
                <span class="badge"><?php echo count($archivos); ?></span>
            </button>
            <button class="tab-btn" onclick="cambiarTab('presupuestos')">
                <i class="fas fa-file-invoice-dollar"></i> Presupuestos
                <span class="badge"><?php echo count($presupuestos); ?></span>
            </button>
        </div>
    </div>

    <!-- Tab: Ficha Médica -->
    <div id="tab-ficha" class="tab-content active">
        <form method="POST" action="guardar_historia.php">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user"></i> Datos Personales</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Fecha de Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" class="form-control" value="<?php echo $historia['fecha_nacimiento'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Sexo</label>
                        <select name="sexo" class="form-control">
                            <option value="">Seleccionar...</option>
                            <option value="M" <?php echo ($historia['sexo'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo ($historia['sexo'] ?? '') === 'F' ? 'selected' : ''; ?>>Femenino</option>
                            <option value="Otro" <?php echo ($historia['sexo'] ?? '') === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ocupación</label>
                        <input type="text" name="ocupacion" class="form-control" value="<?php echo htmlspecialchars($historia['ocupacion'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($historia['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Dirección</label>
                        <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($historia['direccion'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-phone-alt"></i> Contacto de Emergencia</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="contacto_emergencia_nombre" class="form-control" value="<?php echo htmlspecialchars($historia['contacto_emergencia_nombre'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="contacto_emergencia_telefono" class="form-control" value="<?php echo htmlspecialchars($historia['contacto_emergencia_telefono'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Parentesco</label>
                        <input type="text" name="contacto_emergencia_parentesco" class="form-control" value="<?php echo htmlspecialchars($historia['contacto_emergencia_parentesco'] ?? ''); ?>" placeholder="Ej: Esposo(a), Padre, Madre">
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-heartbeat"></i> Antecedentes Médicos</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Grupo Sanguíneo</label>
                        <select name="grupo_sanguineo" class="form-control">
                            <option value="">Desconocido</option>
                            <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $gs): ?>
                            <option value="<?php echo $gs; ?>" <?php echo ($historia['grupo_sanguineo'] ?? '') === $gs ? 'selected' : ''; ?>><?php echo $gs; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label><i class="fas fa-exclamation-triangle text-danger me-1"></i> Alergias</label>
                        <textarea name="alergias" class="form-control" rows="2" placeholder="Medicamentos, anestésicos, látex, etc."><?php echo htmlspecialchars($historia['alergias'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Enfermedades Sistémicas</label>
                        <textarea name="enfermedades_sistemicas" class="form-control" rows="2" placeholder="Diabetes, hipertensión, cardiopatías, asma, epilepsia, etc."><?php echo htmlspecialchars($historia['enfermedades_sistemicas'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Medicamentos Actuales</label>
                        <textarea name="medicamentos_actuales" class="form-control" rows="2" placeholder="Lista de medicamentos que toma actualmente"><?php echo htmlspecialchars($historia['medicamentos_actuales'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Cirugías Previas</label>
                        <textarea name="cirugias_previas" class="form-control" rows="2"><?php echo htmlspecialchars($historia['cirugias_previas'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Hospitalizaciones</label>
                        <textarea name="hospitalizaciones" class="form-control" rows="2"><?php echo htmlspecialchars($historia['hospitalizaciones'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="form-grid" style="margin-top: 15px;">
                    <div class="form-group">
                        <label><input type="checkbox" name="embarazo" value="1" <?php echo ($historia['embarazo'] ?? 0) ? 'checked' : ''; ?>> Embarazada</label>
                    </div>
                    <div class="form-group">
                        <label><input type="checkbox" name="lactancia" value="1" <?php echo ($historia['lactancia'] ?? 0) ? 'checked' : ''; ?>> En período de lactancia</label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-tooth"></i> Antecedentes Odontológicos</h3>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Última Visita al Dentista</label>
                        <input type="date" name="ultima_visita_dentista" class="form-control" value="<?php echo $historia['ultima_visita_dentista'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Experiencia con Anestesia</label>
                        <textarea name="experiencia_anestesia" class="form-control" rows="2" placeholder="Reacciones adversas, problemas previos..."><?php echo htmlspecialchars($historia['experiencia_anestesia'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Hábitos</label>
                        <textarea name="habitos" class="form-control" rows="2" placeholder="Bruxismo, onicofagia, respirador bucal, tabaco, alcohol..."><?php echo htmlspecialchars($historia['habitos'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Higiene Bucal</label>
                        <textarea name="higiene_bucal" class="form-control" rows="2" placeholder="Frecuencia de cepillado, uso de hilo dental, enjuague..."><?php echo htmlspecialchars($historia['higiene_bucal'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clipboard"></i> Observaciones Generales</h3>
                </div>
                <div class="form-group">
                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Notas adicionales sobre el paciente..."><?php echo htmlspecialchars($historia['observaciones'] ?? ''); ?></textarea>
                </div>
            </div>

            <div style="text-align: center;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Ficha Médica
                </button>
            </div>
        </form>
    </div>

    <!-- Tab: Evoluciones -->
    <div id="tab-evoluciones" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Historial de Atenciones</h3>
                <button class="btn btn-primary" onclick="abrirModalEvolucion()">
                    <i class="fas fa-plus"></i> Nueva Evolución
                </button>
            </div>

            <?php if (empty($evoluciones)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h4>Sin evoluciones registradas</h4>
                <p>Registra la primera nota de evolución clínica</p>
            </div>
            <?php else: ?>
            <div class="timeline">
                <?php foreach ($evoluciones as $evo): ?>
                <div class="timeline-item">
                    <div class="timeline-date">
                        <i class="fas fa-calendar me-1"></i><?php echo date('d/m/Y H:i', strtotime($evo['fecha_atencion'])); ?>
                    </div>
                    <div class="timeline-card">
                        <div class="timeline-title"><?php echo htmlspecialchars($evo['motivo_consulta']); ?></div>
                        <div class="timeline-content">
                            <?php if ($evo['diagnostico']): ?>
                            <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($evo['diagnostico']); ?></p>
                            <?php endif; ?>
                            <p><strong>Tratamiento:</strong> <?php echo htmlspecialchars($evo['tratamiento_realizado']); ?></p>
                            <?php if ($evo['dientes_tratados']): ?>
                            <p><strong>Dientes:</strong> <?php echo htmlspecialchars($evo['dientes_tratados']); ?></p>
                            <?php endif; ?>
                            <?php if ($evo['indicaciones_paciente']): ?>
                            <p><strong>Indicaciones:</strong> <?php echo htmlspecialchars($evo['indicaciones_paciente']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($evo['doctor_nombre']): ?>
                        <div class="timeline-doctor"><i class="fas fa-user-md me-1"></i><?php echo $evo['doctor_nombre']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Archivos -->
    <div id="tab-archivos" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-images"></i> Archivos Clínicos</h3>
                <button class="btn btn-primary" onclick="abrirModalArchivo()">
                    <i class="fas fa-upload"></i> Subir Archivo
                </button>
            </div>

            <?php if (empty($archivos)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h4>Sin archivos</h4>
                <p>Sube radiografías, fotos intraorales o documentos</p>
            </div>
            <?php else: ?>
            <div class="archivos-grid">
                <?php foreach ($archivos as $archivo): ?>
                <div class="archivo-card">
                    <?php
                    $iconos = [
                        'radiografia' => 'fa-x-ray',
                        'foto_intraoral' => 'fa-camera',
                        'foto_extraoral' => 'fa-portrait',
                        'documento' => 'fa-file-pdf',
                        'otro' => 'fa-file'
                    ];
                    $icono = $iconos[$archivo['tipo']] ?? 'fa-file';
                    ?>
                    <div class="archivo-icon"><i class="fas <?php echo $icono; ?>"></i></div>
                    <div class="archivo-nombre"><?php echo htmlspecialchars($archivo['descripcion'] ?: $archivo['nombre_archivo']); ?></div>
                    <div class="archivo-fecha"><?php echo date('d/m/Y', strtotime($archivo['created_at'])); ?></div>
                    <div class="d-flex justify-content-center gap-1 mt-2 flex-wrap">
                        <button onclick="verArchivoClinico('<?php echo $archivo['ruta_archivo']; ?>', '<?php echo $archivo['extension']; ?>')" class="btn btn-sm btn-primary" title="Ver Archivo">
                            <i class="fas fa-eye"></i> Ver
                        </button>
                        <button onclick="abrirModalRenombrarArchivo(<?php echo $archivo['id']; ?>, '<?php echo htmlspecialchars($archivo['descripcion'] ?: $archivo['nombre_archivo'], ENT_QUOTES); ?>', '<?php echo $archivo['tipo']; ?>', '<?php echo $archivo['fecha_toma']; ?>')" class="btn btn-sm btn-warning text-white" title="Renombrar / Editar">
                            <i class="fas fa-pen"></i>
                        </button>
                        <button onclick="eliminarArchivoClinico(<?php echo $archivo['id']; ?>, <?php echo $clienteId; ?>)" class="btn btn-sm btn-danger" title="Eliminar Archivo">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Presupuestos -->
    <div id="tab-presupuestos" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice-dollar"></i> Presupuestos del Paciente</h3>
                <a href="crear_presupuesto.php?cliente=<?php echo $clienteId; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Presupuesto
                </a>
            </div>

            <?php if (empty($presupuestos)): ?>
            <div class="empty-state">
                <i class="fas fa-file-invoice"></i>
                <h4>Sin presupuestos</h4>
                <p>Crea un presupuesto para este paciente</p>
            </div>
            <?php else: ?>
            <div class="presupuestos-table">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">Número</th>
                        <th style="padding: 12px; text-align: left;">Fecha</th>
                        <th style="padding: 12px; text-align: right;">Total</th>
                        <th style="padding: 12px; text-align: center;">Estado</th>
                        <th style="padding: 12px; text-align: center;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presupuestos as $pres): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px;"><strong><?php echo $pres['numero']; ?></strong></td>
                        <td style="padding: 12px;"><?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></td>
                        <td style="padding: 12px; text-align: right; font-weight: 700; color: var(--primary);">Bs <?php echo number_format($pres['total'], 2); ?></td>
                        <td style="padding: 12px; text-align: center;">
                            <span style="padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; background: #e9ecef;"><?php echo ucfirst($pres['estado']); ?></span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="ver_presupuesto.php?id=<?php echo $pres['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <!-- Mobile cards -->
            <div class="presupuestos-cards">
                <?php foreach ($presupuestos as $pres): ?>
                <div class="pres-card">
                    <div class="pres-card-header">
                        <span class="pres-card-number"><i class="fas fa-file-invoice" style="color:var(--primary);margin-right:5px;"></i><?php echo $pres['numero']; ?></span>
                        <span class="pres-card-estado <?php echo strtolower($pres['estado']); ?>"><?php echo ucfirst($pres['estado']); ?></span>
                    </div>
                    <div class="pres-card-body">
                        <div class="pres-card-info">
                            <span class="pres-card-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pres['fecha'])); ?></span>
                            <span class="pres-card-total">Bs <?php echo number_format($pres['total'], 2); ?></span>
                        </div>
                        <a href="ver_presupuesto.php?id=<?php echo $pres['id']; ?>" class="pres-card-action" title="Ver presupuesto">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Nueva Evolución -->
<div class="modal-overlay" id="modalEvolucion">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-plus-circle me-2"></i>Nueva Evolución Clínica</h4>
            <button class="modal-close" onclick="cerrarModalEvolucion()">&times;</button>
        </div>
        <form method="POST" action="guardar_evolucion.php">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="form-group">
                <label>Doctor</label>
                <select name="doctor_id" class="form-control">
                    <option value="">Sin asignar</option>
                    <?php foreach ($doctores as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo $d['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Motivo de Consulta *</label>
                <input type="text" name="motivo_consulta" class="form-control" required placeholder="Ej: Dolor en muela 36">
            </div>
            <div class="form-group">
                <label>Diagnóstico</label>
                <textarea name="diagnostico" class="form-control" rows="2" placeholder="Diagnóstico clínico..."></textarea>
            </div>
            <div class="form-group">
                <label>Tratamiento Realizado *</label>
                <textarea name="tratamiento_realizado" class="form-control" rows="3" required placeholder="Describir el procedimiento realizado..."></textarea>
            </div>
            <div class="form-group">
                <label>Dientes Tratados</label>
                <input type="text" name="dientes_tratados" class="form-control" placeholder="Ej: 36, 37">
            </div>
            <div class="form-group">
                <label>Indicaciones al Paciente</label>
                <textarea name="indicaciones_paciente" class="form-control" rows="2" placeholder="Recomendaciones post-tratamiento..."></textarea>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalEvolucion()">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Subir Archivo -->
<div class="modal-overlay" id="modalArchivo">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-upload me-2"></i>Subir Archivo</h4>
            <button class="modal-close" onclick="cerrarModalArchivo()">&times;</button>
        </div>
        <form method="POST" action="subir_archivo_clinico.php" enctype="multipart/form-data">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="form-group">
                <label>Tipo de Archivo *</label>
                <select name="tipo" class="form-control" required>
                    <option value="radiografia">Radiografía</option>
                    <option value="foto_intraoral">Foto Intraoral</option>
                    <option value="foto_extraoral">Foto Extraoral</option>
                    <option value="documento">Documento</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="form-group">
                <label>Archivo * <small style="color: #888;">(Solo PDF, JPG, PNG, GIF, WebP - Máx 5MB)</small></label>
                <input type="file" name="archivo" class="form-control" required accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <input type="text" name="descripcion" class="form-control" placeholder="Ej: Radiografía periapical diente 36">
            </div>
            <div class="form-group">
                <label>Fecha de Toma</label>
                <input type="date" name="fecha_toma" class="form-control" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalArchivo()">Cancelar</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-upload me-1"></i> Subir</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Renombrar/Editar Archivo -->
<div class="modal-overlay" id="modalRenombrarArchivo">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-pen me-2"></i>Renombrar / Editar Archivo</h4>
            <button class="modal-close" onclick="cerrarModalRenombrarArchivo()">&times;</button>
        </div>
        <form method="POST" action="renombrar_archivo_clinico.php">
            <input type="hidden" name="id" id="edit_archivo_id">
            <input type="hidden" name="cliente_id" value="<?php echo $clienteId; ?>">
            
            <div class="form-group">
                <label>Nombre / Descripción del Archivo *</label>
                <input type="text" name="descripcion" id="edit_archivo_descripcion" class="form-control" required placeholder="Ej: Radiografía muela 36">
            </div>

            <div class="form-group">
                <label>Tipo de Archivo</label>
                <select name="tipo" id="edit_archivo_tipo" class="form-control">
                    <option value="radiografia">Radiografía</option>
                    <option value="foto_intraoral">Foto Intraoral</option>
                    <option value="foto_extraoral">Foto Extraoral</option>
                    <option value="documento">Documento</option>
                    <option value="otro">Otro</option>
                </select>
            </div>

            <div class="form-group">
                <label>Fecha de Toma</label>
                <input type="date" name="fecha_toma" id="edit_archivo_fecha" class="form-control">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn" style="background: #6c757d; color: white;" onclick="cerrarModalRenombrarArchivo()">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
// Cambiar tabs
function cambiarTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    document.querySelector(`[onclick="cambiarTab('${tabId}')"]`).classList.add('active');
    document.getElementById('tab-' + tabId).classList.add('active');
}

// Modales
function abrirModalEvolucion() { document.getElementById('modalEvolucion').classList.add('show'); }
function cerrarModalEvolucion() { document.getElementById('modalEvolucion').classList.remove('show'); }
function abrirModalArchivo() { document.getElementById('modalArchivo').classList.add('show'); }
function cerrarModalArchivo() { document.getElementById('modalArchivo').classList.remove('show'); }
function abrirModalRenombrarArchivo(id, descripcion, tipo, fecha) {
    document.getElementById('edit_archivo_id').value = id;
    document.getElementById('edit_archivo_descripcion').value = descripcion;
    document.getElementById('edit_archivo_tipo').value = tipo || 'otro';
    document.getElementById('edit_archivo_fecha').value = fecha || '';
    document.getElementById('modalRenombrarArchivo').classList.add('show');
}
function cerrarModalRenombrarArchivo() {
    document.getElementById('modalRenombrarArchivo').classList.remove('show');
}
function eliminarArchivoClinico(id, clienteId) {
    if (confirm('¿Está seguro de eliminar este archivo clínico?\n\nEsta acción eliminará el archivo del servidor de forma permanente.')) {
        window.location.href = 'eliminar_archivo_clinico.php?id=' + id + '&cliente_id=' + clienteId;
    }
}

// Cerrar con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        cerrarModalEvolucion();
        cerrarModalArchivo();
        cerrarModalRenombrarArchivo();
        cerrarModalVerArchivo();
    }
});

// Cerrar al hacer clic fuera (fondo/overlay)
document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            if (this.id === 'modalVerArchivo') cerrarModalVerArchivo();
            else if (this.id === 'modalEvolucion') cerrarModalEvolucion();
            else if (this.id === 'modalArchivo') cerrarModalArchivo();
            else this.classList.remove('show');
        }
    });
});

// Validación de archivos
document.querySelector('input[name="archivo"]')?.addEventListener('change', function(e) {
    const file = this.files[0];
    if (!file) return;
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    const maxSize = 5 * 1024 * 1024; // 5MB
    
    const extension = file.name.split('.').pop().toLowerCase();
    let errorMsg = '';
    
    if (!allowedExtensions.includes(extension)) {
        errorMsg = '❌ Tipo de archivo no permitido.\n\nSolo se aceptan: PDF, JPG, PNG, GIF, WebP';
    } else if (file.size > maxSize) {
        const sizeMB = (file.size / 1024 / 1024).toFixed(2);
        errorMsg = `❌ El archivo es muy grande (${sizeMB} MB).\n\nEl tamaño máximo permitido es 5 MB`;
    }
    
    if (errorMsg) {
        alert(errorMsg);
        this.value = ''; // Limpiar el input
    }
});

// Ver Archivo Clínico en Modal (Diseño unificado con pagos.php)
function verArchivoClinico(ruta, extension) {
    const modal = document.getElementById('modalVerArchivo');
    const contenido = document.getElementById('contenidoVerArchivo');
    
    extension = extension.toLowerCase();
    
    if (extension === 'pdf') {
        contenido.innerHTML = `
            <iframe src="${ruta}" style="width: 100%; height: 75vh; border: none; border-radius: 8px;"></iframe>
            <div style="margin-top: 15px; text-align: center;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600;">
                    <i class="fas fa-download me-2"></i>Descargar PDF
                </a>
            </div>
        `;
    } else {
        contenido.innerHTML = `
            <img src="${ruta}" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);" alt="Archivo Clínico">
            <div style="margin-top: 15px; text-align: center;">
                <a href="${ruta}" download class="btn" style="background: var(--primary); color: white; padding: 10px 25px; border-radius: 8px; text-decoration: none; display: inline-block; font-weight: 600;">
                    <i class="fas fa-download me-2"></i>Descargar Imagen
                </a>
            </div>
        `;
    }
    
    modal.classList.add('show');
}

function cerrarModalVerArchivo() {
    document.getElementById('modalVerArchivo').classList.remove('show');
    setTimeout(() => {
        document.getElementById('contenidoVerArchivo').innerHTML = '';
    }, 300);
}

// Asegurar cierre al hacer clic fuera para todos los modales
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        const id = e.target.id;
        if (id === 'modalVerArchivo') cerrarModalVerArchivo();
        else if (id === 'modalEvolucion') cerrarModalEvolucion();
        else if (id === 'modalArchivo') cerrarModalArchivo();
        else e.target.classList.remove('show');
    }
});
</script>

<!-- Modal para visualizar archivos clínicos (Ancho ampliado a 1000px) -->
<div class="modal-overlay" id="modalVerArchivo">
    <div class="modal-box" style="max-width: 1000px; width: 95%; max-height: 95vh; padding: 0; overflow: hidden;">
        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 15px 20px; margin-bottom: 0;">
            <h5 style="margin: 0; font-weight: 600; font-size: 1.1rem;"><i class="fas fa-file-medical me-2"></i>Visualizador de Archivo</h5>
            <button class="modal-close" onclick="cerrarModalVerArchivo()" style="color: white; border: none; background: rgba(255,255,255,0.2); width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; opacity: 1; transition: 0.3s;">&times;</button>
        </div>
        <div id="contenidoVerArchivo" style="padding: 20px; overflow-y: auto; max-height: calc(95vh - 70px); text-align: center;">
            <!-- Contenido dinámico -->
        </div>
    </div>
</div>

</body>
</html>
