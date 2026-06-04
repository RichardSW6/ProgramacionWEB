<?php
declare(strict_types=1);

class GastosController extends BaseController
{
    private ExpenseService $expenseService;

    public function __construct()
    {
        parent::__construct();
        $this->expenseService = new ExpenseService($this->db);
    }

    // ─── index ─────────────────────────────────────────────────────────────────
    public function index(array $params = []): void
    {
        $user    = $this->getSessionUser();
        $rolId   = (int)$user['rol_id'];
        $userId  = (int)$user['id'];
        $areaId  = $user['area_id'] ? (int)$user['area_id'] : null;

        $estadoId   = isset($_GET['estado_id'])    && $_GET['estado_id']    !== '' ? (int)$_GET['estado_id']    : null;
        $fechaDesde = trim($_GET['fecha_desde'] ?? '');
        $fechaHasta = trim($_GET['fecha_hasta'] ?? '');
        $provId     = isset($_GET['proveedor_id']) && $_GET['proveedor_id'] !== '' ? (int)$_GET['proveedor_id'] : null;
        $filtAreaId = isset($_GET['area_id'])      && $_GET['area_id']      !== '' ? (int)$_GET['area_id']      : null;
        $page       = (int)($_GET['page']     ?? 1);
        $perPage    = (int)($_GET['per_page'] ?? 25);
        $pagination = $this->paginate($page, $perPage);

        $where = " WHERE 1=1";
        $binds = [];

        // Role-based filtering
        if ($rolId === 2) {
            $where .= " AND g.capturado_por = :cap_user_id";
            $binds[':cap_user_id'] = $userId;
        } elseif ($rolId === 3) {
            $where .= " AND g.area_id = :user_area_id";
            $binds[':user_area_id'] = $areaId;
        }

        if ($estadoId !== null) {
            $where .= " AND g.estado_id = :estado_id";
            $binds[':estado_id'] = $estadoId;
        }
        if ($fechaDesde !== '') {
            $where .= " AND g.fecha_emision >= :fecha_desde";
            $binds[':fecha_desde'] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $where .= " AND g.fecha_emision <= :fecha_hasta";
            $binds[':fecha_hasta'] = $fechaHasta;
        }
        if ($provId !== null) {
            $where .= " AND g.proveedor_id = :proveedor_id";
            $binds[':proveedor_id'] = $provId;
        }
        if ($filtAreaId !== null && in_array($rolId, [1, 4], true)) {
            $where .= " AND g.area_id = :filt_area_id";
            $binds[':filt_area_id'] = $filtAreaId;
        }

        // Count
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM gastos g" . $where);
        $countStmt->execute($binds);
        $total = (int)$countStmt->fetchColumn();

        $sql = "
            SELECT g.id, g.folio, g.uuid_cfdi,
                   g.proveedor_id, p.nombre AS proveedor_nombre,
                   g.area_id, a.nombre AS area_nombre,
                   g.capturado_por, CONCAT(u.nombre,' ',u.apellido) AS capturado_por_nombre,
                   g.estado_id, e.nombre AS estado_nombre, e.clave AS estado_clave,
                   g.fecha_emision, g.fecha_carga, g.fecha_envio_aprobacion,
                   g.subtotal, g.iva, g.total, g.concepto,
                   g.categoria_id, g.cuenta_id, g.moneda,
                   g.rfc_emisor, g.rfc_receptor,
                   g.forma_pago, g.uso_cfdi
            FROM   gastos          g
            JOIN   estatus_gasto   e ON g.estado_id    = e.id
            JOIN   areas           a ON g.area_id       = a.id
            JOIN   usuarios        u ON g.capturado_por = u.id
            LEFT JOIN proveedores  p ON g.proveedor_id  = p.id
            {$where}
            ORDER BY g.fecha_carga DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'],   PDO::PARAM_INT);
        $stmt->execute();

        $this->jsonResponse([
            'items'    => $stmt->fetchAll(),
            'total'    => $total,
            'page'     => $pagination['page'],
            'per_page' => $pagination['per_page'],
        ]);
    }

