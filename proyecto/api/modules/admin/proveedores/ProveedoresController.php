<?php
declare(strict_types=1);

class ProveedoresController extends BaseController
{
    public function index(array $params = []): void
    {
        $search = trim($_GET['search'] ?? '');

        $sql    = "SELECT id, rfc, nombre FROM proveedores WHERE deleted_at IS NULL";
        $binds  = [];

        if ($search !== '') {
            $sql   .= " AND (rfc LIKE :search OR nombre LIKE :search)";
            $binds[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $this->jsonResponse($stmt->fetchAll());
    }

    public function store(array $params = []): void
    {
        $this->requireRole([1]);

        $body   = $this->getJsonBody();
        $rfc    = strtoupper(trim($body['rfc']    ?? ''));
        $nombre = trim($body['nombre'] ?? '');

        if (empty($rfc) || empty($nombre)) {
            $this->errorResponse('RFC y nombre son obligatorios.', 400);
            return;
        }

        // Verify unique RFC
        $check = $this->db->prepare("SELECT id FROM proveedores WHERE rfc = ? AND deleted_at IS NULL");
        $check->execute([$rfc]);
        if ($check->fetch()) {
            $this->errorResponse("El RFC '{$rfc}' ya está registrado.", 409);
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO proveedores (rfc, nombre) VALUES (:rfc, :nombre)");
        $stmt->execute([':rfc' => $rfc, ':nombre' => $nombre]);
        $newId = (int)$this->db->lastInsertId();

        $user = $this->getSessionUser();
        AuditLogger::log((int)$user['id'], 'CREAR', 'proveedores', $newId, null, [
            'rfc'    => $rfc,
            'nombre' => $nombre,
        ], $this->db);

        $this->jsonResponse(['id' => $newId], 'Proveedor creado correctamente.', 201);
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

        $check = $this->db->prepare("SELECT * FROM proveedores WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Proveedor no encontrado.', 404);
            return;
        }

        $rfc    = strtoupper(trim($body['rfc']    ?? $current['rfc']));
        $nombre = trim($body['nombre'] ?? $current['nombre']);

        // Verify unique RFC excluding self
        if ($rfc !== $current['rfc']) {
            $checkRfc = $this->db->prepare("SELECT id FROM proveedores WHERE rfc = ? AND id != ? AND deleted_at IS NULL");
            $checkRfc->execute([$rfc, $id]);
            if ($checkRfc->fetch()) {
                $this->errorResponse("El RFC '{$rfc}' ya está en uso por otro proveedor.", 409);
                return;
            }
        }

        $stmt = $this->db->prepare("UPDATE proveedores SET rfc=:rfc, nombre=:nombre WHERE id=:id");
        $stmt->execute([':rfc' => $rfc, ':nombre' => $nombre, ':id' => $id]);

        $user = $this->getSessionUser();
        AuditLogger::log((int)$user['id'], 'ACTUALIZAR', 'proveedores', $id, $current, [
            'rfc' => $rfc, 'nombre' => $nombre,
        ], $this->db);

        $this->jsonResponse(null, 'Proveedor actualizado correctamente.');
    }

    public function destroy(array $params = []): void
    {
        $this->requireRole([1]);

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        $check = $this->db->prepare("SELECT * FROM proveedores WHERE id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Proveedor no encontrado.', 404);
            return;
        }

        $this->db->prepare("UPDATE proveedores SET deleted_at=NOW() WHERE id=?")->execute([$id]);

        $user = $this->getSessionUser();
        AuditLogger::log((int)$user['id'], 'ELIMINAR', 'proveedores', $id, $current, null, $this->db);

        $this->jsonResponse(null, 'Proveedor eliminado correctamente.');
    }
}
