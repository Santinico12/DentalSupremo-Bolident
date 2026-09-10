<?php
/**
 * Modelo Tratamiento
 * Catálogo de servicios y tratamientos dentales
 */
class Tratamiento {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener todos los tratamientos activos
     */
    public function getAll() {
        $sql = "SELECT * FROM tratamientos WHERE activo = 1 ORDER BY categoria, nombre";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener por categoría
     */
    public function getByCategoria($categoria) {
        $sql = "SELECT * FROM tratamientos WHERE categoria = :cat AND activo = 1 ORDER BY nombre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':cat' => $categoria]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener categorías disponibles
     */
    public function getCategorias() {
        $sql = "SELECT DISTINCT categoria FROM tratamientos WHERE activo = 1 ORDER BY categoria";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Obtener por ID
     */
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM tratamientos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar tratamientos
     */
    public function buscar($termino) {
        $sql = "SELECT * FROM tratamientos 
                WHERE activo = 1 AND (nombre LIKE :term OR codigo LIKE :term OR descripcion LIKE :term)
                ORDER BY nombre LIMIT 20";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':term' => "%{$termino}%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crear tratamiento
     */
    public function crear($data) {
        $codigo = !empty($data['codigo']) ? $data['codigo'] : ('TRAT' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
        $sql = "INSERT INTO tratamientos (codigo, nombre, descripcion, precio, categoria)
                VALUES (:codigo, :nombre, :descripcion, :precio, :categoria)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? '',
            ':precio' => $data['precio'],
            ':categoria' => $data['categoria'] ?? 'General'
        ]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Actualizar tratamiento
     */
    public function actualizar($id, $data) {
        if (empty($data['codigo'])) {
            $existente = $this->getById($id);
            $codigo = !empty($existente['codigo']) ? $existente['codigo'] : ('TRAT' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT));
        } else {
            $codigo = $data['codigo'];
        }
        $sql = "UPDATE tratamientos SET codigo = :codigo, nombre = :nombre, 
                descripcion = :descripcion, precio = :precio, categoria = :categoria WHERE id = :id";
        return $this->pdo->prepare($sql)->execute([
            ':codigo' => $codigo,
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? '',
            ':precio' => $data['precio'],
            ':categoria' => $data['categoria'] ?? 'General',
            ':id' => $id
        ]);
    }

    /**
     * Desactivar tratamiento
     */
    public function desactivar($id) {
        return $this->pdo->prepare("UPDATE tratamientos SET activo = 0 WHERE id = :id")->execute([':id' => $id]);
    }

    /**
     * Activar tratamiento
     */
    public function activar($id) {
        return $this->pdo->prepare("UPDATE tratamientos SET activo = 1 WHERE id = :id")->execute([':id' => $id]);
    }
}
?>
