<?php
declare(strict_types=1);

abstract class BaseController
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    protected function jsonResponse($data = null, string $message = 'OK', int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => [],
        ], JSON_UNESCAPED_UNICODE);
    }

    protected function errorResponse(string $message, int $statusCode = 400, array $errors = []): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], JSON_UNESCAPED_UNICODE);
    }

    protected function getSessionUser(): array
    {
        return [
            'id'       => $_SESSION['user_id']        ?? null,
            'nombre'   => $_SESSION['user_nombre']    ?? '',
            'rol_id'   => $_SESSION['user_rol_id']    ?? null,
            'area_id'  => $_SESSION['user_area_id']   ?? null,
        ];
    }

    protected function requireRole(array $allowedRolIds): void
    {
        $user = $this->getSessionUser();
        if (!in_array((int)$user['rol_id'], $allowedRolIds, true)) {
            $this->errorResponse('No tienes permisos para realizar esta acción.', 403);
            exit;
        }
    }

    protected function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function paginate(int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        return [
            'page'     => $page,
            'per_page' => $perPage,
            'offset'   => $offset,
        ];
    }
}
