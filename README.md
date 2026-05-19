# Isa Boutique

Sistema web para la gestion de inventario, ventas, clientes, trabajadores, proveedores y finanzas de Isa Boutique.

## Tecnologia

- Frontend: HTML, CSS y JavaScript.
- Backend: PHP.
- Base de datos: PostgreSQL.
- Entorno local: XAMPP con PHP y PostgreSQL.

Nota: el documento inicial mencionaba MySQL, pero el proyecto final se desarrollo con PostgreSQL para mantener mejor estructura, integridad y control de datos.

## Roles

- Administrador: gestiona inventario, categorias, ventas, reportes, finanzas, proveedores, trabajadores, usuarios y seguridad.
- Vendedor: registra ventas, consulta inventario, revisa historial, reportes y devoluciones dentro del horario laboral.
- Cliente: navega la tienda, consulta productos, selecciona tallas y registra pedidos desde la pagina web.

## Modulos principales

- Catalogo publico: inicio, productos, categorias, carrito y pedidos.
- Inventario: productos, tallas, stock, categorias, movimientos y cambios de precio.
- Ventas: venta interna, venta publica, canal de venta, tipo de entrega, historial, pedidos pendientes, confirmacion y cancelacion.
- Finanzas: ingresos, gastos, inversiones, compras, ganancias y resumen del negocio.
- Personas: clientes, proveedores, trabajadores y pagos.
- Seguridad: autenticacion, roles, contrasenas con hash, sesion unica y restriccion de horario para vendedor.

## Base de datos

Los archivos principales estan en `database/`:

- `isa_postgretsql.sql`: estructura general del proyecto.
- `produccion_schema.sql`: esquema para produccion.
- `produccion_datos_minimos.sql`: usuarios y categorias iniciales.
- `migracion_*.sql`: cambios aplicables si la base ya existe.

La conexion se configura en:

- `php/config.example.php`: plantilla.
- `php/config.local.php`: configuracion local privada, no debe publicarse.

## Carpetas importantes

- `php/`: endpoints del backend.
- `js/`: scripts del frontend.
- `css/`: estilos visuales.
- `img/`: imagenes del proyecto.
- `database/`: estructura y migraciones PostgreSQL.
- `publicacion_isa_boutique/`: copia limpia preparada para subir a hosting.

## Requisitos cubiertos

- Gestion de categorias.
- Control de inventario por producto y talla.
- Registro de ventas, devoluciones y pedidos pendientes.
- Historial de movimientos.
- Busqueda por nombre, codigo, marca y categoria.
- Registro de clientes.
- Reportes de ventas, productos, medios de pago y canales.
- Cambios de precio con historial.
- Control de medios de pago.
- Alertas de stock bajo.
- Roles y permisos.
- Restriccion de horario para vendedor.
- Sesion unica por usuario.

## Mejoras futuras

- Plan separe o apartados con abonos.
- Escaneo QR real.
- Backup automatico programado.
- Modulo formal de cambios de producto.
- Notificaciones de promociones para clientes registrados.
