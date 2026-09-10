<?php
/**
 * Ver Detalle de Pago
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header('Location: pagos.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Pago.php';

$pagoModel = new Pago($pdo);
$pago = $pagoModel->getById($id);

if (!$pago) {
    $_SESSION['message'] = 'Pago no encontrado';
    $_SESSION['message_type'] = 'danger';
    header('Location: pagos.php');
    exit();
}

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #6B1D49; --primary-dark: #531438; --accent: #C47D9F; }
    body { background: linear-gradient(135deg, #fdf8fa 0%, #f3e6ed 100%); }
    .page-container { max-width: 700px; margin: 0 auto; padding: 20px; }
    
    .page-header {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        border-radius: 16px;
        padding: 25px;
        color: white;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .page-header h1 { margin: 0; font-weight: 700; font-size: 1.4rem; }
    .btn-back { padding: 10px 20px; border-radius: 8px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; font-weight: 600; }

    .detail-card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 20px; }
    .detail-card h3 { margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
    
    .detail-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-weight: 600; color: #555; }
    .detail-value { text-align: right; }
    
    .monto-grande { font-size: 2rem; font-weight: 800; color: var(--primary-dark); text-align: center; padding: 20px; }
    
    .badge { padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
    .badge-efectivo { background: #d1ecf1; color: #0c5460; }
    .badge-qr { background: #e2d5f1; color: #6f42c1; }
    .badge-transferencia { background: #d4edda; color: #155724; }

    .comprobante-preview { text-align: center; margin-top: 20px; }
    .comprobante-preview img { max-width: 100%; max-height: 400px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .comprobante-preview .pdf-icon { font-size: 4rem; color: #dc3545; }
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-receipt me-2"></i>Detalle del Pago</h1>
        <a href="pagos.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i> Volver</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert" style="border-radius: 10px; font-size: 0.95rem; margin-bottom: 20px; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <i class="fas fa-<?php echo ($_SESSION['message_type'] ?? '') === 'warning' ? 'exclamation-triangle' : (($_SESSION['message_type'] ?? '') === 'danger' ? 'times-circle' : 'check-circle'); ?> me-2"></i>
                <?php echo htmlspecialchars($_SESSION['message']); ?>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" onclick="this.parentElement.remove();" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7;">&times;</button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="detail-card">
        <div class="monto-grande">
            Bs <?php echo number_format($pago['monto'], 2); ?>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Fecha</span>
            <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($pago['fecha_pago'])); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Método de Pago</span>
            <span class="detail-value">
                <span class="badge badge-<?php echo $pago['metodo_pago']; ?>">
                    <?php echo ucfirst($pago['metodo_pago']); ?>
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Cliente</span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['cliente_nombre']); ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Presupuesto</span>
            <span class="detail-value">
                <a href="ver_presupuesto.php?id=<?php echo $pago['presupuesto_id']; ?>">
                    <?php echo htmlspecialchars($pago['presupuesto_numero']); ?>
                </a>
            </span>
        </div>
        <?php if ($pago['pagador_nombre']): ?>
        <div class="detail-row">
            <span class="detail-label">Pagó: </span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['pagador_nombre']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pago['pagador_ci']): ?>
        <div class="detail-row">
            <span class="detail-label">CI de quien pagó: </span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['pagador_ci']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pago['referencia']): ?>
        <div class="detail-row">
            <span class="detail-label">Referencia: </span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['referencia']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pago['banco']): ?>
        <div class="detail-row">
            <span class="detail-label">Banco: </span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['banco']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($pago['notas']): ?>
        <div class="detail-row">
            <span class="detail-label">Notas: </span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['notas']); ?></span>
        </div>
        <?php endif; ?>
        <div class="detail-row">
            <span class="detail-label">Registrado por: </span>
            <span class="detail-value"><?php echo htmlspecialchars($pago['registrado_por'] ?? 'admin'); ?></span>
        </div>
    </div>

    <?php if ($pago['comprobante_ruta']): ?>
    <div class="detail-card">
        <h3><i class="fas fa-paperclip me-2"></i>Comprobante</h3>
        <div class="comprobante-preview">
            <?php if ($pago['comprobante_tipo'] === 'pdf'): ?>
            <!-- Visor PDF embebido -->
            <div style="margin-bottom: 10px;">
                <iframe src="<?php echo $pago['comprobante_ruta']; ?>" 
                        style="width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 8px;">
                </iframe>
            </div>
            <a href="<?php echo $pago['comprobante_ruta']; ?>" target="_blank" 
               style="display: inline-block; background: var(--primary); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;">
                <i class="fas fa-external-link-alt me-1"></i>Abrir en nueva pestaña
            </a>
            <?php else: ?>
            <a href="<?php echo $pago['comprobante_ruta']; ?>" target="_blank">
                <img src="<?php echo $pago['comprobante_ruta']; ?>" alt="Comprobante">
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

</body>
</html>

