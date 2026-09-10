<?php

class Doctor {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Obtener todos los doctores con sus especialidades
    public function getAll() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    d.id, 
                    d.nombre, 
                    d.telefono, 
                    d.estado, 
                    d.created_at,
                    COALESCE(GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', '), 'Odontología General') AS especialidades,
                    GROUP_CONCAT(e.id SEPARATOR ',') AS especialidades_ids
                FROM doctores d
                LEFT JOIN doctor_especialidades de ON d.id = de.doctor_id
                LEFT JOIN especialidades e ON de.especialidad_id = e.id
                GROUP BY d.id
                ORDER BY d.nombre ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback en caso de que aún no existan las tablas relacionales
            $stmt = $this->pdo->query("SELECT * FROM doctores ORDER BY nombre ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Obtener doctores activos con especialidades
    public function getActivos() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    d.id, 
                    d.nombre, 
                    d.telefono, 
                    d.estado, 
                    d.created_at,
                    COALESCE(GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', '), 'Odontología General') AS especialidades,
                    GROUP_CONCAT(e.id SEPARATOR ',') AS especialidades_ids
                FROM doctores d
                LEFT JOIN doctor_especialidades de ON d.id = de.doctor_id
                LEFT JOIN especialidades e ON de.especialidad_id = e.id
                WHERE d.estado = 'activo'
                GROUP BY d.id
                ORDER BY d.nombre ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $this->pdo->query("SELECT * FROM doctores WHERE estado = 'activo' ORDER BY nombre ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Obtener un doctor por ID
    public function getById($id) {
        $id = (int)$id;
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    d.id, 
                    d.nombre, 
                    d.telefono, 
                    d.estado, 
                    d.created_at,
                    COALESCE(GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR ', '), 'Odontología General') AS especialidades,
                    GROUP_CONCAT(e.id SEPARATOR ',') AS especialidades_ids
                FROM doctores d
                LEFT JOIN doctor_especialidades de ON d.id = de.doctor_id
                LEFT JOIN especialidades e ON de.especialidad_id = e.id
                WHERE d.id = ?
                GROUP BY d.id
            ");
            $stmt->execute([$id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc && !empty($doc['especialidades_ids'])) {
                $doc['especialidades_array'] = array_map('intval', explode(',', $doc['especialidades_ids']));
            } else {
                $doc['especialidades_array'] = [];
            }
            return $doc;
        } catch (PDOException $e) {
            $stmt = $this->pdo->prepare("SELECT * FROM doctores WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Obtener catálogo completo de especialidades
    public function getAllEspecialidades() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM especialidades ORDER BY nombre ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    // Crear un nuevo doctor
    public function create($nombre, $estado = 'activo', $telefono = null, $especialidadesIds = []) {
        $nombre = trim((string)$nombre);
        $telefono = !empty($telefono) ? trim((string)$telefono) : null;
        
        if (empty($nombre)) {
            throw new Exception("El nombre del doctor es obligatorio.");
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO doctores (nombre, telefono, estado, created_at) VALUES (?, ?, ?, NOW())"
            );
            $stmt->execute([$nombre, $telefono, $estado]);
        } catch (PDOException $e) {
            // Fallback si la columna telefono no existe
            $stmt = $this->pdo->prepare(
                "INSERT INTO doctores (nombre, estado, created_at) VALUES (?, ?, NOW())"
            );
            $stmt->execute([$nombre, $estado]);
        }
        
        $doctorId = (int)$this->pdo->lastInsertId();

        if (!empty($especialidadesIds) && is_array($especialidadesIds)) {
            $this->syncEspecialidades($doctorId, $especialidadesIds);
        }
        
        return $doctorId;
    }

    // Actualizar un doctor
    public function update($id, $nombre, $estado, $telefono = null, $especialidadesIds = null) {
        $id = (int)$id;
        $nombre = trim((string)$nombre);
        $telefono = !empty($telefono) ? trim((string)$telefono) : null;
        
        if (empty($nombre)) {
            throw new Exception("El nombre del doctor es obligatorio.");
        }

        if (!in_array($estado, ['activo', 'inactivo'])) {
            throw new Exception("El estado debe ser 'activo' o 'inactivo'.");
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE doctores SET nombre = ?, telefono = ?, estado = ? WHERE id = ?"
            );
            $stmt->execute([$nombre, $telefono, $estado, $id]);
        } catch (PDOException $e) {
            $stmt = $this->pdo->prepare(
                "UPDATE doctores SET nombre = ?, estado = ? WHERE id = ?"
            );
            $stmt->execute([$nombre, $estado, $id]);
        }

        if ($especialidadesIds !== null && is_array($especialidadesIds)) {
            $this->syncEspecialidades($id, $especialidadesIds);
        }

        return true;
    }

    // Sincronizar las especialidades de un doctor (elimina anteriores e inserta nuevas)
    public function syncEspecialidades($doctorId, array $especialidadesIds) {
        $doctorId = (int)$doctorId;
        try {
            $stmtDel = $this->pdo->prepare("DELETE FROM doctor_especialidades WHERE doctor_id = ?");
            $stmtDel->execute([$doctorId]);

            if (!empty($especialidadesIds)) {
                $stmtIns = $this->pdo->prepare("INSERT INTO doctor_especialidades (doctor_id, especialidad_id) VALUES (?, ?)");
                foreach ($especialidadesIds as $espId) {
                    $espId = (int)$espId;
                    if ($espId > 0) {
                        $stmtIns->execute([$doctorId, $espId]);
                    }
                }
            }
        } catch (PDOException $e) {
            // Ignorar silenciosamente si la tabla relacional aún no existe
        }
    }

    // Eliminar un doctor
    public function delete($id) {
        $id = (int)$id;
        
        // Verificar si el doctor tiene citas asociadas
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM citas WHERE doctor_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception("No se puede eliminar este doctor porque tiene citas asociadas.");
        }

        // Eliminar relaciones de especialidad si existen
        try {
            $stmtDel = $this->pdo->prepare("DELETE FROM doctor_especialidades WHERE doctor_id = ?");
            $stmtDel->execute([$id]);
        } catch (PDOException $e) {}

        $stmt = $this->pdo->prepare("DELETE FROM doctores WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Cambiar estado de un doctor
    public function toggleEstado($id) {
        $doctor = $this->getById($id);
        if (!$doctor) {
            throw new Exception("Doctor no encontrado.");
        }

        $nuevoEstado = $doctor['estado'] === 'activo' ? 'inactivo' : 'activo';
        $telefono = $doctor['telefono'] ?? null;
        return $this->update($id, $doctor['nombre'], $nuevoEstado, $telefono);
    }
}
?>
