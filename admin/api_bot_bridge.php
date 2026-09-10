<?php
/**
 * Dentality - API Bridge para Agente WhatsApp (Render.com / InfinityFree / VPS)
 * Permite que el bot de Node.js interactúe de forma segura con la base de datos PHP/MySQL
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar configuración de base de datos
require_once __DIR__ . '/../src/config/db.php';

// Obtener clave secreta del puente desde variable de entorno o clave por defecto
$bridgeSecret = getenv('API_BRIDGE_KEY') ?: 'bolident_secret_bridge_key_2026';

// Validar Token de autorización
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';

// Leer cuerpo de la petición (JSON)
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true) ?: $_POST;

$token = '';
if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $token = $matches[1];
} elseif (!empty($data['token'])) {
    $token = $data['token'];
} elseif (isset($_GET['key'])) {
    $token = $_GET['key'];
}

if ($token !== $bridgeSecret && $token !== 'bolident_secret_bridge_key_2026' && $token !== 'dentality_secret_bridge_key_2026' && $token !== 'tatianaruiz_secret_bridge_key_2026') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado. Token de API Bridge inválido.']);
    exit;
}

$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    $pdo->exec("SET time_zone = '-04:00'");

    // Auto-verificar y crear columna whatsapp_lid si aún no existe en MySQL
    try {
        $checkCol = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'whatsapp_lid'")->fetch();
        if (!$checkCol) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN whatsapp_lid VARCHAR(50) NULL DEFAULT NULL AFTER telefono, ADD INDEX idx_whatsapp_lid (whatsapp_lid)");
        }
        // Limpieza preventiva de registros que tengan números de teléfono guardados por error en whatsapp_lid
        $pdo->exec("UPDATE clientes SET whatsapp_lid = NULL WHERE whatsapp_lid IS NOT NULL AND (whatsapp_lid LIKE '%@s.whatsapp.net%' OR LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp_lid, '+', ''), ' ', ''), '-', ''), '@lid', '')) <= 12 OR (whatsapp_lid LIKE '591%' AND LENGTH(whatsapp_lid) <= 12))");
    } catch (Exception $e) {}

    switch ($action) {
        case 'ping':
            echo json_encode(['ok' => true, 'mensaje' => 'API Bridge Dentality Activo y Conectado']);
            break;

        case 'obtener_clientes':
            try {
                $stmt = $pdo->query("SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE telefono IS NOT NULL AND TRIM(telefono) != '' ORDER BY id ASC");
                $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['ok' => true, 'clientes' => $clientes]);
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'guardar_lid':
            $clienteId = (int)($data['cliente_id'] ?? $data['clienteId'] ?? 0);
            $telefono = trim($data['telefono'] ?? '');
            $lid = trim($data['lid'] ?? $data['whatsapp_lid'] ?? '');
            if (empty($lid)) {
                echo json_encode(['ok' => false, 'error' => 'Datos insuficientes para guardar LID']);
                break;
            }

            // Normalizar el identificador LID extrayendo sufijos y dispositivos
            $cleanLid = trim(str_replace('@lid', '', explode(':', $lid)[0]));
            $lidDigits = preg_replace('/\D/', '', $cleanLid);
            $esLidReal = (strpos($lid, '@lid') !== false) || (strlen($lidDigits) >= 12 && !preg_match('/^591\d{8}$/', $lidDigits));
            if (strpos($lid, '@s.whatsapp.net') !== false || strlen($lidDigits) < 10 || !$esLidReal) {
                echo json_encode(['ok' => false, 'ignorado' => true, 'mensaje' => 'El identificador no es un LID de WhatsApp válido (es un número de teléfono). Omitido.']);
                break;
            }

            try {
                if ($clienteId > 0) {
                    $stmt = $pdo->prepare("UPDATE clientes SET whatsapp_lid = ? WHERE id = ?");
                    $stmt->execute([$cleanLid, $clienteId]);
                } elseif (!empty($telefono)) {
                    $cleanTel = preg_replace('/\D/', '', $telefono);
                    $ultimos8 = strlen($cleanTel) >= 8 ? substr($cleanTel, -8) : $cleanTel;
                    $stmt = $pdo->prepare("UPDATE clientes SET whatsapp_lid = ? WHERE telefono LIKE ? OR REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?");
                    $stmt->execute([$cleanLid, "%$ultimos8%", "%$ultimos8%"]);
                }
                echo json_encode(['ok' => true, 'mensaje' => 'LID guardado en base de datos', 'lid' => $cleanLid]);
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'cliente_por_lid':
            $lid = trim($data['lid'] ?? $_GET['lid'] ?? '');
            if (empty($lid)) {
                echo json_encode(['ok' => false, 'error' => 'LID vacío']);
                break;
            }
            try {
                $cleanLid = trim(str_replace('@lid', '', explode(':', $lid)[0]));
                $lidAt = $cleanLid . '@lid';
                $stmt = $pdo->prepare("SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE (whatsapp_lid = ? OR whatsapp_lid = ? OR REPLACE(whatsapp_lid, '@lid', '') = ?) AND whatsapp_lid IS NOT NULL AND whatsapp_lid != '' LIMIT 1");
                $stmt->execute([$cleanLid, $lidAt, $cleanLid]);
                $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['ok' => true, 'cliente' => $cliente ?: null]);
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'buscar_paciente':
            $busqueda = trim($data['busqueda'] ?? '');
            $telefono = trim($data['telefono'] ?? '');
            $lid = trim($data['lid'] ?? '');

            $cliente = null;
            $candidatos = [];

            // 0. Si se provee LID real, buscar directamente por whatsapp_lid
            if (!empty($lid)) {
                $cleanLid = trim(str_replace('@lid', '', explode(':', $lid)[0]));
                $lidDigits = preg_replace('/\D/', '', $cleanLid);
                if (strlen($lidDigits) >= 10 || strpos($lid, '@lid') !== false) {
                    try {
                        $lidAt = $cleanLid . '@lid';
                        $stmt = $pdo->prepare("SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE (whatsapp_lid = ? OR whatsapp_lid = ? OR REPLACE(whatsapp_lid, '@lid', '') = ?) AND whatsapp_lid IS NOT NULL AND whatsapp_lid != '' LIMIT 1");
                        $stmt->execute([$cleanLid, $lidAt, $cleanLid]);
                        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {}
                }
            }

            // 1. Si se provee teléfono, buscar por número de teléfono
            if (!$cliente && !empty($telefono)) {
                $soloDigitos = preg_replace('/\D/', '', $telefono);
                if (strlen($soloDigitos) >= 7) {
                    $ultimos8 = substr($soloDigitos, -8);
                    $stmt = $pdo->prepare("SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE telefono LIKE ? OR REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ? LIMIT 1");
                    $stmt->execute(["%$ultimos8%", "%$ultimos8%"]);
                    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            // 2. Si no se proveyó teléfono directo o no se encontró y hay una búsqueda explícita de texto/nombre
            if (!$cliente && !empty($busqueda)) {
                $soloDigitos = preg_replace('/\D/', '', $busqueda);
                $ultimos8 = strlen($soloDigitos) >= 7 ? substr($soloDigitos, -8) : '';

                // Si la búsqueda contiene números (posible teléfono introducido por el usuario)
                if (!empty($ultimos8)) {
                    $stmt = $pdo->prepare("SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE telefono LIKE ? OR REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ? LIMIT 1");
                    $stmt->execute(["%$ultimos8%", "%$ultimos8%"]);
                    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                // Si no se encontró por dígitos, buscar por nombre exacto
                if (!$cliente && strlen($busqueda) >= 3) {
                    $stmt = $pdo->prepare("SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1");
                    $stmt->execute([$busqueda]);
                    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                // Búsqueda inteligente por tokens/palabras (ej: 'claudia noelia flores' -> matchea 'Claudia Flores')
                if (!$cliente && strlen($busqueda) >= 3) {
                    $rawPalabras = preg_split('/\s+/', mb_strtolower($busqueda));
                    $palabras = array_values(array_filter($rawPalabras, function($w) {
                        return strlen(trim($w)) >= 3;
                    }));

                    if (!empty($palabras)) {
                        $whereOr = [];
                        $params = [];
                        foreach ($palabras as $p) {
                            $whereOr[] = "LOWER(nombre) LIKE ?";
                            $params[] = "%" . $p . "%";
                        }

                        $sql = "SELECT id, nombre, telefono, whatsapp_lid, created_at FROM clientes WHERE " . implode(' OR ', $whereOr) . " LIMIT 20";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($filas)) {
                            $puntuados = [];
                            foreach ($filas as $fila) {
                                $nombreBD = mb_strtolower(trim($fila['nombre']));
                                $palabrasBD = preg_split('/\s+/', $nombreBD);

                                $coincidencias = 0;
                                foreach ($palabras as $pQuery) {
                                    foreach ($palabrasBD as $pBD) {
                                        if ($pQuery === $pBD || (strlen($pQuery) >= 4 && strlen($pBD) >= 4 && (strpos($pQuery, $pBD) !== false || strpos($pBD, $pQuery) !== false))) {
                                            $coincidencias++;
                                            break;
                                        }
                                    }
                                }

                                if ($coincidencias > 0) {
                                    $porcentajeBD = $coincidencias / max(1, count($palabrasBD));
                                    $score = ($coincidencias * 10) + ($porcentajeBD * 10);
                                    $puntuados[] = [
                                        'cliente' => $fila,
                                        'score' => $score,
                                        'coincidencias' => $coincidencias
                                    ];
                                }
                            }

                            if (!empty($puntuados)) {
                                usort($puntuados, function($a, $b) {
                                    return $b['score'] <=> $a['score'];
                                });

                                $candidatos = array_map(function($item) { return $item['cliente']; }, $puntuados);
                                $cliente = $puntuados[0]['cliente'];
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'ok' => true,
                'encontrado' => (bool)$cliente,
                'cliente' => $cliente ?: null,
                'candidatos' => array_slice($candidatos, 0, 3)
            ]);
            break;

        case 'registrar_paciente':
            $nombre = trim($data['nombre'] ?? '');
            $telefono = trim($data['telefono'] ?? '');
            $lid = trim($data['lid'] ?? $data['whatsapp_lid'] ?? '');

            if (!$nombre || !$telefono) {
                echo json_encode(['ok' => false, 'error' => 'Nombre y teléfono son requeridos']);
                break;
            }

            $soloDigitos = preg_replace('/\D/', '', $telefono);
            $ultimos8 = substr($soloDigitos, -8);
            $telNormalizado = '+591' . $ultimos8;

            $cleanLid = trim(str_replace('@lid', '', explode(':', $lid)[0]));
            $lidDigits = preg_replace('/\D/', '', $cleanLid);
            $esLidValido = (!empty($cleanLid)) && (strpos($lid, '@lid') !== false || (strlen($lidDigits) >= 12 && !preg_match('/^591\d{8}$/', $lidDigits))) && strpos($lid, '@s.whatsapp.net') === false;
            $lidParaGuardar = $esLidValido ? $cleanLid : null;

            // Verificar si ya existe un paciente con este nombre EXACTO o coincidente con el mismo teléfono
            $stmt = $pdo->prepare("SELECT id, nombre, telefono, whatsapp_lid FROM clientes WHERE (telefono LIKE ? OR REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+', '') LIKE ?) AND LOWER(TRIM(nombre)) = LOWER(TRIM(?)) LIMIT 1");
            $stmt->execute(["%$ultimos8%", "%$ultimos8%", $nombre]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente) {
                if ($lidParaGuardar) {
                    $stmtUp = $pdo->prepare("UPDATE clientes SET whatsapp_lid = ? WHERE id = ?");
                    $stmtUp->execute([$lidParaGuardar, $existente['id']]);
                    $existente['whatsapp_lid'] = $lidParaGuardar;
                }
                echo json_encode(['ok' => true, 'yaExistia' => true, 'cliente' => $existente]);
                break;
            }

            // Si no existe con ese nombre, registrar como nuevo paciente (permite hijos/familiares con el mismo celular de contacto)
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre, telefono, whatsapp_lid, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$nombre, $telNormalizado, $lidParaGuardar]);
            $newId = (int)$pdo->lastInsertId();

            echo json_encode([
                'ok' => true,
                'esNuevo' => true,
                'cliente' => [
                    'id' => $newId,
                    'nombre' => $nombre,
                    'telefono' => $telNormalizado,
                    'whatsapp_lid' => $lidParaGuardar
                ]
            ]);
            break;

        case 'obtener_citas':
            $clienteId = (int)($data['cliente_id'] ?? 0);
            $telefono = trim($data['telefono'] ?? '');
            $nombre = trim($data['nombre'] ?? '');

            $sql = "
                SELECT c.id, c.cliente_id, c.fecha, c.finDeCita, c.descripcion, c.duracion_estimada, c.estado,
                       c.consultorio_id, cons.nombre AS consultorio_nombre, c.doctor_id, COALESCE(d.nombre, 'Sin asignar') AS doctor_nombre,
                       cl.nombre AS paciente_nombre, cl.telefono AS paciente_telefono
                FROM citas c
                JOIN clientes cl ON c.cliente_id = cl.id
                JOIN consultorios cons ON c.consultorio_id = cons.id
                LEFT JOIN doctores d ON c.doctor_id = d.id
                WHERE (c.estado IS NULL OR c.estado IN ('activo', 'confirmado', 'pospuesto'))
            ";
            $params = [];

            if ($clienteId > 0) {
                $sql .= " AND c.cliente_id = ?";
                $params[] = $clienteId;
            } else if (!empty($telefono)) {
                $telLimpio = preg_replace('/[^0-9]/', '', $telefono);
                if (strlen($telLimpio) >= 8) {
                    $ult8 = substr($telLimpio, -8);
                    $sql .= " AND cl.telefono LIKE ?";
                    $params[] = "%$ult8%";
                } else {
                    $sql .= " AND cl.telefono LIKE ?";
                    $params[] = "%$telefono%";
                }
            } else if (!empty($nombre)) {
                $sql .= " AND cl.nombre LIKE ?";
                $params[] = "%$nombre%";
            }

            $sql .= " AND DATE(c.fecha) >= CURDATE() ORDER BY c.fecha ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'total' => count($citas), 'citas' => $citas]);
            break;

        case 'consultar_disponibilidad':
            $fecha = trim($data['fecha'] ?? date('Y-m-d'));
            $duracion = (int)($data['duracion_minutos'] ?? 30);

            // Validar domingos (día 0 en date('w') o 7 en date('N'))
            $diaSemana = (int)date('w', strtotime($fecha));
            if ($diaSemana === 0) {
                echo json_encode([
                    'ok' => true,
                    'fecha' => $fecha,
                    'es_domingo' => true,
                    'total_libres' => 0,
                    'horarios' => [],
                    'mensaje' => 'Los domingos la clínica permanece cerrada. Nuestro horario de atención es de Lunes a Sábado de 08:30 a 19:30.'
                ]);
                break;
            }

            // Obtener consultorios activos
            $stmtCons = $pdo->query("SELECT id, nombre FROM consultorios WHERE activo = 1 ORDER BY id ASC");
            $consultorios = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

            if (empty($consultorios)) {
                echo json_encode(['ok' => false, 'error' => 'No hay consultorios activos.']);
                break;
            }

            // Horarios de apertura y cierre
            $openH = 8; $openM = 30;
            $closeH = 19; $closeM = 30;

            $startMin = $openH * 60 + $openM;
            $endMin = $closeH * 60 + $closeM;

            // Obtener todas las citas y eventos de la fecha
            $stmtCitas = $pdo->prepare("SELECT consultorio_id, TIME(fecha) as inicio, TIME(finDeCita) as fin FROM citas WHERE DATE(fecha) = ? AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
            $stmtCitas->execute([$fecha]);
            $citasFecha = $stmtCitas->fetchAll(PDO::FETCH_ASSOC);

            $stmtEv = $pdo->prepare("SELECT consultorio_id, TIME(fecha) as inicio, TIME(finDeEvento) as fin FROM eventos WHERE DATE(fecha) = ?");
            $stmtEv->execute([$fecha]);
            $eventosFecha = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

            $slotsDisponibles = [];

            for ($m = $startMin; $m + $duracion <= $endMin; $m += $duracion) {
                $hh = str_pad(floor($m / 60), 2, '0', STR_PAD_LEFT);
                $mm = str_pad($m % 60, 2, '0', STR_PAD_LEFT);
                $horaInicioStr = "$hh:$mm:00";

                $finM = $m + $duracion;
                $finHH = str_pad(floor($finM / 60), 2, '0', STR_PAD_LEFT);
                $finMM = str_pad($finM % 60, 2, '0', STR_PAD_LEFT);
                $horaFinStr = "$finHH:$finMM:00";

                $consultorioLibre = null;

                foreach ($consultorios as $c) {
                    $conflicto = false;
                    // Chequear citas
                    foreach ($citasFecha as $cita) {
                        if ($cita['consultorio_id'] == $c['id']) {
                            if ($horaInicioStr < $cita['fin'] && $horaFinStr > $cita['inicio']) {
                                $conflicto = true;
                                break;
                            }
                        }
                    }
                    if ($conflicto) continue;

                    // Chequear eventos
                    foreach ($eventosFecha as $ev) {
                        if ($ev['consultorio_id'] == $c['id']) {
                            if ($horaInicioStr < $ev['fin'] && $horaFinStr > $ev['inicio']) {
                                $conflicto = true;
                                break;
                            }
                        }
                    }

                    if (!$conflicto) {
                        $consultorioLibre = $c;
                        break;
                    }
                }

                if ($consultorioLibre) {
                    $slotsDisponibles[] = [
                        'hora' => "$hh:$mm",
                        'fechaHora' => "$fecha $horaInicioStr",
                        'consultorio_id' => (int)$consultorioLibre['id'],
                        'consultorio_nombre' => $consultorioLibre['nombre']
                    ];
                }
            }

            echo json_encode([
                'ok' => true,
                'fecha' => $fecha,
                'duracion_minutos' => $duracion,
                'total_libres' => count($slotsDisponibles),
                'horarios' => $slotsDisponibles
            ]);
            break;

        case 'agendar_cita':
            $clienteId = (int)($data['cliente_id'] ?? 0);
            $fechaHora = trim($data['fecha_hora'] ?? '');
            $descripcion = trim($data['descripcion'] ?? 'Consulta Odontológica (WhatsApp)');
            $duracion = (int)($data['duracion_minutos'] ?? 30);
            $consultorioId = !empty($data['consultorio_id']) ? (int)$data['consultorio_id'] : null;
            $doctorId = !empty($data['doctor_id']) ? (int)$data['doctor_id'] : null;

            if (!$clienteId || !$fechaHora) {
                echo json_encode(['ok' => false, 'error' => 'cliente_id y fecha_hora son requeridos.']);
                break;
            }

            $inicio = date('Y-m-d H:i:s', strtotime($fechaHora));
            $fin = date('Y-m-d H:i:s', strtotime($fechaHora . " + $duracion minutes"));
            $fechaSolo = date('Y-m-d', strtotime($fechaHora));
            $horaSolo = date('H:i', strtotime($fechaHora));

            // Validar domingos
            if ((int)date('w', strtotime($fechaSolo)) === 0) {
                echo json_encode([
                    'ok' => false,
                    'exito' => false,
                    'error' => 'domingo_cerrado',
                    'mensaje' => 'La clínica está cerrada los domingos. Por favor agenda de Lunes a Sábado de 08:30 a 19:30.'
                ]);
                break;
            }

            // Verificar si el cliente ya tiene una cita activa en este mismo intervalo (evitar duplicados)
            $stmtDuplicada = $pdo->prepare("SELECT id, consultorio_id FROM citas WHERE cliente_id = ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado')) LIMIT 1");
            $stmtDuplicada->execute([$clienteId, $fin, $inicio]);
            $citaExistente = $stmtDuplicada->fetch(PDO::FETCH_ASSOC);

            if ($citaExistente) {
                $stmtConsNombre = $pdo->prepare("SELECT nombre FROM consultorios WHERE id = ?");
                $stmtConsNombre->execute([(int)$citaExistente['consultorio_id']]);
                $consNombre = $stmtConsNombre->fetchColumn() ?: "Consultorio " . $citaExistente['consultorio_id'];

                echo json_encode([
                    'ok' => true,
                    'exito' => true,
                    'yaExistia' => true,
                    'cita_id' => (int)$citaExistente['id'],
                    'fecha' => $inicio,
                    'duracion_minutos' => $duracion,
                    'consultorio_id' => (int)$citaExistente['consultorio_id'],
                    'consultorio_nombre' => $consNombre,
                    'mensaje' => 'La cita ya se encontraba agendada para este paciente.'
                ]);
                break;
            }

            // Prevenir duplicados: si el paciente ya tiene una cita activa/confirmada futura,
            // se reprograma la existente en lugar de crear una nueva duplicada.
            $stmtCitaFutura = $pdo->prepare("SELECT c.id, c.fecha, c.descripcion, c.consultorio_id, cons.nombre AS consultorio_nombre 
                FROM citas c 
                LEFT JOIN consultorios cons ON c.consultorio_id = cons.id 
                WHERE c.cliente_id = ? AND c.fecha >= CURDATE() AND (c.estado IS NULL OR c.estado IN ('activo', 'confirmado')) 
                ORDER BY c.fecha ASC LIMIT 1");
            $stmtCitaFutura->execute([$clienteId]);
            $citaExistente = $stmtCitaFutura->fetch(PDO::FETCH_ASSOC);

            if ($citaExistente) {
                // Si la nueva fecha y hora coincide exactamente, devolver éxito sin duplicar
                if ($citaExistente['fecha'] === $inicio) {
                    $dt = new DateTime($inicio);
                    $dias = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
                    $meses = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
                    $diaSemana = $dias[$dt->format('l')] ?? $dt->format('l');
                    $fechaFormateada = "{$diaSemana} " . $dt->format('d') . " de " . ($meses[$dt->format('m')] ?? '') . " a las " . $dt->format('H:i');
                    echo json_encode([
                        'ok' => true,
                        'exito' => true,
                        'yaExistia' => true,
                        'reprogramada' => false,
                        'cita_id' => (int)$citaExistente['id'],
                        'fecha' => $inicio,
                        'fecha_formateada' => $fechaFormateada,
                        'mensaje' => "El paciente ya cuenta con esta cita activa para $fechaFormateada."
                    ]);
                    break;
                }

                // Si es un cambio de fecha/hora, reprogramar la cita existente
                $citaId = (int)$citaExistente['id'];
                $consultorioExistente = (int)$citaExistente['consultorio_id'];

                // Verificar si el consultorio actual está libre en el nuevo horario
                $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE consultorio_id = ? AND id != ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
                $stmtConf->execute([$consultorioExistente, $citaId, $fin, $inicio]);
                $hayCita = $stmtConf->fetchColumn() > 0;

                $stmtConfEv = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE consultorio_id = ? AND (fecha < ? AND finDeEvento > ?)");
                $stmtConfEv->execute([$consultorioExistente, $fin, $inicio]);
                $hayEvento = $stmtConfEv->fetchColumn() > 0;

                $consultorioValido = (!$hayCita && !$hayEvento) ? $consultorioExistente : null;

                if (!$consultorioValido) {
                    $stmtCons = $pdo->query("SELECT id FROM consultorios WHERE activo = 1 ORDER BY id ASC");
                    $consultorios = $stmtCons->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($consultorios as $cId) {
                        $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE consultorio_id = ? AND id != ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
                        $stmtConf->execute([$cId, $citaId, $fin, $inicio]);
                        if ($stmtConf->fetchColumn() > 0) continue;

                        $stmtConfEv = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE consultorio_id = ? AND (fecha < ? AND finDeEvento > ?)");
                        $stmtConfEv->execute([$cId, $fin, $inicio]);
                        if ($stmtConfEv->fetchColumn() > 0) continue;

                        $consultorioValido = $cId;
                        break;
                    }
                }

                if (!$consultorioValido) {
                    echo json_encode([
                        'ok' => false,
                        'exito' => false,
                        'ocupado' => true,
                        'mensaje' => "El horario de las $horaSolo el $fechaSolo no está disponible porque todos los consultorios están ocupados. Por favor ofrece otros horarios disponibles al paciente."
                    ]);
                    break;
                }

                // Actualizar la cita existente manteniendo el estado como 'activo' y reseteando recordatorio
                $stmt = $pdo->prepare("UPDATE citas SET fecha = ?, finDeCita = ?, consultorio_id = ?, duracion_estimada = ?, estado = 'activo', recordatorio_enviado = 0, fecha_recordatorio = NULL WHERE id = ?");
                $stmt->execute([$inicio, $fin, $consultorioValido, $duracion, $citaId]);

                $dt = new DateTime($inicio);
                $dias = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
                $meses = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
                $diaSemana = $dias[$dt->format('l')] ?? $dt->format('l');
                $fechaFormateada = "{$diaSemana} " . $dt->format('d') . " de " . ($meses[$dt->format('m')] ?? '') . " a las " . $dt->format('H:i');

                echo json_encode([
                    'ok' => true,
                    'exito' => true,
                    'reprogramada' => true,
                    'cita_id' => $citaId,
                    'fecha' => $inicio,
                    'fecha_formateada' => $fechaFormateada,
                    'consultorio_id' => $consultorioValido,
                    'mensaje' => 'Cita reprogramada exitosamente para ' . $fechaFormateada . ' (manteniendo estado activo).'
                ]);
                break;
            }

            // Buscar consultorio libre (verificando citas Y eventos)
            $stmtCons = $pdo->query("SELECT id FROM consultorios WHERE activo = 1 ORDER BY id ASC");
            $consultorios = $stmtCons->fetchAll(PDO::FETCH_COLUMN);

            $consultorioValido = null;
            if ($consultorioId) {
                // Verificar si el consultorio solicitado está libre de citas y eventos
                $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE consultorio_id = ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
                $stmtConf->execute([$consultorioId, $fin, $inicio]);
                $hayCita = $stmtConf->fetchColumn() > 0;

                $stmtConfEv = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE consultorio_id = ? AND (fecha < ? AND finDeEvento > ?)");
                $stmtConfEv->execute([$consultorioId, $fin, $inicio]);
                $hayEvento = $stmtConfEv->fetchColumn() > 0;

                if (!$hayCita && !$hayEvento) {
                    $consultorioValido = $consultorioId;
                }
            }

            if (!$consultorioValido) {
                // Auto-asignar el primer consultorio libre de citas y eventos
                foreach ($consultorios as $cId) {
                    $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE consultorio_id = ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
                    $stmtConf->execute([$cId, $fin, $inicio]);
                    if ($stmtConf->fetchColumn() > 0) continue;

                    $stmtConfEv = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE consultorio_id = ? AND (fecha < ? AND finDeEvento > ?)");
                    $stmtConfEv->execute([$cId, $fin, $inicio]);
                    if ($stmtConfEv->fetchColumn() > 0) continue;

                    $consultorioValido = $cId;
                    break;
                }
            }

            if (!$consultorioValido) {
                echo json_encode([
                    'ok' => false,
                    'exito' => false,
                    'ocupado' => true,
                    'mensaje' => "El horario de las $horaSolo el $fechaSolo no está disponible porque todos los consultorios están ocupados con citas o eventos clínicos. Por favor consulta la disponibilidad y ofrece otros horarios libres al paciente."
                ]);
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO citas (cliente_id, consultorio_id, fecha, finDeCita, descripcion, duracion_estimada, estado, doctor_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'activo', ?, NOW())
            ");
            $stmt->execute([$clienteId, $consultorioValido, $inicio, $fin, $descripcion, $duracion, $doctorId]);
            $citaId = $pdo->lastInsertId();

            $stmtConsNombre = $pdo->prepare("SELECT nombre FROM consultorios WHERE id = ?");
            $stmtConsNombre->execute([$consultorioValido]);
            $consNombre = $stmtConsNombre->fetchColumn() ?: "Consultorio $consultorioValido";

            $dt = new DateTime($inicio);
            $dias = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
            $meses = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
            $diaSemana = $dias[$dt->format('l')] ?? $dt->format('l');
            $diaNum = $dt->format('d');
            $mesNombre = $meses[$dt->format('m')] ?? $dt->format('m');
            $hora = $dt->format('H:i');
            $fechaFormateada = "{$diaSemana} {$diaNum} de {$mesNombre} a las {$hora}";

            echo json_encode([
                'ok' => true,
                'exito' => true,
                'cita_id' => (int)$citaId,
                'fecha' => $inicio,
                'fecha_formateada' => $fechaFormateada,
                'duracion_minutos' => $duracion,
                'consultorio_id' => $consultorioValido,
                'consultorio_nombre' => $consNombre,
                'mensaje' => 'Cita agendada exitosamente para ' . $fechaFormateada . '.'
            ]);
            break;

        case 'reprogramar_cita':
            $citaId = (int)($data['cita_id'] ?? 0);
            $nuevaFechaHora = trim($data['nueva_fecha_hora'] ?? '');
            $duracion = (int)($data['duracion_minutos'] ?? 30);

            if (!$citaId || !$nuevaFechaHora) {
                echo json_encode(['ok' => false, 'error' => 'cita_id y nueva_fecha_hora son requeridos.']);
                break;
            }

            $inicio = date('Y-m-d H:i:s', strtotime($nuevaFechaHora));
            $fin = date('Y-m-d H:i:s', strtotime($nuevaFechaHora . " + $duracion minutes"));
            $fechaSolo = date('Y-m-d', strtotime($nuevaFechaHora));
            $horaSolo = date('H:i', strtotime($nuevaFechaHora));

            // Validar domingos
            if ((int)date('w', strtotime($fechaSolo)) === 0) {
                echo json_encode([
                    'ok' => false,
                    'exito' => false,
                    'error' => 'domingo_cerrado',
                    'mensaje' => 'La clínica está cerrada los domingos. Por favor elige de Lunes a Sábado de 08:30 a 19:30.'
                ]);
                break;
            }

            // Obtener datos actuales de la cita
            $stmtCita = $pdo->prepare("SELECT consultorio_id FROM citas WHERE id = ?");
            $stmtCita->execute([$citaId]);
            $citaActual = $stmtCita->fetch(PDO::FETCH_ASSOC);
            $consultorioId = $citaActual ? (int)$citaActual['consultorio_id'] : null;

            // Verificar si el consultorio actual u otro está libre de citas y eventos
            $stmtCons = $pdo->query("SELECT id FROM consultorios WHERE activo = 1 ORDER BY id ASC");
            $consultorios = $stmtCons->fetchAll(PDO::FETCH_COLUMN);

            $consultorioValido = null;
            if ($consultorioId) {
                $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE consultorio_id = ? AND id != ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
                $stmtConf->execute([$consultorioId, $citaId, $fin, $inicio]);
                $hayCita = $stmtConf->fetchColumn() > 0;

                $stmtConfEv = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE consultorio_id = ? AND (fecha < ? AND finDeEvento > ?)");
                $stmtConfEv->execute([$consultorioId, $fin, $inicio]);
                $hayEvento = $stmtConfEv->fetchColumn() > 0;

                if (!$hayCita && !$hayEvento) {
                    $consultorioValido = $consultorioId;
                }
            }

            if (!$consultorioValido) {
                foreach ($consultorios as $cId) {
                    $stmtConf = $pdo->prepare("SELECT COUNT(*) FROM citas WHERE consultorio_id = ? AND id != ? AND (fecha < ? AND finDeCita > ?) AND (estado IS NULL OR estado IN ('activo', 'confirmado'))");
                    $stmtConf->execute([$cId, $citaId, $fin, $inicio]);
                    if ($stmtConf->fetchColumn() > 0) continue;

                    $stmtConfEv = $pdo->prepare("SELECT COUNT(*) FROM eventos WHERE consultorio_id = ? AND (fecha < ? AND finDeEvento > ?)");
                    $stmtConfEv->execute([$cId, $fin, $inicio]);
                    if ($stmtConfEv->fetchColumn() > 0) continue;

                    $consultorioValido = $cId;
                    break;
                }
            }

            if (!$consultorioValido) {
                echo json_encode([
                    'ok' => false,
                    'exito' => false,
                    'ocupado' => true,
                    'mensaje' => "El horario de las $horaSolo el $fechaSolo no está disponible porque todos los consultorios están ocupados. Por favor ofrece otros horarios disponibles al paciente."
                ]);
                break;
            }

            $descripcion = trim($data['nueva_descripcion'] ?? $data['descripcion'] ?? '');
            if (!empty($descripcion)) {
                $stmt = $pdo->prepare("UPDATE citas SET fecha = ?, finDeCita = ?, consultorio_id = ?, duracion_estimada = ?, descripcion = ?, estado = 'activo', recordatorio_enviado = 0, fecha_recordatorio = NULL WHERE id = ?");
                $stmt->execute([$inicio, $fin, $consultorioValido, $duracion, $descripcion, $citaId]);
            } else {
                $stmt = $pdo->prepare("UPDATE citas SET fecha = ?, finDeCita = ?, consultorio_id = ?, duracion_estimada = ?, estado = 'activo', recordatorio_enviado = 0, fecha_recordatorio = NULL WHERE id = ?");
                $stmt->execute([$inicio, $fin, $consultorioValido, $duracion, $citaId]);
            }

            $dt = new DateTime($inicio);
            $dias = ['Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado'];
            $meses = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
            $diaSemana = $dias[$dt->format('l')] ?? $dt->format('l');
            $diaNum = $dt->format('d');
            $mesNombre = $meses[$dt->format('m')] ?? $dt->format('m');
            $hora = $dt->format('H:i');
            $fechaFormateada = "{$diaSemana} {$diaNum} de {$mesNombre} a las {$hora}";

            echo json_encode([
                'ok' => true,
                'exito' => true,
                'cita_id' => $citaId,
                'nueva_fecha' => $inicio,
                'fecha_formateada' => $fechaFormateada,
                'consultorio_id' => $consultorioValido,
                'mensaje' => 'Cita reprogramada exitosamente para ' . $fechaFormateada . '.'
            ]);
            break;

        case 'cancelar_cita':
            $citaId = (int)($data['cita_id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE citas SET estado = 'cancelado' WHERE id = ?");
            $stmt->execute([$citaId]);
            echo json_encode(['ok' => true, 'exito' => true, 'cita_id' => $citaId]);
            break;

        case 'confirmar_cita':
            $citaId = (int)($data['cita_id'] ?? 0);
            $clienteId = (int)($data['cliente_id'] ?? 0);
            
            $targetCitaId = $citaId;
            if ($targetCitaId > 0) {
                $stmt = $pdo->prepare("UPDATE citas SET estado = 'confirmado' WHERE id = ?");
                $stmt->execute([$targetCitaId]);
            } else if ($clienteId > 0) {
                // Obtener el ID de la próxima cita activa del paciente
                $stmtBuscar = $pdo->prepare("SELECT id FROM citas WHERE cliente_id = ? AND fecha >= CURDATE() AND (estado IS NULL OR estado IN ('activo', 'pospuesto', 'confirmado')) ORDER BY fecha ASC LIMIT 1");
                $stmtBuscar->execute([$clienteId]);
                $targetCitaId = (int)$stmtBuscar->fetchColumn();
                if ($targetCitaId > 0) {
                    $stmt = $pdo->prepare("UPDATE citas SET estado = 'confirmado' WHERE id = ?");
                    $stmt->execute([$targetCitaId]);
                }
            }

            // Consultar datos completos de la cita confirmada para responder con exactitud
            $citaInfo = null;
            if ($targetCitaId > 0) {
                $stmtInfo = $pdo->prepare("
                    SELECT c.id, c.fecha, c.finDeCita, c.descripcion, 
                           cl.nombre AS paciente_nombre, cl.telefono AS paciente_telefono,
                           cons.nombre AS consultorio_nombre, 
                           COALESCE(d.nombre, 'Especialista de turno') AS doctor_nombre
                    FROM citas c
                    JOIN clientes cl ON c.cliente_id = cl.id
                    JOIN consultorios cons ON c.consultorio_id = cons.id
                    LEFT JOIN doctores d ON c.doctor_id = d.id
                    WHERE c.id = ?
                ");
                $stmtInfo->execute([$targetCitaId]);
                $citaInfo = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            }

            if ($citaInfo) {
                $dt = new DateTime($citaInfo['fecha']);
                $dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
                $meses = ['01' => 'enero', '02' => 'febrero', '03' => 'marzo', '04' => 'abril', '05' => 'mayo', '06' => 'junio', '07' => 'julio', '08' => 'agosto', '09' => 'septiembre', '10' => 'octubre', '11' => 'noviembre', '12' => 'diciembre'];
                
                $diaSemana = $dias[$dt->format('l')] ?? $dt->format('l');
                $diaNum = $dt->format('d');
                $mesNombre = $meses[$dt->format('m')] ?? $dt->format('m');
                $anio = $dt->format('Y');
                $hora = $dt->format('H:i');
                
                $hoyStr = date('Y-m-d');
                $mananaStr = date('Y-m-d', strtotime('+1 day'));
                $fechaCitaStr = $dt->format('Y-m-d');
                
                $prefijoDia = $fechaCitaStr === $hoyStr ? 'hoy ' : ($fechaCitaStr === $mananaStr ? 'mañana ' : '');
                $fechaFormateada = "{$prefijoDia}{$diaSemana} {$diaNum} de {$mesNombre} de {$anio} a las {$hora}";

                echo json_encode([
                    'ok' => true,
                    'exito' => true,
                    'cita_id' => $targetCitaId,
                    'paciente' => $citaInfo['paciente_nombre'],
                    'fecha_hora' => $citaInfo['fecha'],
                    'fecha_formateada' => $fechaFormateada,
                    'hora' => $hora,
                    'descripcion' => $citaInfo['descripcion'],
                    'consultorio_nombre' => $citaInfo['consultorio_nombre'],
                    'doctor_nombre' => $citaInfo['doctor_nombre'],
                    'mensaje' => "Cita de {$citaInfo['paciente_nombre']} confirmada exitosamente para {$fechaFormateada}."
                ]);
            } else {
                echo json_encode(['ok' => true, 'exito' => true, 'cita_id' => $targetCitaId]);
            }
            break;

        case 'obtener_recordatorios_pendientes':
            // Citas para mañana (o fecha indicada)
            $fechaTarget = !empty($data['fecha']) ? trim($data['fecha']) : date('Y-m-d', strtotime('+1 day'));
            $soloPendientes = isset($data['solo_pendientes']) ? (bool)$data['solo_pendientes'] : false;
            $wherePendientes = $soloPendientes ? "AND (c.recordatorio_enviado IS NULL OR c.recordatorio_enviado = 0)" : "";
            
            $stmt = $pdo->prepare("
                SELECT c.id as cita_id, c.cliente_id, c.fecha, c.finDeCita, c.descripcion, c.duracion_estimada, c.estado,
                       c.recordatorio_enviado, c.fecha_recordatorio,
                       cl.nombre as paciente_nombre, cl.telefono as paciente_telefono,
                       cons.nombre as consultorio_nombre,
                       COALESCE(d.nombre, 'Especialista de turno') as doctor_nombre
                FROM citas c
                JOIN clientes cl ON c.cliente_id = cl.id
                JOIN consultorios cons ON c.consultorio_id = cons.id
                LEFT JOIN doctores d ON c.doctor_id = d.id
                WHERE DATE(c.fecha) = ?
                  AND (c.estado IS NULL OR c.estado IN ('activo', 'confirmado', 'pospuesto'))
                  $wherePendientes
                  AND cl.telefono IS NOT NULL AND TRIM(cl.telefono) != ''
                ORDER BY c.recordatorio_enviado ASC, c.fecha ASC
            ");
            $stmt->execute([$fechaTarget]);
            $citasRecordatorio = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'fecha' => $fechaTarget,
                'total' => count($citasRecordatorio),
                'citas' => $citasRecordatorio
            ]);
            break;

        case 'marcar_recordatorio_enviado':
            $citaId = (int)($data['cita_id'] ?? 0);
            if ($citaId > 0) {
                $stmt = $pdo->prepare("UPDATE citas SET recordatorio_enviado = 1, fecha_recordatorio = NOW() WHERE id = ?");
                $stmt->execute([$citaId]);
            }
            echo json_encode(['ok' => true, 'cita_id' => $citaId]);
            break;

        case 'informacion_clinica':
            $stmtCons = $pdo->query("SELECT id, nombre, color FROM consultorios WHERE activo = 1 ORDER BY id ASC");
            $consultorios = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

            try {
                $stmtDoc = $pdo->query("
                    SELECT 
                        d.id, 
                        d.nombre, 
                        d.telefono, 
                        COALESCE(GROUP_CONCAT(e.nombre SEPARATOR ', '), 'Odontología General') AS especialidades
                    FROM doctores d
                    LEFT JOIN doctor_especialidades de ON d.id = de.doctor_id
                    LEFT JOIN especialidades e ON de.especialidad_id = e.id
                    WHERE d.estado = 'activo'
                    GROUP BY d.id
                    ORDER BY d.nombre ASC
                ");
                $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $stmtDoc = $pdo->query("SELECT id, nombre FROM doctores WHERE estado = 'activo' ORDER BY nombre ASC");
                $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);
            }

            $stmtTrat = $pdo->query("SELECT id, nombre, precio FROM tratamientos ORDER BY id ASC LIMIT 30");
            $tratamientos = $stmtTrat->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'nombre_clinica' => 'Dentality',
                'direccion' => 'Av. Antezana 847 Edificio Torre Atlanta piso 6 oficina 4 , Cochabamba, Bolivia',
                'telefono' => '76969699',
                'horarios' => '08:30 a 19:30 (Lunes a Sábado)',
                'consultorios' => array_column($consultorios, 'nombre'),
                'doctores' => array_map(function($d) {
                    $esp = !empty($d['especialidades']) ? " ({$d['especialidades']})" : "";
                    return $d['nombre'] . $esp;
                }, $doctores),
                'doctores_detallados' => $doctores,
                'tratamientos' => $tratamientos
            ]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => "Acción desconocida: $action"]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
