<?php
declare(strict_types=1);

class PresupuestosController extends BaseController
{
    public function index(array $params = []): void
    {
        $this->requireRole([1, 4]);

        $areaId = isset($_GET['area_id']) && $_GET['area_id'] !== '' ? (int)$_GET['area_id'] : null;
        $anio   = isset($_GET['anio'])    && $_GET['anio']    !== '' ? (int)$_GET['anio']    : null;
        $mes    = isset($_GET['mes'])     && $_GET['mes']     !== '' ? (int)$_GET['mes']     : null;

        $sql   = "
            SELECT p.*,
                   a.nombre AS area_nombre,
                   COALESCE((
                       SELECT SUM(g.total)
                       FROM   gastos g
                       WHERE  g.area_id       = p.area_id
                         AND  YEAR(g.fecha_emision)  = p.anio
                         AND  MONTH(g.fecha_emision) = p.mes
                         AND  g.estado_id    IN (3, 5)
                   ), 0) AS total_aprobado
            FROM   presupuestos p
            JOIN   areas        a ON p.area_id = a.id
            WHERE  1=1
        ";
        $binds = [];

        if ($areaId !== null) {
            $sql .= " AND p.area_id = :area_id";
            $binds[':area_id'] = $areaId;
        }
        if ($anio !== null) {
            $sql .= " AND p.anio = :anio";
            $binds[':anio'] = $anio;
        }
        if ($mes !== null) {
            $sql .= " AND p.mes = :mes";
            $binds[':mes'] = $mes;
        }

        $sql .= " ORDER BY p.anio DESC, p.mes DESC, a.nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $this->jsonResponse($stmt->fetchAll());
    }

    public function store(array $params = []): void
    {
        $this->requireRole([1]);

        $body          = $this->getJsonBody();
        $areaId        = isset($body['area_id'])        ? (int)$body['area_id']        : 0;
        $anio          = isset($body['anio'])           ? (int)$body['anio']           : 0;
        $mes           = isset($body['mes'])            ? (int)$body['mes']            : 0;
        $montoAsignado = isset($body['monto_asignado']) ? (float)$body['monto_asignado'] : 0.0;

        $errors = [];
        if ($areaId <= 0)           $errors[] = 'El área es obligatoria.';
        if ($anio < 2000)           $errors[] = 'El año es inválido.';
        if ($mes < 1 || $mes > 12)  $errors[] = 'El mes debe estar entre 1 y 12.';
        if ($montoAsignado <= 0)    $errors[] = 'El monto asignado debe ser mayor a cero.';

        if (!empty($errors)) {
            $this->errorResponse('Datos incompletos o inválidos.', 400, $errors);
            return;
        }

        // Verify uniqueness
        $check = $this->db->prepare("SELECT id FROM presupuestos WHERE area_id=? AND anio=? AND mes=?");
        $check->execute([$areaId, $anio, $mes]);
        if ($check->fetch()) {
            $this->errorResponse('Ya existe un presupuesto para esa área en ese período.', 409);
            return;
        }

        $user = $this->getSessionUser();
        $stmt = $this->db->prepare("
            INSERT INTO presupuestos (area_id, anio, mes, monto_asignado, created_by)
            VALUES (:area_id, :anio, :mes, :monto, :created_by)
        ");
        $stmt->execute([
            ':area_id'    => $areaId,
            ':anio'       => $anio,
            ':mes'        => $mes,
            ':monto'      => $montoAsignado,
            ':created_by' => (int)$user['id'],
        ]);
        $newId = (int)$this->db->lastInsertId();

        AuditLogger::log((int)$user['id'], 'CREAR', 'presupuestos', $newId, null, [
            'area_id' => $areaId, 'anio' => $anio, 'mes' => $mes, 'monto_asignado' => $montoAsignado,
        ], $this->db);

        $this->jsonResponse(['id' => $newId], 'Presupuesto creado correctamente.', 201);
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

        $check = $this->db->prepare("SELECT * FROM presupuestos WHERE id=?");
        $check->execute([$id]);
        $current = $check->fetch();
        if (!$current) {
            $this->errorResponse('Presupuesto no encontrado.', 404);
            return;
        }

        $montoAsignado = isset($body['monto_asignado']) ? (float)$body['monto_asignado'] : (float)$current['monto_asignado'];

        if ($montoAsignado <= 0) {
            $this->errorResponse('El monto asignado debe ser mayor a cero.', 400);
            return;
        }

        $user = $this->getSessionUser();
        $stmt = $this->db->prepare("
            UPDATE presupuestos SET monto_asignado=:monto, updated_by=:updated_by WHERE id=:id
        ");
        $stmt->execute([
            ':monto'      => $montoAsignado,
            ':updated_by' => (int)$user['id'],
            ':id'         => $id,
        ]);

        AuditLogger::log((int)$user['id'], 'ACTUALIZAR', 'presupuestos', $id,
            ['monto_asignado' => $current['monto_asignado']],
            ['monto_asignado' => $montoAsignado],
            $this->db
        );

        $this->jsonResponse(null, 'Presupuesto actualizado correctamente.');
    }
}
