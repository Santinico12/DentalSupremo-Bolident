<?php
/**
 * Modelo Pago
 * Gestiona los pagos de presupuestos
 */
class Pago {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener todos los pagos con información de cliente y presupuesto
     */
    public function getAll($limite = 100) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                       pr.numero as presupuesto_numero, pr.total as presupuesto_total
                FROM pagos p
                JOIN clientes c ON p.cliente_id = c.id
                JOIN presupuestos pr ON p.presupuesto_id = pr.id
                ORDER BY p.fecha_pago DESC
                LIMIT :limite";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pagos por presupuesto
     */
    public function getByPresupuesto($presupuestoId) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre
                FROM pagos p
                JOIN clientes c ON p.cliente_id = c.id
                WHERE p.presupuesto_id = :id
                ORDER BY p.fecha_pago ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $presupuestoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener pago por ID
     */
    public function getById($id) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                       pr.numero as presupuesto_numero, pr.total as presupuesto_total
                FROM pagos p
                JOIN clientes c ON p.cliente_id = c.id
                JOIN presupuestos pr ON p.presupuesto_id = pr.id
                WHERE p.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Registrar nuevo pago
     */
    public function crear($data) {
        $sql = "INSERT INTO pagos (
                    presupuesto_id, cliente_id, monto, metodo_pago, referencia, banco,
                    comprobante_ruta, comprobante_tipo, pagador_nombre, pagador_ci,
                    notas, fecha_pago, registrado_por
                ) VALUES (
                    :presupuesto_id, :cliente_id, :monto, :metodo_pago, :referencia, :banco,
                    :comprobante_ruta, :comprobante_tipo, :pagador_nombre, :pagador_ci,
                    :notas, :fecha_pago, :registrado_por
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':presupuesto_id' => $data['presupuesto_id'],
            ':cliente_id' => $data['cliente_id'],
            ':monto' => $data['monto'],
            ':metodo_pago' => $data['metodo_pago'],
            ':referencia' => $data['referencia'] ?? null,
            ':banco' => $data['banco'] ?? null,
            ':comprobante_ruta' => $data['comprobante_ruta'] ?? null,
            ':comprobante_tipo' => $data['comprobante_tipo'] ?? null,
            ':pagador_nombre' => $data['pagador_nombre'] ?? null,
            ':pagador_ci' => $data['pagador_ci'] ?? null,
            ':notas' => $data['notas'] ?? null,
            ':fecha_pago' => $data['fecha_pago'] ?? date('Y-m-d H:i:s'),
            ':registrado_por' => $data['registrado_por'] ?? null
        ]);
        
        $pagoId = $this->pdo->lastInsertId();
        
        // Actualizar monto pagado en presupuesto
        $this->actualizarMontoPagado($data['presupuesto_id']);
        
        return $pagoId;
    }

    /**
     * Actualizar monto pagado en presupuesto
     */
    private function actualizarMontoPagado($presupuestoId) {
        // Primero calcular el total pagado
        $sqlSum = "SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE presupuesto_id = :id";
        $stmtSum = $this->pdo->prepare($sqlSum);
        $stmtSum->execute([':id' => $presupuestoId]);
        $totalPagado = $stmtSum->fetchColumn();
        
        // Actualizar monto_pagado en presupuesto
        $sqlUpdate = "UPDATE presupuestos SET monto_pagado = :total WHERE id = :id";
        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([':total' => $totalPagado, ':id' => $presupuestoId]);
        
        // Obtener el total del presupuesto
        $sqlTotal = "SELECT total FROM presupuestos WHERE id = :id";
        $stmtTotal = $this->pdo->prepare($sqlTotal);
        $stmtTotal->execute([':id' => $presupuestoId]);
        $totalPresupuesto = floatval($stmtTotal->fetchColumn());
        
        // Determinar el estado según el monto pagado
        if ($totalPagado >= $totalPresupuesto) {
            // Completamente pagado
            $nuevoEstado = 'pagado';
        } else if ($totalPagado > 0) {
            // Pago parcial - marcar como aprobado
            $nuevoEstado = 'aprobado';
        } else {
            // Sin pagos, no cambiar
            return;
        }
        
        // Actualizar estado del presupuesto
        $sqlEstado = "UPDATE presupuestos SET estado = :estado WHERE id = :id";
        $stmtEstado = $this->pdo->prepare($sqlEstado);
        $stmtEstado->execute([':estado' => $nuevoEstado, ':id' => $presupuestoId]);
    }

    /**
     * Eliminar pago
     */
    public function eliminar($id) {
        // Obtener presupuesto_id antes de eliminar
        $pago = $this->getById($id);
        if (!$pago) return false;
        
        // Eliminar comprobante si existe
        if ($pago['comprobante_ruta'] && file_exists($pago['comprobante_ruta'])) {
            unlink($pago['comprobante_ruta']);
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM pagos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Actualizar monto pagado
        $this->actualizarMontoPagado($pago['presupuesto_id']);
        
        return true;
    }

    /**
     * Obtener total pagado de un presupuesto
     */
    public function getTotalPagado($presupuestoId) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE presupuesto_id = :id");
        $stmt->execute([':id' => $presupuestoId]);
        return floatval($stmt->fetchColumn());
    }

    /**
     * Obtener saldo pendiente de un presupuesto
     */
    public function getSaldoPendiente($presupuestoId) {
        $sql = "SELECT (pr.total - COALESCE(SUM(p.monto), 0)) as saldo
                FROM presupuestos pr
                LEFT JOIN pagos p ON pr.id = p.presupuesto_id
                WHERE pr.id = :id
                GROUP BY pr.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $presupuestoId]);
        return floatval($stmt->fetchColumn());
    }

    /**
     * Obtener presupuestos pendientes de pago (aprobados con saldo)
     */
    public function getPresupuestosPendientes() {
        $sql = "SELECT pr.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                       (pr.total - COALESCE(pr.monto_pagado, 0)) as saldo_pendiente
                FROM presupuestos pr
                JOIN clientes c ON pr.cliente_id = c.id
                WHERE pr.estado IN ('aprobado', 'pagado')
                AND (pr.total - COALESCE(pr.monto_pagado, 0)) > 0
                ORDER BY pr.fecha DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estadísticas de pagos
     */
    public function getEstadisticas($fechaInicio = null, $fechaFin = null) {
        $where = "";
        $params = [];
        
        if ($fechaInicio && $fechaFin) {
            $where = "WHERE DATE(fecha_pago) BETWEEN :inicio AND :fin";
            $params = [':inicio' => $fechaInicio, ':fin' => $fechaFin];
        } elseif ($fechaInicio) {
            $where = "WHERE DATE(fecha_pago) >= :inicio";
            $params = [':inicio' => $fechaInicio];
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_pagos,
                    COALESCE(SUM(monto), 0) as monto_total,
                    COALESCE(SUM(CASE WHEN metodo_pago = 'efectivo' THEN monto ELSE 0 END), 0) as total_efectivo,
                    COALESCE(SUM(CASE WHEN metodo_pago = 'qr' THEN monto ELSE 0 END), 0) as total_qr,
                    COALESCE(SUM(CASE WHEN metodo_pago = 'transferencia' THEN monto ELSE 0 END), 0) as total_transferencia
                FROM pagos $where";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Pagos del día
     */
    public function getPagosHoy() {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, pr.numero as presupuesto_numero
                FROM pagos p
                JOIN clientes c ON p.cliente_id = c.id
                JOIN presupuestos pr ON p.presupuesto_id = pr.id
                WHERE DATE(p.fecha_pago) = CURDATE()
                ORDER BY p.fecha_pago DESC";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Subir comprobante
     */
    public function subirComprobante($pagoId, $file) {
        // Validaciones de seguridad
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        $maxSize = 8 * 1024 * 1024; // 8MB máximo
        
        // Validar tamaño
        if ($file['size'] > $maxSize) {
            return false; // Archivo muy grande
        }
        
        // Validar extensión
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            return false; // Extensión no permitida
        }

        // Validar tipo MIME real y contenido
        $allowedTypes = [
            'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif', 'image/webp',
            'application/pdf', 'application/x-pdf', 'application/acrobat', 'applications/vnd.pdf', 'text/pdf', 'text/x-pdf'
        ];
        
        $mimeType = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($file['tmp_name']);
        }
        
        $esValido = in_array($mimeType, $allowedTypes);
        
        // Si la extensión es PDF pero finfo devolvió octet-stream u otro MIME (común en apps bancarias o móviles),
        // verificar la firma mágica %PDF en los primeros 1024 bytes
        if (!$esValido && $extension === 'pdf') {
            $h = @fopen($file['tmp_name'], 'rb');
            if ($h) {
                $bytes = fread($h, 1024);
                fclose($h);
                if (strpos($bytes, '%PDF') !== false) {
                    $esValido = true;
                }
            }
        }
        
        // Si es imagen pero finfo falló, verificar con getimagesize
        if (!$esValido && in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $img = @getimagesize($file['tmp_name']);
            if ($img !== false) {
                $esValido = true;
            }
        }
        
        if (!$esValido) {
            return false; // Tipo no permitido
        }
        
        $uploadDir = 'uploads/comprobantes/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $nombreArchivo = 'comprobante_' . $pagoId . '_' . date('Ymd_His') . '.' . $extension;
        $ruta = $uploadDir . $nombreArchivo;
        
        if (move_uploaded_file($file['tmp_name'], $ruta)) {
            $stmt = $this->pdo->prepare("UPDATE pagos SET comprobante_ruta = :ruta, comprobante_tipo = :tipo WHERE id = :id");
            $stmt->execute([':ruta' => $ruta, ':tipo' => $extension, ':id' => $pagoId]);
            return $ruta;
        }
        return false;
    }
}
?>
