<?php
class Consultorio {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function create($nombre, $color) {
        $stmt = $this->pdo->prepare('INSERT INTO consultorios (nombre, color, activo) VALUES (?, ?, 1)');
        return $stmt->execute([$nombre, $color]);
    }

    /**
     * Obtiene todos los consultorios ACTIVOS (para uso general en el sistema)
     * Usado en: formularios de citas, eventos, calendarios, etc.
     */
    public function getAll() {
        $stmt = $this->pdo->query('SELECT * FROM consultorios WHERE activo = 1 ORDER BY nombre ASC');
        return $stmt->fetchAll();
    }

    /**
     * Obtiene TODOS los consultorios incluyendo inactivos
     * Usado para: mostrar historico de citas/eventos con consultorios eliminados
     */
    public function getAllIncludingInactive() {
        $stmt = $this->pdo->query('SELECT * FROM consultorios ORDER BY activo DESC, nombre ASC');
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un consultorio por ID (incluyendo inactivos)
     * Necesario para mostrar informacion de citas/eventos pasados
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM consultorios WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Obtiene un consultorio activo por ID
     */
    public function getActiveById($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM consultorios WHERE id = ? AND activo = 1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getColor($id) {
        $stmt = $this->pdo->prepare('SELECT color FROM consultorios WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['color'] : '#CCCCCC';
    }

    public function update($id, $nombre, $color) {
        $stmt = $this->pdo->prepare('UPDATE consultorios SET nombre = ?, color = ? WHERE id = ?');
        return $stmt->execute([$nombre, $color, $id]);
    }

    /**
     * SOFT DELETE: En lugar de eliminar, desactiva el consultorio
     * El consultorio permanece en la BD pero no se muestra en listados
     * Las citas y eventos asociados conservan su referencia
     */
    public function delete($id) {
        $stmt = $this->pdo->prepare('UPDATE consultorios SET activo = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Reactivar un consultorio previamente eliminado
     */
    public function reactivate($id) {
        $stmt = $this->pdo->prepare('UPDATE consultorios SET activo = 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Verifica si un consultorio esta activo
     */
    public function isActive($id) {
        $stmt = $this->pdo->prepare('SELECT activo FROM consultorios WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (bool)$result['activo'] : false;
    }

    /**
     * Cuenta cuantas citas tiene un consultorio
     */
    public function countCitas($id) {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM citas WHERE consultorio_id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    }

    /**
     * Cuenta cuantos eventos tiene un consultorio
     */
    public function countEventos($id) {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM eventos WHERE consultorio_id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['total'] : 0;
    }
}
?>