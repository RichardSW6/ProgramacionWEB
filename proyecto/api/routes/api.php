<?php
declare(strict_types=1);

$router = new Router();

// ─── Ping ─────────────────────────────────────────────────────────────────────
$router->get('/ping', [PingController::class, 'index']);

// ─── Auth ─────────────────────────────────────────────────────────────────────
$router->post('/auth/login',  [AuthController::class, 'login']);
$router->post('/auth/logout', [AuthController::class, 'logout'], ['auth']);
$router->get('/auth/me',      [AuthController::class, 'me'],     ['auth']);

// ─── Catálogos auxiliares ─────────────────────────────────────────────────────
$router->get('/catalogos/categorias',  [CatalogosController::class, 'categorias'],  ['auth']);
$router->get('/catalogos/cuentas',     [CatalogosController::class, 'cuentas'],     ['auth']);
$router->get('/catalogos/conceptos',   [CatalogosController::class, 'conceptos'],   ['auth']);
$router->get('/catalogos/roles',       [CatalogosController::class, 'roles'],       ['auth']);
$router->get('/catalogos/estatuses',   [CatalogosController::class, 'estatuses'],   ['auth']);

// ─── Admin - Áreas ───────────────────────────────────────────────────────────
$router->get('/admin/areas',         [AreasController::class, 'index'],   ['auth']);
$router->post('/admin/areas',        [AreasController::class, 'store'],   ['auth']);
$router->put('/admin/areas/{id}',    [AreasController::class, 'update'],  ['auth']);
$router->delete('/admin/areas/{id}', [AreasController::class, 'destroy'], ['auth']);

// ─── Admin - Usuarios ────────────────────────────────────────────────────────
$router->get('/admin/usuarios',         [UsuariosController::class, 'index'],   ['auth']);
$router->post('/admin/usuarios',        [UsuariosController::class, 'store'],   ['auth']);
$router->get('/admin/usuarios/{id}',    [UsuariosController::class, 'show'],    ['auth']);
$router->put('/admin/usuarios/{id}',    [UsuariosController::class, 'update'],  ['auth']);
$router->delete('/admin/usuarios/{id}', [UsuariosController::class, 'destroy'], ['auth']);

// ─── Proveedores ─────────────────────────────────────────────────────────────
$router->get('/proveedores',               [ProveedoresController::class, 'index'],   ['auth']);
$router->post('/admin/proveedores',        [ProveedoresController::class, 'store'],   ['auth']);
$router->put('/admin/proveedores/{id}',    [ProveedoresController::class, 'update'],  ['auth']);
$router->delete('/admin/proveedores/{id}', [ProveedoresController::class, 'destroy'], ['auth']);

// ─── Presupuestos ─────────────────────────────────────────────────────────────
$router->get('/admin/presupuestos',      [PresupuestosController::class, 'index'],  ['auth']);
$router->post('/admin/presupuestos',     [PresupuestosController::class, 'store'],  ['auth']);
$router->put('/admin/presupuestos/{id}', [PresupuestosController::class, 'update'], ['auth']);

// ─── Bitácora ─────────────────────────────────────────────────────────────────
$router->get('/admin/bitacora', [BitacoraController::class, 'index'], ['auth']);

// ─── Gastos ───────────────────────────────────────────────────────────────────
$router->get('/gastos',                  [GastosController::class, 'index'],    ['auth']);
$router->post('/gastos',                 [GastosController::class, 'store'],    ['auth']);
$router->get('/gastos/{id}',             [GastosController::class, 'show'],     ['auth']);
$router->put('/gastos/{id}',             [GastosController::class, 'update'],   ['auth']);
$router->post('/gastos/{id}/enviar',     [GastosController::class, 'enviar'],   ['auth']);
$router->post('/gastos/{id}/aprobar',    [GastosController::class, 'aprobar'],  ['auth']);
$router->post('/gastos/{id}/rechazar',   [GastosController::class, 'rechazar'], ['auth']);
$router->post('/gastos/{id}/finalizar',  [GastosController::class, 'finalizar'],['auth']);

// ─── Reportes ─────────────────────────────────────────────────────────────────
$router->get('/reportes/gastos-por-area',          [ReportesController::class, 'gastosPorArea'],          ['auth']);
$router->get('/reportes/presupuesto-vs-ejecutado', [ReportesController::class, 'presupuestoVsEjecutado'], ['auth']);

return $router;
