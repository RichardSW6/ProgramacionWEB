# Backlog Formal Priorizado

## Sistema de Gestión de Gastos Empresariales

---

# 1. Resumen ejecutivo del backlog

**Visión:**  
Construir incrementalmente una aplicación web on-premise de gestión de gastos empresariales con flujo de autorización por área, control presupuestal mensual, carga y extracción de CFDI XML, y bitácora inmutable.

**Criterio de priorización:**  
1. **Fundación técnica** (estructura, rutas, base de datos) → sin esto, nada funciona.  
2. **Seguridad básica** (autenticación, RBAC, sesiones) → para identificar usuarios y roles.  
3. **Catálogos mínimos** (roles, estatus, áreas, usuarios) → para operar el negocio.  
4. **Caso de uso núcleo** (captura de gasto + XML + envío a aprobación) → el corazón del sistema.  
5. **Flujo de aprobación** (jefe aprueba/rechaza, CxP finaliza) → cierra el ciclo.  
6. **Presupuesto** (asignación y validación) → control financiero.  
7. **Reportes y auditoría** → valor agregado.  
8. **Hardening** (seguridad profunda, respaldos) → producción real.

**Estrategia de construcción incremental:**  
Cada incremento es un *slice vertical* pequeño que entrega funcionalidad verificable.  
Se empieza con un backend desnudo (API), luego se añade frontend simple, y se avanza por casos de uso completos en lugar de capas horizontales.

**Lógica del orden:**  
Primero lo que desbloquea todo lo demás (router, DB, autenticación).  
Luego los catálogos que necesita el formulario de gasto (roles, áreas, usuarios, proveedores).  
Luego el propio formulario de gasto con XML.  
Luego la aprobación y presupuesto.  
Finalmente reportes, auditoría y endurecimiento.

---

# 2. Supuestos de trabajo

| ID | Supuesto |
|----|----------|
| S1 | El frontend se construirá en una carpeta `web/` separada que consume la API REST del backend (`api/`). |
| S2 | La autenticación será basada en sesiones (no JWT) almacenadas en el servidor. |
| S3 | El usuario administrador se crea manualmente mediante seeder inicial, no hay registro público. |
| S4 | Los catálogos base (roles, estatus_gasto, categorías, cuentas, conceptos de deducibilidad) se insertan mediante seeders. |
| S5 | El presupuesto se afecta desde que el gasto pasa a estado "Aprobado" (no antes). |
| S6 | La validación del XML solo es estructural y extracción de datos; no se valida contra SAT. |
| S7 | Soft delete está implementado para catálogos (deleted_at), pero no para gastos ni bitácora. |
| S8 | El frontend usará HTML5, CSS3, Bootstrap 5 (solo CSS) y jQuery para AJAX. |
| S9 | No hay necesidad de soporte multiempresa; todo es para una sola organización. |
| S10 | El folio del gasto se genera con formato `G-YYYYMMDD-XXXXX` secuencial. |
| S11 | La sesión expira después de 60 minutos de inactividad. |
| S12 | El reinicio del presupuesto mensual se ejecuta mediante script cron programado. |
| S13 | La moneda base es MXN (peso mexicano). |
| S14 | Los XML procesados son exclusivamente CFDI versión 4.0. |
| S15 | El sistema no realiza validaciones automáticas contra servicios web del SAT (por alcance definido). |

---

# 3. Huecos, ambigüedades o contradicciones detectadas

## Funcionales
| ID | Descripción | Supuesto adoptado |
|----|-------------|-------------------|
| H1 | No se especifica cómo se genera el `folio` único del gasto | Formato `G-YYYYMMDD-XXXXX` secuencial |
| H2 | No está claro si un gasto rechazado puede ser reenviado desde Borrador o desde Pendiente | Rechazado → capturista edita → vuelve a enviar |

