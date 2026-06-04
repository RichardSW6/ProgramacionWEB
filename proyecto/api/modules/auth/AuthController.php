<?php
declare(strict_types=1);

class AuthController extends BaseController
{
    public function login(array $params = []): void
    {
        $body    = $this->getJsonBody();
        $correo  = trim($body['correo']   ?? '');
        $password = $body['password'] ?? '';

        if (empty($correo) || empty($password)) {
            $this->errorResponse('Correo y contraseña son obligatorios.', 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre AS rol_nombre
            FROM   usuarios u
            JOIN   roles    r ON u.rol_id = r.id
            WHERE  u.correo     = :correo
              AND  u.deleted_at IS NULL
              AND  u.activo     = 1
            LIMIT 1
        ");
        $stmt->execute([':correo' => $correo]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->errorResponse('Credenciales inválidas.', 401);
            return;
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']        = (int)$user['id'];
        $_SESSION['user_nombre']    = $user['nombre'] . ' ' . $user['apellido'];
        $_SESSION['user_correo']    = $user['correo'];
        $_SESSION['user_rol_id']    = (int)$user['rol_id'];
        $_SESSION['user_rol_nombre']= $user['rol_nombre'];
        $_SESSION['user_area_id']   = $user['area_id'] !== null ? (int)$user['area_id'] : null;
        $_SESSION['last_activity']  = time();

        AuditLogger::log(
            (int)$user['id'],
            'LOGIN',
            'usuarios',
            (int)$user['id'],
            null,
            null,
            $this->db
        );

        $this->jsonResponse([
            'id'        => (int)$user['id'],
            'nombre'    => $user['nombre'],
            'apellido'  => $user['apellido'],
            'correo'    => $user['correo'],
            'rol_id'    => (int)$user['rol_id'],
            'rol_nombre'=> $user['rol_nombre'],
            'area_id'   => $user['area_id'] !== null ? (int)$user['area_id'] : null,
        ], 'Inicio de sesión exitoso.');
    }

    public function logout(array $params = []): void
    {
        $user = $this->getSessionUser();

        AuditLogger::log(
            $user['id'] ? (int)$user['id'] : null,
            'LOGOUT',
            'usuarios',
            $user['id'] ? (int)$user['id'] : null,
            null,
            null,
            $this->db
        );

        session_unset();
        session_destroy();

        $this->jsonResponse(null, 'Sesión cerrada correctamente.');
    }

    public function me(array $params = []): void
    {
        $sessionUser = $this->getSessionUser();
        $userId      = (int)$sessionUser['id'];

        $stmt = $this->db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.correo, u.rol_id, u.area_id, u.activo,
                   r.nombre AS rol_nombre,
                   a.nombre AS area_nombre
            FROM   usuarios u
            JOIN   roles    r ON u.rol_id  = r.id
            LEFT JOIN areas a ON u.area_id = a.id
            WHERE  u.id = :id
              AND  u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->errorResponse('Usuario no encontrado.', 404);
            return;
        }

        $this->jsonResponse([
            'id'         => (int)$user['id'],
            'nombre'     => $user['nombre'],
            'apellido'   => $user['apellido'],
            'correo'     => $user['correo'],
            'rol_id'     => (int)$user['rol_id'],
            'rol_nombre' => $user['rol_nombre'],
            'area_id'    => $user['area_id'] !== null ? (int)$user['area_id'] : null,
            'area_nombre'=> $user['area_nombre'],
            'activo'     => (bool)$user['activo'],
        ]);
    }
}
