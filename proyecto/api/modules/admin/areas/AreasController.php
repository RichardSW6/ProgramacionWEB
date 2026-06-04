<?php
declare(strict_types=1);

class AreasController extends BaseController
{
    public function index(array $params = []): void
    {
        $stmt = $this->db->query("
            SELECT a.id,
                   a.codigo_centro_costo,
                   a.nombre,
                   a.jefe_usuario_id,
                   CONCAT(u.nombre, ' ', u.apellido) AS jefe_nombre
            FROM   areas   a
            LEFT JOIN usuarios u ON a.jefe_usuario_id = u.id
            WHERE  a.deleted_at IS NULL
            ORDER BY a.nombre
        ");
        $this->jsonResponse($stmt->fetchAll());
    }

    public function store(array $params = []): void
    {
        $this->requireRole([1]);

        $body   = $this->getJsonBody();
        $codigo = trim($body['codigo_centro_costo'] ?? '');
        $nombre = trim($body['nombre']              ?? '');
        $jefeId = isset($body['jefe_usuario_id']) && $body['jefe_usuario_id'] !== ''
                  ? (int)$body['jefe_usuario_id']
                  : null;

        if (empty($codigo) || empty($nombre)) {
            $this->errorResponse('El código de centro de costo y el nombre son obligatorios.', 400);
            return;
        }

        // Verify unique code
        $check = $this->db->prepare("SELECT id FROM areas WHERE codigo_centro_costo = ? AND deleted_at IS NULL");
        $check->execute([$codigo]);
        if ($check->fetch()) {
            $this->errorResponse("El código '{$codigo}' ya está en uso.", 409);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO areas (codigo_centro_costo, nombre, jefe_usuario_id)
            VALUES (:codigo, :nombre, :jefe_id)
        ");
        $stmt->execute([
            ':codigo'   => $codigo,
            ':nombre'   => $nombre,
            ':jefe_id'  => $jefeId,
        ]);
        $newId = (int)$this->db->lastInsertId();

        $user = $this->getSessionUser();
        AuditLogger::log((int)$user['id'], 'CREAR', 'areas', $newId, null, [
            'codigo_centro_costo' => $codigo,
            'nombre'              => $nombre,
        ], $this->db);

        $this->jsonResponse(['id' => $newId], 'Área creada correctamente.', 201);
    }

    public function update(array $params = []): void
    {
        $this->requireRole([1]);

        $id     = (int)($params['id'] ?? 0);
        $body   = $this->getJsonBody();
        $codigo = trim($body['codigo_centro_costo'] ?? '');
        $nombre = trim($body['nombre']              ?? '');
        $jefeId = isset($body['jefe_usuario_id']) && $body['jefe_usuario_id'] !== ''
                  ? (int)$body['jefe_usuario_id']
                  : null;

        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        // Check area exists
        $check = $this->db->prepare("SELECT * FROM areas WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Área no encontrada.', 404);
            return;
        }

        if (empty($codigo)) $codigo = $current['codigo_centro_costo'];
        if (empty($nombre)) $nombre = $current['nombre'];

        // Verify unique code excluding self
        $checkCode = $this->db->prepare("
            SELECT id FROM areas
            WHERE codigo_centro_costo = ? AND id != ? AND deleted_at IS NULL
        ");
        $checkCode->execute([$codigo, $id]);
        if ($checkCode->fetch()) {
            $this->errorResponse("El código '{$codigo}' ya está en uso por otra área.", 409);
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE areas
            SET    codigo_centro_costo = :codigo,
                   nombre              = :nombre,
                   jefe_usuario_id     = :jefe_id
            WHERE  id = :id
        ");
        $stmt->execute([
            ':codigo'  => $codigo,
            ':nombre'  => $nombre,
            ':jefe_id' => $jefeId,
            ':id'      => $id,
        ]);

        $user = $this->getSessionUser();
        AuditLogger::log((int)$user['id'], 'ACTUALIZAR', 'areas', $id, $current, [
            'codigo_centro_costo' => $codigo,
            'nombre'              => $nombre,
            'jefe_usuario_id'     => $jefeId,
        ], $this->db);

        $this->jsonResponse(null, 'Área actualizada correctamente.');
    }

    public function destroy(array $params = []): void
    {
        $this->requireRole([1]);

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        $check = $this->db->prepare("SELECT * FROM areas WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Área no encontrada.', 404);
            return;
        }

        $stmt = $this->db->prepare("UPDATE areas SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        $user = $this->getSessionUser();
        AuditLogger::log((int)$user['id'], 'ELIMINAR', 'areas', $id, $current, null, $this->db);

        $this->jsonResponse(null, 'Área eliminada correctamente.');
    }
}
