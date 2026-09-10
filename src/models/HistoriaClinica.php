<?php
/**
 * Modelo HistoriaClinica
 * Gestiona antecedentes médicos, odontológicos, diagnóstico y firma del paciente
 * Formato oficial del Colegio / Ministerio de Odontología de Bolivia
 */
class HistoriaClinica {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->asegurarColumnas();
    }

    /**
     * Asegura automáticamente que todas las columnas del formato oficial existan en la tabla historia_clinica
     */
    public function asegurarColumnas() {
        try {
            $colsExistentes = $this->pdo->query("SHOW COLUMNS FROM historia_clinica")->fetchAll(PDO::FETCH_COLUMN);
            $nuevasColumnas = [
                'ci' => "VARCHAR(30) NULL DEFAULT NULL",
                'numero_hc' => "VARCHAR(50) NULL DEFAULT NULL",
                'codificacion' => "VARCHAR(50) NULL DEFAULT NULL",
                'lugar_nacimiento' => "VARCHAR(150) NULL DEFAULT NULL",
                'edad' => "INT NULL DEFAULT NULL",
                'antecedentes_familiares' => "TEXT NULL",
                'patologias_personales' => "TEXT NULL",
                'en_tratamiento_medico' => "VARCHAR(255) NULL DEFAULT NULL",
                'toma_medicamento' => "VARCHAR(255) NULL DEFAULT NULL",
                'hemorragia_extraccion' => "VARCHAR(50) NULL DEFAULT NULL",
                'atm' => "VARCHAR(255) NULL DEFAULT NULL",
                'ganglios_linfaticos' => "VARCHAR(255) NULL DEFAULT NULL",
                'tipo_respirador' => "VARCHAR(50) NULL DEFAULT NULL",
                'examen_extraoral_otros' => "TEXT NULL",
                'labios' => "VARCHAR(255) NULL DEFAULT NULL",
                'lengua' => "VARCHAR(255) NULL DEFAULT NULL",
                'paladar' => "VARCHAR(255) NULL DEFAULT NULL",
                'piso_boca' => "VARCHAR(255) NULL DEFAULT NULL",
                'mucosa_yugal' => "VARCHAR(255) NULL DEFAULT NULL",
                'encias' => "VARCHAR(255) NULL DEFAULT NULL",
                'usa_protesis' => "TINYINT(1) DEFAULT 0",
                'habitos_fuma' => "TINYINT(1) DEFAULT 0",
                'habitos_bebe' => "TINYINT(1) DEFAULT 0",
                'habitos_otros' => "VARCHAR(255) NULL DEFAULT NULL",
                'usa_cepillo' => "TINYINT(1) DEFAULT 1",
                'usa_hilo' => "TINYINT(1) DEFAULT 0",
                'usa_enjuague' => "TINYINT(1) DEFAULT 0",
                'frecuencia_cepillado' => "VARCHAR(100) NULL DEFAULT NULL",
                'sangrado_encias' => "TINYINT(1) DEFAULT 0",
                'nivel_higiene_bucal' => "VARCHAR(20) DEFAULT 'Buena'",
                'problema_grave_dental_anterior' => "TEXT NULL",
                'motivo_consulta' => "TEXT NULL",
                'examen_clinico' => "TEXT NULL",
                'diagnostico' => "TEXT NULL",
                'plan_tratamiento' => "TEXT NULL",
                'firma_paciente' => "LONGTEXT NULL",
                'fecha_firma' => "DATETIME NULL",
                'firma_token' => "VARCHAR(64) NULL"
            ];

            foreach ($nuevasColumnas as $col => $tipo) {
                if (!in_array($col, $colsExistentes)) {
                    $this->pdo->exec("ALTER TABLE historia_clinica ADD COLUMN `$col` $tipo");
                }
            }
        } catch (\Exception $e) {
            error_log("Error al asegurar columnas de historia_clinica: " . $e->getMessage());
        }
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if (empty($row['firma_token'])) {
                $token = bin2hex(random_bytes(16));
                $updateStmt = $this->pdo->prepare("UPDATE historia_clinica SET firma_token = :token WHERE id = :id");
                $updateStmt->execute([':token' => $token, ':id' => $row['id']]);
                $row['firma_token'] = $token;
            }
            if (empty($row['numero_hc'])) {
                $row['numero_hc'] = 'HC-' . str_pad($clienteId, 5, '0', STR_PAD_LEFT);
            }
        }

        return $row;
    }

    /**
     * Obtener historia clínica por token único de firma
     */
    public function getByToken($token) {
        if (!$token || strlen($token) < 10) return null;
        $sql = "SELECT h.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono
                FROM historia_clinica h
                JOIN clientes c ON h.cliente_id = c.id
                WHERE h.firma_token = :token LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':token' => $token]);
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
     * Crear historia clínica completa
     */
    public function crear($data) {
        $token = $data['firma_token'] ?? bin2hex(random_bytes(16));
        $numeroHc = $data['numero_hc'] ?? ('HC-' . str_pad($data['cliente_id'], 5, '0', STR_PAD_LEFT));

        $sql = "INSERT INTO historia_clinica (
                    cliente_id, ci, numero_hc, codificacion, fecha_nacimiento, lugar_nacimiento, edad, sexo, ocupacion, direccion, email,
                    contacto_emergencia_nombre, contacto_emergencia_telefono, contacto_emergencia_parentesco,
                    grupo_sanguineo, alergias, enfermedades_sistemicas, antecedentes_familiares, patologias_personales,
                    en_tratamiento_medico, toma_medicamento, hemorragia_extraccion,
                    medicamentos_actuales, cirugias_previas, hospitalizaciones, ultima_visita_dentista, experiencia_anestesia,
                    atm, ganglios_linfaticos, tipo_respirador, examen_extraoral_otros,
                    labios, lengua, paladar, piso_boca, mucosa_yugal, encias, usa_protesis,
                    habitos, habitos_fuma, habitos_bebe, habitos_otros,
                    higiene_bucal, usa_cepillo, usa_hilo, usa_enjuague, frecuencia_cepillado, sangrado_encias, nivel_higiene_bucal, problema_grave_dental_anterior,
                    embarazo, lactancia, observaciones,
                    motivo_consulta, examen_clinico, diagnostico, plan_tratamiento,
                    firma_paciente, fecha_firma, firma_token
                ) VALUES (
                    :cliente_id, :ci, :numero_hc, :codificacion, :fecha_nacimiento, :lugar_nacimiento, :edad, :sexo, :ocupacion, :direccion, :email,
                    :contacto_nombre, :contacto_telefono, :contacto_parentesco,
                    :grupo_sanguineo, :alergias, :enfermedades, :antecedentes_familiares, :patologias_personales,
                    :en_tratamiento_medico, :toma_medicamento, :hemorragia_extraccion,
                    :medicamentos, :cirugias, :hospitalizaciones, :ultima_visita, :experiencia_anestesia,
                    :atm, :ganglios_linfaticos, :tipo_respirador, :examen_extraoral_otros,
                    :labios, :lengua, :paladar, :piso_boca, :mucosa_yugal, :encias, :usa_protesis,
                    :habitos, :habitos_fuma, :habitos_bebe, :habitos_otros,
                    :higiene_bucal, :usa_cepillo, :usa_hilo, :usa_enjuague, :frecuencia_cepillado, :sangrado_encias, :nivel_higiene_bucal, :problema_grave_dental_anterior,
                    :embarazo, :lactancia, :observaciones,
                    :motivo_consulta, :examen_clinico, :diagnostico, :plan_tratamiento,
                    :firma_paciente, :fecha_firma, :firma_token
                )";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':cliente_id' => $data['cliente_id'],
            ':ci' => $data['ci'] ?? null,
            ':numero_hc' => $numeroHc,
            ':codificacion' => $data['codificacion'] ?? null,
            ':fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            ':lugar_nacimiento' => $data['lugar_nacimiento'] ?? null,
            ':edad' => !empty($data['edad']) ? intval($data['edad']) : null,
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
            ':antecedentes_familiares' => $data['antecedentes_familiares'] ?? null,
            ':patologias_personales' => is_array($data['patologias_personales'] ?? null) ? json_encode($data['patologias_personales'], JSON_UNESCAPED_UNICODE) : ($data['patologias_personales'] ?? null),
            ':en_tratamiento_medico' => $data['en_tratamiento_medico'] ?? null,
            ':toma_medicamento' => $data['toma_medicamento'] ?? null,
            ':hemorragia_extraccion' => $data['hemorragia_extraccion'] ?? null,
            ':medicamentos' => $data['medicamentos_actuales'] ?? null,
            ':cirugias' => $data['cirugias_previas'] ?? null,
            ':hospitalizaciones' => $data['hospitalizaciones'] ?? null,
            ':ultima_visita' => $data['ultima_visita_dentista'] ?? null,
            ':experiencia_anestesia' => $data['experiencia_anestesia'] ?? null,
            ':atm' => $data['atm'] ?? null,
            ':ganglios_linfaticos' => $data['ganglios_linfaticos'] ?? null,
            ':tipo_respirador' => $data['tipo_respirador'] ?? null,
            ':examen_extraoral_otros' => $data['examen_extraoral_otros'] ?? null,
            ':labios' => $data['labios'] ?? null,
            ':lengua' => $data['lengua'] ?? null,
            ':paladar' => $data['paladar'] ?? null,
            ':piso_boca' => $data['piso_boca'] ?? null,
            ':mucosa_yugal' => $data['mucosa_yugal'] ?? null,
            ':encias' => $data['encias'] ?? null,
            ':usa_protesis' => !empty($data['usa_protesis']) ? 1 : 0,
            ':habitos' => $data['habitos'] ?? null,
            ':habitos_fuma' => !empty($data['habitos_fuma']) ? 1 : 0,
            ':habitos_bebe' => !empty($data['habitos_bebe']) ? 1 : 0,
            ':habitos_otros' => $data['habitos_otros'] ?? null,
            ':higiene_bucal' => $data['higiene_bucal'] ?? null,
            ':usa_cepillo' => isset($data['usa_cepillo']) ? intval($data['usa_cepillo']) : 1,
            ':usa_hilo' => !empty($data['usa_hilo']) ? 1 : 0,
            ':usa_enjuague' => !empty($data['usa_enjuague']) ? 1 : 0,
            ':frecuencia_cepillado' => $data['frecuencia_cepillado'] ?? null,
            ':sangrado_encias' => !empty($data['sangrado_encias']) ? 1 : 0,
            ':nivel_higiene_bucal' => $data['nivel_higiene_bucal'] ?? 'Buena',
            ':problema_grave_dental_anterior' => $data['problema_grave_dental_anterior'] ?? null,
            ':embarazo' => !empty($data['embarazo']) ? 1 : 0,
            ':lactancia' => !empty($data['lactancia']) ? 1 : 0,
            ':observaciones' => $data['observaciones'] ?? null,
            ':motivo_consulta' => $data['motivo_consulta'] ?? null,
            ':examen_clinico' => $data['examen_clinico'] ?? null,
            ':diagnostico' => $data['diagnostico'] ?? null,
            ':plan_tratamiento' => $data['plan_tratamiento'] ?? null,
            ':firma_paciente' => $data['firma_paciente'] ?? null,
            ':fecha_firma' => !empty($data['firma_paciente']) ? (date('Y-m-d H:i:s')) : null,
            ':firma_token' => $token
        ]);
    }

    /**
     * Actualizar historia clínica completa
     */
    public function actualizar($clienteId, $data) {
        $sql = "UPDATE historia_clinica SET
                    ci = :ci,
                    numero_hc = :numero_hc,
                    codificacion = :codificacion,
                    fecha_nacimiento = :fecha_nacimiento,
                    lugar_nacimiento = :lugar_nacimiento,
                    edad = :edad,
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
                    antecedentes_familiares = :antecedentes_familiares,
                    patologias_personales = :patologias_personales,
                    en_tratamiento_medico = :en_tratamiento_medico,
                    toma_medicamento = :toma_medicamento,
                    hemorragia_extraccion = :hemorragia_extraccion,
                    medicamentos_actuales = :medicamentos,
                    cirugias_previas = :cirugias,
                    hospitalizaciones = :hospitalizaciones,
                    ultima_visita_dentista = :ultima_visita,
                    experiencia_anestesia = :experiencia_anestesia,
                    atm = :atm,
                    ganglios_linfaticos = :ganglios_linfaticos,
                    tipo_respirador = :tipo_respirador,
                    examen_extraoral_otros = :examen_extraoral_otros,
                    labios = :labios,
                    lengua = :lengua,
                    paladar = :paladar,
                    piso_boca = :piso_boca,
                    mucosa_yugal = :mucosa_yugal,
                    encias = :encias,
                    usa_protesis = :usa_protesis,
                    habitos = :habitos,
                    habitos_fuma = :habitos_fuma,
                    habitos_bebe = :habitos_bebe,
                    habitos_otros = :habitos_otros,
                    higiene_bucal = :higiene_bucal,
                    usa_cepillo = :usa_cepillo,
                    usa_hilo = :usa_hilo,
                    usa_enjuague = :usa_enjuague,
                    frecuencia_cepillado = :frecuencia_cepillado,
                    sangrado_encias = :sangrado_encias,
                    nivel_higiene_bucal = :nivel_higiene_bucal,
                    problema_grave_dental_anterior = :problema_grave_dental_anterior,
                    embarazo = :embarazo,
                    lactancia = :lactancia,
                    observaciones = :observaciones,
                    motivo_consulta = :motivo_consulta,
                    examen_clinico = :examen_clinico,
                    diagnostico = :diagnostico,
                    plan_tratamiento = :plan_tratamiento
                WHERE cliente_id = :cliente_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':cliente_id' => $clienteId,
            ':ci' => $data['ci'] ?? null,
            ':numero_hc' => $data['numero_hc'] ?? ('HC-' . str_pad($clienteId, 5, '0', STR_PAD_LEFT)),
            ':codificacion' => $data['codificacion'] ?? null,
            ':fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            ':lugar_nacimiento' => $data['lugar_nacimiento'] ?? null,
            ':edad' => !empty($data['edad']) ? intval($data['edad']) : null,
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
            ':antecedentes_familiares' => $data['antecedentes_familiares'] ?? null,
            ':patologias_personales' => is_array($data['patologias_personales'] ?? null) ? json_encode($data['patologias_personales'], JSON_UNESCAPED_UNICODE) : ($data['patologias_personales'] ?? null),
            ':en_tratamiento_medico' => $data['en_tratamiento_medico'] ?? null,
            ':toma_medicamento' => $data['toma_medicamento'] ?? null,
            ':hemorragia_extraccion' => $data['hemorragia_extraccion'] ?? null,
            ':medicamentos' => $data['medicamentos_actuales'] ?? null,
            ':cirugias' => $data['cirugias_previas'] ?? null,
            ':hospitalizaciones' => $data['hospitalizaciones'] ?? null,
            ':ultima_visita' => $data['ultima_visita_dentista'] ?? null,
            ':experiencia_anestesia' => $data['experiencia_anestesia'] ?? null,
            ':atm' => $data['atm'] ?? null,
            ':ganglios_linfaticos' => $data['ganglios_linfaticos'] ?? null,
            ':tipo_respirador' => $data['tipo_respirador'] ?? null,
            ':examen_extraoral_otros' => $data['examen_extraoral_otros'] ?? null,
            ':labios' => $data['labios'] ?? null,
            ':lengua' => $data['lengua'] ?? null,
            ':paladar' => $data['paladar'] ?? null,
            ':piso_boca' => $data['piso_boca'] ?? null,
            ':mucosa_yugal' => $data['mucosa_yugal'] ?? null,
            ':encias' => $data['encias'] ?? null,
            ':usa_protesis' => !empty($data['usa_protesis']) ? 1 : 0,
            ':habitos' => $data['habitos'] ?? null,
            ':habitos_fuma' => !empty($data['habitos_fuma']) ? 1 : 0,
            ':habitos_bebe' => !empty($data['habitos_bebe']) ? 1 : 0,
            ':habitos_otros' => $data['habitos_otros'] ?? null,
            ':higiene_bucal' => $data['higiene_bucal'] ?? null,
            ':usa_cepillo' => isset($data['usa_cepillo']) ? intval($data['usa_cepillo']) : 1,
            ':usa_hilo' => !empty($data['usa_hilo']) ? 1 : 0,
            ':usa_enjuague' => !empty($data['usa_enjuague']) ? 1 : 0,
            ':frecuencia_cepillado' => $data['frecuencia_cepillado'] ?? null,
            ':sangrado_encias' => !empty($data['sangrado_encias']) ? 1 : 0,
            ':nivel_higiene_bucal' => $data['nivel_higiene_bucal'] ?? 'Buena',
            ':problema_grave_dental_anterior' => $data['problema_grave_dental_anterior'] ?? null,
            ':embarazo' => !empty($data['embarazo']) ? 1 : 0,
            ':lactancia' => !empty($data['lactancia']) ? 1 : 0,
            ':observaciones' => $data['observaciones'] ?? null,
            ':motivo_consulta' => $data['motivo_consulta'] ?? null,
            ':examen_clinico' => $data['examen_clinico'] ?? null,
            ':diagnostico' => $data['diagnostico'] ?? null,
            ':plan_tratamiento' => $data['plan_tratamiento'] ?? null
        ]);
    }

    /**
     * Guardar firma digital del paciente
     */
    public function guardarFirma($clienteId, $firmaBase64, $ci = null) {
        if (!$this->existe($clienteId)) {
            $this->crear([
                'cliente_id' => $clienteId,
                'ci' => $ci,
                'firma_paciente' => $firmaBase64,
                'fecha_firma' => date('Y-m-d H:i:s')
            ]);
            return true;
        }

        $sql = "UPDATE historia_clinica SET 
                    firma_paciente = :firma,
                    fecha_firma = NOW()" . ($ci ? ", ci = :ci" : "") . "
                WHERE cliente_id = :cliente_id";
        
        $params = [
            ':firma' => $firmaBase64,
            ':cliente_id' => $clienteId
        ];
        if ($ci) {
            $params[':ci'] = $ci;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
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
            
            // Revisar patologías personales seleccionadas
            if (!empty($historia['patologias_personales'])) {
                $patologias = is_array($historia['patologias_personales']) 
                    ? $historia['patologias_personales'] 
                    : json_decode($historia['patologias_personales'], true);
                
                if (is_array($patologias) && !empty($patologias)) {
                    $peligros = array_intersect($patologias, ['cardiopatias', 'chagas', 'diabetes', 'hipertension', 'problemas_coagulacion', 'hepatitis', 'vih', 'epilepsia', 'asma', 'problemas_renales', 'tuberculosis']);
                    if (!empty($peligros)) {
                        $nombresMap = [
                            'cardiopatias' => 'Cardiopatías',
                            'chagas' => 'Chagas',
                            'diabetes' => 'Diabetes',
                            'hipertension' => 'Hipertensión',
                            'problemas_coagulacion' => 'Trastorno Coagulación',
                            'hepatitis' => 'Hepatitis',
                            'vih' => 'VIH',
                            'epilepsia' => 'Epilepsia',
                            'asma' => 'Asma',
                            'problemas_renales' => 'Problemas Renales',
                            'tuberculosis' => 'Tuberculosis'
                        ];
                        $peligroNombres = array_map(fn($k) => $nombresMap[$k] ?? ucfirst($k), $peligros);
                        $alertas[] = ['tipo' => 'danger', 'texto' => 'Patologías: ' . implode(', ', $peligroNombres)];
                    }
                }
            }

            if (!empty($historia['medicamentos_actuales']) || !empty($historia['toma_medicamento'])) {
                $medText = trim(($historia['medicamentos_actuales'] ?? '') . ' ' . ($historia['toma_medicamento'] ?? ''));
                $alertas[] = ['tipo' => 'info', 'texto' => 'Medicamentos: ' . $medText];
            }
            if (!empty($historia['hemorragia_extraccion']) && $historia['hemorragia_extraccion'] !== 'No') {
                $alertas[] = ['tipo' => 'danger', 'texto' => 'Hemorragia post-extracción previa: ' . $historia['hemorragia_extraccion']];
            }
            if (!empty($historia['embarazo'])) {
                $alertas[] = ['tipo' => 'danger', 'texto' => 'Paciente embarazada'];
            }
            if (!empty($historia['firma_paciente'])) {
                $fechaFmt = !empty($historia['fecha_firma']) ? date('d/m/Y', strtotime($historia['fecha_firma'])) : '';
                $alertas[] = ['tipo' => 'info', 'texto' => 'Ficha médica firmada por el paciente ' . ($fechaFmt ? "el $fechaFmt" : '')];
            }
        }
        
        return $alertas;
    }
}
?>
