# Instrucciones de Configuración

## Sistema de Gestión de Gastos Empresariales

### Prerequisitos
- XAMPP instalado con Apache y MySQL en ejecución
- El proyecto debe estar accesible en: http://localhost/proyecto/

### Paso 1: Crear la base de datos y ejecutar migraciones

Opción A - Desde la terminal de XAMPP (CMD/PowerShell):
```
php c:\Projects\proyecto\api\run_migrations.php
```

Opción B - Desde phpMyAdmin (http://localhost/phpmyadmin):
1. Crear BD `gastos_empresariales` (charset utf8mb4, collation utf8mb4_unicode_ci)
2. Ejecutar el contenido de: api/database/migrations/001_create_catalogs.sql
3. Ejecutar el contenido de: api/database/migrations/002_create_business_tables.sql
4. Ejecutar el contenido de: api/database/seeders/001_seed_catalogs.sql

### Paso 2: Crear usuario administrador

Desde terminal:
```
php c:\Projects\proyecto\api\database\seeders\002_seed_admin.php
```

Credenciales del admin:
- Email: admin@empresa.com
- Password: Admin123!

### Paso 3: Verificar instalación

1. Abrir: http://localhost/proyecto/api/public/ping
   - Debe responder: {"success":true,"message":"API funcionando correctamente.",...}

2. Abrir: http://localhost/proyecto/web/index.html
   - Debe mostrar el formulario de login

### Estructura de la aplicación

```
http://localhost/proyecto/
├── api/public/    → API REST PHP
└── web/           → Frontend HTML/JS
```

### Configuración del archivo .env

Si necesitas cambiar la configuración de BD, edita:
`c:\Projects\proyecto\api\.env`

### Notas importantes

- El directorio `api/storage/xml/` debe tener permisos de escritura
- La sesión expira después de 60 minutos de inactividad
- Solo CFDI versión 4.0 es soportado para carga de XML
- El folio de gastos se genera automáticamente: G-YYYYMMDD-XXXXX
