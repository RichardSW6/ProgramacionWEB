USE gastos_empresariales;

INSERT IGNORE INTO roles (nombre, descripcion) VALUES
('Administrador', 'Acceso total al sistema'),
('Capturista', 'Captura y envía gastos para aprobación'),
('Jefe de Área', 'Aprueba o rechaza gastos de su área'),
('Cuentas por Pagar', 'Finaliza gastos aprobados y gestiona pagos');

INSERT IGNORE INTO estatus_gasto (clave, nombre, descripcion) VALUES
('borrador', 'Borrador', 'Gasto en elaboración, no enviado a aprobación'),
('pendiente', 'Pendiente', 'Enviado a aprobación del jefe de área'),
('aprobado', 'Aprobado', 'Aprobado por jefe de área'),
('rechazado', 'Rechazado', 'Rechazado por jefe de área con motivo'),
('finalizado', 'Finalizado', 'Procesado por Cuentas por Pagar');

INSERT IGNORE INTO categorias_gasto (nombre) VALUES
('Viáticos y gastos de viaje'),
('Servicios profesionales'),
('Materiales y suministros'),
('Equipo de cómputo y tecnología'),
('Comunicaciones y telefonía'),
('Capacitación y desarrollo'),
('Mantenimiento y reparaciones'),
('Publicidad y marketing'),
('Otros gastos operativos');

INSERT IGNORE INTO cuentas_gasto (nombre, numero_cuenta) VALUES
('Gastos de administración', '6001'),
('Gastos de venta', '6002'),
('Gastos de operación', '6003'),
('Gastos de representación', '6004'),
('Compras de mercancía', '1310'),
('Servicios profesionales', '6010'),
('Gastos de viaje', '6005');

INSERT IGNORE INTO conceptos_deducibilidad (clave, descripcion) VALUES
('G01', 'Adquisición de mercancias'),
('G03', 'Gastos en general'),
('I04', 'Equipo de computo y accesorios'),
('I03', 'Equipo de transporte'),
('D01', 'Honorarios médicos, dentales y gastos hospitalarios'),
('S01', 'Sin efectos fiscales');
