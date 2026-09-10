<?php
/**
 * Registrar Nuevo Pago
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';
require_once '../src/models/Presupuesto.php';

$pagoModel = new Pago($pdo);
$presupuestoModel = new Presupuesto($pdo);

$mensaje = '';
$tipoMensaje = '';

// Obtener presupuesto preseleccionado si viene por parámetro
$presupuestoSeleccionado = null;
if (isset($_GET['presupuesto_id'])) {
    $presupuestoSeleccionado = $presupuestoModel->getById($_GET['presupuesto_id']);
}

// Obtener presupuestos aprobados con saldo pendiente
$presupuestosPendientes = $pagoModel->getPresupuestosPendientes();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $presupuestoId = intval($_POST['presupuesto_id']);
    $monto = floatval($_POST['monto']);
    $metodoPago = $_POST['metodo_pago'];
    
    // Validar
    if (!$presupuestoId || $monto <= 0) {
        $mensaje = 'Por favor complete todos los campos requeridos';
        $tipoMensaje = 'danger';
    } else {
        // Obtener datos del presupuesto
        $presupuesto = $presupuestoModel->getById($presupuestoId);
        if (!$presupuesto) {
            $mensaje = 'Presupuesto no encontrado';
            $tipoMensaje = 'danger';
        } else {
            $saldoPendiente = $pagoModel->getSaldoPendiente($presupuestoId);
            
            if ($monto > $saldoPendiente) {
                $mensaje = 'El monto excede el saldo pendiente (Bs ' . number_format($saldoPendiente, 2) . ')';
                $tipoMensaje = 'warning';
            } else {
                // Preparar datos del pago
                $dataPago = [
                    'presupuesto_id' => $presupuestoId,
                    'cliente_id' => $presupuesto['cliente_id'],
                    'monto' => $monto,
                    'metodo_pago' => $metodoPago,
                    'referencia' => $_POST['referencia'] ?? null,
                    'banco' => $_POST['banco'] ?? null,
                    'pagador_nombre' => $_POST['pagador_nombre'] ?? $presupuesto['cliente_nombre'],
                    'pagador_ci' => $_POST['pagador_ci'] ?? null,
                    'notas' => $_POST['notas'] ?? null,
                    'fecha_pago' => $_POST['fecha_pago'] . ' ' . date('H:i:s'),
                    'registrado_por' => $_SESSION['user']['username'] ?? 'admin'
                ];
                
                // Crear pago
                $pagoId = $pagoModel->crear($dataPago);
                
                if ($pagoId) {
                    // Subir comprobante si existe
                    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                        $compFile = $_FILES['comprobante'];
                        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                        $maxSize = 10 * 1024 * 1024; // 10MB
                        
                        $ext = strtolower(pathinfo($compFile['name'], PATHINFO_EXTENSION));
                        
                        $allowedTypes = [
                            'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp',
                            'application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf', 'text/x-pdf'
                        ];

                        $mimeType = '';
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            $mimeType = finfo_file($finfo, $compFile['tmp_name']);
                            finfo_close($finfo);
                        } elseif (function_exists('mime_content_type')) {
                            $mimeType = mime_content_type($compFile['tmp_name']);
                        }

                        $esValido = in_array($ext, $allowedExts) && in_array($mimeType, $allowedTypes);

                        // Si la extensión es PDF pero finfo devolvió octet-stream u otro MIME (común en bancos/scanners), verificar firma mágica %PDF
                        if (!$esValido && $ext === 'pdf') {
                            $h = @fopen($compFile['tmp_name'], 'rb');
                            if ($h) {
                                $bytes = fread($h, 1024);
                                fclose($h);
                                if (strpos($bytes, '%PDF') !== false) {
                                    $esValido = true;
                                }
                            }
                        }

                        // Si es imagen pero finfo falló, verificar con getimagesize
                        if (!$esValido && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                            if (@getimagesize($compFile['tmp_name']) !== false) {
                                $esValido = true;
                            }
                        }
                        
                        if ($compFile['size'] > $maxSize) {
                            $_SESSION['message'] = 'Pago registrado pero el comprobante supera los 10MB';
                            $_SESSION['message_type'] = 'warning';
                        } elseif (!$esValido) {
                            $_SESSION['message'] = 'Pago registrado pero el comprobante debe ser un archivo PDF o imagen válida';
                            $_SESSION['message_type'] = 'warning';
                        } else {
                            $subida = $pagoModel->subirComprobante($pagoId, $compFile);
                            if ($subida) {
                                $_SESSION['message'] = 'Pago de Bs ' . number_format($monto, 2) . ' y comprobante registrados correctamente';
                                $_SESSION['message_type'] = 'success';
                            } else {
                                $_SESSION['message'] = 'Pago de Bs ' . number_format($monto, 2) . ' registrado pero falló el guardado del archivo';
                                $_SESSION['message_type'] = 'warning';
                            }
                        }
                    } else {
                        $_SESSION['message'] = 'Pago de Bs ' . number_format($monto, 2) . ' registrado correctamente';
                        $_SESSION['message_type'] = 'success';
                    }

                    $redirectUrl = !empty($presupuestoId) ? "ver_presupuesto.php?id={$presupuestoId}" : "pagos.php";
                    header("Location: {$redirectUrl}");
                    exit();
                } else {
                    $mensaje = 'Error al registrar el pago';
                    $tipoMensaje = 'danger';
                }
            }
        }
    }
}

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #6B1D49; --primary-dark: #531438; --accent: #C47D9F; }
    body { background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%); }
    .page-container { max-width: 800px; margin: 0 auto; padding: 20px; }
    
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
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.4rem; display: flex; align-items: center; gap: 12px; }
    .btn-back { padding: 10px 20px; border-radius: 8px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px; }

    .form-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .form-card h3 { margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .text-success { color: var(--primary) !important; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #555; }
    .form-group label .required { color: #dc3545; }
    .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s; }
    .form-control:focus { border-color: var(--primary); outline: none; }
    
    .presupuesto-selector { position: relative; }
    .presupuesto-option { padding: 15px; border: 2px solid #e9ecef; border-radius: 10px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s; }
    .presupuesto-option:hover { border-color: var(--primary); background: #fff8f0; }
    .presupuesto-option.selected { border-color: var(--primary); background: #fff8f0; }
    .presupuesto-option h4 { margin: 0 0 5px 0; font-size: 1rem; }
    .presupuesto-option .info { font-size: 0.85rem; color: #666; }
    .presupuesto-option .saldo { font-weight: 700; color: var(--primary-dark); float: right; }

    .metodo-group { display: flex; gap: 10px; flex-wrap: wrap; }
    .metodo-option { flex: 1; min-width: 100px; }
    .metodo-option input { display: none; }
    .metodo-option label { display: block; padding: 15px; border: 2px solid #e9ecef; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.2s; }
    .metodo-option label i { font-size: 1.5rem; display: block; margin-bottom: 8px; }
    .metodo-option input:checked + label { border-color: var(--primary); background: #fff8f0; color: var(--primary-dark); }
    
    .comprobante-section { display: none; padding: 20px; background: #f8f9fa; border-radius: 10px; margin-top: 15px; }
    .comprobante-section.show { display: block; }

    .btn-submit { background: var(--primary); color: white; border: none; padding: 15px 40px; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .btn-submit:hover { background: var(--primary-dark); }

    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    .alert-danger { background: #f8d7da; color: #721c24; }
    .alert-warning { background: #fff3cd; color: #856404; }

    /* Botones de monto rápido */
    .monto-rapido-btn {
        flex: 1;
        min-width: 120px;
        padding: 14px 16px;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        background: white;
        cursor: pointer;
        transition: all 0.25s ease;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        text-align: center;
    }
    .monto-rapido-btn i {
        font-size: 1.3rem;
        color: #999;
        transition: color 0.25s;
    }
    .monto-rapido-btn span {
        font-size: 0.78rem;
        color: #888;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .monto-rapido-btn strong {
        font-size: 0.95rem;
        color: #555;
        transition: color 0.25s;
    }
    .monto-rapido-btn:hover {
        border-color: var(--primary);
        background: #fff8f0;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(196, 162, 126, 0.2);
    }
    .monto-rapido-btn.active {
        border-color: var(--primary);
        background: linear-gradient(135deg, #fff8f0, #fef3e8);
        box-shadow: 0 4px 15px rgba(196, 162, 126, 0.25);
    }
    .monto-rapido-btn.active i { color: var(--primary-dark); }
    .monto-rapido-btn.active strong { color: var(--primary-dark); }
    .monto-rapido-btn.active span { color: var(--primary-dark); }

    /* Input de monto con estado de error */
    .form-control.error { border-color: #dc3545 !important; background: #fff5f5; }
    .form-control.valid { border-color: #28a745 !important; }

    @media (max-width: 480px) {
        .monto-rapido-btn { min-width: 90px; padding: 10px 8px; }
        .monto-rapido-btn i { font-size: 1rem; }
        .monto-rapido-btn strong { font-size: 0.85rem; }
    }
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Registrar Pago</h1>
        <a href="pagos.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert" style="border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px;">
        <?php echo htmlspecialchars($_SESSION['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if ($mensaje): ?>
    <div class="alert alert-<?php echo $tipoMensaje; ?>" style="border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensaje; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="formPago">
        <?php if ($presupuestoSeleccionado): 
            $saldoPendiente = $pagoModel->getSaldoPendiente($presupuestoSeleccionado['id']);
            $totalPagadoPrev = $pagoModel->getTotalPagado($presupuestoSeleccionado['id']);
            $totalPresupuesto = floatval($presupuestoSeleccionado['total']);
            $porcentajePagado = $totalPresupuesto > 0 ? ($totalPagadoPrev / $totalPresupuesto) * 100 : 0;
            $pagosAnteriores = $pagoModel->getByPresupuesto($presupuestoSeleccionado['id']);
        ?>
        <!-- Info del Presupuesto Seleccionado -->
        <div class="form-card" style="border-left: 4px solid #28a745;">
            <h3><i class="fas fa-file-invoice-dollar text-success"></i> Presupuesto a Pagar</h3>
            <input type="hidden" name="presupuesto_id" value="<?php echo $presupuestoSeleccionado['id']; ?>">
            
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <h4 style="margin: 0 0 5px 0; font-size: 1.2rem;"><?php echo htmlspecialchars($presupuestoSeleccionado['cliente_nombre']); ?></h4>
                    <div style="color: #666;">
                        <i class="fas fa-file me-1"></i><?php echo $presupuestoSeleccionado['numero']; ?> | 
                        Total: Bs <?php echo number_format($presupuestoSeleccionado['total'], 2); ?>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.5rem; font-weight: 800; color: <?php echo $totalPagadoPrev > 0 ? '#dc3545' : '#28a745'; ?>;">Bs <?php echo number_format($saldoPendiente, 2); ?></div>
                    <div style="font-size: 0.85rem; color: #666;">Saldo Pendiente</div>
                </div>
            </div>

            <!-- Barra de progreso de pagos -->
            <?php if ($totalPagadoPrev > 0): ?>
            <div style="margin-top: 18px; padding-top: 18px; border-top: 1px solid #e9ecef;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.85rem; color: #666;"><i class="fas fa-chart-bar me-1"></i>Progreso de pago</span>
                    <span style="font-size: 0.85rem; font-weight: 700; color: #28a745;"><?php echo number_format($porcentajePagado, 0); ?>%</span>
                </div>
                <div style="width: 100%; height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                    <div style="height: 100%; width: <?php echo min(100, $porcentajePagado); ?>%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 5px; transition: width 0.5s;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 6px; font-size: 0.8rem; color: #888;">
                    <span>Pagado: Bs <?php echo number_format($totalPagadoPrev, 2); ?></span>
                    <span>Total: Bs <?php echo number_format($totalPresupuesto, 2); ?></span>
                </div>
            </div>

            <!-- Historial de pagos anteriores -->
            <div style="margin-top: 15px;">
                <div style="font-size: 0.82rem; font-weight: 600; color: #888; margin-bottom: 8px;">
                    <i class="fas fa-history me-1"></i>Pagos anteriores (<?php echo count($pagosAnteriores); ?>)
                </div>
                <?php foreach ($pagosAnteriores as $pagoAnt): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #f0fdf4; border-radius: 8px; margin-bottom: 5px; font-size: 0.85rem;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-check-circle" style="color: #28a745; font-size: 0.75rem;"></i>
                        <span style="color: #666;"><?php echo date('d/m/Y', strtotime($pagoAnt['fecha_pago'])); ?></span>
                        <span style="background: #e2e8f0; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; text-transform: uppercase; font-weight: 600;"><?php echo ucfirst($pagoAnt['metodo_pago']); ?></span>
                    </div>
                    <span style="font-weight: 700; color: #28a745;">Bs <?php echo number_format($pagoAnt['monto'], 2); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Monto a Pagar -->
        <div class="form-card" style="border-left: 4px solid var(--primary);">
            <h3><i class="fas fa-coins text-success"></i> Monto a Pagar</h3>
            
            <!-- Botones de montos rápidos -->
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                <button type="button" class="monto-rapido-btn active" onclick="setMontoRapido(<?php echo $saldoPendiente; ?>, this)" data-tipo="total">
                    <i class="fas fa-check-double"></i>
                    <span>Pagar Total</span>
                    <strong>Bs <?php echo number_format($saldoPendiente, 2); ?></strong>
                </button>
                <?php if ($saldoPendiente > 1): ?>
                <button type="button" class="monto-rapido-btn" onclick="setMontoRapido(<?php echo round($saldoPendiente / 2, 2); ?>, this)" data-tipo="mitad">
                    <i class="fas fa-divide"></i>
                    <span>50%</span>
                    <strong>Bs <?php echo number_format($saldoPendiente / 2, 2); ?></strong>
                </button>
                <?php endif; ?>
                <button type="button" class="monto-rapido-btn" onclick="activarMontoPersonalizado(this)" data-tipo="custom">
                    <i class="fas fa-edit"></i>
                    <span>Personalizado</span>
                    <strong>Ingrese monto</strong>
                </button>
            </div>

            <div class="form-group" id="montoInputGroup">
                <label>Monto del pago (Bs) <span class="required">*</span></label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-weight: 700; color: #888; font-size: 1.1rem;">Bs</span>
                    <input type="number" name="monto" id="montoInput" class="form-control" 
                           value="<?php echo $saldoPendiente; ?>" 
                           min="0.01" 
                           max="<?php echo $saldoPendiente; ?>" 
                           step="0.01"
                           required
                           oninput="validarMonto()"
                           style="padding-left: 45px; font-size: 1.3rem; font-weight: 700; text-align: right;">
                </div>
                <div id="montoFeedback" style="margin-top: 8px; font-size: 0.85rem; transition: all 0.2s;"></div>
            </div>

            <!-- Preview del resultado después del pago -->
            <div id="pagoPreview" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 12px; padding: 16px; margin-top: 15px; border: 1px solid #bbf7d0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.85rem; color: #666;"><i class="fas fa-calculator me-1"></i>Después de este pago:</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-size: 0.8rem; color: #888;">Saldo restante</div>
                        <div id="saldoRestante" style="font-size: 1.3rem; font-weight: 800; color: #28a745;">Bs 0.00</div>
                    </div>
                    <div id="estadoPreview">
                        <span style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                            <i class="fas fa-check-circle me-1"></i>Quedará PAGADO
                        </span>
                    </div>
                </div>
                <!-- Barra de progreso proyectada -->
                <div style="margin-top: 12px;">
                    <div style="width: 100%; height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden;">
                        <div id="progressPreview" style="height: 100%; width: 100%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 4px; transition: width 0.3s;"></div>
                    </div>
                    <div style="text-align: right; margin-top: 4px;">
                        <span id="progressPercent" style="font-size: 0.78rem; font-weight: 700; color: #28a745;">100%</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif (empty($presupuestosPendientes)): ?>
        <div class="form-card">
            <div style="text-align: center; padding: 30px; color: #888;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745; margin-bottom: 15px;"></i>
                <h4>No hay presupuestos pendientes de pago</h4>
                <p>Todos los presupuestos aprobados están completamente pagados</p>
                <a href="pagos.php" class="btn-back" style="display: inline-block; margin-top: 15px;">
                    <i class="fas fa-arrow-left"></i> Volver a Pagos
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="form-card">
            <div style="text-align: center; padding: 30px; color: #888;">
                <i class="fas fa-hand-pointer" style="font-size: 3rem; color: #ffc107; margin-bottom: 15px;"></i>
                <h4>Seleccione un presupuesto pendiente</h4>
                <p>Vaya a la pestaña "Pendientes" y haga clic en "Registrar Pago"</p>
                <a href="pagos.php#pendientes" class="btn-back" style="display: inline-block; margin-top: 15px; background: #28a745; color: white;">
                    <i class="fas fa-clock"></i> Ver Pendientes
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($presupuestoSeleccionado): ?>
        <!-- Método de Pago -->
        <div class="form-card">
            <h3><i class="fas fa-money-bill-wave text-success"></i> Método de Pago</h3>
            
            <div class="form-group">
                <label>Fecha del Pago <span class="required">*</span></label>
                <input type="date" name="fecha_pago" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label>Método de Pago <span class="required">*</span></label>
                <div class="metodo-group">
                    <div class="metodo-option">
                        <input type="radio" name="metodo_pago" id="metodo_efectivo" value="efectivo" checked onchange="toggleComprobante()">
                        <label for="metodo_efectivo">
                            <i class="fas fa-money-bill-wave"></i>
                            Efectivo
                        </label>
                    </div>
                    <div class="metodo-option">
                        <input type="radio" name="metodo_pago" id="metodo_qr" value="qr" onchange="toggleComprobante()">
                        <label for="metodo_qr">
                            <i class="fas fa-qrcode"></i>
                            QR
                        </label>
                    </div>
                    <div class="metodo-option">
                        <input type="radio" name="metodo_pago" id="metodo_transferencia" value="transferencia" onchange="toggleComprobante()">
                        <label for="metodo_transferencia">
                            <i class="fas fa-exchange-alt"></i>
                            Transferencia o Tarjeta
                        </label>
                    </div>
                </div>
            </div>

            <!-- Sección comprobante (QR/Transferencia) -->
            <div class="comprobante-section" id="comprobanteSection">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Número de Referencia</label>
                        <input type="text" name="referencia" class="form-control" placeholder="Ej: 123456789">
                    </div>
                    <div class="form-group">
                        <label>Banco</label>
                        <select name="banco" class="form-control">
                            <option value="">Seleccionar...</option>
                            <option value="BNB">Banco Nacional de Bolivia</option>
                            <option value="Mercantil">Banco Mercantil Santa Cruz</option>
                            <option value="BCP">Banco de Crédito</option>
                            <option value="Union">Banco Unión</option>
                            <option value="Economico">Banco Económico</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-paperclip me-1"></i> Comprobante (imagen o PDF)</label>
                    <div class="dropzone" id="dropzone" onclick="document.getElementById('fileInput').click();">
                        <input type="file" name="comprobante" id="fileInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,image/jpeg,image/png,image/gif,image/webp,application/pdf" style="display: none;">
                        <div id="dropContent">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                            <p style="margin: 0; font-weight: 600;">Arrastra tu archivo aquí</p>
                            <p style="margin: 5px 0 0; font-size: 0.85rem; color: #888;">o haz clic para seleccionar</p>
                            <small style="color: #aaa;">JPG, PNG, PDF (máx. 8MB)</small>
                        </div>
                        <div id="previewContainer" style="display: none;">
                            <img id="imagePreview" style="max-width: 100%; max-height: 150px; border-radius: 8px;">
                            <div id="pdfPreview" style="display: none;">
                                <i class="fas fa-file-pdf" style="font-size: 3rem; color: #dc3545;"></i>
                                <p id="pdfName" style="margin: 10px 0 0; font-weight: 600;"></p>
                            </div>
                            <button type="button" onclick="removeFile(event)" style="margin-top: 10px; background: #dc3545; color: white; border: none; padding: 5px 15px; border-radius: 5px; cursor: pointer;">
                                <i class="fas fa-times"></i> Quitar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .dropzone {
                border: 2px dashed #ccc;
                border-radius: 10px;
                padding: 25px;
                text-align: center;
                transition: all 0.3s;
                cursor: pointer;
                background: #fafafa;
            }
            .dropzone:hover, .dropzone.dragover {
                border-color: var(--primary);
                background: #fff8f0;
            }
            .dropzone.dragover {
                transform: scale(1.02);
            }
        </style>

        <!-- Datos del Pagador -->
        <div class="form-card">
            <h3><i class="fas fa-user text-success"></i> Datos del Pagador (Opcional)</h3>
            <p style="color: #888; margin-bottom: 15px;">Completar si el pago lo realiza otra persona diferente al paciente</p>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Nombre de quien paga</label>
                    <input type="text" name="pagador_nombre" id="pagadorNombre" class="form-control" 
                           placeholder="Nombre completo">
                </div>
                <div class="form-group">
                    <label>CI del pagador</label>
                    <input type="text" name="pagador_ci" class="form-control" placeholder="Ej: 12345678">
                </div>
            </div>
            <div class="form-group">
                <label>Notas adicionales</label>
                <textarea name="notas" class="form-control" rows="2" placeholder="Observaciones del pago..."></textarea>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fas fa-check-circle"></i> Registrar Pago
        </button>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleComprobante() {
    const metodo = document.querySelector('input[name="metodo_pago"]:checked').value;
    const section = document.getElementById('comprobanteSection');
    
    if (metodo === 'qr' || metodo === 'transferencia') {
        section.classList.add('show');
    } else {
        section.classList.remove('show');
    }
}

// Drag and Drop functionality
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const dropContent = document.getElementById('dropContent');
const previewContainer = document.getElementById('previewContainer');
const imagePreview = document.getElementById('imagePreview');
const pdfPreview = document.getElementById('pdfPreview');
const pdfName = document.getElementById('pdfName');

if (dropzone) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'));
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'));
    });
    
    dropzone.addEventListener('drop', handleDrop);
    fileInput.addEventListener('change', handleFiles);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        handleFiles({ target: { files: files } });
    }
    
    function handleFiles(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validación de tipo y tamaño
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        const maxSize = 8 * 1024 * 1024; // 8MB
        const extension = file.name.split('.').pop().toLowerCase();
        
        if (!allowedExtensions.includes(extension)) {
            alert('❌ Tipo de archivo no permitido.\n\nSolo se aceptan: PDF, JPG, PNG, GIF, WebP');
            fileInput.value = '';
            return;
        }
        
        if (file.size > maxSize) {
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            alert(`❌ El archivo es muy grande (${sizeMB} MB).\n\nEl tamaño máximo permitido es 8 MB`);
            fileInput.value = '';
            return;
        }
        
        dropContent.style.display = 'none';
        previewContainer.style.display = 'block';
        
        if (file.type.startsWith('image/') || ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                pdfPreview.style.display = 'none';
            };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf' || extension === 'pdf') {
            imagePreview.style.display = 'none';
            pdfPreview.style.display = 'block';
            pdfName.textContent = file.name;
        }
    }
}