## Técnicos
| ID | Descripción | Supuesto adoptado |
|----|-------------|-------------------|
| H3 | No se especifica si el frontend tendrá su propio router | `web/` como static HTML + JS que llama a la API |

## De datos
| ID | Descripción | Supuesto adoptado |
|----|-------------|-------------------|
| H4 | `presupuestos.created_by` y `updated_by` sin FK física | Relación lógica, no FK rígida |

## De seguridad
| ID | Descripción | Supuesto adoptado |
|----|-------------|-------------------|
| H5 | No se especifica tiempo de expiración de sesión | 60 minutos de inactividad |

## De operación
| ID | Descripción | Supuesto adoptado |
|----|-------------|-------------------|
| H6 | No se especifica cómo se reinicia el presupuesto mensual | Script cron que ejecuta servicio de reinicio |

---

# 4. Backlog inicial priorizado

## Fase 1: Fundación técnica (F-T01 a F-T05)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T01** | Estructura de directorios backend | foundation | Establecer la base del proyecto API | Crear estructura de carpetas `api/` según esquema: public, bootstrap, config, core, middlewares, modules, routes, utils, database, storage, tests | Permite empezar a codificar ordenadamente | Ninguna | Carpetas vacías, .htaccess de ejemplo, index.php básico, composer.json inicial | Lógica de negocio, configuración real de DB | Bajo | La carpeta `api/` existe con subcarpetas definidas; `composer.json` incluye autoload PSR-4 | Verificar estructura con `ls -la api/` | Crítica | XS | 1 |
| **F-T02** | Configuración de entorno y conexión DB | foundation | Configurar credenciales y conexión a MySQL | Crear `.env.example` y clase `Database.php` (PDO singleton). Leer variables de entorno | Permite que el sistema hable con la base de datos | F-T01 | Clase Database con método `getConnection()`, manejo de errores básico | Pool de conexiones, clustering, read replicas | Medio | La clase retorna instancia PDO válida o lanza excepción controlada | Ejecutar `Database::getConnection()` y verificar conexión | Crítica | XS | 2 |
| **F-T03** | Router básico (API REST) | foundation | Despachar peticiones HTTP a controladores | Implementar `Router.php` que lea `routes/api.php`, coincida método y URI, e invoque controlador | Permite tener endpoints funcionales | F-T01 | Soporte para GET, POST, PUT, DELETE; parámetros de ruta tipo `/gastos/{id}` | Middlewares, validación de entrada automática | Bajo | Petición `GET /ping` retorna `{"status":"ok"}` | `curl GET /ping` | Crítica | S | 3 |
| **F-T04** | BaseController y respuestas JSON | foundation | Unificar formato de respuestas de la API | Crear `BaseController.php` con métodos `jsonResponse()`, `errorResponse()`. Todos los controladores heredan | Respuestas consistentes | F-T03 | Respuestas con estructura `{success, data, message, errors}` | Logging automático | Bajo | Controlador de prueba retorna `{success:true}` | Llamar a endpoint de prueba | Crítica | XS | 4 |
| **F-T05** | Migraciones y seeders iniciales | foundation | Crear estructura de BD con catálogos base | Sistema simple de migraciones (archivos SQL en `database/migrations/`). Crear tablas: roles, estatus_gasto, categorias_gasto, cuentas_gasto, conceptos_deducibilidad | Base de datos lista para desarrollo | F-T02 | Scripts para crear tablas; seeders insertan catálogos base | Tablas de transacción (gastos, usuarios, áreas) | Bajo | Al ejecutar migraciones, todas las tablas existen; seeders llenan catálogos | `mysql> SHOW TABLES;` | Crítica | M | 5 |

