<?php
declare(strict_types=1);

class UsuariosController extends BaseController
{
    public function index(array $params = []): void
    {
        $this->requireRole([1]);

        $stmt = $this->db->query("
            SELECT u.id, u.nombre, u.apellido, u.correo,
                   u.rol_id, u.area_id, u.activo,
                   r.nombre AS rol_nombre,
                   a.nombre AS area_nombre
            FROM   usuarios u
            JOIN   roles    r ON u.rol_id  = r.id
            LEFT JOIN areas a ON u.area_id = a.id
            WHERE  u.deleted_at IS NULL
            ORDER BY u.nombre
        ");
        $this->jsonResponse($stmt->fetchAll());
    }

    public function show(array $params = []): void
    {
        $this->requireRole([1]);

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.correo,
                   u.rol_id, u.area_id, u.activo,
                   r.nombre AS rol_nombre,
                   a.nombre AS area_nombre,
                   u.created_at, u.updated_at
            FROM   usuarios u
            JOIN   roles    r ON u.rol_id  = r.id
            LEFT JOIN areas a ON u.area_id = a.id
            WHERE  u.id = :id AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->errorResponse('Usuario no encontrado.', 404);
            return;
        }

        $this->jsonResponse($user);
    }

    public function store(array $params = []): void
    {
        $this->requireRole([1]);

        $body     = $this->getJsonBody();
        $nombre   = trim($body['nombre']   ?? '');
        $apellido = trim($body['apellido'] ?? '');
        $correo   = trim($body['correo']   ?? '');
        $password = $body['password']      ?? '';
        $rolId    = isset($body['rol_id'])  ? (int)$body['rol_id']  : 0;
        $areaId   = isset($body['area_id']) && $body['area_id'] !== '' ? (int)$body['area_id'] : null;
        $activo   = isset($body['activo'])  ? (int)(bool)$body['activo'] : 1;

        $errors = [];
        if (empty($nombre))   $errors[] = 'El nombre es obligatorio.';
        if (empty($apellido)) $errors[] = 'El apellido es obligatorio.';
        if (empty($correo))   $errors[] = 'El correo es obligatorio.';
        if (empty($password)) $errors[] = 'La contraseña es obligatoria.';
        if ($rolId <= 0)      $errors[] = 'El rol es obligatorio.';

        if (!empty($errors)) {
            $this->errorResponse('Datos incompletos.', 400, $errors);
            return;
        }

        // Validate email format
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $this->errorResponse('El correo no tiene un formato válido.', 400);
            return;
        }

        // Check unique email
        $check = $this->db->prepare("SELECT id FROM usuarios WHERE correo = ? AND deleted_at IS NULL");
        $check->execute([$correo]);
        if ($check->fetch()) {
            $this->errorResponse("El correo '{$correo}' ya está registrado.", 409);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->db->prepare("
            INSERT INTO usuarios (nombre, apellido, correo, password_hash, rol_id, area_id, activo)
            VALUES (:nombre, :apellido, :correo, :hash, :rol_id, :area_id, :activo)
        ");
        $stmt->execute([
            ':nombre'   => $nombre,
            ':apellido' => $apellido,
            ':correo'   => $correo,
            ':hash'     => $hash,
            ':rol_id'   => $rolId,
            ':area_id'  => $areaId,
            ':activo'   => $activo,
        ]);
        $newId = (int)$this->db->lastInsertId();

        $sessionUser = $this->getSessionUser();
        AuditLogger::log((int)$sessionUser['id'], 'CREAR', 'usuarios', $newId, null, [
            'nombre'  => $nombre,
            'correo'  => $correo,
            'rol_id'  => $rolId,
        ], $this->db);

        $this->jsonResponse(['id' => $newId], 'Usuario creado correctamente.', 201);
    }

    public function update(array $params = []): void
    {
        $this->requireRole([1]);

        $id   = (int)($params['id'] ?? 0);
        $body = $this->getJsonBody();

        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        // Check user exists
        $check = $this->db->prepare("SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Usuario no encontrado.', 404);
            return;
        }

        $nombre   = trim($body['nombre']   ?? $current['nombre']);
        $apellido = trim($body['apellido'] ?? $current['apellido']);
        $correo   = trim($body['correo']   ?? $current['correo']);
        $rolId    = isset($body['rol_id'])  ? (int)$body['rol_id']  : (int)$current['rol_id'];
        $areaId   = array_key_exists('area_id', $body)
                    ? ($body['area_id'] !== null && $body['area_id'] !== '' ? (int)$body['area_id'] : null)
                    : $current['area_id'];
        $activo   = isset($body['activo'])  ? (int)(bool)$body['activo'] : (int)$current['activo'];

        // Check unique email excluding self
        if ($correo !== $current['correo']) {
            $checkMail = $this->db->prepare("SELECT id FROM usuarios WHERE correo = ? AND id != ? AND deleted_at IS NULL");
            $checkMail->execute([$correo, $id]);
            if ($checkMail->fetch()) {
                $this->errorResponse("El correo '{$correo}' ya está en uso.", 409);
                return;
            }
        }

        // Build update
        $sets   = "nombre=:nombre, apellido=:apellido, correo=:correo, rol_id=:rol_id, area_id=:area_id, activo=:activo";
        $binds  = [
            ':nombre'   => $nombre,
            ':apellido' => $apellido,
            ':correo'   => $correo,
            ':rol_id'   => $rolId,
            ':area_id'  => $areaId,
            ':activo'   => $activo,
            ':id'       => $id,
        ];

        // Update password if provided
        if (!empty($body['password'])) {
            $sets .= ', password_hash=:hash';
            $binds[':hash'] = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->db->prepare("UPDATE usuarios SET {$sets} WHERE id=:id")->execute($binds);

        $sessionUser = $this->getSessionUser();
        AuditLogger::log((int)$sessionUser['id'], 'ACTUALIZAR', 'usuarios', $id, [
            'nombre' => $current['nombre'],
            'correo' => $current['correo'],
        ], [
            'nombre' => $nombre,
            'correo' => $correo,
        ], $this->db);

        $this->jsonResponse(null, 'Usuario actualizado correctamente.');
    }

    public function destroy(array $params = []): void
    {
        $this->requireRole([1]);

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        $check = $this->db->prepare("SELECT * FROM usuarios WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Usuario no encontrado.', 404);
            return;
        }

        $stmt = $this->db->prepare("UPDATE usuarios SET deleted_at=NOW(), activo=0 WHERE id=?");
        $stmt->execute([$id]);

        $sessionUser = $this->getSessionUser();
        AuditLogger::log((int)$sessionUser['id'], 'ELIMINAR', 'usuarios', $id, $current, null, $this->db);

        $this->jsonResponse(null, 'Usuario eliminado correctamente.');
    }
}
