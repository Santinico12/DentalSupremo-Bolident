<?php
class Client {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll()
    {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $stmt = $this->pdo->query('SELECT id, nombre, telefono FROM clientes ORDER BY nombre ASC');
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return [];
    }

    public function getById($id)
    {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $stmt = $this->pdo->prepare('SELECT id, nombre, telefono, created_at FROM clientes WHERE id = :id');
                $stmt->execute([':id' => $id]);
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return false;
    }

    public function create($nombre, $telefono) {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $sql = "INSERT INTO clientes (nombre, telefono) VALUES (:nombre, :telefono)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':telefono', $telefono);
                return $stmt->execute();
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return false;
    }

    // public function getAll() {
    //     $stmt = $this->pdo->query('SELECT * FROM clientes ORDER BY nombre ASC');
    //     return $stmt->fetchAll();
    // }

    public function search($search) {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Preparar la consulta SQL para buscar coincidencias parciales en nombre o teléfono
                $stmt = $this->pdo->prepare('SELECT id, nombre, telefono FROM clientes WHERE nombre LIKE :search_nombre OR telefono LIKE :search_telefono ORDER BY nombre ASC');
                
                // Formatear el parámetro de búsqueda con comodines para la cláusula LIKE
                $searchParam = '%' . $search . '%';
                
                // Ejecutar la consulta con los parámetros correctamente vinculados
                $stmt->execute([
                    'search_nombre' => $searchParam,
                    'search_telefono' => $searchParam
                ]);
                
                // Devolver los resultados como un array asociativo
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return [];
    }
    
    public function update($id, $nombre,$telefono) {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $stmt = $this->pdo->prepare('UPDATE clientes SET nombre = ?, telefono = ? WHERE id = ?');
                return $stmt->execute([$nombre, $telefono, $id]);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return false;
    }

    public function delete($id)
    {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Iniciar una transacción
                $this->pdo->beginTransaction();

                // Eliminar las citas asociadas al cliente
                $stmt = $this->pdo->prepare('DELETE FROM citas WHERE cliente_id = ?');
                $stmt->execute([$id]);

                // Eliminar el cliente
                $stmt = $this->pdo->prepare('DELETE FROM clientes WHERE id = ?');
                $stmt->execute([$id]);

                // Confirmar la transacción
                $this->pdo->commit();

                return true;
            } catch (PDOException $e) {
                // Revertir la transacción en caso de error
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                // Error 1615: Prepared statement needs to be re-prepared - reintentar
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000); // Esperar 100ms antes de reintentar
                    continue;
                }
                throw $e;
            }
        }
        return false;
    }

    //metodo findbyphone
    public function findByPhone($telefono) {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM clientes WHERE telefono = :telefono");
                $stmt->bindParam(':telefono', $telefono);
                $stmt->execute();
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return false;
    }

    //metodo deletebyphone
    public function deleteByPhone($telefono) {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM clientes WHERE telefono = :telefono");
                $stmt->bindParam(':telefono', $telefono);
                return $stmt->execute();
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return false;
    }

    /**
     * Obtener lista de clientes paginada desde MySQL (Server-Side)
     */
    public function getPaginated($start = 0, $length = 15, $search = '', $orderColIndex = 0, $orderDir = 'asc') {
        $columnsMap = [
            0 => 'nombre',
            1 => 'telefono',
            2 => 'created_at'
        ];
        
        $col = $columnsMap[$orderColIndex] ?? 'nombre';
        $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $start = max(0, (int)$start);
        $length = max(1, (int)$length);

        $where = "";
        $params = [];

        if (!empty($search)) {
            $where = "WHERE nombre LIKE :search_nombre OR telefono LIKE :search_telefono";
            $params[':search_nombre'] = '%' . $search . '%';
            $params[':search_telefono'] = '%' . $search . '%';
        }

        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $sql = "SELECT id, nombre, telefono, created_at 
                        FROM clientes 
                        $where 
                        ORDER BY $col $dir 
                        LIMIT :limit OFFSET :offset";
                
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $key => $val) {
                    $stmt->bindValue($key, $val, PDO::PARAM_STR);
                }
                $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return [];
    }

    /**
     * Obtener el total absoluto y el total filtrado de clientes
     */
    public function getPaginatedCounts($search = '') {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Total absoluto
                $totalStmt = $this->pdo->query("SELECT COUNT(*) FROM clientes");
                $recordsTotal = (int)$totalStmt->fetchColumn();

                if (empty($search)) {
                    return [
                        'recordsTotal' => $recordsTotal,
                        'recordsFiltered' => $recordsTotal
                    ];
                }

                // Total filtrado con búsqueda
                $filteredStmt = $this->pdo->prepare("SELECT COUNT(*) FROM clientes WHERE nombre LIKE :search_nombre OR telefono LIKE :search_telefono");
                $searchParam = '%' . $search . '%';
                $filteredStmt->execute([
                    ':search_nombre' => $searchParam,
                    ':search_telefono' => $searchParam
                ]);
                $recordsFiltered = (int)$filteredStmt->fetchColumn();

                return [
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => $recordsFiltered
                ];
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1615 && $attempt < $maxRetries) {
                    usleep(100000);
                    continue;
                }
                throw $e;
            }
        }
        return ['recordsTotal' => 0, 'recordsFiltered' => 0];
    }
}
?>