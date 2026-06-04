<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';

$router = require_once __DIR__ . '/../routes/api.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';

// Strip base path dynamically
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$uri = substr($uri, strlen($scriptDir));

if ($uri === '' || $uri === false) {
    $uri = '/';
}

// Handle CORS preflight
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $router->dispatch($method, $uri);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor.',
        'data'    => null,
        'errors'  => [
            getenv('APP_DEBUG') === 'true' ? $e->getMessage() : 'Ocurrió un error inesperado.'
        ],
    ], JSON_UNESCAPED_UNICODE);
}
