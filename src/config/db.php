<?php
// Cargar variables de entorno desde .env si existe (entorno simple)
if (file_exists(__DIR__ . '/../../.env')) {
    $envLines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($envLines as $line) {
        if (strpos(trim($line), '#') === 0) { continue; }
        if (!str_contains($line, '=')) { continue; }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (!isset($_ENV[$k]) && !isset($_SERVER[$k])) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

$host = getenv('DB_HOST') ?: 'sql102.infinityfree.com';
$db   = getenv('DB_NAME') ?: 'if0_42919963_dentalsupremo';
$user = getenv('DB_USER') ?: 'if0_42919963';
$pass = (getenv('DB_PASS') !== false) ? getenv('DB_PASS') : 'dentalsupremo';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => true,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die('<div style="font-family:sans-serif; max-width:600px; margin:40px auto; padding:20px; background:#fff3f3; color:#900; border:1px solid #f99; border-radius:8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">' .
        '<h2 style="margin-top:0;">⚠️ Error de conexión a la Base de Datos</h2>' .
        '<p><strong>Detalle del error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
        '<p>Asegúrate de que el archivo <code>.env</code> en la raíz de tu hosting contenga las credenciales correctas asignadas por tu servidor (DB_HOST, DB_NAME, DB_USER, DB_PASS).</p>' .
        '</div>');
}
?>