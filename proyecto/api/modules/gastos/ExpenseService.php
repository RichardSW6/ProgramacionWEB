<?php
declare(strict_types=1);

class ExpenseService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function generateFolio(): string
    {
        $today = date('Ymd');

        $this->db->beginTransaction();
        try {
            // Lock and count gastos for today
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM gastos WHERE folio LIKE :pattern FOR UPDATE"
            );
            $stmt->execute([':pattern' => "G-{$today}-%"]);
            $count = (int)$stmt->fetchColumn();

            $folio = sprintf('G-%s-%05d', $today, $count + 1);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $folio;
    }

    public function validateUuidUnique(string $uuid, ?int $excludeId = null): bool
    {
        $sql   = "SELECT COUNT(*) FROM gastos WHERE uuid_cfdi = :uuid";
        $binds = [':uuid' => $uuid];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $binds[':exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($binds);
        return (int)$stmt->fetchColumn() === 0;
    }

    public function saveXml(string $xmlContent, string $uuid): string
    {
        $year  = date('Y');
        $month = date('m');

        // Base directory is the api root (two levels up from this file)
        $baseDir  = dirname(__DIR__, 3) . '/storage/xml/' . $year . '/' . $month;

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $filename = $uuid . '.xml';
        $fullPath = $baseDir . '/' . $filename;
        file_put_contents($fullPath, $xmlContent);

        return "storage/xml/{$year}/{$month}/{$filename}";
    }

    public function getBudgetStatus(int $areaId, int $anio, int $mes): array
    {
        // Get assigned budget
        $stmtP = $this->db->prepare("
            SELECT monto_asignado FROM presupuestos
            WHERE area_id=:area_id AND anio=:anio AND mes=:mes
        ");
        $stmtP->execute([':area_id' => $areaId, ':anio' => $anio, ':mes' => $mes]);
        $asignado = (float)($stmtP->fetchColumn() ?: 0);

        // Get approved + finalized total
        $stmtG = $this->db->prepare("
            SELECT COALESCE(SUM(total), 0)
            FROM   gastos
            WHERE  area_id      = :area_id
              AND  YEAR(fecha_emision)  = :anio
              AND  MONTH(fecha_emision) = :mes
              AND  estado_id   IN (3, 5)
        ");
        $stmtG->execute([':area_id' => $areaId, ':anio' => $anio, ':mes' => $mes]);
        $aprobado = (float)$stmtG->fetchColumn();

        $disponible = $asignado - $aprobado;

        return [
            'asignado'   => $asignado,
            'aprobado'   => $aprobado,
            'disponible' => $disponible,
            'excede'     => $disponible < 0,
        ];
    }
}