function removeFile(e) {
    e.stopPropagation();
    fileInput.value = '';
    dropContent.style.display = 'block';
    previewContainer.style.display = 'none';
    imagePreview.src = '';
    pdfName.textContent = '';
}

// ========== PAGOS PARCIALES ==========
<?php if ($presupuestoSeleccionado): ?>
const SALDO_PENDIENTE = <?php echo $saldoPendiente; ?>;
const TOTAL_PRESUPUESTO = <?php echo $totalPresupuesto; ?>;
const TOTAL_PAGADO_PREV = <?php echo $totalPagadoPrev; ?>;

function setMontoRapido(monto, btn) {
    const input = document.getElementById('montoInput');
    if (!input) return;
    
    // Actualizar botones activos
    document.querySelectorAll('.monto-rapido-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Establecer monto
    input.value = monto;
    validarMonto();
}

function activarMontoPersonalizado(btn) {
    const input = document.getElementById('montoInput');
    if (!input) return;
    
    // Actualizar botones activos
    document.querySelectorAll('.monto-rapido-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    // Limpiar input y enfocar
    input.value = '';
    input.focus();
    input.select();
    validarMonto();
}

function validarMonto() {
    const input = document.getElementById('montoInput');
    const feedback = document.getElementById('montoFeedback');
    const saldoRestanteEl = document.getElementById('saldoRestante');
    const estadoPreview = document.getElementById('estadoPreview');
    const progressPreview = document.getElementById('progressPreview');
    const progressPercent = document.getElementById('progressPercent');
    const pagoPreview = document.getElementById('pagoPreview');
    const submitBtn = document.querySelector('.btn-submit');
    
    if (!input || !feedback) return;
    
    const monto = parseFloat(input.value) || 0;
    
    // Validación
    if (monto <= 0) {
        input.classList.remove('valid');
        input.classList.add('error');
        feedback.innerHTML = '<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> <span style="color: #dc3545;">Ingrese un monto mayor a 0</span>';
        if (submitBtn) submitBtn.disabled = true;
        updatePreview(0);
        return;
    }
    
    if (monto > SALDO_PENDIENTE) {
        input.classList.remove('valid');
        input.classList.add('error');
        feedback.innerHTML = `<i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i> <span style="color: #dc3545;">El monto excede el saldo pendiente (Bs ${SALDO_PENDIENTE.toFixed(2)})</span>`;
        if (submitBtn) submitBtn.disabled = true;
        updatePreview(monto);
        return;
    }
    
    // Válido
    input.classList.remove('error');
    input.classList.add('valid');
    if (submitBtn) submitBtn.disabled = false;
    
    const saldoDespues = SALDO_PENDIENTE - monto;
    
    if (saldoDespues === 0) {
        feedback.innerHTML = '<i class="fas fa-check-circle" style="color: #28a745;"></i> <span style="color: #28a745;">Pago completo — se saldará todo el presupuesto</span>';
    } else {
        feedback.innerHTML = `<i class="fas fa-info-circle" style="color: #17a2b8;"></i> <span style="color: #17a2b8;">Pago parcial — quedarán Bs ${saldoDespues.toFixed(2)} pendientes</span>`;
    }
    
    updatePreview(monto);
    
    // Sincronizar botones rápidos
    syncBotones(monto);
}

function updatePreview(monto) {
    const saldoRestanteEl = document.getElementById('saldoRestante');
    const estadoPreview = document.getElementById('estadoPreview');
    const progressPreview = document.getElementById('progressPreview');
    const progressPercent = document.getElementById('progressPercent');
    const pagoPreview = document.getElementById('pagoPreview');
    
    if (!saldoRestanteEl) return;
    
    const saldoDespues = Math.max(0, SALDO_PENDIENTE - monto);
    const totalPagadoProyectado = TOTAL_PAGADO_PREV + monto;
    const porcentajeProyectado = TOTAL_PRESUPUESTO > 0 ? Math.min(100, (totalPagadoProyectado / TOTAL_PRESUPUESTO) * 100) : 0;
    
    saldoRestanteEl.textContent = `Bs ${saldoDespues.toFixed(2)}`;
    
    if (monto > SALDO_PENDIENTE) {
        // Excedido
        pagoPreview.style.background = 'linear-gradient(135deg, #fff5f5, #ffe0e0)';
        pagoPreview.style.borderColor = '#fca5a5';
        saldoRestanteEl.style.color = '#dc3545';
        estadoPreview.innerHTML = `
            <span style="background: #dc3545; color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                <i class="fas fa-exclamation-circle me-1"></i>Monto excedido
            </span>`;
    } else if (saldoDespues === 0) {
        // Pagado completo
        pagoPreview.style.background = 'linear-gradient(135deg, #f0fdf4, #dcfce7)';
        pagoPreview.style.borderColor = '#bbf7d0';
        saldoRestanteEl.style.color = '#28a745';
        estadoPreview.innerHTML = `
            <span style="background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                <i class="fas fa-check-circle me-1"></i>Quedará PAGADO
            </span>`;
    } else if (monto > 0) {
        // Pago parcial
        pagoPreview.style.background = 'linear-gradient(135deg, #fffbeb, #fef3c7)';
        pagoPreview.style.borderColor = '#fde68a';
        saldoRestanteEl.style.color = '#d97706';
        estadoPreview.innerHTML = `
            <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                <i class="fas fa-clock me-1"></i>Pago parcial
            </span>`;
    } else {
        // Sin monto
        pagoPreview.style.background = 'linear-gradient(135deg, #f8f9fa, #e9ecef)';
        pagoPreview.style.borderColor = '#dee2e6';
        saldoRestanteEl.style.color = '#888';
        estadoPreview.innerHTML = `
            <span style="background: #6c757d; color: white; padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700;">
                <i class="fas fa-minus-circle me-1"></i>Sin monto
            </span>`;
    }
    
    if (progressPreview) {
        progressPreview.style.width = `${porcentajeProyectado}%`;
        if (porcentajeProyectado >= 100) {
            progressPreview.style.background = 'linear-gradient(90deg, #28a745, #20c997)';
        } else {
            progressPreview.style.background = 'linear-gradient(90deg, #f59e0b, #d97706)';
        }
    }
    if (progressPercent) {
        progressPercent.textContent = `${porcentajeProyectado.toFixed(0)}%`;
        progressPercent.style.color = porcentajeProyectado >= 100 ? '#28a745' : '#d97706';
    }
}

function syncBotones(monto) {
    const btns = document.querySelectorAll('.monto-rapido-btn');
    let matched = false;
    
    btns.forEach(btn => {
        const tipo = btn.dataset.tipo;
        if (tipo === 'total' && Math.abs(monto - SALDO_PENDIENTE) < 0.01) {
            btn.classList.add('active');
            matched = true;
        } else if (tipo === 'mitad' && Math.abs(monto - (SALDO_PENDIENTE / 2)) < 0.01) {
            btn.classList.add('active');
            matched = true;
        } else if (tipo !== 'custom') {
            btn.classList.remove('active');
        }
    });
    
    // Si no coincide con ningún preset, marcar "Personalizado"
    if (!matched) {
        btns.forEach(btn => {
            if (btn.dataset.tipo === 'custom') {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
}

// Validar formulario antes de enviar
document.getElementById('formPago')?.addEventListener('submit', function(e) {
    const input = document.getElementById('montoInput');
    if (!input) return;
    
    const monto = parseFloat(input.value) || 0;
    
    if (monto <= 0) {
        e.preventDefault();
        alert('❌ Ingrese un monto válido mayor a 0');
        input.focus();
        return;
    }
    
    if (monto > SALDO_PENDIENTE) {
        e.preventDefault();
        alert(`❌ El monto (Bs ${monto.toFixed(2)}) excede el saldo pendiente (Bs ${SALDO_PENDIENTE.toFixed(2)})`);
        input.focus();
        return;
    }
    
    // Confirmar pago parcial
    if (monto < SALDO_PENDIENTE) {
        const saldoRestante = (SALDO_PENDIENTE - monto).toFixed(2);
        if (!confirm(`¿Confirmar pago parcial de Bs ${monto.toFixed(2)}?\n\nQuedarán Bs ${saldoRestante} pendientes.`)) {
            e.preventDefault();
        }
    }
});

// Inicializar preview al cargar
document.addEventListener('DOMContentLoaded', function() {
    validarMonto();
});
<?php endif; ?>
</script>

</body>
</html>

