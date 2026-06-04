<?php
declare(strict_types=1);

class ReportesController extends BaseController
{
    public function gastosPorArea(array $params = []): void
    {
        $this->requireRole([1, 4]);

        $fechaDesde = trim($_GET['fecha_desde'] ?? date('Y-01-01'));
        $fechaHasta = trim($_GET['fecha_hasta'] ?? date('Y-12-31'));
        $areaId     = isset($_GET['area_id']) && $_GET['area_id'] !== '' ? (int)$_GET['area_id'] : null;
        $formato    = strtolower(trim($_GET['formato'] ?? 'json'));

        $sql = "
            SELECT a.nombre AS area,
                   DATE_FORMAT(g.fecha_emision, '%Y-%m') AS periodo,
                   COUNT(g.id)      AS cantidad_gastos,
                   SUM(g.total)     AS total_gastado,
                   SUM(g.iva)       AS total_iva,
                   SUM(g.subtotal)  AS total_subtotal
            FROM   gastos g
            JOIN   areas  a ON g.area_id = a.id
            WHERE  g.estado_id IN (3, 5)
              AND  g.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
        ";
        $binds = [':fecha_desde' => $fechaDesde, ':fecha_hasta' => $fechaHasta];

        if ($areaId !== null) {
            $sql .= " AND g.area_id = :area_id";
            $binds[':area_id'] = $areaId;
        }

        $sql .= " GROUP BY a.id, periodo ORDER BY periodo DESC, a.nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        $rows = $stmt->fetchAll();

        if ($formato === 'csv') {
            // Override content-type for CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gastos_por_area_' . date('Ymd') . '.csv"');

            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Área', 'Período', 'Cantidad Gastos', 'Total Gastado', 'Total IVA', 'Total Subtotal']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['area'],
                    $row['periodo'],
                    $row['cantidad_gastos'],
                    $row['total_gastado'],
                    $row['total_iva'],
                    $row['total_subtotal'],
                ]);
            }
            fclose($out);
            return;
        }

        $this->jsonResponse($rows);
    }

    public function presupuestoVsEjecutado(array $params = []): void
    {
        $this->requireRole([1, 4]);

        $anio = isset($_GET['anio']) && $_GET['anio'] !== '' ? (int)$_GET['anio'] : (int)date('Y');
        $mes  = isset($_GET['mes'])  && $_GET['mes']  !== '' ? (int)$_GET['mes']  : (int)date('m');

        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            $this->errorResponse('Año o mes inválido.', 400);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT a.nombre AS area,
                   p.monto_asignado AS presupuesto_asignado,
                   COALESCE(SUM(g.total), 0) AS total_aprobado,
                   p.monto_asignado - COALESCE(SUM(g.total), 0) AS disponible,
                   ROUND(COALESCE(SUM(g.total), 0) / p.monto_asignado * 100, 2) AS porcentaje_ejecutado
            FROM   presupuestos p
            JOIN   areas        a ON p.area_id   = a.id
            LEFT JOIN gastos    g ON g.area_id   = p.area_id
                                 AND YEAR(g.fecha_emision)  = p.anio
                                 AND MONTH(g.fecha_emision) = p.mes
                                 AND g.estado_id IN (3, 5)
            WHERE  p.anio = :anio AND p.mes = :mes
            GROUP BY p.id, a.id
            ORDER BY a.nombre
        ");
        $stmt->execute([':anio' => $anio, ':mes' => $mes]);
        $rows = $stmt->fetchAll();

        $this->jsonResponse($rows);
    }
}
