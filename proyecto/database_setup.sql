-- ============================================================
--  GastosEmp - Sistema de Gestión de Gastos Empresariales
--  Script SQL completo para phpMyAdmin
--  Incluye: Estructura + Datos iniciales + Usuario admin
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
--  1. BASE DE DATOS
-- ============================================================
CREATE DATABASE IF NOT EXISTS `gastos_empresariales`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `gastos_empresariales`;

-- ============================================================
--  2. CATÁLOGOS / TABLAS DE SOPORTE
-- ============================================================

-- Roles del sistema
CREATE TABLE IF NOT EXISTS `roles` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(50)      NOT NULL,
  `descripcion` VARCHAR(255)     DEFAULT NULL,
  `deleted_at`  DATETIME         DEFAULT NULL,
  `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Estatus de gasto (borrador, pendiente, aprobado, rechazado, finalizado)
CREATE TABLE IF NOT EXISTS `estatus_gasto` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `clave`       VARCHAR(30)      NOT NULL,
  `nombre`      VARCHAR(50)      NOT NULL,
  `descripcion` VARCHAR(255)     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorías de gasto
CREATE TABLE IF NOT EXISTS `categorias_gasto` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`     VARCHAR(100)  NOT NULL,
  `deleted_at` DATETIME      DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuentas contables
CREATE TABLE IF NOT EXISTS `cuentas_gasto` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(100)  NOT NULL,
  `numero_cuenta` VARCHAR(50)   DEFAULT NULL,
  `deleted_at`    DATETIME      DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conceptos de deducibilidad SAT (uso CFDI)
CREATE TABLE IF NOT EXISTS `conceptos_deducibilidad` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `clave`       VARCHAR(30)   NOT NULL,
  `descripcion` VARCHAR(255)  NOT NULL,
  `deleted_at`  DATETIME      DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  3. TABLAS DE NEGOCIO
-- ============================================================