    // ─── show ──────────────────────────────────────────────────────────────────
    public function show(array $params = []): void
    {
        $user   = $this->getSessionUser();
        $rolId  = (int)$user['rol_id'];
        $id     = (int)($params['id'] ?? 0);

        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT g.*,
                   e.nombre AS estado_nombre, e.clave AS estado_clave,
                   a.nombre AS area_nombre,
                   CONCAT(u.nombre,' ',u.apellido) AS capturado_por_nombre,
                   p.nombre AS proveedor_nombre, p.rfc AS proveedor_rfc
            FROM   gastos         g
            JOIN   estatus_gasto  e ON g.estado_id    = e.id
            JOIN   areas          a ON g.area_id       = a.id
            JOIN   usuarios       u ON g.capturado_por = u.id
            LEFT JOIN proveedores p ON g.proveedor_id  = p.id
            WHERE  g.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $gasto = $stmt->fetch();

        if (!$gasto) {
            $this->errorResponse('Gasto no encontrado.', 404);
            return;
        }

        // Permission check by role
        if ($rolId === 2 && (int)$gasto['capturado_por'] !== (int)$user['id']) {
            $this->errorResponse('No tienes permiso para ver este gasto.', 403);
            return;
        }
        if ($rolId === 3 && (int)$gasto['area_id'] !== (int)$user['area_id']) {
            $this->errorResponse('No tienes permiso para ver este gasto.', 403);
            return;
        }

        // Get detalles
        $detStmt = $this->db->prepare("SELECT * FROM gasto_detalles WHERE gasto_id=:id");
        $detStmt->execute([':id' => $id]);
        $gasto['detalles'] = $detStmt->fetchAll();

        $this->jsonResponse($gasto);
    }

    // ─── store ─────────────────────────────────────────────────────────────────
    public function store(array $params = []): void
    {
        $this->requireRole([1, 2]);

        $user   = $this->getSessionUser();
        $userId = (int)$user['id'];

        // Validate required fields from $_POST (multipart/form-data)
        $areaId      = isset($_POST['area_id'])      && $_POST['area_id']      !== '' ? (int)$_POST['area_id']     : 0;
        $concepto    = trim($_POST['concepto']    ?? '');
        $categoriaId = isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '' ? (int)$_POST['categoria_id'] : null;
        $cuentaId    = isset($_POST['cuenta_id'])    && $_POST['cuenta_id']    !== '' ? (int)$_POST['cuenta_id']    : null;
        $proveedorId = isset($_POST['proveedor_id']) && $_POST['proveedor_id'] !== '' ? (int)$_POST['proveedor_id'] : null;

        $errors = [];
        if ($areaId <= 0)    $errors[] = 'El área es obligatoria.';
        if (empty($concepto))$errors[] = 'El concepto es obligatorio.';

        if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'El archivo XML del CFDI es obligatorio.';
        }

        if (!empty($errors)) {
            $this->errorResponse('Datos incompletos.', 400, $errors);
            return;
        }

        // 1. Read & parse XML
        $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
        try {
            $cfdi = CfdiProcessor::parse($xmlContent);
        } catch (\Exception $e) {
            $this->errorResponse($e->getMessage(), 422);
            return;
        }

        // 2. Verify UUID uniqueness
        if (!$this->expenseService->validateUuidUnique($cfdi['uuid'])) {
            $this->errorResponse('Este CFDI ya fue registrado en el sistema (UUID duplicado).', 409);
            return;
        }

        // 3. Generate folio
        $folio = $this->expenseService->generateFolio();

        // 4. Save XML
        $xmlPath = $this->expenseService->saveXml($xmlContent, $cfdi['uuid']);

