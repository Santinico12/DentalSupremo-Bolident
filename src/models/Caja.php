<?php
/**
 * Modelo Caja
 * Gestiona el flujo de caja: ingresos de presupuestos, otros ingresos y egresos
 */
class Caja {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener estadísticas de caja en un rango de fechas
     */
    public function getEstadisticas($desde = '', $hasta = '') {
        // Ingresos de presupuestos (pagos)
        $wherePagos = [];
        $paramsPagos = [];
        if ($desde) {
            $wherePagos[] = "DATE(fecha_pago) >= :desde";
            $paramsPagos[':desde'] = $desde;
        }
        if ($hasta) {
            $wherePagos[] = "DATE(fecha_pago) <= :hasta";
            $paramsPagos[':hasta'] = $hasta;
        }
        $sqlPagos = "SELECT SUM(monto) FROM pagos" . (count($wherePagos) > 0 ? " WHERE " . implode(" AND ", $wherePagos) : "");
        $stmt = $this->pdo->prepare($sqlPagos);
        $stmt->execute($paramsPagos);
        $totalPagos = floatval($stmt->fetchColumn());

        // Otros ingresos
        $whereOtros = [];
        $paramsOtros = [];
        if ($desde) {
            $whereOtros[] = "DATE(fecha) >= :desde";
            $paramsOtros[':desde'] = $desde;
        }
        if ($hasta) {
            $whereOtros[] = "DATE(fecha) <= :hasta";
            $paramsOtros[':hasta'] = $hasta;
        }
        $sqlOtros = "SELECT SUM(monto) FROM otros_ingresos" . (count($whereOtros) > 0 ? " WHERE " . implode(" AND ", $whereOtros) : "");
        $stmt = $this->pdo->prepare($sqlOtros);
        $stmt->execute($paramsOtros);
        $totalOtros = floatval($stmt->fetchColumn());

        // Egresos
        $whereEgresos = [];
        $paramsEgresos = [];
        if ($desde) {
            $whereEgresos[] = "DATE(fecha) >= :desde";
            $paramsEgresos[':desde'] = $desde;
        }
        if ($hasta) {
            $whereEgresos[] = "DATE(fecha) <= :hasta";
            $paramsEgresos[':hasta'] = $hasta;
        }
        $sqlEgresos = "SELECT SUM(monto) FROM egresos" . (count($whereEgresos) > 0 ? " WHERE " . implode(" AND ", $whereEgresos) : "");
        $stmt = $this->pdo->prepare($sqlEgresos);
        $stmt->execute($paramsEgresos);
        $totalEgresos = floatval($stmt->fetchColumn());

        return [
            'total_pagos' => $totalPagos,
            'total_otros_ingresos' => $totalOtros,
            'total_ingresos' => $totalPagos + $totalOtros,
            'total_egresos' => $totalEgresos,
            'saldo_neto' => ($totalPagos + $totalOtros) - $totalEgresos
        ];
    }

