<?php
/**
 * Modelo Presupuesto
 * Gestiona presupuestos y cotizaciones para pacientes
 */
class Presupuesto {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->repararNumerosFaltantes();
    }

    /**
     * Generar el siguiente número correlativo (ej: PRE-000001)
     */
    public function generarSiguienteNumero() {
        try {
            $stmt = $this->pdo->query("SELECT MAX(CAST(SUBSTRING(numero, 5) AS UNSIGNED)) as max_num FROM presupuestos WHERE numero LIKE 'PRE-%'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextNum = ($row && !empty($row['max_num'])) ? intval($row['max_num']) + 1 : 1;
        } catch (Exception $e) {
            $nextNum = 1;
        }
        return 'PRE-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Reparar presupuestos que no tengan número asignado (ej. en hosting sin triggers)
     */
    public function repararNumerosFaltantes() {
        try {
            $stmt = $this->pdo->query("SELECT id FROM presupuestos WHERE numero IS NULL OR numero = '' OR numero = '0' ORDER BY id ASC");
            $sinNumero = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sinNumero as $row) {
                $nuevoNum = $this->generarSiguienteNumero();
                $update = $this->pdo->prepare("UPDATE presupuestos SET numero = :numero WHERE id = :id");
                $update->execute([':numero' => $nuevoNum, ':id' => $row['id']]);
            }
        } catch (Exception $e) {
            // Silencioso en caso de error
        }
    }

    /**
     * Obtener todos los presupuestos
     */
    public function getAll($limite = 50) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                       d.nombre as doctor_nombre
                FROM presupuestos p
                JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN doctores d ON p.doctor_id = d.id
                ORDER BY p.created_at DESC
                LIMIT :limite";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener presupuesto por ID con items
     */
    public function getById($id) {
        $sql = "SELECT p.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                       d.nombre as doctor_nombre
                FROM presupuestos p
                JOIN clientes c ON p.cliente_id = c.id
                LEFT JOIN doctores d ON p.doctor_id = d.id
                WHERE p.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $presupuesto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($presupuesto) {
            $presupuesto['items'] = $this->getItems($id);
        }
        return $presupuesto;
    }

    /**
     * Obtener items de un presupuesto
     */
    public function getItems($presupuestoId) {
        $sql = "SELECT pi.*, t.nombre as tratamiento_nombre, t.codigo as tratamiento_codigo
                FROM presupuesto_items pi
                LEFT JOIN tratamientos t ON pi.tratamiento_id = t.id
                WHERE pi.presupuesto_id = :id
                ORDER BY pi.id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $presupuestoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener presupuestos por cliente
     */
    public function getByCliente($clienteId) {
        $sql = "SELECT * FROM presupuestos WHERE cliente_id = :cliente_id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cliente_id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear nuevo presupuesto
     */
    public function crear($data) {
        $numero = !empty($data['numero']) ? $data['numero'] : $this->generarSiguienteNumero();

        $sql = "INSERT INTO presupuestos (numero, cliente_id, doctor_id, fecha, fecha_vencimiento, notas, estado)
                VALUES (:numero, :cliente_id, :doctor_id, :fecha, :fecha_vencimiento, :notas, 'borrador')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':numero' => $numero,
            ':cliente_id' => $data['cliente_id'],
            ':doctor_id' => $data['doctor_id'] ?? null,
            ':fecha' => $data['fecha'] ?? date('Y-m-d'),
            ':fecha_vencimiento' => $data['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days')),
            ':notas' => $data['notas'] ?? ''
        ]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Agregar item al presupuesto
     */
    public function agregarItem($presupuestoId, $item) {
        $subtotal = $item['cantidad'] * $item['precio_unitario'];
        
        $sql = "INSERT INTO presupuesto_items (presupuesto_id, tratamiento_id, descripcion, diente, cantidad, precio_unitario, subtotal)
                VALUES (:presupuesto_id, :tratamiento_id, :descripcion, :diente, :cantidad, :precio, :subtotal)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':presupuesto_id' => $presupuestoId,
            ':tratamiento_id' => $item['tratamiento_id'] ?? null,
            ':descripcion' => $item['descripcion'],
            ':diente' => $item['diente'] ?? null,
            ':cantidad' => $item['cantidad'],
            ':precio' => $item['precio_unitario'],
            ':subtotal' => $subtotal
        ]);
        
        $this->recalcularTotales($presupuestoId);
        return $this->pdo->lastInsertId();
    }

    /**
     * Eliminar item del presupuesto
     */
    public function eliminarItem($itemId) {
        $stmt = $this->pdo->prepare("SELECT presupuesto_id FROM presupuesto_items WHERE id = :id");
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            $this->pdo->prepare("DELETE FROM presupuesto_items WHERE id = :id")->execute([':id' => $itemId]);
            $this->recalcularTotales($item['presupuesto_id']);
        }
    }

    /**
     * Recalcular totales del presupuesto
     */
    public function recalcularTotales($presupuestoId) {
        $stmt = $this->pdo->prepare("SELECT SUM(subtotal) as total FROM presupuesto_items WHERE presupuesto_id = :id");
        $stmt->execute([':id' => $presupuestoId]);
        $subtotal = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        // Obtener el descuento en monto fijo (Bs)
        $stmt = $this->pdo->prepare("SELECT descuento_monto FROM presupuestos WHERE id = :id");
        $stmt->execute([':id' => $presupuestoId]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);
        $descuentoMonto = $pres['descuento_monto'] ?? 0;
        
        // Asegurar que el descuento no sea mayor al subtotal
        if ($descuentoMonto > $subtotal) {
            $descuentoMonto = $subtotal;
        }
        
        $total = $subtotal - $descuentoMonto;

        $sql = "UPDATE presupuestos SET subtotal = :subtotal, total = :total WHERE id = :id";
        $this->pdo->prepare($sql)->execute([
            ':subtotal' => $subtotal,
            ':total' => $total,
            ':id' => $presupuestoId
        ]);
    }

    /**
     * Aplicar descuento en monto fijo (Bs)
     */
    public function aplicarDescuento($presupuestoId, $montoDescuento) {
        $this->pdo->prepare("UPDATE presupuestos SET descuento_monto = :monto WHERE id = :id")
            ->execute([':monto' => $montoDescuento, ':id' => $presupuestoId]);
        $this->recalcularTotales($presupuestoId);
    }

    /**
     * Cambiar estado
     */
    public function cambiarEstado($presupuestoId, $estado) {
        $sql = "UPDATE presupuestos SET estado = :estado WHERE id = :id";
        return $this->pdo->prepare($sql)->execute([':estado' => $estado, ':id' => $presupuestoId]);
    }

    /**
     * Eliminar presupuesto
     */
    public function eliminar($id) {
        return $this->pdo->prepare("DELETE FROM presupuestos WHERE id = :id")->execute([':id' => $id]);
    }

    /**
     * Obtener estadísticas
     */
    public function getEstadisticas() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
                    SUM(CASE WHEN estado = 'borrador' THEN 1 ELSE 0 END) as borradores,
                    SUM(CASE WHEN estado = 'aprobado' THEN total ELSE 0 END) as monto_aprobado
                FROM presupuestos";
        return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    }
}
?>
