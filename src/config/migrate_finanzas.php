<?php
require_once __DIR__ . '/db.php';

try {
    // Tabla de Egresos
    $sqlEgresos = "CREATE TABLE IF NOT EXISTS `egresos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(255) NOT NULL,
        `monto` DECIMAL(10,2) NOT NULL,
        `descripcion` TEXT DEFAULT NULL,
        `comprobante_ruta` VARCHAR(500) DEFAULT NULL,
        `comprobante_tipo` VARCHAR(50) DEFAULT NULL,
        `realizado_por` VARCHAR(255) NOT NULL,
        `fecha` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $pdo->exec($sqlEgresos);
    echo "Tabla 'egresos' creada con éxito.\n";

    // Tabla de Otros Ingresos
    $sqlOtrosIngresos = "CREATE TABLE IF NOT EXISTS `otros_ingresos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nombre` VARCHAR(255) NOT NULL,
        `monto` DECIMAL(10,2) NOT NULL,
        `descripcion` TEXT DEFAULT NULL,
        `metodo_pago` ENUM('efectivo', 'qr', 'transferencia') NOT NULL DEFAULT 'efectivo',
        `comprobante_ruta` VARCHAR(500) DEFAULT NULL,
        `comprobante_tipo` VARCHAR(50) DEFAULT NULL,
        `registrado_por` VARCHAR(255) NOT NULL,
        `fecha` DATETIME NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $pdo->exec($sqlOtrosIngresos);
    echo "Tabla 'otros_ingresos' creada con éxito.\n";

} catch (PDOException $e) {
    echo "Error de migración: " . $e->getMessage() . "\n";
}
?>
