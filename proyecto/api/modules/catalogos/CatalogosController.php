<?php
declare(strict_types=1);

class CatalogosController extends BaseController
{
    public function categorias(array $params = []): void
    {
        $stmt = $this->db->query("
            SELECT id, nombre
            FROM   categorias_gasto
            WHERE  deleted_at IS NULL
            ORDER BY nombre
        ");
        $this->jsonResponse($stmt->fetchAll());
    }

    public function cuentas(array $params = []): void
    {
        $stmt = $this->db->query("
            SELECT id, nombre, numero_cuenta
            FROM   cuentas_gasto
            WHERE  deleted_at IS NULL
            ORDER BY nombre
        ");
        $this->jsonResponse($stmt->fetchAll());
    }

    public function conceptos(array $params = []): void
    {
        $stmt = $this->db->query("
            SELECT id, clave, descripcion
            FROM   conceptos_deducibilidad
            WHERE  deleted_at IS NULL
            ORDER BY clave
        ");
        $this->jsonResponse($stmt->fetchAll());
    }

    public function roles(array $params = []): void
    {
        $stmt = $this->db->query("
            SELECT id, nombre
            FROM   roles
            WHERE  deleted_at IS NULL
            ORDER BY id
        ");
        $this->jsonResponse($stmt->fetchAll());
    }

    public function estatuses(array $params = []): void
    {
        $stmt = $this->db->query("
            SELECT id, clave, nombre
            FROM   estatus_gasto
            ORDER BY id
        ");
        $this->jsonResponse($stmt->fetchAll());
    }
}
