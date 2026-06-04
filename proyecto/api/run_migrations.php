<?php
declare(strict_types=1);

// CLI only
if (php_sapi_name() !== 'cli') {
    die('Solo CLI' . PHP_EOL);
}

$apiDir = __DIR__;

// ─── Load .env ────────────────────────────────────────────────────────────────
$envFile = $apiDir . '/.env';
if (!file_exists($envFile)) {
    die("Error: No se encontró el archivo .env\n");
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

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// ─── Connect without dbname to allow CREATE DATABASE ─────────────────────────
echo "Conectando a MySQL en {$host}:{$port}...\n";
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass ?: null,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . "\n");
}

echo "✅ Conectado.\n\n";

// ─── Helper: execute SQL file ─────────────────────────────────────────────────
function executeSqlFile(PDO $pdo, string $filePath): void
{
    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new \RuntimeException("No se pudo leer el archivo: {$filePath}");
    }

    // Split by semicolons (basic approach – works for standard DDL/DML)
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn(string $s) => $s !== ''
    );

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

// ─── Run Migrations ───────────────────────────────────────────────────────────
$migrationsDir = $apiDir . '/database/migrations';
echo "=== Ejecutando migraciones ===\n";

$migrationFiles = glob($migrationsDir . '/*.sql');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $name = basename($file);
    echo "  → {$name} ... ";
    try {
        executeSqlFile($pdo, $file);
        echo "✅\n";
    } catch (\Throwable $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ─── Run SQL Seeders ──────────────────────────────────────────────────────────
$seedersDir = $apiDir . '/database/seeders';
echo "=== Ejecutando seeders SQL ===\n";

$sqlSeeders = glob($seedersDir . '/*.sql');
sort($sqlSeeders);

foreach ($sqlSeeders as $file) {
    $name = basename($file);
    echo "  → {$name} ... ";
    try {
        executeSqlFile($pdo, $file);
        echo "✅\n";
    } catch (\Throwable $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ─── Run PHP Seeders ──────────────────────────────────────────────────────────
echo "=== Ejecutando seeders PHP ===\n";

$phpSeeders = glob($seedersDir . '/*.php');
sort($phpSeeders);

foreach ($phpSeeders as $file) {
    $name = basename($file);
    echo "  → {$name}\n";
    passthru(PHP_BINARY . ' ' . escapeshellarg($file));
    echo "\n";
}

echo "\n=== ✅ Proceso completado ===\n";