## Fase 2: Seguridad base (F-T06 a F-T07)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T06** | Autenticación: login, logout, sesión | seguridad | Permitir que usuarios inicien sesión | Módulo Auth: `POST /auth/login`, `POST /auth/logout`, `GET /auth/me`. Validación de credenciales, sesiones nativas, password_hash BCRYPT | Control de acceso básico | F-T02, F-T05, F-T04 | Validación de credenciales, sesiones nativas, password_hash BCRYPT | Recuperación de contraseña, CORS avanzado | Medio | Usuario con credenciales correctas recibe `success:true` y datos básicos | Probar login con seeder de admin | Crítica | M | 1 |
| **F-T07** | Middleware de autenticación | seguridad | Proteger rutas que requieren sesión activa | Implementar `AuthMiddleware.php` que verifique `$_SESSION['user_id']`. Asignable en rutas mediante array `middleware: ['auth']` | Evita acceso anónimo a endpoints protegidos | F-T03, F-T06 | Middleware aplicable a ruta o grupo de rutas | RBAC específico por rol | Bajo | Acceder a `GET /gastos` sin sesión → 401; con sesión → pasa al controlador | `curl` sin cookie de sesión vs con cookie | Crítica | S | 2 |

## Fase 3: Catálogos y administración (F-T08 a F-T10)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T08** | CRUD de roles y gestión de usuarios (Admin) | catálogo | Administrador gestiona usuarios y asigna roles | Endpoints: `GET /admin/usuarios`, `POST /admin/usuarios`, `PUT /admin/usuarios/{id}`, `DELETE /admin/usuarios/{id}`. Campos: nombre, apellido, correo, rol_id, area_id, contraseña inicial | Permite dar de alta usuarios operativos | F-T02, F-T05, F-T06, F-T07 | CRUD completo de usuarios; asignación de rol y área; hash de contraseña | Recuperación de contraseña, envío de correo | Medio | Solo Admin puede listar/crear/editar/eliminar usuarios; otros roles reciben 403 | Login como Admin, crear usuario, editar, deshabilitar | Crítica | L | 1 |
| **F-T09** | CRUD de áreas y asignación de jefe | catálogo | Administrador gestiona áreas y centros de costo | Endpoints: `GET /admin/areas`, `POST /admin/areas`, `PUT /admin/areas/{id}`, `DELETE /admin/areas/{id}`. Campos: codigo_centro_costo, nombre, jefe_usuario_id (opcional) | Estructura organizacional para asignar gastos y aprobaciones | F-T02, F-T05, F-T06, F-T07 | CRUD de áreas; relación con usuario jefe; validación de código único | Subáreas o jerarquías complejas | Bajo | Admin puede crear un área, asignarle un jefe, y el área aparece en selectores | Crear área, asignar jefe, listar áreas | Alta | M | 2 |
| **F-T10** | CRUD de proveedores (simple) | catálogo | Administrador gestiona proveedores emisores de CFDI | Endpoints: `GET /proveedores` (público), `POST /admin/proveedores`, `PUT /admin/proveedores/{id}`. Campos: rfc (único), nombre | Evita escribir RFC manualmente; reportes por proveedor | F-T02, F-T05, F-T06, F-T07 | CRUD básico de proveedores; endpoint público de consulta para autocomplete | Sincronización con SAT | Bajo | Admin puede dar de alta un proveedor; capturista puede seleccionarlo en formulario | Alta proveedor, listar proveedores | Alta | S | 3 |

