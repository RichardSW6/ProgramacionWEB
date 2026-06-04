<?php
declare(strict_types=1);

class AuditLogger
{
    public static function log(
        ?int   $userId,
        string $accion,
        string $entidad,
        ?int   $entidadId   = null,
               $valorAnterior = null,
               $valorNuevo    = null,
        PDO    $db          = null
    ): void {
        if ($db === null) {
            return;
        }

        try {
            $ip        = $_SERVER['REMOTE_ADDR']     ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            $anterior = $valorAnterior !== null ? json_encode($valorAnterior, JSON_UNESCAPED_UNICODE) : null;
            $nuevo    = $valorNuevo    !== null ? json_encode($valorNuevo,    JSON_UNESCAPED_UNICODE) : null;

            $stmt = $db->prepare("
                INSERT INTO bitacora_auditoria
                    (usuario_id, accion, entidad, entidad_id, valor_anterior, valor_nuevo, ip, user_agent)
                VALUES
                    (:usuario_id, :accion, :entidad, :entidad_id, :valor_anterior, :valor_nuevo, :ip, :user_agent)
            ");

            $stmt->execute([
                ':usuario_id'     => $userId,
                ':accion'         => $accion,
                ':entidad'        => $entidad,
                ':entidad_id'     => $entidadId,
                ':valor_anterior' => $anterior,
                ':valor_nuevo'    => $nuevo,
                ':ip'             => $ip,
                ':user_agent'     => $userAgent,
            ]);
        } catch (\Throwable $e) {
            // Silently fail – audit logging must not interrupt the main flow
        }
    }
}
