<?php
/**
 * Modelo Odontograma
 * Gestiona el registro del estado dental de cada paciente
 */
class Odontograma {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getByCliente($clienteId) {
        $sql = "SELECT * FROM odontograma WHERE cliente_id = :cliente_id ORDER BY diente, superficie";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':cliente_id', $clienteId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByClienteIndexado($clienteId) {
        $registros = $this->getByCliente($clienteId);
        $indexado = [];
        foreach ($registros as $reg) {
            $key = $reg['diente'] . '_' . $reg['superficie'];
            $indexado[$key] = $reg;
        }
        return $indexado;
    }

    public function guardar($clienteId, $diente, $superficie, $condicion, $notas = '') {
        $sql = "SELECT id, condicion FROM odontograma 
                WHERE cliente_id = :cliente_id AND diente = :diente AND superficie = :superficie";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cliente_id' => $clienteId, ':diente' => $diente, ':superficie' => $superficie]);
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            $sql = "UPDATE odontograma SET condicion = :condicion, notas = :notas WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([':condicion' => $condicion, ':notas' => $notas, ':id' => $existente['id']]);
        } else {
            $sql = "INSERT INTO odontograma (cliente_id, diente, superficie, condicion, notas) 
                    VALUES (:cliente_id, :diente, :superficie, :condicion, :notas)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                ':cliente_id' => $clienteId,
                ':diente' => $diente,
                ':superficie' => $superficie,
                ':condicion' => $condicion,
                ':notas' => $notas
            ]);
        }
    }

    public function eliminar($clienteId, $diente, $superficie = 'completo') {
        $sql = "DELETE FROM odontograma WHERE cliente_id = :cliente_id AND diente = :diente AND superficie = :superficie";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':cliente_id' => $clienteId, ':diente' => $diente, ':superficie' => $superficie]);
    }

    public static function getCondiciones() {
        return [
            'sano' => ['nombre' => 'Sano', 'color' => '#FFFFFF'],
            'caries' => ['nombre' => 'Caries', 'color' => '#FF4444'],
            'obturacion' => ['nombre' => 'Obturación', 'color' => '#4A90D9'],
            'corona' => ['nombre' => 'Corona', 'color' => '#FFD700'],
            'extraccion' => ['nombre' => 'Extracción', 'color' => '#FF0000'],
            'ausente' => ['nombre' => 'Ausente', 'color' => '#CCCCCC'],
            'endodoncia' => ['nombre' => 'Endodoncia', 'color' => '#9B59B6'],
            'protesis' => ['nombre' => 'Prótesis', 'color' => '#27AE60'],
            'implante' => ['nombre' => 'Implante', 'color' => '#3498DB'],
        ];
    }

    public static function getDientesAdulto() {
        return [
            'superior_derecho' => ['18', '17', '16', '15', '14', '13', '12', '11'],
            'superior_izquierdo' => ['21', '22', '23', '24', '25', '26', '27', '28'],
            'inferior_izquierdo' => ['31', '32', '33', '34', '35', '36', '37', '38'],
            'inferior_derecho' => ['48', '47', '46', '45', '44', '43', '42', '41']
        ];
    }

    public static function getDientesPediatrico() {
        return [
            'superior_derecho' => ['55', '54', '53', '52', '51'],
            'superior_izquierdo' => ['61', '62', '63', '64', '65'],
            'inferior_izquierdo' => ['71', '72', '73', '74', '75'],
            'inferior_derecho' => ['85', '84', '83', '82', '81']
        ];
    }
}
?>