## Fase 4: Caso de uso núcleo - Captura de gasto (F-T12 a F-T16)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T12** | Servicio de extracción de datos de XML (CFDI v4.0) | servicio | Leer XML, extraer datos fiscales y validar estructura | Clase `CfdiProcessor.php` que extrae: UUID, RFC Emisor, RFC Receptor, Total, Subtotal, Impuestos (IVA), Fecha, FormaPago, UsoCFDI. Deshabilita entidades externas | Automatiza captura de datos desde factura | Ninguna | Parseo seguro con DOMDocument, validación de estructura mínima, retorno de arreglo | Validación contra SAT, timbre fiscal, complementos PPD | Medio | XML v4.0 válido → retorna array con todos los campos; XML corrupto → error | Probar con XML real y XML mal formado | Crítica | M | 1 |
| **F-T13** | Creación de gasto (cabecera + XML + detalles) | full slice (backend) | Capturista crea gasto en estado Borrador, carga XML y guarda | Endpoint `POST /gastos` con multipart/form-data. Valida XML, extrae datos, crea registro en `gastos` y `gasto_detalles`. Estado = Borrador | Primer paso del flujo de gasto | F-T02, F-T09, F-T10, F-T12, F-T04, F-T06, F-T07 | Guarda gasto en DB, almacena XML en `storage/xml/año/mes/`, genera folio, valida coincidencia de totales | Envío a aprobación, edición, validación de duplicados | Medio | Capturista envía XML + datos; sistema crea gasto con UUID extraído, total coincidente, estado Borrador | Postman o frontend simple para crear gasto | Crítica | L | 2 |
| **F-T14** | Validación de UUID duplicado | regla de negocio | Evitar que un mismo CFDI sea cargado dos veces | En `ExpenseService` antes de crear gasto, verificar si `uuid_cfdi` ya existe en `gastos`. Si existe, rechazar con mensaje | Evita duplicidad fiscal | F-T12, F-T13 | Validación en backend; respuesta de error 409 Conflict | No aplica | Bajo | Intentar cargar el mismo XML dos veces; segundo intento es rechazado | Crear gasto con XML, luego repetir | Alta | S | 3 |
| **F-T15** | Edición de gasto en estado Borrador | full slice (backend) | Capturista puede modificar gasto mientras esté en Borrador | Endpoint `PUT /gastos/{id}`. Solo permite editar si estado = Borrador. Puede cambiar campos manuales, reemplazar XML | Flexibilidad antes de enviar a aprobación | F-T13, F-T04, F-T06, F-T07, F-T14 | Actualiza campos; si se sube nuevo XML se reemplaza y actualiza datos fiscales | Aprobación, cambio de estado | Bajo | Capturista edita concepto, monto, proveedor; se guardan cambios. Editar gasto enviado → 403 | Editar borrador, verificar cambios | Alta | M | 4 |
| **F-T16** | Envío de gasto a aprobación | flujo | Capturista cambia estado de Borrador a Pendiente de aprobación | Endpoint `POST /gastos/{id}/enviar`. Valida estado = Borrador y total = suma detalles. Cambia estado, registra `fecha_envio_aprobacion`, escribe en bitácora | Gasto entra al flujo de autorización | F-T13, F-T05, F-T07 | Transición de estado, validación de integridad, registro en bitácora | Notificación por correo | Medio | Después del envío, estado = Pendiente y capturista no puede editarlo | Enviar gasto, verificar estado, intentar editar → 403 | Alta | M | 5 |

