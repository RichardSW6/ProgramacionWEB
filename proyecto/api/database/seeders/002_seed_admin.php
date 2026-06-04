<?php
declare(strict_types=1);

// CLI only
if (php_sapi_name() !== 'cli') {
    die('Solo CLI' . PHP_EOL);
}

$dir = dirname(__DIR__, 2);

// Load .env
$envFile = $dir . '/.env';
if (!file_exists($envFile)) {
    die("Error: No se encontró el archivo .env en {$dir}" . PHP_EOL);
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    putenv(trim($k) . '=' . trim($v, " \t\n\r\0\x0B\"'"));
}

$dsn = 'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4';

try {
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS') ?: null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    die('Error de conexión: ' . $e->getMessage() . PHP_EOL);
}

// Crear área de administración si no existe
$pdo->exec("INSERT IGNORE INTO areas (codigo_centro_costo, nombre) VALUES ('ADM-001', 'Administración General')");
$areaId = $pdo->query("SELECT id FROM areas WHERE codigo_centro_costo='ADM-001'")->fetchColumn();

// Crear usuario admin si no existe
$hash = password_hash('Admin123!', PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("
    INSERT IGNORE INTO usuarios (nombre, apellido, correo, password_hash, rol_id, area_id, activo)
    VALUES ('Administrador', 'Sistema', 'admin@empresa.com', ?, 1, ?, 1)
");
$stmt->execute([$hash, $areaId]);

$affected = $stmt->rowCount();

if ($affected > 0) {
    echo '✅ Usuario administrador creado:' . PHP_EOL;
    echo '   Email:    admin@empresa.com' . PHP_EOL;
    echo '   Password: Admin123!' . PHP_EOL;
} else {
    echo 'ℹ️  El usuario admin@empresa.com ya existe. No se realizaron cambios.' . PHP_EOL;
}
