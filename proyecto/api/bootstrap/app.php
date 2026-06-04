<?php
declare(strict_types=1);

// ─── 1. Load .env ────────────────────────────────────────────────────────────
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("$key=$value");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}

// ─── 2. Error reporting ──────────────────────────────────────────────────────
if (getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ─── 3. Timezone ─────────────────────────────────────────────────────────────
date_default_timezone_set('America/Mexico_City');

// ─── 4. Content-Type ─────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ─── 5. CORS Headers ─────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? 'http://localhost';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-CSRF-Token');

// ─── 6. Session ──────────────────────────────────────────────────────────────
$sessionName     = getenv('SESSION_NAME')    ?: 'gastos_session';
$sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 3600);

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => 0,  // Browser-session cookie; inactivity timeout handled in AuthMiddleware
    'path'     => '/',
    'domain'   => '',
    'secure'   => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ─── 7. Core classes ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Router.php';
require_once __DIR__ . '/../core/BaseController.php';

// ─── 8. Middlewares ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';

// ─── 9. Utils ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../utils/AuditLogger.php';
require_once __DIR__ . '/../utils/CfdiProcessor.php';

// ─── 10. Controllers ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../modules/ping/PingController.php';
require_once __DIR__ . '/../modules/auth/AuthController.php';
require_once __DIR__ . '/../modules/catalogos/CatalogosController.php';
require_once __DIR__ . '/../modules/admin/areas/AreasController.php';
require_once __DIR__ . '/../modules/admin/usuarios/UsuariosController.php';
require_once __DIR__ . '/../modules/admin/proveedores/ProveedoresController.php';
require_once __DIR__ . '/../modules/admin/presupuestos/PresupuestosController.php';
require_once __DIR__ . '/../modules/admin/bitacora/BitacoraController.php';
require_once __DIR__ . '/../modules/gastos/ExpenseService.php';
require_once __DIR__ . '/../modules/gastos/GastosController.php';
require_once __DIR__ . '/../modules/reportes/ReportesController.php';
