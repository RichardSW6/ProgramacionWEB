<?php
declare(strict_types=1);

class AuthMiddleware
{
    public static function handle(): void
    {
        $lifetime = (int)(getenv('SESSION_LIFETIME') ?: 3600);

        // Check session exists
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'No autenticado. Por favor inicia sesión.',
                'data'    => null,
                'errors'  => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Check inactivity
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $lifetime) {
            session_unset();
            session_destroy();
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión expirada por inactividad. Por favor inicia sesión nuevamente.',
                'data'    => null,
                'errors'  => [],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Refresh activity timestamp
        $_SESSION['last_activity'] = time();
    }
}
