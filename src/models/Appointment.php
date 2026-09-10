<?php
date_default_timezone_set('America/La_Paz'); // Cambia 'America/La_Paz' por tu zona horaria
class Appointment {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // public function create($cliente_id, $fecha, $descripcion, $duracion_estimada, $consultorio_id) {
    //     $stmt = $this->pdo->prepare('INSERT INTO citas (cliente_id, fecha, descripcion, duracion_estimada, consultorio_id) VALUES (?, ?, ?, ?, ?)');
    //     $stmt->execute([$cliente_id, $fecha, $descripcion, $duracion_estimada, $consultorio_id]);
    //     return $this->pdo->lastInsertId();
    // }
    public function create($cliente_id, $fecha, $descripcion, $duracion_estimada, $consultorio_id, $doctor_id = null)
    {
        // Asegurarse de que la fecha tenga el formato correcto
        $fechaHoraInicio = date('Y-m-d H:i:s', strtotime($fecha)); // Fecha completa con hora
        $horaInicio = date('H:i:s', strtotime($fecha)); // Extraer solo la hora de inicio
        $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio . " + $duracion_estimada minutes")); // Sumar duración

        // Insertar la cita con el campo finDeCita, estado y doctor_id
        $stmt = $this->pdo->prepare('INSERT INTO citas (cliente_id, fecha, descripcion, duracion_estimada, consultorio_id, finDeCita, estado, doctor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$cliente_id, $fechaHoraInicio, $descripcion, $duracion_estimada, $consultorio_id, $fechaHoraFin, 'activo', $doctor_id]);

        return $this->pdo->lastInsertId();
    }


    // public function getAll() {
    //     $stmt = $this->pdo->query('SELECT citas.*, clientes.nombre as cliente_nombre, consultorios.nombre as consultorio_nombre, consultorios.color as consultorio_color FROM citas JOIN clientes ON citas.cliente_id = clientes.id JOIN consultorios ON citas.consultorio_id = consultorios.id ORDER BY fecha ASC');
    //     return $stmt->fetchAll();
    // }
    public function getAll() {
        $stmt = $this->pdo->query('
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.id AS consultorio_id,
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, "Sin asignar") AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
            ORDER BY citas.fecha ASC
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateEstado($id, $estado)
    {
        $permitidos = ['activo', 'confirmado', 'pospuesto', 'cancelado'];
        if (!in_array($estado, $permitidos, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('UPDATE citas SET estado = :estado WHERE id = :id');
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function getUpcoming() {
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
              AND citas.fecha >= NOW()
              AND (citas.estado IN ('activo','confirmado') OR citas.estado IS NULL)
            ORDER BY citas.fecha ASC
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByDateRange($fecha_inicio, $fecha_fin) {
        $stmt = $this->pdo->prepare('
            SELECT 
                citas.*, 
                clientes.nombre as cliente_nombre,
                clientes.telefono as telefono,
                consultorios.nombre as consultorio_nombre,
                consultorios.color as consultorio_color,
                consultorios.id as consultorio_id,
                COALESCE(doctores.nombre, "Sin asignar") AS doctor_nombre
            FROM citas 
            JOIN clientes ON citas.cliente_id = clientes.id 
            JOIN consultorios ON citas.consultorio_id = consultorios.id 
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1 AND fecha BETWEEN ? AND ? 
            ORDER BY fecha ASC
        ');
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getByDateRangeAndConsultorio($fecha_inicio, $fecha_fin, $consultorio_id) {
        $stmt = $this->pdo->prepare('
            SELECT 
                citas.*, 
                clientes.nombre as cliente_nombre,
                clientes.telefono as telefono,
                consultorios.nombre as consultorio_nombre,
                consultorios.color as consultorio_color,
                consultorios.id as consultorio_id,
                COALESCE(doctores.nombre, "Sin asignar") AS doctor_nombre
            FROM citas 
            JOIN clientes ON citas.cliente_id = clientes.id 
            JOIN consultorios ON citas.consultorio_id = consultorios.id 
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1 AND fecha BETWEEN ? AND ? AND citas.consultorio_id = ? 
            ORDER BY fecha ASC
        ');
        $stmt->execute([$fecha_inicio, $fecha_fin, $consultorio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update($id, $fecha, $descripcion, $duracion_estimada, $consultorio_id, $doctor_id = null) {
        $fechaHoraInicio = date('Y-m-d H:i:s', strtotime($fecha));
        $duracion = !empty($duracion_estimada) ? (int)$duracion_estimada : 30;
        $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio . " + $duracion minutes"));
        $doctorIdVal = (!empty($doctor_id) && $doctor_id !== '0') ? (int)$doctor_id : null;

        $stmt = $this->pdo->prepare('
            UPDATE citas 
            SET fecha = ?, 
                descripcion = ?, 
                duracion_estimada = ?, 
                consultorio_id = ?, 
                doctor_id = ?, 
                finDeCita = ?,
                estado = "activo",
                recordatorio_enviado = 0,
                fecha_recordatorio = NULL
            WHERE id = ?
        ');
        return $stmt->execute([$fechaHoraInicio, $descripcion, $duracion, $consultorio_id, $doctorIdVal, $fechaHoraFin, $id]);
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM citas WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Verificar si existe conflicto de horario con citas o eventos en el mismo consultorio
     */
    public function checkConflict($consultorioId, $fechaHoraInicio, $duracionEstimada, $excludeCitaId = null) {
        $fecha = date('Y-m-d', strtotime($fechaHoraInicio));
        $horaInicio = date('H:i:s', strtotime($fechaHoraInicio));
        $duracion = !empty($duracionEstimada) ? (int)$duracionEstimada : 30;
        $horaFin = date('H:i:s', strtotime($fechaHoraInicio . " + $duracion minutes"));

        // 1. Consultar citas activas/confirmadas en el mismo consultorio y mismo día
        $sql = "SELECT citas.*, clientes.nombre as cliente_nombre 
                FROM citas 
                JOIN clientes ON citas.cliente_id = clientes.id
                WHERE citas.consultorio_id = :consultorio_id 
                AND DATE(citas.fecha) = :fecha 
                AND (citas.estado IS NULL OR citas.estado IN ('activo', 'confirmado'))";
        
        if ($excludeCitaId) {
            $sql .= " AND citas.id != :exclude_id";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':consultorio_id', (int)$consultorioId, PDO::PARAM_INT);
        $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        if ($excludeCitaId) {
            $stmt->bindValue(':exclude_id', (int)$excludeCitaId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($citas as $cita) {
            $citaInicio = date('H:i:s', strtotime($cita['fecha']));
            $citaFin = date('H:i:s', strtotime($cita['finDeCita']));

            // Solapamiento: (horaInicio < citaFin) && (horaFin > citaInicio)
            if (($horaInicio < $citaFin) && ($horaFin > $citaInicio)) {
                return [
                    'conflict' => true,
                    'type' => 'cita',
                    'nombre' => $cita['cliente_nombre'],
                    'hora_inicio' => date('H:i', strtotime($cita['fecha'])),
                    'hora_fin' => date('H:i', strtotime($cita['finDeCita']))
                ];
            }
        }

        // 2. Consultar eventos en el mismo consultorio y día
        $sqlEv = "SELECT * FROM eventos WHERE consultorio_id = :consultorio_id AND DATE(fecha) = :fecha";
        $stmtEv = $this->pdo->prepare($sqlEv);
        $stmtEv->bindValue(':consultorio_id', (int)$consultorioId, PDO::PARAM_INT);
        $stmtEv->bindValue(':fecha', $fecha, PDO::PARAM_STR);
        $stmtEv->execute();
        $eventos = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

        foreach ($eventos as $evento) {
            $evInicio = date('H:i:s', strtotime($evento['fecha']));
            $evFin = date('H:i:s', strtotime($evento['finDeEvento']));

            if (($horaInicio < $evFin) && ($horaFin > $evInicio)) {
                return [
                    'conflict' => true,
                    'type' => 'evento',
                    'nombre' => $evento['nombre'],
                    'hora_inicio' => date('H:i', strtotime($evento['fecha'])),
                    'hora_fin' => date('H:i', strtotime($evento['finDeEvento']))
                ];
            }
        }

        return ['conflict' => false];
    }
    


    //nuevo
    public function getByDate($fecha) {
        $stmt = $this->pdo->prepare('SELECT citas.*, clientes.nombre as cliente_nombre, consultorios.nombre as consultorio_nombre, consultorios.color as consultorio_color FROM citas JOIN clientes ON citas.cliente_id = clientes.id JOIN consultorios ON citas.consultorio_id = consultorios.id WHERE consultorios.activo = 1 AND DATE(fecha) = ? ORDER BY fecha ASC');
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare('SELECT citas.*, clientes.nombre as cliente_nombre FROM citas JOIN clientes ON citas.cliente_id = clientes.id WHERE citas.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function isTimeSlotAvailable($consultorioId, $fecha, $horaInicio, $duracion)
    {
        // Calcular la hora de fin de la nueva cita
        $horaFin = date('H:i:s', strtotime($horaInicio . " + $duracion minutes"));

        // Consulta SQL para validar solapamientos
        $query = "SELECT COUNT(*) as count FROM citas 
                WHERE consultorio_id = :consultorio_id 
                AND DATE(fecha) = :fecha 
                AND (estado IS NULL OR estado NOT IN ('cancelado','pospuesto'))
                AND (
                    (TIME(fecha) < :hora_fin AND TIME(finDeCita) > :hora_inicio)
                )";

        // Preparar la consulta
        $stmt = $this->pdo->prepare($query);

        // Vincular los parÃ¡metros
        $stmt->bindParam(':consultorio_id', $consultorioId, PDO::PARAM_INT);
        $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->bindParam(':hora_inicio', $horaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':hora_fin', $horaFin, PDO::PARAM_STR);

        // Ejecutar la consulta
        $stmt->execute();

        // Obtener el resultado
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Retornar true si no hay conflictos, false si hay conflictos
        return $result['count'] == 0;
    }

    public function getCitasByDateAndConsultorio($fecha, $consultorioId)
    {
        $query = "SELECT * FROM citas 
                WHERE consultorio_id = :consultorio_id 
                AND DATE(fecha) = :fecha 
                ORDER BY fecha ASC";

        $stmt = $this->pdo->prepare($query);
        $stmt->bindParam(':consultorio_id', $consultorioId, PDO::PARAM_INT);
        $stmt->bindParam(':fecha', $fecha, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateDescripcion($id, $descripcion)
    {
        $stmt = $this->pdo->prepare('UPDATE citas SET descripcion = :descripcion WHERE id = :id');
        $stmt->bindParam(':descripcion', $descripcion, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /* Obtener citas por hoy, mañana y semana */
    public function getToday() {
        $today = date('Y-m-d');
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
              AND DATE(citas.fecha) = :today
              AND citas.fecha >= NOW()
              AND (citas.estado IN ('activo','confirmado') OR citas.estado IS NULL)
            ORDER BY citas.fecha ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':today', $today, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTomorrow() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
              AND DATE(citas.fecha) = :tomorrow
              AND (citas.estado IN ('activo','confirmado') OR citas.estado IS NULL)
            ORDER BY citas.fecha ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tomorrow', $tomorrow, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getThisWeek() {
        $startOfWeek = date('Y-m-d', strtotime('monday this week'));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
        $now = date('Y-m-d H:i:s');
        
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
              AND DATE(citas.fecha) BETWEEN :start AND :end
              AND citas.fecha >= :now
              AND (citas.estado IN ('activo','confirmado') OR citas.estado IS NULL)
            ORDER BY citas.fecha ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':start', $startOfWeek, PDO::PARAM_STR);
        $stmt->bindParam(':end', $endOfWeek, PDO::PARAM_STR);
        $stmt->bindParam(':now', $now, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener todas las citas para recordatorios (tanto pendientes como ya enviadas) para una fecha dada
     * @param string|null $fecha YYYY-MM-DD
     * @return array
     */
    public function getCitasParaRecordatorios($fecha = null) {
        $targetDate = !empty($fecha) ? $fecha : date('Y-m-d', strtotime('+1 day'));
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
              AND DATE(citas.fecha) = :targetDate
              AND (citas.estado IN ('activo','confirmado','pospuesto') OR citas.estado IS NULL)
            ORDER BY citas.recordatorio_enviado ASC, citas.fecha ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':targetDate', $targetDate, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener citas que no tienen recordatorio enviado para una fecha dada (por defecto mañana)
     * @param string|null $fecha YYYY-MM-DD
     * @return array
     */
    public function getCitasPendientesRecordatorio($fecha = null) {
        $targetDate = !empty($fecha) ? $fecha : date('Y-m-d', strtotime('+1 day'));
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE consultorios.activo = 1
              AND DATE(citas.fecha) = :targetDate
              AND (citas.estado IN ('activo','confirmado','pospuesto') OR citas.estado IS NULL)
              AND (citas.recordatorio_enviado = 0 OR citas.recordatorio_enviado IS NULL)
            ORDER BY citas.fecha ASC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':targetDate', $targetDate, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marcar una cita como recordatorio enviado
     * @param int $id
     * @return bool
     */
    public function marcarRecordatorioEnviado($id) {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE citas SET recordatorio_enviado = 1, fecha_recordatorio = :fecha WHERE id = :id');
        $stmt->bindParam(':fecha', $now, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Contar citas pendientes de recordatorio para mañana
     * @return int
     */
    public function contarRecordatoriosPendientes() {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $sql = "
            SELECT COUNT(*) as total
            FROM citas
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1
              AND DATE(citas.fecha) = :tomorrow
              AND (citas.estado IN ('activo','confirmado') OR citas.estado IS NULL)
              AND (citas.recordatorio_enviado = 0 OR citas.recordatorio_enviado IS NULL)
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tomorrow', $tomorrow, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }

    /**
     * Obtener todas las citas de un cliente por su ID
     * @param int $clienteId
     * @return array
     */
    public function getByClientId($clienteId) {
        $sql = "
            SELECT 
                citas.*, 
                clientes.nombre AS cliente_nombre, 
                clientes.telefono AS telefono, 
                consultorios.nombre AS consultorio_nombre, 
                consultorios.color AS consultorio_color,
                COALESCE(doctores.nombre, 'Sin asignar') AS doctor_nombre
            FROM citas
            JOIN clientes ON citas.cliente_id = clientes.id
            JOIN consultorios ON citas.consultorio_id = consultorios.id
            LEFT JOIN doctores ON citas.doctor_id = doctores.id
            WHERE citas.cliente_id = :cliente_id
            ORDER BY citas.fecha DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':cliente_id', $clienteId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>