        // 5. Resolve proveedor
        if ($proveedorId !== null) {
            // Use given proveedor_id
        } else {
            // Try to find by RFC
            $stmtProv = $this->db->prepare("SELECT id FROM proveedores WHERE rfc=? AND deleted_at IS NULL");
            $stmtProv->execute([$cfdi['rfc_emisor']]);
            $existing = $stmtProv->fetchColumn();
            if ($existing) {
                $proveedorId = (int)$existing;
            } else {
                // Auto-create
                $ins = $this->db->prepare("INSERT INTO proveedores (rfc, nombre) VALUES (:rfc, :nombre)");
                $ins->execute([':rfc' => $cfdi['rfc_emisor'], ':nombre' => $cfdi['nombre_emisor'] ?: $cfdi['rfc_emisor']]);
                $proveedorId = (int)$this->db->lastInsertId();
            }
        }

        // 6. INSERT gasto
        $stmt = $this->db->prepare("
            INSERT INTO gastos
                (folio, uuid_cfdi, proveedor_id, area_id, capturado_por, estado_id,
                 fecha_emision, subtotal, iva, total, concepto,
                 categoria_id, cuenta_id, xml_path, moneda,
                 rfc_emisor, rfc_receptor, forma_pago, uso_cfdi)
            VALUES
                (:folio, :uuid, :proveedor_id, :area_id, :capturado_por, 1,
                 :fecha_emision, :subtotal, :iva, :total, :concepto,
                 :categoria_id, :cuenta_id, :xml_path, :moneda,
                 :rfc_emisor, :rfc_receptor, :forma_pago, :uso_cfdi)
        ");
        $stmt->execute([
            ':folio'        => $folio,
            ':uuid'         => $cfdi['uuid'],
            ':proveedor_id' => $proveedorId,
            ':area_id'      => $areaId,
            ':capturado_por'=> $userId,
            ':fecha_emision'=> $cfdi['fecha_emision'],
            ':subtotal'     => $cfdi['subtotal'],
            ':iva'          => $cfdi['iva'],
            ':total'        => $cfdi['total'],
            ':concepto'     => $concepto,
            ':categoria_id' => $categoriaId,
            ':cuenta_id'    => $cuentaId,
            ':xml_path'     => $xmlPath,
            ':moneda'       => 'MXN',
            ':rfc_emisor'   => $cfdi['rfc_emisor'],
            ':rfc_receptor' => $cfdi['rfc_receptor'],
            ':forma_pago'   => $cfdi['forma_pago'],
            ':uso_cfdi'     => $cfdi['uso_cfdi'],
        ]);
        $gastoId = (int)$this->db->lastInsertId();

        // 7. INSERT gasto_detalles
        $stmtDet = $this->db->prepare("
            INSERT INTO gasto_detalles (gasto_id, descripcion, cantidad, precio_unitario, importe)
            VALUES (:gasto_id, :desc, :cant, :precio, :importe)
        ");
        foreach ($cfdi['conceptos'] as $concepto_item) {
            $stmtDet->execute([
                ':gasto_id' => $gastoId,
                ':desc'     => $concepto_item['descripcion'],
                ':cant'     => $concepto_item['cantidad'],
                ':precio'   => $concepto_item['valor_unitario'],
                ':importe'  => $concepto_item['importe'],
            ]);
        }

        // 8. Audit
        AuditLogger::log($userId, 'CREAR', 'gastos', $gastoId, null, [
            'folio' => $folio, 'uuid' => $cfdi['uuid'], 'total' => $cfdi['total'],
        ], $this->db);

        $this->jsonResponse(['id' => $gastoId, 'folio' => $folio], 'Gasto registrado correctamente.', 201);
    }

    // ─── update ────────────────────────────────────────────────────────────────
    public function update(array $params = []): void
    {
        $this->requireRole([1, 2]);

        $user   = $this->getSessionUser();
        $userId = (int)$user['id'];
        $rolId  = (int)$user['rol_id'];
        $id     = (int)($params['id'] ?? 0);

        if ($id <= 0) {
            $this->errorResponse('ID inválido.', 400);
            return;
        }

        $checkStmt = $this->db->prepare("SELECT * FROM gastos WHERE id=?");
        $checkStmt->execute([$id]);
        $gasto = $checkStmt->fetch();

        if (!$gasto) {
            $this->errorResponse('Gasto no encontrado.', 404);
            return;
        }

        // Only borrador can be updated
        if ((int)$gasto['estado_id'] !== 1) {
            $this->errorResponse('Solo se pueden editar gastos en estado borrador.', 422);
            return;
        }

        // Capturista can only update own gastos
        if ($rolId === 2 && (int)$gasto['capturado_por'] !== $userId) {
            $this->errorResponse('No tienes permiso para editar este gasto.', 403);
            return;
        }

        // Read fields (multipart or JSON depending on client)
        $concepto    = isset($_POST['concepto'])     ? trim($_POST['concepto'])     : null;
        $categoriaId = isset($_POST['categoria_id']) && $_POST['categoria_id'] !== '' ? (int)$_POST['categoria_id'] : null;
        $cuentaId    = isset($_POST['cuenta_id'])    && $_POST['cuenta_id']    !== '' ? (int)$_POST['cuenta_id']    : null;
        $areaId      = isset($_POST['area_id'])      && $_POST['area_id']      !== '' ? (int)$_POST['area_id']      : null;
        $proveedorId = isset($_POST['proveedor_id']) && $_POST['proveedor_id'] !== '' ? (int)$_POST['proveedor_id'] : null;

        // Detect JSON body if no POST data
        if (empty($_POST)) {
            $body        = $this->getJsonBody();
            $concepto    = isset($body['concepto'])     ? trim($body['concepto'])     : null;
            $categoriaId = isset($body['categoria_id']) && $body['categoria_id'] !== '' ? (int)$body['categoria_id'] : null;
            $cuentaId    = isset($body['cuenta_id'])    && $body['cuenta_id']    !== '' ? (int)$body['cuenta_id']    : null;
            $areaId      = isset($body['area_id'])      && $body['area_id']      !== '' ? (int)$body['area_id']      : null;
            $proveedorId = isset($body['proveedor_id']) && $body['proveedor_id'] !== '' ? (int)$body['proveedor_id'] : null;
        }

        // Merge with existing
        $newConcepto    = $concepto    ?? $gasto['concepto'];
        $newCategoriaId = $categoriaId ?? $gasto['categoria_id'];
        $newCuentaId    = $cuentaId    ?? $gasto['cuenta_id'];
        $newAreaId      = $areaId      ?? $gasto['area_id'];
        $newProveedorId = $proveedorId ?? $gasto['proveedor_id'];

        // Handle new XML if uploaded
        $newUuid        = $gasto['uuid_cfdi'];
        $newXmlPath     = $gasto['xml_path'];
        $newSubtotal    = $gasto['subtotal'];
        $newIva         = $gasto['iva'];
        $newTotal       = $gasto['total'];
        $newFecha       = $gasto['fecha_emision'];
        $newRfcEmisor   = $gasto['rfc_emisor'];
        $newRfcReceptor = $gasto['rfc_receptor'];
        $newFormaPago   = $gasto['forma_pago'];
        $newUsoCfdi     = $gasto['uso_cfdi'];

        if (isset($_FILES['xml_file']) && $_FILES['xml_file']['error'] === UPLOAD_ERR_OK) {
            $xmlContent = file_get_contents($_FILES['xml_file']['tmp_name']);
            try {
                $cfdi = CfdiProcessor::parse($xmlContent);
            } catch (\Exception $e) {
                $this->errorResponse($e->getMessage(), 422);
                return;
            }

            if (!$this->expenseService->validateUuidUnique($cfdi['uuid'], $id)) {
                $this->errorResponse('El UUID del nuevo CFDI ya está registrado.', 409);
                return;
            }

            $newUuid        = $cfdi['uuid'];
            $newXmlPath     = $this->expenseService->saveXml($xmlContent, $cfdi['uuid']);
            $newSubtotal    = $cfdi['subtotal'];
            $newIva         = $cfdi['iva'];
            $newTotal       = $cfdi['total'];
            $newFecha       = $cfdi['fecha_emision'];
            $newRfcEmisor   = $cfdi['rfc_emisor'];
            $newRfcReceptor = $cfdi['rfc_receptor'];
            $newFormaPago   = $cfdi['forma_pago'];
            $newUsoCfdi     = $cfdi['uso_cfdi'];

            // Replace detalles
            $this->db->prepare("DELETE FROM gasto_detalles WHERE gasto_id=?")->execute([$id]);
            $stmtDet = $this->db->prepare("
                INSERT INTO gasto_detalles (gasto_id, descripcion, cantidad, precio_unitario, importe)
                VALUES (:gasto_id, :desc, :cant, :precio, :importe)
            ");
            foreach ($cfdi['conceptos'] as $c) {
                $stmtDet->execute([
                    ':gasto_id' => $id,
                    ':desc'     => $c['descripcion'],
                    ':cant'     => $c['cantidad'],
                    ':precio'   => $c['valor_unitario'],
                    ':importe'  => $c['importe'],
                ]);
            }
        }

        $stmt = $this->db->prepare("
            UPDATE gastos SET
                concepto=:concepto, categoria_id=:categoria_id, cuenta_id=:cuenta_id,
                area_id=:area_id, proveedor_id=:proveedor_id,
                uuid_cfdi=:uuid, xml_path=:xml_path,
                subtotal=:subtotal, iva=:iva, total=:total,
                fecha_emision=:fecha, rfc_emisor=:rfc_emisor, rfc_receptor=:rfc_receptor,
                forma_pago=:forma_pago, uso_cfdi=:uso_cfdi
            WHERE id=:id
        ");
        $stmt->execute([
            ':concepto'     => $newConcepto,
            ':categoria_id' => $newCategoriaId,
            ':cuenta_id'    => $newCuentaId,
            ':area_id'      => $newAreaId,
            ':proveedor_id' => $newProveedorId,
            ':uuid'         => $newUuid,
            ':xml_path'     => $newXmlPath,
            ':subtotal'     => $newSubtotal,
            ':iva'          => $newIva,
            ':total'        => $newTotal,
            ':fecha'        => $newFecha,
            ':rfc_emisor'   => $newRfcEmisor,
            ':rfc_receptor' => $newRfcReceptor,
            ':forma_pago'   => $newFormaPago,
            ':uso_cfdi'     => $newUsoCfdi,
            ':id'           => $id,
        ]);

        AuditLogger::log($userId, 'ACTUALIZAR', 'gastos', $id, null, null, $this->db);

        $this->jsonResponse(null, 'Gasto actualizado correctamente.');
    }

    // ─── enviar ────────────────────────────────────────────────────────────────
    public function enviar(array $params = []): void
    {
        $this->requireRole([1, 2]);

        $user   = $this->getSessionUser();
        $userId = (int)$user['id'];
        $rolId  = (int)$user['rol_id'];
        $id     = (int)($params['id'] ?? 0);

        $gasto = $this->fetchGasto($id);
        if (!$gasto) { $this->errorResponse('Gasto no encontrado.', 404); return; }

        if ((int)$gasto['estado_id'] !== 1) {
            $this->errorResponse('Solo se pueden enviar gastos en estado borrador.', 422);
            return;
        }
        if ($rolId === 2 && (int)$gasto['capturado_por'] !== $userId) {
            $this->errorResponse('Solo puedes enviar tus propios gastos.', 403);
            return;
        }

        $this->db->prepare("
            UPDATE gastos SET estado_id=2, fecha_envio_aprobacion=NOW() WHERE id=?
        ")->execute([$id]);

        AuditLogger::log($userId, 'ENVIAR_APROBACION', 'gastos', $id, null, ['estado_id' => 2], $this->db);

        $this->jsonResponse(null, 'Gasto enviado a aprobación correctamente.');
    }

    // ─── aprobar ───────────────────────────────────────────────────────────────
    public function aprobar(array $params = []): void
    {
        $this->requireRole([1, 3]);

        $user   = $this->getSessionUser();
        $userId = (int)$user['id'];
        $rolId  = (int)$user['rol_id'];
        $id     = (int)($params['id'] ?? 0);

        $gasto = $this->fetchGasto($id);
        if (!$gasto) { $this->errorResponse('Gasto no encontrado.', 404); return; }

        if ((int)$gasto['estado_id'] !== 2) {
            $this->errorResponse('Solo se pueden aprobar gastos en estado pendiente.', 422);
            return;
        }
        if ($rolId === 3 && (int)$gasto['area_id'] !== (int)$user['area_id']) {
            $this->errorResponse('Solo puedes aprobar gastos de tu área.', 403);
            return;
        }

        $this->db->prepare("
            UPDATE gastos SET estado_id=3, aprobado_por=? WHERE id=?
        ")->execute([$userId, $id]);

        AuditLogger::log($userId, 'APROBAR', 'gastos', $id, null, ['estado_id' => 3], $this->db);

        // Budget status info
        $fecha       = new \DateTime($gasto['fecha_emision']);
        $budgetStatus = $this->expenseService->getBudgetStatus(
            (int)$gasto['area_id'],
            (int)$fecha->format('Y'),
            (int)$fecha->format('m')
        );

        $message = 'Gasto aprobado correctamente.';
        if ($budgetStatus['excede']) {
            $message .= ' ADVERTENCIA: El área ha excedido su presupuesto para este período.';
        }

        $this->jsonResponse(['presupuesto' => $budgetStatus], $message);
    }

    // ─── rechazar ──────────────────────────────────────────────────────────────
    public function rechazar(array $params = []): void
    {
        $this->requireRole([1, 3]);

        $user   = $this->getSessionUser();
        $userId = (int)$user['id'];
        $rolId  = (int)$user['rol_id'];
        $id     = (int)($params['id'] ?? 0);
        $body   = $this->getJsonBody();

        $motivo = trim($body['motivo_rechazo'] ?? '');

        if (strlen($motivo) < 10) {
            $this->errorResponse('El motivo de rechazo debe tener al menos 10 caracteres.', 400);
            return;
        }

        $gasto = $this->fetchGasto($id);
        if (!$gasto) { $this->errorResponse('Gasto no encontrado.', 404); return; }

        if ((int)$gasto['estado_id'] !== 2) {
            $this->errorResponse('Solo se pueden rechazar gastos en estado pendiente.', 422);
            return;
        }
        if ($rolId === 3 && (int)$gasto['area_id'] !== (int)$user['area_id']) {
            $this->errorResponse('Solo puedes rechazar gastos de tu área.', 403);
            return;
        }

        $this->db->prepare("
            UPDATE gastos SET estado_id=4, rechazado_por=:por, motivo_rechazo=:motivo WHERE id=:id
        ")->execute([':por' => $userId, ':motivo' => $motivo, ':id' => $id]);

        AuditLogger::log($userId, 'RECHAZAR', 'gastos', $id, null, [
            'estado_id' => 4, 'motivo_rechazo' => $motivo,
        ], $this->db);

        $this->jsonResponse(null, 'Gasto rechazado.');
    }

    // ─── finalizar ─────────────────────────────────────────────────────────────
    public function finalizar(array $params = []): void
    {
        $this->requireRole([4]);

        $user   = $this->getSessionUser();
        $userId = (int)$user['id'];
        $id     = (int)($params['id'] ?? 0);

        $gasto = $this->fetchGasto($id);
        if (!$gasto) { $this->errorResponse('Gasto no encontrado.', 404); return; }

        if ((int)$gasto['estado_id'] !== 3) {
            $this->errorResponse('Solo se pueden finalizar gastos en estado aprobado.', 422);
            return;
        }

        $this->db->prepare("
            UPDATE gastos SET estado_id=5, finalizado_por=?, fecha_finalizacion=NOW() WHERE id=?
        ")->execute([$userId, $id]);

        AuditLogger::log($userId, 'FINALIZAR', 'gastos', $id, null, ['estado_id' => 5], $this->db);

        $this->jsonResponse(null, 'Gasto finalizado correctamente.');
    }

    // ─── Helper ────────────────────────────────────────────────────────────────
    private function fetchGasto(int $id): array|false
    {
        if ($id <= 0) return false;
        $stmt = $this->db->prepare("SELECT * FROM gastos WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
