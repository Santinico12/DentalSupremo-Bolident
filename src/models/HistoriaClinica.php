<?php
/**
 * Modelo HistoriaClinica
 * Gestiona antecedentes médicos y odontológicos del paciente
 */
class HistoriaClinica {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener historia clínica por cliente
     */
    public function getByCliente($clienteId) {
        $sql = "SELECT h.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono
                FROM historia_clinica h
                JOIN clientes c ON h.cliente_id = c.id
                WHERE h.cliente_id = :cliente_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cliente_id' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si existe historia clínica
     */
    public function existe($clienteId) {
        $stmt = $this->pdo->prepare("SELECT id FROM historia_clinica WHERE cliente_id = :id");
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Crear historia clínica
     */
    public function crear($data) {
        $sql = "INSERT INTO historia_clinica (
                    cliente_id, fecha_nacimiento, sexo, ocupacion, direccion, email,
                    contacto_emergencia_nombre, contacto_emergencia_telefono, contacto_emergencia_parentesco,
                    grupo_sanguineo, alergias, enfermedades_sistemicas, medicamentos_actuales,
                    cirugias_previas, hospitalizaciones, ultima_visita_dentista, experiencia_anestesia,
                    habitos, higiene_bucal, embarazo, lactancia, observaciones
                ) VALUES (
                    :cliente_id, :fecha_nacimiento, :sexo, :ocupacion, :direccion, :email,
                    :contacto_nombre, :contacto_telefono, :contacto_parentesco,
                    :grupo_sanguineo, :alergias, :enfermedades, :medicamentos,
                    :cirugias, :hospitalizaciones, :ultima_visita, :experiencia_anestesia,
                    :habitos, :higiene_bucal, :embarazo, :lactancia, :observaciones
                )";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':cliente_id' => $data['cliente_id'],
            ':fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            ':sexo' => $data['sexo'] ?? null,
            ':ocupacion' => $data['ocupacion'] ?? null,
            ':direccion' => $data['direccion'] ?? null,
            ':email' => $data['email'] ?? null,
            ':contacto_nombre' => $data['contacto_emergencia_nombre'] ?? null,
            ':contacto_telefono' => $data['contacto_emergencia_telefono'] ?? null,
            ':contacto_parentesco' => $data['contacto_emergencia_parentesco'] ?? null,
            ':grupo_sanguineo' => $data['grupo_sanguineo'] ?? null,
            ':alergias' => $data['alergias'] ?? null,
            ':enfermedades' => $data['enfermedades_sistemicas'] ?? null,
            ':medicamentos' => $data['medicamentos_actuales'] ?? null,
            ':cirugias' => $data['cirugias_previas'] ?? null,
            ':hospitalizaciones' => $data['hospitalizaciones'] ?? null,
            ':ultima_visita' => $data['ultima_visita_dentista'] ?? null,
            ':experiencia_anestesia' => $data['experiencia_anestesia'] ?? null,
            ':habitos' => $data['habitos'] ?? null,
            ':higiene_bucal' => $data['higiene_bucal'] ?? null,
            ':embarazo' => $data['embarazo'] ?? 0,
            ':lactancia' => $data['lactancia'] ?? 0,
            ':observaciones' => $data['observaciones'] ?? null
        ]);
    }

    /**
     * Actualizar historia clínica
     */
    public function actualizar($clienteId, $data) {
        $sql = "UPDATE historia_clinica SET
                    fecha_nacimiento = :fecha_nacimiento,
                    sexo = :sexo,
                    ocupacion = :ocupacion,
                    direccion = :direccion,
                    email = :email,
                    contacto_emergencia_nombre = :contacto_nombre,
                    contacto_emergencia_telefono = :contacto_telefono,
                    contacto_emergencia_parentesco = :contacto_parentesco,
                    grupo_sanguineo = :grupo_sanguineo,
                    alergias = :alergias,
                    enfermedades_sistemicas = :enfermedades,
                    medicamentos_actuales = :medicamentos,
                    cirugias_previas = :cirugias,
                    hospitalizaciones = :hospitalizaciones,
                    ultima_visita_dentista = :ultima_visita,
                    experiencia_anestesia = :experiencia_anestesia,
                    habitos = :habitos,
                    higiene_bucal = :higiene_bucal,
                    embarazo = :embarazo,
                    lactancia = :lactancia,
                    observaciones = :observaciones
                WHERE cliente_id = :cliente_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':cliente_id' => $clienteId,
            ':fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            ':sexo' => $data['sexo'] ?? null,
            ':ocupacion' => $data['ocupacion'] ?? null,
            ':direccion' => $data['direccion'] ?? null,
            ':email' => $data['email'] ?? null,
            ':contacto_nombre' => $data['contacto_emergencia_nombre'] ?? null,
            ':contacto_telefono' => $data['contacto_emergencia_telefono'] ?? null,
            ':contacto_parentesco' => $data['contacto_emergencia_parentesco'] ?? null,
            ':grupo_sanguineo' => $data['grupo_sanguineo'] ?? null,
            ':alergias' => $data['alergias'] ?? null,
            ':enfermedades' => $data['enfermedades_sistemicas'] ?? null,
            ':medicamentos' => $data['medicamentos_actuales'] ?? null,
            ':cirugias' => $data['cirugias_previas'] ?? null,
            ':hospitalizaciones' => $data['hospitalizaciones'] ?? null,
            ':ultima_visita' => $data['ultima_visita_dentista'] ?? null,
            ':experiencia_anestesia' => $data['experiencia_anestesia'] ?? null,
            ':habitos' => $data['habitos'] ?? null,
            ':higiene_bucal' => $data['higiene_bucal'] ?? null,
            ':embarazo' => $data['embarazo'] ?? 0,
            ':lactancia' => $data['lactancia'] ?? 0,
            ':observaciones' => $data['observaciones'] ?? null
        ]);
    }

    /**
     * Crear o actualizar historia clínica
     */
    public function guardar($clienteId, $data) {
        $data['cliente_id'] = $clienteId;
        if ($this->existe($clienteId)) {
            return $this->actualizar($clienteId, $data);
        } else {
            return $this->crear($data);
        }
    }

    /**
     * Obtener resumen de alertas médicas
     */
    public function getAlertasMedicas($clienteId) {
        $historia = $this->getByCliente($clienteId);
        $alertas = [];
        
        if ($historia) {
            if (!empty($historia['alergias'])) {
                $alertas[] = ['tipo' => 'danger', 'texto' => 'Alergias: ' . $historia['alergias']];
            }
            if (!empty($historia['enfermedades_sistemicas'])) {
                $alertas[] = ['tipo' => 'warning', 'texto' => 'Enfermedades: ' . $historia['enfermedades_sistemicas']];
            }
            if (!empty($historia['medicamentos_actuales'])) {
                $alertas[] = ['tipo' => 'info', 'texto' => 'Medicamentos: ' . $historia['medicamentos_actuales']];
            }
            if ($historia['embarazo']) {
                $alertas[] = ['tipo' => 'danger', 'texto' => 'Paciente embarazada'];
            }
        }
        
        return $alertas;
    }
}
?>
