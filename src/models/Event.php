<?php

class Event {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Obtener todos los eventos (solo de consultorios activos)
    public function getAll() {
        $stmt = $this->pdo->query("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1
            ORDER BY eventos.fecha ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener un evento por ID
    public function getById($id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE eventos.id = ?
        ");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener eventos por fecha (solo de consultorios activos)
    public function getByDate($fecha) {
        $stmt = $this->pdo->prepare("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1 AND DATE(eventos.fecha) = ?
            ORDER BY eventos.fecha ASC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener eventos por rango de fechas (solo de consultorios activos)
    public function getByDateRange($fecha_inicio, $fecha_fin) {
        $stmt = $this->pdo->prepare("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1 AND eventos.fecha BETWEEN ? AND ?
            ORDER BY eventos.fecha ASC
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener eventos por rango de fechas y consultorio (solo de consultorios activos)
    public function getByDateRangeAndConsultorio($fecha_inicio, $fecha_fin, $consultorio_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1 AND eventos.fecha BETWEEN ? AND ? AND eventos.consultorio_id = ?
            ORDER BY eventos.fecha ASC
        ");
        $stmt->execute([$fecha_inicio, $fecha_fin, (int)$consultorio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener eventos por consultorio (solo de consultorios activos)
    public function getByConsultorio($consultorio_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1 AND eventos.consultorio_id = ?
            ORDER BY eventos.fecha ASC
        ");
        $stmt->execute([(int)$consultorio_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener eventos por consultorio y fecha (solo de consultorios activos)
    public function getByConsultorioAndDate($consultorio_id, $fecha) {
        $stmt = $this->pdo->prepare("
            SELECT 
                eventos.*, 
                consultorios.nombre AS consultorio_nombre,
                consultorios.color AS consultorio_color
            FROM eventos
            JOIN consultorios ON eventos.consultorio_id = consultorios.id
            WHERE consultorios.activo = 1 AND eventos.consultorio_id = ? AND DATE(eventos.fecha) = ?
            ORDER BY eventos.fecha ASC
        ");
        $stmt->execute([(int)$consultorio_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear un nuevo evento
    public function create($nombre, $descripcion, $consultorio_id, $fecha, $duracion_estimada, $color = '#808080') {
        $nombre = trim((string)$nombre);
        
        if (empty($nombre)) {
            throw new Exception("El nombre del evento es obligatorio.");
        }

        if (empty($fecha)) {
            throw new Exception("La fecha es obligatoria.");
        }

        // Normalizar la fecha: Reemplazar 'T' con espacio para formato ISO
        $fechaFormato = str_replace('T', ' ', $fecha);
        
        // Calcular hora de fin
        $fechaHoraInicio = date('Y-m-d H:i:s', strtotime($fechaFormato));
        $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio . " + $duracion_estimada minutes"));

        // Normalizar descripción
        $descripcion = preg_replace("/[\r\n]+/u", ' ', (string)$descripcion);
        $descripcion = preg_replace('/\s{2,}/u', ' ', trim($descripcion));

        $stmt = $this->pdo->prepare(
            "INSERT INTO eventos (nombre, descripcion, consultorio_id, fecha, finDeEvento, duracion_estimada, color, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$nombre, $descripcion, $consultorio_id, $fechaHoraInicio, $fechaHoraFin, $duracion_estimada, $color]);
        
        return $this->pdo->lastInsertId();
    }

    // Actualizar un evento
    public function update($id, $nombre, $descripcion, $consultorio_id, $fecha, $duracion_estimada, $color) {
        $id = (int)$id;
        $nombre = trim((string)$nombre);
        
        if (empty($nombre)) {
            throw new Exception("El nombre del evento es obligatorio.");
        }

        // Normalizar la fecha: Reemplazar 'T' con espacio para formato ISO
        $fechaFormato = str_replace('T', ' ', $fecha);

        // Calcular hora de fin
        $fechaHoraInicio = date('Y-m-d H:i:s', strtotime($fechaFormato));
        $fechaHoraFin = date('Y-m-d H:i:s', strtotime($fechaHoraInicio . " + $duracion_estimada minutes"));

        // Normalizar descripción
        $descripcion = preg_replace("/[\r\n]+/u", ' ', (string)$descripcion);
        $descripcion = preg_replace('/\s{2,}/u', ' ', trim($descripcion));

        $stmt = $this->pdo->prepare(
            "UPDATE eventos SET nombre = ?, descripcion = ?, consultorio_id = ?, fecha = ?, finDeEvento = ?, duracion_estimada = ?, color = ? WHERE id = ?"
        );
        return $stmt->execute([$nombre, $descripcion, $consultorio_id, $fechaHoraInicio, $fechaHoraFin, $duracion_estimada, $color, $id]);
    }

    // Eliminar un evento
    public function delete($id) {
        $id = (int)$id;
        $stmt = $this->pdo->prepare("DELETE FROM eventos WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // Verificar disponibilidad de horario
    public function isTimeSlotAvailable($consultorio_id, $fecha, $hora_inicio, $duracion) {
        $hora_fin = date('H:i:s', strtotime($hora_inicio . " + $duracion minutes"));
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM eventos 
            WHERE consultorio_id = ? 
            AND DATE(fecha) = ?
            AND (
                (TIME(fecha) < ? AND TIME(finDeEvento) > ?)
            )
        ");
        $stmt->execute([(int)$consultorio_id, date('Y-m-d', strtotime($fecha)), $hora_fin, $hora_inicio]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] == 0;
    }

    // Obtener eventos por fecha y consultorio
    public function getEventosByDateAndConsultorio($fecha, $consultorio_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM eventos 
            WHERE consultorio_id = ? 
            AND DATE(fecha) = ? 
            ORDER BY fecha ASC
        ");
        $stmt->execute([(int)$consultorio_id, $fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