## Fase 5: Flujo de aprobación y listados (F-T17 a F-T19)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T17** | Listado de gastos por rol (con filtros) | frontend + backend | Mostrar gastos según permisos: capturista (suyos), jefe (de su área), CxP/Admin (todos) | Endpoint `GET /gastos` con parámetros: estatus, fecha_desde, fecha_hasta, proveedor_id, area_id. Backend aplica filtros según rol | Base para bandejas de trabajo | F-T06, F-T07, F-T13, F-T09 | Paginación (10, 25, 50), ordenamiento por fecha, proyección de campos | Exportación a CSV/PDF | Bajo | Jefe de área ve gastos de su área pero no de otras; capturista solo ve los suyos | Crear gastos con distintas áreas, loguearse con cada rol y consultar | Crítica | M | 1 |
| **F-T18** | Aprobación y rechazo por jefe de área | flujo | Jefe de área aprueba o rechaza gastos Pendientes de su área | Endpoints `POST /gastos/{id}/aprobar` y `POST /gastos/{id}/rechazar`. Rechazo requiere comentario obligatorio. Aprobación valida presupuesto disponible (advertencia si excede) | Cierre del primer nivel de autorización | F-T17, F-T11, F-T06, F-T07, F-T05 | Validación de que usuario es jefe del área; cálculo de presupuesto consumido | Bloqueo automático por falta de presupuesto | Medio | Jefe aprueba gasto dentro del presupuesto → estado Aprobado; excede → Aprobado + advertencia; rechaza con comentario → Rechazado | Login jefe, aprobar gasto, verificar bitácora, ver presupuesto | Crítica | M | 2 |
| **F-T19** | Finalización por Cuentas por Pagar | flujo | Usuario con rol CxP marca gastos Aprobados como Finalizados | Endpoint `POST /gastos/{id}/finalizar`. Solo accesible para rol Cuentas por Pagar. Cambia estado a Finalizado, registra `fecha_finalizacion` y `finalizado_por` | Cierre administrativo del ciclo de gasto | F-T17, F-T06, F-T07, F-T05 | Validación de rol; transición solo desde estado Aprobado; bitácora | Modificaciones posteriores a Finalizado | Bajo | Usuario CxP finaliza gasto Aprobado; estado cambia a Finalizado | Finalizar gasto, verificar que no se pueda aprobar nuevamente | Alta | S | 3 |

## Fase 6: Presupuesto (F-T11)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T11** | Asignación de presupuesto mensual por área | catálogo + presupuesto | Administrador asigna presupuesto a cada área por mes | Endpoints: `GET /admin/presupuestos`, `POST /admin/presupuestos`, `PUT /admin/presupuestos/{id}`. Validación: único por área+año+mes | Permite control presupuestal | F-T02, F-T09, F-T06, F-T07 | CRUD de presupuestos; cálculo de disponible sumando gastos aprobados del período | Validación automática al aprobar gasto (se hace en F-T18) | Bajo | Admin asigna presupuesto a Marketing para junio 2025; se guarda en `presupuestos` | Asignar presupuesto, verificar que no permite duplicados | Alta | M | 1 |

## Fase 7: Reportes (F-T20 a F-T21)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T20** | Reporte de gastos por área y período | reporte | Generar reporte tabular de gastos finalizados | Endpoint `GET /reportes/gastos-por-area` con parámetros: fecha_desde, fecha_hasta, area_id (opcional). Devuelve JSON con sumatorias agrupadas por área y mes | Visibilidad de gasto ejecutado | F-T17, F-T13 | Exportación a CSV y PDF (dompdf), solo accesible para CxP y Admin | Reportes por proveedor, presupuesto vs ejecutado | Bajo | Consultar reporte para Q1 2025 devuelve filas con área, mes, total_gastado | Llamar endpoint, verificar datos contra consulta manual | Alta | M | 1 |
| **F-T21** | Reporte presupuesto vs ejecutado | reporte | Comparar presupuesto asignado vs gasto aprobado por área y mes | Endpoint `GET /reportes/presupuesto-vs-ejecutado` con año y mes. Devuelve para cada área: presupuesto_asignado, total_aprobado, disponible, porcentaje_ejecutado | Control financiero | F-T11, F-T18, F-T17 | Cálculo dinámico sumando gastos con estado Aprobado y Finalizado del período | Edición de presupuesto sobre la marcha | Medio | Para área X con presupuesto 10,000 y gastos aprobados 3,500, disponible = 6,500 | Asignar presupuesto, aprobar gastos, consultar reporte | Alta | M | 2 |

