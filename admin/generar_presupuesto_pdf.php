<?php
/**
 * Generar PDF de Presupuesto
 * Versión simple sin librerías externas (HTML to PDF via navegador)
 */
date_default_timezone_set('America/La_Paz');
session_start();

if (!isset($_SESSION['user']) || !isset($_GET['id'])) {
    exit('Acceso denegado');
}

require_once '../src/config/db.php';
require_once '../src/models/Presupuesto.php';

$presupuestoModel = new Presupuesto($pdo);
$presupuesto = $presupuestoModel->getById($_GET['id']);

if (!$presupuesto) {
    exit('Presupuesto no encontrado');
}

// Configuración de la clínica
$clinica = [
    'nombre' => 'Dental Supremo',
    'direccion' => 'Sacaba, Cochabamba, Calle Bolivar.',
    'telefono' => '72752039',
    'email' => '',
    'nit' => ''
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto <?php echo $presupuesto['numero']; ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.5;
            color: #333;
            background: white;
        }
        
        .container { max-width: 210mm; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #003B73;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .logo-section h1 { 
            color: #003B73; 
            font-size: 28px; 
            font-weight: 700;
            margin-bottom: 5px;
        }
        .logo-section p { color: #666; font-size: 11px; margin: 2px 0; }
        .doc-info { text-align: right; min-width: 160px; }
        .doc-number { 
            background: linear-gradient(135deg, #003B73, #2998EC); 
            color: white; 
            padding: 12px 20px; 
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
            display: block;
            text-align: center;
            letter-spacing: 1px;
        }
        .doc-date { color: #666; font-size: 11px; line-height: 1.6; }
        
        /* Secciones */
        .section { margin-bottom: 25px; }
        .section-title { 
            font-size: 13px; 
            font-weight: 700; 
            color: #003B73;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        
        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-box { background: #f8f9fa; border-radius: 8px; padding: 15px; }
        .info-box h4 { color: #003B73; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; }
        .info-box p { margin: 3px 0; }
        .info-box strong { color: #333; }
        
        /* Tabla de items */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th { 
            background: #003B73; 
            color: white; 
            padding: 12px 10px; 
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
        }
        .items-table th.text-right { text-align: right; }
        .items-table td { 
            padding: 12px 10px; 
            border-bottom: 1px solid #eee;
        }
        .items-table td.text-right { text-align: right; }
        .items-table tr:nth-child(even) { background: #fafafa; }
        
        /* Totales */
        .totals { 
            margin-top: 20px; 
            display: flex; 
            justify-content: flex-end;
        }
        .totals-box { 
            width: 280px; 
            background: #f8f9fa; 
            border-radius: 8px; 
            padding: 15px;
        }
        .total-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 6px 0;
            font-size: 12px;
        }
        .total-row.final { 
            border-top: 2px solid #003B73; 
            margin-top: 10px; 
            padding-top: 12px;
            font-size: 16px;
            font-weight: 700;
            color: #003B73;
        }
        
        /* Notas */
        .notes { 
            background: #fff9e6; 
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 20px;
        }
        .notes h4 { color: #856404; font-size: 11px; margin-bottom: 8px; }
        .notes p { color: #666; font-size: 11px; }
        
        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #888;
            font-size: 10px;
        }
        .validity { 
            background: #e8f4fd; 
            border: 1px solid #b8daff;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            color: #004085;
            font-size: 11px;
        }
        
        /* Action Buttons */
        .action-buttons {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        .action-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        .action-btn:hover { transform: translateY(-2px); }
        .btn-print { background: #003B73; color: white; }
        .btn-print:hover { background: #2998EC; }
        .btn-pdf { background: #dc3545; color: white; }
        .btn-pdf:hover { background: #c82333; }
        .btn-back { background: #6c757d; color: white; text-decoration: none; }
        .btn-back:hover { background: #545b62; color: white; }
        
        @media print {
            .action-buttons { display: none; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }

        /* En móviles, botones estáticos arriba del contenido */
        @media (max-width: 768px) {
            .action-buttons {
                position: static;
                justify-content: center;
                flex-wrap: wrap;
                padding: 15px;
                background: linear-gradient(135deg, #f8f9fa, #e9ecef);
                border-radius: 0 0 12px 12px;
                margin-bottom: 20px;
            }
            .action-btn {
                padding: 10px 15px;
                font-size: 0.8rem;
            }
            .container { padding-top: 10px; }
        }
    </style>
</head>
<body>
    <div class="action-buttons">
        <a href="ver_presupuesto.php?id=<?php echo $presupuesto['id']; ?>" class="action-btn btn-back">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
        <button class="action-btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button class="action-btn btn-pdf" onclick="guardarPDF()">
            <i class="fas fa-file-pdf"></i> Guardar PDF
        </button>
    </div>

    <!-- Librería para generar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
    function guardarPDF() {
        // Ocultar temporalmente los botones para el PDF
        const buttons = document.querySelector('.action-buttons');
        buttons.style.display = 'none';
        
        // Nombre del archivo: codigo_cliente_fecha
        const nombreArchivo = '<?php 
            $nombreCliente = preg_replace('/[^a-zA-Z0-9]/', '_', $presupuesto['cliente_nombre']);
            $fecha = date('d-m-Y', strtotime($presupuesto['fecha']));
            echo $presupuesto['numero'] . '_' . $nombreCliente . '_' . $fecha;
        ?>.pdf';
        
        // Configuración del PDF
        const opciones = {
            margin: 10,
            filename: nombreArchivo,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        // Generar y descargar el PDF
        const elemento = document.querySelector('.container');
        
        html2pdf().set(opciones).from(elemento).save().then(() => {
            // Restaurar los botones después de generar
            buttons.style.display = 'flex';
        });
    }
    </script>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <img src="assets/images/logo_Dentality.png" alt="Dental Supremo" style="height: 55px; margin-bottom: 8px; border-radius: 5px;" onerror="this.style.display='none'">
                <h1><?php echo $clinica['nombre']; ?></h1>
                <p><?php echo $clinica['direccion']; ?></p>
                <p>Tel: <?php echo $clinica['telefono']; ?><?php echo !empty($clinica['email']) ? ' | '.$clinica['email'] : ''; ?></p>
                <?php if (!empty($clinica['nit'])): ?><p>NIT: <?php echo $clinica['nit']; ?></p><?php endif; ?>
            </div>
            <div class="doc-info">
                <div class="doc-number"><?php echo $presupuesto['numero']; ?></div>
                <div class="doc-date">
                    <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($presupuesto['fecha'])); ?><br>
                    <strong>Válido hasta:</strong> <?php echo date('d/m/Y', strtotime($presupuesto['fecha'] . ' +30 days')); ?>
                </div>
            </div>
        </div>

        <!-- Información -->
        <div class="section">
            <div class="info-grid">
                <div class="info-box">
                    <h4>Paciente</h4>
                    <p><strong><?php echo htmlspecialchars($presupuesto['cliente_nombre']); ?></strong></p>
                    <p>Tel: <?php echo htmlspecialchars($presupuesto['cliente_telefono']); ?></p>
                </div>
                <div class="info-box">
                    <h4>Atendido por</h4>
                    <p><strong><?php echo $presupuesto['doctor_nombre'] ?? 'Por asignar'; ?></strong></p>
                    <p>Estado: <?php echo ucfirst($presupuesto['estado']); ?></p>
                </div>
            </div>
        </div>

        <!-- Detalle de tratamientos -->
        <div class="section">
            <div class="section-title">Detalle de Tratamientos</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Tratamiento</th>
                        <th style="width: 10%;">Diente</th>
                        <th class="text-right" style="width: 10%;">Cant.</th>
                        <th class="text-right" style="width: 15%;">P. Unit.</th>
                        <th class="text-right" style="width: 15%;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presupuesto['items'] as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                        <td><?php echo $item['diente'] ?: '-'; ?></td>
                        <td class="text-right"><?php echo $item['cantidad']; ?></td>
                        <td class="text-right">Bs <?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td class="text-right"><strong>Bs <?php echo number_format($item['subtotal'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="totals">
                <div class="totals-box">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>Bs <?php echo number_format($presupuesto['subtotal'], 2); ?></span>
                    </div>
                    <?php if ($presupuesto['descuento_monto'] > 0): ?>
                    <div class="total-row" style="color: #28a745;">
                        <span>Descuento:</span>
                        <span>- Bs <?php echo number_format($presupuesto['descuento_monto'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="total-row final">
                        <span>TOTAL:</span>
                        <span>Bs <?php echo number_format($presupuesto['total'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notas -->
        <?php if (!empty($presupuesto['notas'])): ?>
        <div class="notes">
            <h4>Observaciones</h4>
            <p><?php echo nl2br(htmlspecialchars($presupuesto['notas'])); ?></p>
        </div>
        <?php endif; ?>

        <!-- Firma del Cliente -->
        <div style="margin-top: 60px; margin-bottom: 40px; display: flex; justify-content: flex-end;">
            <div style="width: 260px; text-align: center;">
                <div style="height: 60px;"></div> <!-- Espacio en blanco para la firma física -->
                <div style="border-top: 1.5px solid #333; padding-top: 6px;">
                    <strong style="font-size: 11px; color: #333; display: block; margin-bottom: 2px;">Firma del Paciente / Cliente</strong>
                    <span style="font-size: 11px; color: #666; font-style: italic;"><?php echo htmlspecialchars($presupuesto['cliente_nombre']); ?></span>
                </div>
            </div>
        </div>

        <!-- Validez -->
        <div class="validity">
            <strong>⏰ Validez:</strong> Este presupuesto tiene una validez de 30 días a partir de la fecha de emisión.
            Los precios están sujetos a cambios sin previo aviso después de dicho período.
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong><?php echo $clinica['nombre']; ?></strong> - Tu sonrisa es nuestra prioridad</p>
            <p>Documento generado el <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</body>
</html>
