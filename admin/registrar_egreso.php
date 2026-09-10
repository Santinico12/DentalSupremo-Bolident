<?php
/**
 * Registrar Egreso
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

require_once '../src/config/db.php';
require_once '../src/models/Caja.php';

$cajaModel = new Caja($pdo);
$mensaje = '';
$tipoMensaje = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $monto = floatval($_POST['monto']);
    $descripcion = trim($_POST['descripcion']);
    $realizadoPor = trim($_POST['realizado_por']);
    $fecha = $_POST['fecha'] . ' ' . date('H:i:s');

    if (empty($nombre) || $monto <= 0 || empty($realizadoPor)) {
        $mensaje = 'Por favor complete todos los campos requeridos';
        $tipoMensaje = 'danger';
    } else {
        $dataEgreso = [
            'nombre' => $nombre,
            'monto' => $monto,
            'descripcion' => $descripcion,
            'realizado_por' => $realizadoPor,
            'fecha' => $fecha
        ];

        $egresoId = $cajaModel->crearEgreso($dataEgreso);

        if ($egresoId) {
            // Subir comprobante si existe y no tiene error
            if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
                $compFile = $_FILES['comprobante'];
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
                $maxSize = 8 * 1024 * 1024; // 8MB
                
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

                // Si la extensión es PDF pero finfo devolvió octet-stream u otro MIME, verificar firma mágica %PDF
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
                    $_SESSION['message'] = 'Egreso registrado pero el comprobante supera los 10MB';
                    $_SESSION['message_type'] = 'warning';
                } elseif (!$esValido) {
                    $_SESSION['message'] = 'Egreso registrado pero el comprobante debe ser PDF o imagen válida';
                    $_SESSION['message_type'] = 'warning';
                } else {
                    // Guardar comprobante
                    $uploadDir = 'uploads/comprobantes/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $nombreArchivo = 'egreso_' . $egresoId . '_' . date('Ymd_His') . '.' . $ext;
                    $ruta = $uploadDir . $nombreArchivo;

                    if (move_uploaded_file($compFile['tmp_name'], $ruta)) {
                        $cajaModel->actualizarComprobanteEgreso($egresoId, $ruta, $ext);
                        $_SESSION['message'] = 'Egreso registrado con éxito';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Egreso registrado pero falló la subida del comprobante';
                        $_SESSION['message_type'] = 'warning';
                    }
                }
            } else {
                $_SESSION['message'] = 'Egreso registrado con éxito';
                $_SESSION['message_type'] = 'success';
            }
            header('Location: caja.php');
            exit();
        } else {
            $mensaje = 'Error al registrar el egreso en la base de datos';
            $tipoMensaje = 'danger';
        }
    }
}

require_once '../templates/header_general.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
    :root { --primary: #003B73; --primary-dark: #062846; --accent: #2998EC; }
    body { background: linear-gradient(135deg, #F4F9FD 0%, #E8F2FA 100%); }
    .page-container { max-width: 800px; margin: 0 auto; padding: 20px; }
    
    .page-header {
        background: linear-gradient(135deg, #dc3545, #bd2130);
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
    .text-danger { color: #dc3545 !important; }
    
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #555; }
    .form-group label .required { color: #dc3545; }
    .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e9ecef; border-radius: 10px; font-size: 1rem; transition: border-color 0.2s; }
    .form-control:focus { border-color: #dc3545; outline: none; }

    .btn-submit { background: #dc3545; color: white; border: none; padding: 15px 40px; border-radius: 10px; font-size: 1.1rem; font-weight: 700; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background 0.2s; }
    .btn-submit:hover { background: #bd2130; }

    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; }
    .alert-danger { background: #f8d7da; color: #721c24; }

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
        border-color: #dc3545;
        background: #fff5f5;
    }
    .dropzone.dragover {
        transform: scale(1.02);
    }
</style>

<div class="page-container">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipoMensaje; ?>">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1><i class="fas fa-minus-circle"></i> Registrar Egreso</h1>
        <a href="caja.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Volver a Caja
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" id="formEgreso">
        <!-- Datos Principales -->
        <div class="form-card" style="border-left: 4px solid #dc3545;">
            <h3><i class="fas fa-info-circle text-danger"></i> Detalles del Egreso</h3>
            
            <div class="form-group">
                <label>Concepto / Nombre del Egreso <span class="required">*</span></label>
                <input type="text" name="nombre" class="form-control" placeholder="Ej: Compra de insumos, Pago de energía eléctrica..." required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Monto (Bs) <span class="required">*</span></label>
                    <input type="number" name="monto" class="form-control" min="0.01" step="0.01" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Fecha <span class="required">*</span></label>
                    <input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Descripción / Detalles</label>
                <textarea name="descripcion" class="form-control" rows="3" placeholder="Información adicional del egreso..."></textarea>
            </div>
        </div>

        <!-- Quién Realiza / Comprobante -->
        <div class="form-card">
            <h3><i class="fas fa-user-check text-danger"></i> Responsable y Comprobante</h3>

            <div class="form-group">
                <label>¿Quién realizó el egreso? <span class="required">*</span></label>
                <input type="text" name="realizado_por" class="form-control" 
                       value="<?php echo htmlspecialchars($_SESSION['user']['username'] ?? ''); ?>" 
                       placeholder="Nombre de la persona o doctor" required>
            </div>

            <!-- Upload File con Drag & Drop -->
            <div class="form-group" style="margin-top: 20px;">
                <label>Comprobante de Egreso (Opcional)</label>
                <div class="dropzone" id="dropzone" onclick="document.getElementById('fileInput').click()">
                    <input type="file" name="comprobante" id="fileInput" style="display: none;" accept="image/*,application/pdf">
                    <div id="dropContent">
                        <i class="fas fa-cloud-upload-alt text-danger" style="font-size: 2.5rem; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-weight: 600;">Arrastra tu comprobante aquí o haz clic para buscar</p>
                        <span style="font-size: 0.8rem; color: #888;">Formatos permitidos: PDF, JPG, PNG, GIF, WebP (Máx. 8MB)</span>
                    </div>
                    
                    <!-- Vista previa -->
                    <div id="previewContainer" style="display: none; text-align: center;">
                        <img id="imagePreview" src="" style="max-width: 100%; max-height: 200px; border-radius: 8px; display: none; margin: 0 auto 10px auto;">
                        <div id="pdfPreview" style="display: none; padding: 15px; background: #fff5f5; border-radius: 8px; margin-bottom: 10px;">
                            <i class="fas fa-file-pdf text-danger" style="font-size: 2.5rem; margin-bottom: 8px;"></i>
                            <div id="pdfName" style="font-weight: 600; font-size: 0.9rem; word-break: break-all;"></div>
                        </div>
                        <button type="button" onclick="removeFile(event)" style="background: #dc3545; color: white; border: none; padding: 5px 15px; border-radius: 5px; cursor: pointer; font-size: 0.9rem;">
                            <i class="fas fa-times me-1"></i> Quitar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fas fa-check-circle"></i> Guardar Egreso
        </button>
    </form>
</div>

<script>
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
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
