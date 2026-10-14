# Revisión de estructura — 14 de octubre de 2026

## Cambios realizados

- Eliminada la carpeta anidada `isa_boutique/`: contenía diez archivos de una
  versión antigua, incluidos cuatro duplicados exactos de imágenes y un login
  de prueba con contraseña fija. El código actual no dependía de esa carpeta.
- Corregido `index.php` para redirigir a `html/index.html` en lugar del dashboard
  de XAMPP.
- Documentos de publicación, pruebas y desarrollo agrupados en `docs/`.
- Actualizados el README, los enlaces de documentación y las rutas de pruebas.
- Corregido el escape de las expresiones de bloqueo en los `.htaccess` de la raíz
  y de `php/`. Agregado bloqueo de acceso web a la documentación interna.
- Conservados los cambios que ya existían en `html/admin.html`,
  `html/vendedor.html` y `js/auth.js`.

## Validación

- 60 archivos PHP sin errores de sintaxis.
- 8 archivos o bloques JavaScript sin errores de sintaxis.
- 70 referencias locales de HTML y CSS sin archivos ausentes.
- 168 referencias a endpoints PHP e imports JavaScript sin archivos ausentes.
- Configuración principal de Apache: `Syntax OK`.
- Conexión PostgreSQL comprobada mediante consultas de lectura.
- `git diff --check` sin errores de espacios.

Apache estaba apagado durante esta revisión. Queda pendiente comprobar en el
navegador la redirección, los bloqueos de acceso y los flujos de la aplicación
siguiendo `docs/PRUEBAS_FINALES.md`. La comprobación de sintaxis no sustituye esas
pruebas funcionales.

## Archivos conservados por su función

- Los scripts `migracion_*.sql` actualizan bases existentes; no son copias de los
  esquemas de instalación.
- `database/isa_postgretsql.sql` es una referencia histórica con limpieza previa
  de tablas y datos de ejemplo. El README distingue su uso del esquema de producción.
- `database/backups/` y `php/config.local.php` son privados y siguen excluidos de Git.
- En `img/productos/` hay seis grupos de imágenes con contenido idéntico y nombres
  diferentes. Se conservaron para mantener compatibilidad con registros y respaldos
  existentes. Su eliminación requiere comprobar las rutas de cada base y respaldo
  que se quiera conservar.

La revisión no modificó datos de la base ni ejecutó migraciones.

## Segunda limpieza: auditoria del codigo

- Separado el JavaScript de cinco paginas en `js/admin.js`, `js/catalogo.js`,
  `js/categoria.js`, `js/inicio.js` y `js/restablecer-password.js`.
- Centralizadas 27 funciones de tienda, incluido el carrito y el envio del pedido,
  en `js/tienda-comun.js`. Las funciones especificas de cada pagina se conservan
  en su propio archivo.
- Centralizadas la paginacion interna y la impresion de facturas en
  `js/sistema-comun.js`, manteniendo los formatos propios de cada panel.
- Unificada la normalizacion de categorias de PHP en `php/categoria_util.php`.
- Corregido el manejo de listas y carritos corruptos, y escapados los valores
  insertados en los atributos de miniaturas, colores y el resumen del carrito.
- Agregadas pruebas de regresion en `tests/` para comprobar la logica compartida.
- Retiradas mas de 600 lineas de codigo no vacias respecto al estado anterior
  de las paginas y scripts reorganizados.

Validacion de esta etapa:

- 64 archivos PHP y 10 JavaScript sin errores de sintaxis.
- 7 pruebas de frontend y 12 comprobaciones de backend correctas.
- 251 referencias locales revisadas sin archivos ausentes.
- Seis paginas cargadas en Chrome de prueba sin excepciones JavaScript: inicio,
  productos, categoria, administrador, vendedor y recuperacion de contrasena.
- Captcha cargado en los dos paneles.
- Acceso HTTP bloqueado con 403 a configuracion privada, SQL, documentos
  y archivos de pruebas.
- Ningun grupo de archivos de codigo identicos en `php/`, `js/`, `html/` y `css/`.
- `git diff --check` sin errores.

Las pruebas no registraron ventas ni enviaron correos. La carga de paginas no
comprueba todos los flujos autenticados del negocio. Pueden quedar incidencias o
duplicaciones parciales que requieran una revision adicional.
