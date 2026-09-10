<?php
/**
 * Modelo Evolucion
 * Gestiona las notas clínicas por cada visita del paciente
 */
class Evolucion {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener todas las evoluciones de un paciente
     */
    public function getByCliente($clienteId, $limite = 50) {
        $sql = "SELECT e.*, d.nombre as doctor_nombre
                FROM evoluciones e
                LEFT JOIN doctores d ON e.doctor_id = d.id
                WHERE e.cliente_id = :cliente_id
                ORDER BY e.fecha_atencion DESC
                LIMIT :limite";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':cliente_id', $clienteId, PDO::PARAM_INT);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener evolución por ID
     */
    public function getById($id) {
        $sql = "SELECT e.*, d.nombre as doctor_nombre, c.nombre as cliente_nombre
                FROM evoluciones e
                LEFT JOIN doctores d ON e.doctor_id = d.id
                JOIN clientes c ON e.cliente_id = c.id
                WHERE e.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crear nueva evolución
     */
    public function crear($data) {
        $sql = "INSERT INTO evoluciones (
                    cliente_id, doctor_id, cita_id, fecha_atencion, motivo_consulta,
                    examen_clinico, diagnostico, tratamiento_realizado, dientes_tratados,
                    materiales_usados, indicaciones_paciente, receta_medica, proxima_cita, updated_by
                ) VALUES (
                    :cliente_id, :doctor_id, :cita_id, :fecha_atencion, :motivo_consulta,
                    :examen_clinico, :diagnostico, :tratamiento_realizado, :dientes_tratados,
                    :materiales_usados, :indicaciones_paciente, :receta_medica, :proxima_cita, :updated_by
                )";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':cliente_id' => $data['cliente_id'],
            ':doctor_id' => $data['doctor_id'] ?? null,
            ':cita_id' => $data['cita_id'] ?? null,
            ':fecha_atencion' => $data['fecha_atencion'] ?? date('Y-m-d H:i:s'),
            ':motivo_consulta' => $data['motivo_consulta'],
            ':examen_clinico' => $data['examen_clinico'] ?? null,
            ':diagnostico' => $data['diagnostico'] ?? null,
            ':tratamiento_realizado' => $data['tratamiento_realizado'],
            ':dientes_tratados' => $data['dientes_tratados'] ?? null,
            ':materiales_usados' => $data['materiales_usados'] ?? null,
            ':indicaciones_paciente' => $data['indicaciones_paciente'] ?? null,
            ':receta_medica' => $data['receta_medica'] ?? null,
            ':proxima_cita' => $data['proxima_cita'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Actualizar evolución
     */
    public function actualizar($id, $data) {
        $sql = "UPDATE evoluciones SET
                    doctor_id = :doctor_id,
                    motivo_consulta = :motivo_consulta,
                    examen_clinico = :examen_clinico,
                    diagnostico = :diagnostico,
                    tratamiento_realizado = :tratamiento_realizado,
                    dientes_tratados = :dientes_tratados,
                    materiales_usados = :materiales_usados,
                    indicaciones_paciente = :indicaciones_paciente,
                    receta_medica = :receta_medica,
                    proxima_cita = :proxima_cita,
                    updated_by = :updated_by
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':doctor_id' => $data['doctor_id'] ?? null,
            ':motivo_consulta' => $data['motivo_consulta'],
            ':examen_clinico' => $data['examen_clinico'] ?? null,
            ':diagnostico' => $data['diagnostico'] ?? null,
            ':tratamiento_realizado' => $data['tratamiento_realizado'],
            ':dientes_tratados' => $data['dientes_tratados'] ?? null,
            ':materiales_usados' => $data['materiales_usados'] ?? null,
            ':indicaciones_paciente' => $data['indicaciones_paciente'] ?? null,
            ':receta_medica' => $data['receta_medica'] ?? null,
            ':proxima_cita' => $data['proxima_cita'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null
        ]);
    }

    /**
     * Eliminar evolución
     */
    public function eliminar($id) {
        return $this->pdo->prepare("DELETE FROM evoluciones WHERE id = :id")->execute([':id' => $id]);
    }

    /**
     * Contar evoluciones de un paciente
     */
    public function contar($clienteId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM evoluciones WHERE cliente_id = :id");
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchColumn();
    }

    /**
     * Obtener última evolución de un paciente
     */
    public function getUltima($clienteId) {
        $sql = "SELECT e.*, d.nombre as doctor_nombre
                FROM evoluciones e
                LEFT JOIN doctores d ON e.doctor_id = d.id
                WHERE e.cliente_id = :cliente_id
                ORDER BY e.fecha_atencion DESC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cliente_id' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
