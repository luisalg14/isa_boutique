# Checklist antes de publicar Isa Boutique

## Base de datos

- Crear una base PostgreSQL en el hosting.
- Importar `database/produccion_schema.sql`.
- Importar `database/produccion_datos_minimos.sql`.
- Si ya existe una base anterior, ejecutar las migraciones nuevas como `database/migracion_canal_venta.sql`, `database/migracion_entrega_estado_pedido.sql` y `database/migracion_sesion_unica.sql`.
- Crear un usuario de base de datos que no sea `postgres`.
- Usar una contraseña fuerte para la base de datos.
- Guardar el backup local en `database/backups/`; no subir esa carpeta al hosting ni a GitHub.

## Configuración

En el servidor puedes usar variables de entorno:

- `ISA_DB_HOST`
- `ISA_DB_PORT`
- `ISA_DB_NAME`
- `ISA_DB_USER`
- `ISA_DB_PASSWORD`

Si el hosting no permite variables de entorno, copia `php/config.example.php` como `php/config.local.php` y llena los datos reales. Ese archivo no debe subirse a GitHub.

## Usuarios internos

- Cambiar la contraseña del administrador.
- Cambiar la contraseña del vendedor.
- No usar `123456` en producción.
- Mantener las contraseñas guardadas con hash.
- Usar la pestaña `Seguridad` del panel interno para cambiar o restablecer contraseñas.
- Si usas `produccion_datos_minimos.sql`, las claves temporales son:
- Admin: `admin@isaboutique.com` / `Admin12345`
- Vendedor: `vendedora@isaboutique.com` / `Vendedor12345`

## Archivos sensibles

- No publicar `php/config.local.php`.
- No publicar `.git`.
- No dejar respaldos `.sql` descargables en carpetas públicas.
- La carpeta `database` tiene bloqueo por `.htaccess`, pero aun así es mejor no subir respaldos innecesarios.

## Pruebas finales

- Revisar `README.md` para explicar el alcance del sistema.
- Marcar `PRUEBAS_FINALES.md` antes de publicar o exponer.
- Login administrador.
- Login vendedor.
- Agregar producto.
- Crear, editar y revisar categorías desde el panel administrador.
- Registrar compra de mercancía.
- Registrar venta pública.
- Validar tallas y stock.
- Revisar finanzas.
- Cerrar sesión.

## Permisos por rol

- Administrador: inventario completo, finanzas, proveedores, trabajadores, usuarios, eliminaciones y cambios de precio.
- Vendedor: registrar ventas, revisar inventario, historial, devoluciones y resumen de ventas.
- Público: ver productos y registrar compra desde la tienda.
- `venta_registrar.php` queda disponible para compras públicas; los endpoints administrativos requieren sesión y rol.
- Las contraseñas de usuarios internos son administradas solo por el administrador.