    /**
     * Obtener lista consolidada de movimientos
     */
    public function getMovimientos($desde = '', $hasta = '', $tipo = '', $metodo = '') {
        $queries = [];
        $params = [];

        // Query 1: Pagos de presupuestos
        if ($tipo === '' || $tipo === 'ingreso' || $tipo === 'ingreso_presupuesto') {
            $where = ["1=1"];
            if ($desde) {
                $where[] = "DATE(p.fecha_pago) >= :desde_p";
                $params[':desde_p'] = $desde;
            }
            if ($hasta) {
                $where[] = "DATE(p.fecha_pago) <= :hasta_p";
                $params[':hasta_p'] = $hasta;
            }
            if ($metodo) {
                $where[] = "p.metodo_pago = :metodo_p";
                $params[':metodo_p'] = $metodo;
            }
            $queries[] = "SELECT 
                'ingreso_presupuesto' AS tipo_movimiento,
                p.id,
                CONCAT('Pago Presupuesto ', pr.numero, ' - ', c.nombre) AS nombre,
                p.monto,
                p.metodo_pago,
                p.notas AS descripcion,
                p.fecha_pago AS fecha,
                p.registrado_por AS realizado_por,
                p.comprobante_ruta,
                p.comprobante_tipo,
                pr.id AS presupuesto_id,
                pr.numero AS presupuesto_numero
                FROM pagos p
                JOIN clientes c ON p.cliente_id = c.id
                JOIN presupuestos pr ON p.presupuesto_id = pr.id
                WHERE " . implode(" AND ", $where);
        }

        // Query 2: Otros ingresos
        if ($tipo === '' || $tipo === 'ingreso' || $tipo === 'ingreso_otro') {
            $where = ["1=1"];
            if ($desde) {
                $where[] = "DATE(fecha) >= :desde_o";
                $params[':desde_o'] = $desde;
            }
            if ($hasta) {
                $where[] = "DATE(fecha) <= :hasta_o";
                $params[':hasta_o'] = $hasta;
            }
            if ($metodo) {
                $where[] = "metodo_pago = :metodo_o";
                $params[':metodo_o'] = $metodo;
            }
            $queries[] = "SELECT 
                'ingreso_otro' AS tipo_movimiento,
                id,
                nombre,
                monto,
                metodo_pago,
                descripcion,
                fecha,
                registrado_por AS realizado_por,
                comprobante_ruta,
                comprobante_tipo,
                NULL AS presupuesto_id,
                NULL AS presupuesto_numero
                FROM otros_ingresos
                WHERE " . implode(" AND ", $where);
        }

        // Query 3: Egresos
        if ($tipo === '' || $tipo === 'egreso') {
            $where = ["1=1"];
            if ($desde) {
                $where[] = "DATE(fecha) >= :desde_e";
                $params[':desde_e'] = $desde;
            }
            if ($hasta) {
                $where[] = "DATE(fecha) <= :hasta_e";
                $params[':hasta_e'] = $hasta;
            }
            $queries[] = "SELECT 
                'egreso' AS tipo_movimiento,
                id,
                nombre,
                monto,
                'otro' AS metodo_pago,
                descripcion,
                fecha,
                realizado_por,
                comprobante_ruta,
                comprobante_tipo,
                NULL AS presupuesto_id,
                NULL AS presupuesto_numero
                FROM egresos
                WHERE " . implode(" AND ", $where);
        }

        if (empty($queries)) {
            return [];
        }

        $sql = implode(" UNION ALL ", $queries) . " ORDER BY fecha DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear Egreso
     */
    public function crearEgreso($data) {
        $sql = "INSERT INTO egresos (nombre, monto, descripcion, comprobante_ruta, comprobante_tipo, realizado_por, fecha)
                VALUES (:nombre, :monto, :descripcion, :comprobante_ruta, :comprobante_tipo, :realizado_por, :fecha)";
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute([
            ':nombre' => $data['nombre'],
            ':monto' => $data['monto'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':comprobante_ruta' => $data['comprobante_ruta'] ?? null,
            ':comprobante_tipo' => $data['comprobante_tipo'] ?? null,
            ':realizado_por' => $data['realizado_por'],
            ':fecha' => $data['fecha'] ?? date('Y-m-d H:i:s')
        ])) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    /**
     * Crear Otro Ingreso
     */
    public function crearOtroIngreso($data) {
        $sql = "INSERT INTO otros_ingresos (nombre, monto, descripcion, metodo_pago, comprobante_ruta, comprobante_tipo, registrado_por, fecha)
                VALUES (:nombre, :monto, :descripcion, :metodo_pago, :comprobante_ruta, :comprobante_tipo, :registrado_por, :fecha)";
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute([
            ':nombre' => $data['nombre'],
            ':monto' => $data['monto'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':metodo_pago' => $data['metodo_pago'],
            ':comprobante_ruta' => $data['comprobante_ruta'] ?? null,
            ':comprobante_tipo' => $data['comprobante_tipo'] ?? null,
            ':registrado_por' => $data['registrado_por'],
            ':fecha' => $data['fecha'] ?? date('Y-m-d H:i:s')
        ])) {
            return $this->pdo->lastInsertId();
        }
        return false;
    }

    /**
     * Obtener egreso por ID
     */
    public function getEgresoById($id) {
        $sql = "SELECT * FROM egresos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener otro ingreso por ID
     */
    public function getOtroIngresoById($id) {
        $sql = "SELECT * FROM otros_ingresos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Eliminar egreso
     */
    public function eliminarEgreso($id) {
        $sql = "DELETE FROM egresos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Eliminar otro ingreso
     */
    public function eliminarOtroIngreso($id) {
        $sql = "DELETE FROM otros_ingresos WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Actualizar comprobante de egreso
     */
    public function actualizarComprobanteEgreso($id, $ruta, $tipo) {
        $sql = "UPDATE egresos SET comprobante_ruta = :ruta, comprobante_tipo = :tipo WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':ruta' => $ruta, ':tipo' => $tipo, ':id' => $id]);
    }

    /**
     * Actualizar comprobante de otro ingreso
     */
    public function actualizarComprobanteOtroIngreso($id, $ruta, $tipo) {
        $sql = "UPDATE otros_ingresos SET comprobante_ruta = :ruta, comprobante_tipo = :tipo WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':ruta' => $ruta, ':tipo' => $tipo, ':id' => $id]);
    }
}
?>