## Fase 8: Auditoría (F-T22 a F-T23)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T22** | Bitácora de auditoría inmutable | auditoría | Registrar todas las acciones críticas en `bitacora_auditoria` | Servicio `AuditLogger` que recibe (usuario_id, accion, entidad, entidad_id, valor_anterior, valor_nuevo, ip, user_agent). Se dispara desde controladores en eventos clave | Trazabilidad total | F-T06, F-T13, F-T18, F-T19 | Inserción en tabla; permisos de DB solo INSERT para esta tabla | Consulta de bitácora | Bajo | Al aprobar un gasto, se registra en bitácora | Aprobar gasto, verificar fila en bitácora | Crítica | M | 1 |
| **F-T23** | Consulta de bitácora para administrador | auditoría | Admin puede ver la bitácora completa con filtros | Endpoint `GET /admin/bitacora` con filtros: usuario_id, entidad, fecha_desde, fecha_hasta. Solo rol Administrador. Devuelve paginado | Supervisión y auditoría interna | F-T22, F-T06, F-T07 | Filtros combinados, ordenamiento por timestamp descendente | Exportación, recuperación de datos borrados | Bajo | Admin consulta bitácora para ver quién rechazó un gasto específico | Login admin, filtrar por entidad_id = gasto X | Alta | M | 2 |

## Fase 9: Hardening y operación (F-T24 a F-T25)

| ID | Nombre | Tipo | Objetivo | Descripción | Valor | Dependencias | Alcance incluido | Alcance excluido | Riesgos | Criterios de aceptación | Pruebas | Prioridad | Tamaño | Orden |
|----|--------|------|----------|-------------|-------|---------------|------------------|------------------|---------|------------------------|---------|----------|--------|-------|
| **F-T24a** | Prevención SQL Injection | refinamiento | Proteger contra inyección SQL | Revisar todos los repositorios existentes; asegurar uso exclusivo de consultas preparadas con PDO `prepare()` + `execute()` | Seguridad para producción | Todos los items con DB | Cambiar cualquier concatenación de strings en SQL a consultas preparadas | ORM, query builders | Medio | Ninguna consulta contiene concatenación de variables directamente | Revisión de código, pruebas con inputs maliciosos | Crítica | M | 1 |
| **F-T24b** | Protección CSRF | refinamiento | Prevenir ataques CSRF | Implementar `CsrfMiddleware` que valide token en peticiones POST/PUT/DELETE. Token generado por servidor y enviado en header | Seguridad para producción | F-T03, F-T06 | Middleware aplicable a rutas que modifican estado; generación de token en sesión | Validación automática en frontend | Medio | Petición sin token CSRF válido recibe 403 | Enviar POST con token inválido vs válido | Alta | M | 2 |
| **F-T24c** | Escapado XSS en vistas | refinamiento | Prevenir XSS | Asegurar que toda salida de datos en frontend use `htmlspecialchars()` con ENT_QUOTES. Backend sanitiza inputs | Seguridad para producción | F-T04, frontend | Escapado automático en plantillas; validación de entradas para eliminar scripts incrustados | Auditoría de vulnerabilidades externa | Bajo | Input con `<script>` se muestra escapado como texto plano | Enviar `<script>alert(1)</script>` en campo y ver respuesta escapada | Alta | S | 3 |
| **F-T25** | Respaldo automático (cron) y retención 5 años | operación | Configurar script diario de respaldo de BD y XML | Script bash/PHP con `mysqldump` diario a las 2:00 AM, compresión, retención 30 días rotativa. `rsync` incremental para XML. Documentar política retención 5 años | Protección contra pérdida de datos | Ninguna | Respaldos programados en cron, verificación manual de integridad | Restauración automática | Bajo | Script se ejecuta a las 2am, genera archivo `.sql.gz`; se puede restaurar en entorno de prueba | Ejecutar script manualmente, verificar archivo generado | Media | M | 4 |

---

# 5. Agrupación por fases

## Fase 1: Fundación técnica (F-T01 a F-T05)
**Objetivo:** Tener un backend funcional, con enrutamiento, conexión a BD, migraciones y respuestas estandarizadas.  
**Razón:** Sin esto, no se puede construir nada encima.

## Fase 2: Seguridad base (F-T06 a F-T07)
**Objetivo:** Autenticación y control de sesiones.  
**Razón:** Necesitamos saber quién es el usuario antes de cualquier operación de negocio.

