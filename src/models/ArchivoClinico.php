<?php
/**
 * Modelo ArchivoClinico
 * Gestiona radiografías, fotos y documentos adjuntos con compresión inteligente de imágenes.
 */
class ArchivoClinico {
    private $pdo;
    private $uploadDir = 'uploads/historias_clinicas/';

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener archivos de un paciente
     */
    public function getByCliente($clienteId) {
        $sql = "SELECT * FROM archivos_clinicos WHERE cliente_id = :id ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener archivos por tipo
     */
    public function getByTipo($clienteId, $tipo) {
        $sql = "SELECT * FROM archivos_clinicos WHERE cliente_id = :id AND tipo = :tipo ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $clienteId, ':tipo' => $tipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Subir archivo con compresión inteligente de imágenes sin pérdida visual de calidad
     */
    public function subir($clienteId, $file, $data = []) {
        // Crear directorio si no existe
        $clienteDir = $this->uploadDir . $clienteId . '/';
        if (!is_dir($clienteDir)) {
            mkdir($clienteDir, 0777, true);
        }

        $mimeType = $file['type'] ?? '';
        if (empty($mimeType) && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }

        $extensionOriginal = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $isImagen = in_array($mimeType, $allowedImageTypes) || in_array($extensionOriginal, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

        // Si es una imagen, usar WebP o mantener la extensión procesada
        $extensionFinal = ($isImagen && function_exists('imagewebp')) ? 'webp' : $extensionOriginal;
        $nombreArchivo = date('Ymd_His') . '_' . uniqid() . '.' . $extensionFinal;
        $rutaCompleta = $clienteDir . $nombreArchivo;

        $uploadSuccess = false;

        if ($isImagen) {
            // Procesar y comprimir la imagen sin perder calidad de diagnóstico dental
            $uploadSuccess = $this->comprimirImagen($file['tmp_name'], $rutaCompleta, $mimeType, 2000, 82);
            if (!$uploadSuccess) {
                // Fallback a movimiento de archivo directo si falla el procesamiento gráfico
                $rutaCompleta = $clienteDir . date('Ymd_His') . '_' . uniqid() . '.' . $extensionOriginal;
                $extensionFinal = $extensionOriginal;
                $uploadSuccess = move_uploaded_file($file['tmp_name'], $rutaCompleta);
            }
        } else {
            // Para archivos PDF y documentos no-imagen
            $uploadSuccess = move_uploaded_file($file['tmp_name'], $rutaCompleta);
        }

        if ($uploadSuccess && file_exists($rutaCompleta)) {
            $tamanoFinal = filesize($rutaCompleta);

            // Guardar registro en la Base de Datos
            $sql = "INSERT INTO archivos_clinicos (cliente_id, evolucion_id, tipo, nombre_archivo, ruta_archivo, extension, tamano_bytes, descripcion, fecha_toma, dientes_relacionados)
                    VALUES (:cliente_id, :evolucion_id, :tipo, :nombre, :ruta, :ext, :tamano, :descripcion, :fecha_toma, :dientes)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':cliente_id' => $clienteId,
                ':evolucion_id' => $data['evolucion_id'] ?? null,
                ':tipo' => $data['tipo'] ?? 'otro',
                ':nombre' => $file['name'],
                ':ruta' => $rutaCompleta,
                ':ext' => $extensionFinal,
                ':tamano' => $tamanoFinal,
                ':descripcion' => $data['descripcion'] ?? null,
                ':fecha_toma' => $data['fecha_toma'] ?? date('Y-m-d'),
                ':dientes' => $data['dientes_relacionados'] ?? null
            ]);
            
            return $this->pdo->lastInsertId();
        }
        
        return false;
    }

    /**
     * Motor de Compresión Inteligente de Imágenes Médicas / Clínicas
     * Redimensiona proporcionalmente si supera 2000px y optimiza en WebP/JPEG a calidad 82 (80-90% ahorro de espacio).
     */
    private function comprimirImagen($sourcePath, $destinationPath, $mimeType, $maxDimension = 2000, $calidad = 82) {
        if (!file_exists($sourcePath)) return false;

        // Cargar recurso GD según tipo de imagen
        $image = null;
        if (preg_match('/jpeg|jpg/i', $mimeType) || preg_match('/\.(jpg|jpeg)$/i', $sourcePath)) {
            $image = @imagecreatefromjpeg($sourcePath);
        } elseif (preg_match('/png/i', $mimeType) || preg_match('/\.png$/i', $sourcePath)) {
            $image = @imagecreatefrompng($sourcePath);
        } elseif (preg_match('/webp/i', $mimeType) || preg_match('/\.webp$/i', $sourcePath)) {
            $image = @imagecreatefromwebp($sourcePath);
        } elseif (preg_match('/gif/i', $mimeType) || preg_match('/\.gif$/i', $sourcePath)) {
            $image = @imagecreatefromgif($sourcePath);
        }

        if (!$image) return false;

        // Dimensiones originales
        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        // Escalar proporcionalmente si supera el límite de diagnóstico (2000px)
        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
            if ($origWidth >= $origHeight) {
                $newWidth = $maxDimension;
                $newHeight = (int)round(($origHeight / $origWidth) * $maxDimension);
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int)round(($origWidth / $origHeight) * $maxDimension);
            }
        }

        // Crear nuevo lienzo con la dimensión adecuada
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preservar transparencia para PNG / WebP
        if (preg_match('/png|webp/i', $mimeType)) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        } else {
            $white = imagecolorallocate($resizedImage, 255, 255, 255);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $white);
        }

        // Copiar y resamplear en alta resolución bicúbica
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Guardar la imagen comprimida
        $destExt = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));
        $success = false;

        if ($destExt === 'webp' && function_exists('imagewebp')) {
            $success = @imagewebp($resizedImage, $destinationPath, $calidad);
        } elseif ($destExt === 'png') {
            $success = @imagepng($resizedImage, $destinationPath, 6);
        } else {
            $success = @imagejpeg($resizedImage, $destinationPath, $calidad);
        }

        imagedestroy($image);
        imagedestroy($resizedImage);

        return $success;
    }

    /**
     * Actualizar descripción/nombre del archivo clínico
     */
    public function actualizar($id, $data = []) {
        $sql = "UPDATE archivos_clinicos 
                SET descripcion = :descripcion, tipo = :tipo, fecha_toma = :fecha_toma 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':descripcion' => $data['descripcion'] ?? '',
            ':tipo' => $data['tipo'] ?? 'otro',
            ':fecha_toma' => $data['fecha_toma'] ?? date('Y-m-d'),
            ':id' => $id
        ]);
    }

    /**
     * Eliminar archivo
     */
    public function eliminar($id) {
        $stmt = $this->pdo->prepare("SELECT ruta_archivo FROM archivos_clinicos WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($archivo && file_exists($archivo['ruta_archivo'])) {
            unlink($archivo['ruta_archivo']);
        }
        
        return $this->pdo->prepare("DELETE FROM archivos_clinicos WHERE id = :id")->execute([':id' => $id]);
    }

    /**
     * Contar archivos de un paciente
     */
    public function contar($clienteId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM archivos_clinicos WHERE cliente_id = :id");
        $stmt->execute([':id' => $clienteId]);
        return $stmt->fetchColumn();
    }
}
?>