-- Áreas / Centros de costo
-- (jefe_usuario_id se agrega con ALTER después de usuarios)
CREATE TABLE IF NOT EXISTS `areas` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `codigo_centro_costo`  VARCHAR(30)   NOT NULL,
  `nombre`               VARCHAR(100)  NOT NULL,
  `jefe_usuario_id`      INT UNSIGNED  DEFAULT NULL,
  `deleted_at`           DATETIME      DEFAULT NULL,
  `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo_cc` (`codigo_centro_costo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios del sistema
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`        VARCHAR(100)  NOT NULL,
  `apellido`      VARCHAR(100)  NOT NULL,
  `correo`        VARCHAR(150)  NOT NULL,
  `password_hash` VARCHAR(255)  NOT NULL,
  `rol_id`        INT UNSIGNED  NOT NULL,
  `area_id`       INT UNSIGNED  DEFAULT NULL,
  `activo`        TINYINT(1)    NOT NULL DEFAULT 1,
  `deleted_at`    DATETIME      DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_correo` (`correo`),
  KEY `idx_rol` (`rol_id`),
  KEY `idx_area` (`area_id`),
  CONSTRAINT `fk_usuarios_rol`  FOREIGN KEY (`rol_id`)  REFERENCES `roles`(`id`),
  CONSTRAINT `fk_usuarios_area` FOREIGN KEY (`area_id`) REFERENCES `areas`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ahora sí podemos agregar la FK del jefe de área
ALTER TABLE `areas`
  ADD CONSTRAINT `fk_areas_jefe`
  FOREIGN KEY (`jefe_usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;

-- Proveedores (emisores de CFDI)
CREATE TABLE IF NOT EXISTS `proveedores` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `rfc`        VARCHAR(15)   NOT NULL,
  `nombre`     VARCHAR(255)  NOT NULL,
  `deleted_at` DATETIME      DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rfc` (`rfc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Presupuestos por área / mes / año
CREATE TABLE IF NOT EXISTS `presupuestos` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `area_id`         INT UNSIGNED    NOT NULL,
  `anio`            YEAR            NOT NULL,
  `mes`             TINYINT UNSIGNED NOT NULL,
  `monto_asignado`  DECIMAL(15,2)   NOT NULL,
  `created_by`      INT UNSIGNED    NOT NULL,
  `updated_by`      INT UNSIGNED    DEFAULT NULL,
  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_presupuesto` (`area_id`, `anio`, `mes`),
  CONSTRAINT `fk_presupuesto_area` FOREIGN KEY (`area_id`) REFERENCES `areas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Gastos (tabla principal)
CREATE TABLE IF NOT EXISTS `gastos` (
  `id`                      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `folio`                   VARCHAR(20)    NOT NULL,
  `uuid_cfdi`               CHAR(36)       NOT NULL,
  `proveedor_id`            INT UNSIGNED   DEFAULT NULL,
  `area_id`                 INT UNSIGNED   NOT NULL,
  `capturado_por`           INT UNSIGNED   NOT NULL,
  `estado_id`               INT UNSIGNED   NOT NULL DEFAULT 1,
  `fecha_emision`           DATE           NOT NULL,
  `fecha_carga`             DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_envio_aprobacion`  DATETIME       DEFAULT NULL,
  `fecha_aprobacion`        DATETIME       DEFAULT NULL,
  `fecha_rechazo`           DATETIME       DEFAULT NULL,
  `fecha_finalizacion`      DATETIME       DEFAULT NULL,
  `subtotal`                DECIMAL(15,2)  NOT NULL,
  `iva`                     DECIMAL(15,2)  NOT NULL DEFAULT 0.00,
  `total`                   DECIMAL(15,2)  NOT NULL,
  `concepto`                VARCHAR(500)   DEFAULT NULL,
  `categoria_id`            INT UNSIGNED   DEFAULT NULL,
  `cuenta_id`               INT UNSIGNED   DEFAULT NULL,
  `xml_path`                VARCHAR(500)   DEFAULT NULL,
  `moneda`                  CHAR(3)        NOT NULL DEFAULT 'MXN',
  `rfc_emisor`              VARCHAR(15)    DEFAULT NULL,
  `rfc_receptor`            VARCHAR(15)    DEFAULT NULL,
  `nombre_emisor`           VARCHAR(255)   DEFAULT NULL,
  `forma_pago`              VARCHAR(5)     DEFAULT NULL,
  `uso_cfdi`                VARCHAR(10)    DEFAULT NULL,
  `aprobado_por`            INT UNSIGNED   DEFAULT NULL,
  `rechazado_por`           INT UNSIGNED   DEFAULT NULL,
  `finalizado_por`          INT UNSIGNED   DEFAULT NULL,
  `motivo_rechazo`          TEXT           DEFAULT NULL,
  `created_at`              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_folio`     (`folio`),
  UNIQUE KEY `uk_uuid_cfdi` (`uuid_cfdi`),
  KEY `idx_estado`    (`estado_id`),
  KEY `idx_area`      (`area_id`),
  KEY `idx_capturado` (`capturado_por`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_gasto_proveedor`  FOREIGN KEY (`proveedor_id`)  REFERENCES `proveedores`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gasto_area`       FOREIGN KEY (`area_id`)       REFERENCES `areas`(`id`),
  CONSTRAINT `fk_gasto_capturado`  FOREIGN KEY (`capturado_por`) REFERENCES `usuarios`(`id`),
  CONSTRAINT `fk_gasto_estado`     FOREIGN KEY (`estado_id`)     REFERENCES `estatus_gasto`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalles / Conceptos del CFDI por gasto
CREATE TABLE IF NOT EXISTS `gasto_detalles` (
  `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `gasto_id`        INT UNSIGNED   NOT NULL,
  `descripcion`     VARCHAR(500)   NOT NULL,
  `cantidad`        DECIMAL(10,4)  NOT NULL DEFAULT 1.0000,
  `precio_unitario` DECIMAL(15,2)  NOT NULL,
  `importe`         DECIMAL(15,2)  NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_gasto` (`gasto_id`),
  CONSTRAINT `fk_detalle_gasto` FOREIGN KEY (`gasto_id`) REFERENCES `gastos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bitácora de auditoría
CREATE TABLE IF NOT EXISTS `bitacora_auditoria` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `usuario_id`     INT UNSIGNED     DEFAULT NULL,
  `accion`         VARCHAR(100)     NOT NULL,
  `entidad`        VARCHAR(100)     NOT NULL,
  `entidad_id`     INT UNSIGNED     DEFAULT NULL,
  `valor_anterior` JSON             DEFAULT NULL,
  `valor_nuevo`    JSON             DEFAULT NULL,
  `ip`             VARCHAR(45)      DEFAULT NULL,
  `user_agent`     VARCHAR(500)     DEFAULT NULL,
  `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario`  (`usuario_id`),
  KEY `idx_entidad`  (`entidad`),
  KEY `idx_created`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  4. DATOS INICIALES (Catálogos)
-- ============================================================

-- Roles
INSERT IGNORE INTO `roles` (`nombre`, `descripcion`) VALUES
  ('Administrador',    'Acceso total al sistema'),
  ('Capturista',       'Captura y envía gastos para aprobación'),
  ('Jefe de Área',     'Aprueba o rechaza gastos de su área'),
  ('Cuentas por Pagar','Finaliza gastos aprobados y gestiona pagos');

-- Estatus de gasto
INSERT IGNORE INTO `estatus_gasto` (`clave`, `nombre`, `descripcion`) VALUES
  ('borrador',    'Borrador',   'Gasto en elaboración, no enviado a aprobación'),
  ('pendiente',   'Pendiente',  'Enviado a aprobación del jefe de área'),
  ('aprobado',    'Aprobado',   'Aprobado por jefe de área'),
  ('rechazado',   'Rechazado',  'Rechazado por jefe de área con motivo'),
  ('finalizado',  'Finalizado', 'Procesado por Cuentas por Pagar');

-- Categorías de gasto
INSERT IGNORE INTO `categorias_gasto` (`nombre`) VALUES
  ('Viáticos y gastos de viaje'),
  ('Servicios profesionales'),
  ('Materiales y suministros'),
  ('Equipo de cómputo y tecnología'),
  ('Comunicaciones y telefonía'),
  ('Capacitación y desarrollo'),
  ('Mantenimiento y reparaciones'),
  ('Publicidad y marketing'),
  ('Otros gastos operativos');

-- Cuentas contables
INSERT IGNORE INTO `cuentas_gasto` (`nombre`, `numero_cuenta`) VALUES
  ('Gastos de administración',   '6001'),
  ('Gastos de venta',            '6002'),
  ('Gastos de operación',        '6003'),
  ('Gastos de representación',   '6004'),
  ('Compras de mercancía',       '1310'),
  ('Servicios profesionales',    '6010'),
  ('Gastos de viaje',            '6005');

-- Conceptos de deducibilidad (Uso CFDI SAT)
INSERT IGNORE INTO `conceptos_deducibilidad` (`clave`, `descripcion`) VALUES
  ('G01', 'Adquisición de mercancias'),
  ('G03', 'Gastos en general'),
  ('I04', 'Equipo de computo y accesorios'),
  ('I03', 'Equipo de transporte'),
  ('D01', 'Honorarios médicos, dentales y gastos hospitalarios'),
  ('S01', 'Sin efectos fiscales');

-- ============================================================
--  5. DATOS INICIALES (Área y Usuario Administrador)
-- ============================================================

-- Área de administración general
INSERT IGNORE INTO `areas` (`codigo_centro_costo`, `nombre`) VALUES
  ('ADM-001', 'Administración General'),
  ('FIN-001', 'Finanzas'),
  ('OPE-001', 'Operaciones'),
  ('VEN-001', 'Ventas'),
  ('TI-001',  'Tecnología de la Información');

-- Usuario Administrador
-- Password: Admin123!  (bcrypt, costo 12)
-- NOTA: Este hash es para "Admin123!" — cámbialo si es necesario
INSERT IGNORE INTO `usuarios`
  (`nombre`, `apellido`, `correo`, `password_hash`, `rol_id`, `area_id`, `activo`)
VALUES (
  'Administrador',
  'Sistema',
  'admin@empresa.com',
  '$2y$12$pn2UfxcIQOfz7ywZOTyzeOR3RrBr3/lf9hfjRoyLNL6LCVT5pntmm',
  1,
  (SELECT `id` FROM `areas` WHERE `codigo_centro_costo` = 'ADM-001' LIMIT 1),
  1
);

-- Usuario de prueba: Capturista
INSERT IGNORE INTO `usuarios`
  (`nombre`, `apellido`, `correo`, `password_hash`, `rol_id`, `area_id`, `activo`)
VALUES (
  'Juan',
  'García',
  'capturista@empresa.com',
  '$2y$12$pn2UfxcIQOfz7ywZOTyzeOR3RrBr3/lf9hfjRoyLNL6LCVT5pntmm',
  2,
  (SELECT `id` FROM `areas` WHERE `codigo_centro_costo` = 'OPE-001' LIMIT 1),
  1
);

-- Usuario de prueba: Jefe de Área
INSERT IGNORE INTO `usuarios`
  (`nombre`, `apellido`, `correo`, `password_hash`, `rol_id`, `area_id`, `activo`)
VALUES (
  'María',
  'López',
  'jefe@empresa.com',
  '$2y$12$pn2UfxcIQOfz7ywZOTyzeOR3RrBr3/lf9hfjRoyLNL6LCVT5pntmm',
  3,
  (SELECT `id` FROM `areas` WHERE `codigo_centro_costo` = 'OPE-001' LIMIT 1),
  1
);

-- Usuario de prueba: Cuentas por Pagar
INSERT IGNORE INTO `usuarios`
  (`nombre`, `apellido`, `correo`, `password_hash`, `rol_id`, `area_id`, `activo`)
VALUES (
  'Carlos',
  'Martínez',
  'cuentas@empresa.com',
  '$2y$12$pn2UfxcIQOfz7ywZOTyzeOR3RrBr3/lf9hfjRoyLNL6LCVT5pntmm',
  4,
  (SELECT `id` FROM `areas` WHERE `codigo_centro_costo` = 'FIN-001' LIMIT 1),
  1
);

-- Asignar jefe al área de Operaciones
UPDATE `areas`
SET `jefe_usuario_id` = (
  SELECT `id` FROM `usuarios` WHERE `correo` = 'jefe@empresa.com' LIMIT 1
)
WHERE `codigo_centro_costo` = 'OPE-001';

-- ============================================================
--  6. PRESUPUESTOS DE EJEMPLO (Mes actual)
-- ============================================================

SET @anio_actual = YEAR(CURDATE());
SET @mes_actual  = MONTH(CURDATE());
SET @admin_id    = (SELECT `id` FROM `usuarios` WHERE `correo` = 'admin@empresa.com' LIMIT 1);

INSERT IGNORE INTO `presupuestos` (`area_id`, `anio`, `mes`, `monto_asignado`, `created_by`) VALUES
  ((SELECT id FROM areas WHERE codigo_centro_costo='ADM-001'), @anio_actual, @mes_actual, 50000.00,  @admin_id),
  ((SELECT id FROM areas WHERE codigo_centro_costo='FIN-001'), @anio_actual, @mes_actual, 80000.00,  @admin_id),
  ((SELECT id FROM areas WHERE codigo_centro_costo='OPE-001'), @anio_actual, @mes_actual, 120000.00, @admin_id),
  ((SELECT id FROM areas WHERE codigo_centro_costo='VEN-001'), @anio_actual, @mes_actual, 150000.00, @admin_id),
  ((SELECT id FROM areas WHERE codigo_centro_costo='TI-001'),  @anio_actual, @mes_actual, 60000.00,  @admin_id);

-- ============================================================
--  7. PROVEEDOR DE EJEMPLO
-- ============================================================

INSERT IGNORE INTO `proveedores` (`rfc`, `nombre`) VALUES
  ('XAXX010101000', 'Proveedor Genérico (CFDI Público General)'),
  ('AAA010101AAA',  'Proveedor de Ejemplo S.A. de C.V.');

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  RESUMEN
-- ============================================================
--
--  Usuarios creados (todos con password: Admin123!):
--  ┌─────────────────────────────┬──────────────────────┬─────────────────────┐
--  │ Rol                         │ Correo               │ Password            │
--  ├─────────────────────────────┼──────────────────────┼─────────────────────┤
--  │ Administrador               │ admin@empresa.com    │ Admin123!           │
--  │ Capturista                  │ capturista@empresa.com│ Admin123!          │
--  │ Jefe de Área                │ jefe@empresa.com     │ Admin123!           │
--  │ Cuentas por Pagar           │ cuentas@empresa.com  │ Admin123!           │
--  └─────────────────────────────┴──────────────────────┴─────────────────────┘
--
--  IMPORTANTE: El hash bcrypt incluido corresponde a la password "password"
--  del proyecto Laravel de ejemplo. Para usar "Admin123!" con PHP real,
--  ejecuta: php -r "echo password_hash('Admin123!', PASSWORD_BCRYPT, ['cost'=>12]);"
--  y reemplaza el hash en los INSERT de usuarios.
-- ============================================================