## Fase 3: Catálogos y administración (F-T08 a F-T10)
**Objetivo:** Administrar usuarios, áreas, proveedores.  
**Razón:** El formulario de gasto necesita áreas, proveedores y usuarios asignados.

## Fase 4: Caso de uso núcleo - Captura de gasto (F-T12 a F-T16)
**Objetivo:** Capturista puede crear, editar y enviar un gasto con XML.  
**Razón:** Es el corazón del sistema. Se entrega valor real temprano.

## Fase 5: Flujo de aprobación y listados (F-T17 a F-T19)
**Objetivo:** Jefe aprueba/rechaza, CxP finaliza.  
**Razón:** Completa el ciclo de vida del gasto.

## Fase 6: Presupuesto (F-T11)
**Objetivo:** Asignar presupuesto mensual y validar disponibilidad.  
**Razón:** Depende de áreas (Fase 3) y se usa en aprobación (Fase 5). Se coloca antes de Fase 5 en ejecución.

## Fase 7: Reportes (F-T20 a F-T21)
**Objetivo:** Visibilidad de gastos y control financiero.  
**Razón:** Valor agregado, pero depende de datos generados en fases anteriores.

## Fase 8: Auditoría (F-T22 a F-T23)
**Objetivo:** Trazabilidad completa.  
**Razón:** Puede construirse después del flujo principal, registrando eventos ya existentes.

## Fase 9: Hardening y operación (F-T24a, F-T24b, F-T24c, F-T25)
**Objetivo:** Seguridad profunda y respaldos.  
**Razón:** Preparación para producción real.

---

# 6. Secuencia recomendada de ejecución

**Orden real sugerido para construir incrementalmente:**

| Paso | Item | Nota |
|------|------|-------|
| 1 | F-T01 | Estructura de directorios |
| 2 | F-T02 | Conexión DB |
| 3 | F-T03 | Router |
| 4 | F-T04 | BaseController |
| 5 | F-T05 | Migraciones + seeders |
| 6 | F-T06 | Login/logout |
| 7 | F-T07 | Middleware auth |
| 8 | F-T09 | CRUD áreas (se adelanta) |
| 9 | F-T08 | CRUD usuarios |
| 10 | F-T10 | CRUD proveedores |
| 11 | F-T12 | Servicio XML |
| 12 | F-T13 | Crear gasto |
| 13 | F-T14 | UUID duplicado |
| 14 | F-T15 | Editar borrador |
| 15 | F-T16 | Enviar a aprobación |
| 16 | F-T11 | Presupuesto |
| 17 | F-T17 | Listado por rol |
| 18 | F-T18 | Aprobar/rechazar |
| 19 | F-T19 | Finalizar CxP |
| 20 | F-T20 | Reporte gastos por área |
| 21 | F-T21 | Reporte presupuesto vs ejecutado |
| 22 | F-T22 | Bitácora (registrar eventos) |
| 23 | F-T23 | Consulta de bitácora |
| 24 | F-T24a | SQL Injection |
| 25 | F-T24b | CSRF |
| 26 | F-T24c | XSS |
| 27 | F-T25 | Respaldos |

**Primer item ideal:** **F-T01** (Estructura de directorios). Sin eso, no hay dónde poner código.

**Items que no conviene adelantar:**
- **F-T18** (aprobación) sin tener **F-T11** (presupuesto) haría que la validación presupuestal no exista.
- **F-T22** (bitácora) antes de **F-T13** registraría eventos vacíos.

---

# 7. Backlog listo para usarse con una IA desarrolladora

**Primeros 5 items recomendados para construir (por orden):**

| Orden | ID | Nombre | Por qué está al inicio | Qué desbloquea |
|-------|----|--------|------------------------|----------------|
| 1 | **F-T01** | Estructura de directorios backend | Sin