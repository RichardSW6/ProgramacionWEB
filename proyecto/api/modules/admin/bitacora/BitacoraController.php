<?php
declare(strict_types=1);

class BitacoraController extends BaseController
{
    public function index(array $params = []): void
    {
        $this->requireRole([1]);

        $usuarioId  = isset($_GET['usuario_id'])  && $_GET['usuario_id']  !== '' ? (int)$_GET['usuario_id']  : null;
        $entidad    = trim($_GET['entidad']    ?? '');
        $fechaDesde = trim($_GET['fecha_desde'] ?? '');
        $fechaHasta = trim($_GET['fecha_hasta'] ?? '');
        $page       = (int)($_GET['page']     ?? 1);
        $perPage    = (int)($_GET['per_page'] ?? 25);

        $pagination = $this->paginate($page, $perPage);

        $where  = " WHERE 1=1";
        $binds  = [];

        if ($usuarioId !== null) {
            $where .= " AND b.usuario_id = :usuario_id";
            $binds[':usuario_id'] = $usuarioId;
        }
        if ($entidad !== '') {
            $where .= " AND b.entidad = :entidad";
            $binds[':entidad'] = $entidad;
        }
        if ($fechaDesde !== '') {
            $where .= " AND b.created_at >= :fecha_desde";
            $binds[':fecha_desde'] = $fechaDesde . ' 00:00:00';
        }
        if ($fechaHasta !== '') {
            $where .= " AND b.created_at <= :fecha_hasta";
            $binds[':fecha_hasta'] = $fechaHasta . ' 23:59:59';
        }

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM bitacora_auditoria b" . $where);
        $countStmt->execute($binds);
        $total = (int)$countStmt->fetchColumn();

        // Fetch paginated data
        $sql = "
            SELECT b.*,
                   CONCAT(u.nombre, ' ', u.apellido) AS usuario_nombre
            FROM   bitacora_auditoria b
            LEFT JOIN usuarios u ON b.usuario_id = u.id
            {$where}
            ORDER BY b.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'],   PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        $this->jsonResponse([
            'items'    => $items,
            'total'    => $total,
            'page'     => $pagination['page'],
            'per_page' => $pagination['per_page'],
        ]);
    }
}
