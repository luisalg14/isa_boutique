# Primer bloque de correcciones — 14 de octubre de 2026

Estado: cambios locales revisados. Migracion de auditoria aplicada con autorizacion del usuario.

## Cambios

- Apache bloquea el acceso HTTP a directorios de control de versiones.
- Las solicitudes autenticadas requieren un token CSRF explicito en cabecera o formulario; una cookie sola no autoriza la operacion.
- Las ventas autenticadas validan rol, sesion vigente y horario del vendedor. Los pedidos publicos no admiten descuentos enviados por el cliente.
- El captcha usa un token aleatorio, ligado a la sesion y consumido con cada intento. Esto evita revelar la respuesta en el token y reutilizarlo; una suma sigue siendo una proteccion limitada frente a automatizacion.
- El historial del vendedor escapa los textos identificados como vulnerables a XSS.
- Los errores de auditoria se propagan para impedir continuar y confirmar transacciones fallidas.
- Cambiar una contrasena o un correo incluye la auditoria en la misma transaccion. Restablecer la contrasena de otro usuario invalida su sesion.
- Se eliminaron dos bloques de captura de excepciones inalcanzables.

## Migracion aplicada

Archivo: `database/migracion_auditoria_evento.sql`.

La version preparada conserva registros y columnas existentes, completa los campos nuevos a partir de los historicos y quita la obligatoriedad de `operacion` y `tabla_afectada` cuando existen. Conserva sus restricciones de valores y ejecuta el conjunto dentro de una transaccion.

Se ejecuto despues de crear el respaldo `database/backups/antes_auditoria_20261014_223914.dump` (156378 bytes). PostgreSQL pudo leer su indice con `pg_restore --list`; no se probo una restauracion completa. SHA-256: `6f2457759f3deb6977d4fd2a8fb6e629bdf18e3e9476a3e80ab2d065a9d76215`. El respaldo esta excluido de Git.

La migracion se aplico dentro de una transaccion, con limite de espera de bloqueos y de ejecucion. Se comprobo que ambos campos historicos admiten NULL y que los cinco eventos existentes se conservaron. La funcion real de auditoria pudo insertar un evento temporal dentro de un SAVEPOINT; se revirtio ese evento antes de confirmar la migracion. PostgreSQL puede haber avanzado la secuencia del identificador durante la prueba; no se conservaron eventos de prueba.

No se ejecutaron ventas, compras ni devoluciones reales. Los cambios de codigo siguen locales y no se han publicado en GitHub. Cualquier otra base de datos de despliegue necesita esta migracion antes de publicar el bloque de codigo.

## Verificacion

Pruebas locales en `tests/seguridad.php`, sin PostgreSQL ni servicios de correo. Cubren captcha valido, reutilizacion, respuesta incorrecta, sesion ajena, expiracion, propagacion del error de auditoria, CSRF mediante cookie/formulario/cabecera y sesiones vigentes o revocadas mediante dobles de prueba.

Tambien se reviso la sintaxis PHP y JavaScript, el escape HTML del vendedor y las respuestas HTTP locales de `.git/HEAD` (403), el panel (200) y el captcha (200).

## Pendiente

No se considera terminada la auditoria ni superada la puerta de calidad. Falta consultar el listado de incidencias y bloques duplicados de SonarCloud; el detalle no fue accesible desde la herramienta de consulta. No se agregaron configuraciones ni extensiones de Sonar.

Siguen pendientes la paginacion de listas en el servidor, el esquema de instalacion y la duplicacion restante, incluidas plantillas de facturas. La correccion del carrito y su migracion estan aplicadas en la base configurada; sus limites de verificacion se detallan en el septimo bloque. Faltan la valoracion posterior de mercancia nueva y precisar la conciliacion de aportes e inversiones. El limite compartido de acceso y su migracion estan aplicados en la base configurada, con los limites de verificacion descritos en el octavo bloque. El enlace de recuperacion ya usa una base configurada, que debe apuntar al dominio HTTPS definitivo al publicar. Tambien quedan otros puntos de interpolacion HTML del panel y la configuracion de permisos/TLS del despliegue.

## Segundo bloque: devoluciones e inventario

Cambios locales preparados:

- Se bloquea la fila de venta durante la devolucion para serializar solicitudes simultaneas. Solo se admiten ventas pagadas y cantidades enteras positivas.
- El historial y los botones de ambos paneles identifican la linea de venta. El reembolso se calcula sobre su subtotal cobrado, con descuento incluido, y el redondeo se acumula para que varias devoluciones parciales no excedan ese subtotal.
- Una devolucion parcial conserva la venta pagada; pasa a devuelta cuando se han devuelto todas sus unidades. Se repone el color y la talla originales; cualquier fallo revierte la operacion completa, incluido el stock general actualizado por el trigger.
- Las reposiciones seleccionan un color existente, actualizan variante y talla agregada, y bloquean el producto antes de calcular su costo promedio. Las compras bloquean productos en orden de identificador para reducir conflictos.
- El envio de una compra mixta se reparte entre todas las unidades, evitando cargarlo entero a las reposiciones. La clasificacion y valoracion posterior de mercancia nueva sigue requiriendo revision; no se modificaron costos historicos.
- Editar un producto conserva identificadores, colores y tallas. Con varios colores se rechazan cambios ambiguos de stock desde el editor resumido; las reposiciones y devoluciones permiten seleccionar la variante. Con un solo color y talla se permite el ajuste directo sin recrear la variante.

Migracion aplicada con autorizacion del usuario: `database/migracion_devoluciones_variantes.sql`. Agrega una referencia nullable desde cada detalle de devolucion a su linea de venta y un indice. Vincula registros antiguos solo cuando hay una unica linea candidata; no adivina variantes de devoluciones ambiguas. La base configurada no tenia detalles de devolucion: se conservaron cero registros y no quedaron registros sin identificar. Se verificaron la columna nullable, la clave foranea validada con borrado restringido, el indice valido y la ausencia de enlaces inconsistentes. No se registraron devoluciones de prueba en la base real; los casos funcionales se probaron previamente en PostgreSQL temporal. En otra base con devoluciones historicas ambiguas se requiere revision manual aun despues de la migracion.

Respaldo previo: `database/backups/antes_devoluciones_variantes_20261014_194107.dump` (156361 bytes), legible con `pg_restore --list`. SHA-256: `9dd2998afa37257ddb90b90ad2be8c64c3d06d7b62d6e149cbc7f9612eb3c500`. No se hizo una restauracion completa de este respaldo. La migracion se ejecuto en una transaccion con limites de espera y ejecucion; se compararon cantidad y huella de los datos originales antes de confirmar.

Verificacion: `tests/inventario_devoluciones.php` paso en un PostgreSQL 18 local temporal separado de la configuracion del proyecto. Incluyo estados no pagados, descuentos y redondeos, compatibilidad sin migracion para ventas simples, rechazo de ventas ambiguas sin migracion, repeticion de la migracion, bloqueo desde una segunda conexion, devolucion parcial y completa, exceso de unidades, reposicion de color y talla, edicion que conserva variantes y reversibilidad tras una talla faltante. El servidor temporal se detuvo al terminar. No se realizaron ventas ni movimientos de inventario en la base del proyecto.

Tambien pasaron la sintaxis de 66 archivos PHP, siete scripts incrustados en HTML y `js/vendedor.js`. Se comprobo con un DOM simulado que el formulario muestra los colores existentes, limita tallas al color elegido y selecciona automaticamente un color unico. No se hizo una prueba completa con navegador ni una prueba de carga de varios usuarios. No se reevaluo SonarCloud ni se publicaron cambios en GitHub.

## Tercer bloque: finanzas, reportes y salario fijo

- Se comparten calculos entre resumen y reportes. El costo vendido solo incluye ventas pagadas o devueltas, excluyendo pedidos pendientes y cancelados.
- La utilidad resta el IVA neto. Las devoluciones revierten importe, costo e IVA en la fecha de la devolucion; el IVA se reparte con redondeo acumulado para no dejar centavos al completar varias devoluciones parciales. La caja conserva los cobros y pagos completos: separar IVA de utilidad no crea un pago de impuestos ficticio.
- Para detalles historicos sin linea identificada, el IVA se estima con la proporcion del IVA total de la venta. La base revisada no tenia devoluciones; si otro despliegue conserva devoluciones ambiguas o tasas mezcladas, requiere revision manual de esos historicos.
- Las ventas clasificadas y el ranking de productos incluyen las devoluciones. Los filtros de rango incluyen todo el ultimo dia, validan fechas del calendario y usan intervalos abiertos al dia siguiente.
- El resumen, los reportes y los movimientos se leen dentro de transacciones de solo lectura con una instantanea consistente. Los errores tecnicos se registran sin publicarse en las respuestas.
- El vendedor recibe exclusivamente indicadores de ventas, nombre del producto principal y metas comerciales mediante una lista positiva de campos; no recibe el bloque financiero, costos, utilidades ni nomina. Las metas siguen siendo datos comerciales compartidos. Los endpoints de resumen y movimientos conservan permiso exclusivo de administrador. No se modificaron aqui los permisos de otros endpoints del catalogo; ocultar costos publicos sigue pendiente.
- Las metas estimadas usan gastos operativos; las compras ya intervienen en el margen por el costo vendido y no se suman otra vez como gasto fijo. Se conserva el minimo comercial existente de $7.000.000 y el criterio de ganancia objetivo. Son estimaciones; no se configuraron nuevas metas del negocio.
- Por peticion del usuario, nuevos trabajadores y pagos admiten salario fijo de $2.000.000, validado en servidor y mostrado como campo de solo lectura. Se retiraron los otros tipos del formulario. No se registran pagos automaticamente, no se deducen adelantos y no se modificaron registros anteriores. Se encontro un adelanto historico que sigue contando como salida de caja. Las anotaciones historicas de tipo deduccion se muestran con efecto cero en caja y no se suman como nuevos pagos; no hay deducciones registradas en la base revisada.
- La clasificacion incluye los gastos historicos de tipo nomina dentro de trabajadores y la ganancia libre coincide con la utilidad neta del resumen. Posibles registros manuales duplicados entre gastos y pagos requieren conciliacion; no se borraron ni reinterpretaron registros para adivinar duplicados.

Pruebas: `tests/finanzas_reportes.php` verifica campos del vendedor, fechas y pago fijo. En un PostgreSQL 18 temporal separado verifico ventas pendientes y canceladas, devoluciones entre meses, redondeo de IVA, ultimo dia a las 23:59:59, igualdad entre resumen y reporte, caja frente a movimientos, metas consistentes y rechazo de escrituras dentro de las lecturas. El constructor de reportes, resumen y movimientos tambien se ejecuto contra la base configurada dentro de una transaccion de solo lectura: las cifras mensuales coincidieron. No se crearon trabajadores, pagos ni operaciones comerciales reales. Falta prueba completa de sesion y formulario en navegador. Este bloque no requiere migraciones nuevas ni cambios de servicios externos, y no se publico en GitHub ni se ejecuto SonarCloud.

## Cuarto bloque: datos personales, catalogo y facturas

- Registrar una venta ya no actualiza perfiles mediante el telefono. Solo reutiliza un cliente cuando nombre, telefono, correo y documento coinciden; si cambian los datos, crea un registro separado sin modificar al anterior. Un bloqueo por contacto reduce duplicados simultaneos de estos registros. Esto evita sobrescrituras; no demuestra identidad ni verifica la titularidad del telefono en pedidos anonimos.
- El directorio de clientes es exclusivo del administrador; el panel del vendedor no utiliza este endpoint. El vendedor conserva los datos necesarios para atender ventas y consultar facturas.
- El catalogo publico y el del vendedor omiten costos y productos inactivos. Ver costos requiere administrador activo con token vigente comprobado en la base; una sesion revocada recibe el catalogo publico.
- Consultar una factura es una lectura y no crea ni actualiza documentos. Emitirla sucede dentro de la transaccion de venta o de confirmacion del pedido. Emitir nuevamente una factura existente conserva numero e importes.
- La emision nueva prepara una copia de los datos de cliente, empresa, vendedor, productos y valores para que cambios posteriores no alteren el documento. Las facturas antiguas sin copia se identifican como datos actuales al imprimir, sin inventar informacion historica. No se modificaron reglas tributarias ni se habilito facturacion electronica.
- Se bloqueo el borrado global de historial desde el panel y se retiro la eliminacion forzada de productos con ventas o devoluciones. El producto se bloquea durante la comprobacion y eliminacion; los productos con historial deben desactivarse. No se borraron datos durante estas correcciones.

Migracion aplicada con autorizacion del usuario: `database/migracion_factura_datos_emision.sql`. Agrega `factura.datos_emision` de tipo JSONB nullable y una comprobacion de tipo objeto. No completa documentos antiguos con datos actuales. Se verificaron la columna, la restriccion validada y su reconocimiento por el codigo. Se compararon cantidad y huella de los campos originales antes de confirmar: la base tenia y conserva cero facturas. No se emitieron documentos de prueba. La dependencia de esquema para nuevas emisiones quedo cubierta en la base configurada; en otras bases de despliegue se debe aplicar esta migracion antes de publicar el codigo.

Respaldo previo: `database/backups/antes_factura_datos_emision_20261014_195650.dump` (157257 bytes). SHA-256: `2c8077bbcdd6ecbe08ca3c702f7980d9473828d6ea6da88e2846d0afa540cdcf`. Se comprobo su lectura con `pg_restore --list`, sin restauracion completa. La migracion se ejecuto en una transaccion con limites de bloqueo y ejecucion. El respaldo permanece excluido de Git y bloqueado por web.

Verificacion: `tests/clientes_facturas.php` paso en PostgreSQL 18 temporal separado de la base del proyecto. Incluyo cliente distinto con telefono compartido, reutilizacion con datos identicos, compatibilidad de lectura antigua, rechazo de emision sin migracion, migracion repetible, datos e importes inmutables tras editar entidades, numero de factura sin duplicados y catalogo para anonimo, vendedor, administrador vigente y token revocado. La consulta real del catalogo y la compatibilidad de lectura de facturas se verificaron en transaccion de solo lectura. No se emitieron facturas ni se registraron clientes en la base real. Pasaron la sintaxis de 75 archivos PHP y los scripts modificados; falta prueba completa de formularios y sesiones en navegador. No se ejecuto SonarCloud ni se publicaron cambios en GitHub.

## Quinto bloque: cola de correos

- Procesar pendientes requiere POST, administrador autenticado, origen permitido y token CSRF explicito. GET devuelve 405 y el limite debe estar entre 1 y 20. Se mantiene el envio directo de recuperacion de contrasena; no se añadieron tareas automaticas ni se activaron envios reales.
- Una transaccion selecciona un lote con `FOR UPDATE SKIP LOCKED`, lo marca `procesando` e incrementa los intentos antes de confirmar. El envio externo ocurre despues de confirmar la reserva, sin mantener una transaccion abierta durante la conexion de correo. Otros procesadores no pueden volver a seleccionar el lote reservado.
- `enviada` significa que el transporte confirmo la aceptacion; no demuestra entrega en bandeja de entrada. Un rechazo temporal confirmado puede volver a `pendiente` hasta un maximo de tres intentos, uno por ejecucion del lote. Un rechazo definitivo pasa a `fallida`.
- Un timeout, respuesta incierta o excepcion sin confirmacion de rechazo pasa a `revision`, sin reenvio automatico. Si se interrumpe el proceso o falla la persistencia despues de enviar, la reserva queda `procesando` y tampoco se selecciona otra vez. Esto evita duplicados automaticos a costa de requerir revision de esos mensajes; no garantiza entrega ni exactamente una entrega ante cualquier fallo externo.
- Antes de devolver `revision` o una reserva abandonada a `pendiente`, comprobar los registros del proveedor y la actividad del procesador. No se implemento una tarea que libere reservas automaticamente, porque podria reenviar un correo ya aceptado. Un proceso interrumpido puede dejar reservados mensajes del lote que todavia no intento enviar.
- Los contadores incluyen los pendientes reales de toda la cola, los mensajes en proceso y los que necesitan revision; no se calculan restando del lote los intentos fallidos. Los errores del proveedor no se publican en la respuesta del endpoint.

La tabla existente admite estados de hasta 20 caracteres y no tiene una restriccion de estados; este bloque no requiere migracion en la base revisada. No se modificaron registros reales: las diez notificaciones existentes siguen pendientes.

Verificacion: `tests/notificaciones.php` paso con un remitente simulado y PostgreSQL 18 temporal separado. Se probaron dos conexiones que reservan lotes sin duplicarlos, filas bloqueadas, reintentos hasta el limite, conteo de pendientes tras fallo temporal, rechazos definitivos, timeout ambiguo, reserva abandonada y fallo de persistencia posterior a la aceptacion. Tambien se probaron respuestas simuladas del adaptador Brevo, GET 405 y POST anonimo 401 del endpoint local. Pasaron 78 archivos PHP. No se enviaron correos reales, no se llamo a la API de Brevo ni se verifico entrega externa. No se ejecuto SonarCloud ni se publico en GitHub.

Referencia del contrato del adaptador: [Brevo, envio de correo transaccional](https://developers.brevo.com/reference/send-transac-email). No se añadieron encabezados de idempotencia ni se cambio la configuracion del servicio.

## Sexto bloque: consultas e implementacion compartida del catalogo

- Se extrajeron 24 funciones identicas del catalogo general y categorias a `js/catalogo-comun.js` (338 lineas repetidas antes de la extraccion). Ambas paginas cargan el archivo antes de sus scripts; no se modificaron los controles ni se añadieron dependencias externas. Esto reduce duplicacion local, sin afirmar un porcentaje de SonarCloud.
- Se unifico la lectura defensiva de listas JSON. Un carrito guardado con JSON invalido o que no sea una lista ya no detiene el arranque; los elementos de carrito con cantidades o precios invalidos no se recuperan.
- Se escapan textos y atributos de fichas, miniaturas, colores, tallas y carrito. Se rechazan esquemas de imagen ejecutables. La pagina de categoria declara explicitamente su contenedor en lugar de depender de la variable global creada implicitamente por el navegador.
- Los filtros diarios, mensuales y anuales de reportes y resumen comparan fechas con limites de intervalo, evitando aplicar funciones a la columna filtrada. Se conservan las agrupaciones por fecha y mes donde son necesarias para los graficos.
- El catalogo obtiene sus tallas con una subconsulta por producto: ya no multiplica filas mediante el JOIN de tallas para luego agrupar todas las columnas del producto. Se conserva el contrato JSON de imagenes, colores y tallas y la visibilidad de costos segun permisos.

Migracion aplicada con autorizacion del usuario: `database/migracion_indices_consultas.sql`. Agrega 13 indices para detalle de venta por venta/producto, detalle de devolucion por cabecera/producto, devolucion por venta/estado, fechas de ventas/devoluciones/gastos/nomina/compras/inversiones, telefono de cliente e imagenes por producto. Se revisaron los indices existentes y no se agregan duplicados de las claves de producto-color, color-talla o producto-talla, ni otro indice de la cola de notificaciones. La migracion solo crea indices, con limites de bloqueo y ejecucion, y no modifica registros. En otras bases con tablas grandes debe revisarse la ventana de mantenimiento: CREATE INDEX puede bloquear escrituras mientras se construye.

La aplicacion se hizo dentro de una transaccion, despues del respaldo `database/backups/antes_indices_consultas_20261014_204100.dump` (157424 bytes), legible con `pg_restore --list`. SHA-256: `7ccdef44db06e7c13c8e545a4159a8a7c4d4c5fcd12fa1e22ef62fc42224fea6`. No se probo una restauracion completa. Se verificaron validez, disponibilidad, tabla y columnas de cada uno de los 13 indices. Se compararon cantidad y huella de los registros en las diez tablas afectadas antes y despues: se conservaron. No se registraron operaciones comerciales. El respaldo esta excluido de Git y bloqueado por web.

Pruebas: `node tests/catalogo_comun.cjs` comprueba arranque de ambas paginas con sus scripts reales y DOM simulado, carrito corrupto, listas invalidas, escape de campos, apertura de compra, seleccion de variante y limite acumulado de stock. No sustituye una prueba visual en navegador. `tests/finanzas_reportes.php` con opcion `indices` paso en PostgreSQL temporal: los 13 indices quedaron validos y la migracion se repitio sin cambios de datos. En una muestra sintetica con 3.000 ventas adicionales, EXPLAIN uso el indice de fecha. Pasaron los casos financieros anteriores y la regresion `tests/clientes_facturas.php` con la nueva consulta del catalogo. Se consultaron catalogo y reportes reales dentro de una transaccion de solo lectura, sin diferencias entre resumen y reporte; el JS compartido se sirvio con HTTP 200 y tipo text/javascript.

No se midieron tiempos con datos productivos representativos ni una carga de usuarios; por tanto no se cuantifica una mejora de rendimiento real. Las listas que actualmente devuelven todo el historial siguen necesitando paginacion en servidor. No se ejecuto SonarCloud ni se publicaron cambios en GitHub.

## Septimo bloque: carrito atomico y reintentos

Codigo local y migracion `database/migracion_pedido_carrito.sql` aplicados en la base configurada con autorizacion del usuario. El nuevo endpoint dispone de las columnas e indice necesarios. En otra base de despliegue se debe aplicar primero la migracion; los cambios de codigo aun no se han publicado en GitHub.

- Catalogo y categorias comparten un unico envio de carrito a `php/pedido_carrito_registrar.php`. Todos sus detalles pertenecen a una venta pendiente, dentro de una transaccion; cliente, movimientos y stock tambien se revierten si falla cualquier detalle. No se emite factura ni se considera cobrado el pedido.
- El servidor valida cantidades enteras, variantes y suma de unidades por producto. Obtiene precios y costos de PostgreSQL e ignora los importes del navegador. Bloquea productos en orden de identificador. Las validaciones de inventario y calculos monetarios se comparten con la venta individual mediante `php/venta_util.php`. Se conserva el IVA fijo del 19% del flujo existente; conectarlo con la configuracion tributaria sigue pendiente.
- Una clave aleatoria identifica el intento y una huella vincula su contenido. Un bloqueo transaccional serializa solicitudes con la misma clave y un indice unico impide insertarla dos veces. Repetirla con otro contenido se rechaza; repetirla con el mismo contenido devuelve el pedido existente sin volver a descontar stock.
- La interfaz bloquea el doble clic y conserva clave y huella en localStorage antes de enviar, sin guardar los datos de contacto en ese registro auxiliar. Tras perder la respuesta o recargar la pagina, el mismo carrito y datos reutilizan la clave. Un rechazo comercial confirmado libera el intento para corregirlo; un error ambiguo conserva la clave. Cambiar el contenido mientras hay un resultado desconocido bloquea otro envio y pide comprobar el pedido anterior. Borrar el almacenamiento o enviar desde pestanas independientes con claves distintas no garantiza deduplicacion. El endpoint anterior de venta individual tampoco tiene esta proteccion.
- La migracion agrega dos columnas nullable, una restriccion de formato/coherencia y un indice unico. Las ventas anteriores quedan sin clave. Se aplico despues del respaldo y con autorizacion del usuario. Se verificaron ambas columnas VARCHAR(64) nullable, la restriccion CHECK validada y el indice unico valido y disponible sobre clave_pedido. Dentro de la misma transaccion se bloquearon escrituras en venta y detalle_venta y se compararon cantidades y huellas de los registros antes y despues (excluyendo las nuevas columnas): se conservaron cero ventas y cero detalles. No se crearon pedidos de prueba en la base real.

Respaldo previo: `database/backups/antes_pedido_carrito_20261015_020826.dump` (161295 bytes; nombre con fecha UTC), legible con `pg_restore --list`. SHA-256: `ee97b2ecd3d65fe0777b114d154607920cfab79be885911239cef463c81ee03b`. No se probo una restauracion completa. El respaldo esta excluido de Git y su acceso HTTP se comprueba bloqueado.

Verificacion: `tests/pedido_carrito.php` paso en PostgreSQL 18 temporal separado. Comprueba una venta con dos productos, precios del servidor, agrupacion de variantes, reintentos con orden distinto, conflicto de contenido, bloqueo con dos conexiones, rollback del cliente/stock/movimientos al fallar el segundo detalle, recuperacion posterior y limites de stock agregado y por variante. La migracion se aplico dos veces solo en esa base temporal. No se crearon pedidos ni clientes reales.

`node tests/carrito_envio.cjs` paso con transporte y DOM simulados: un solo POST con varios productos, doble clic, respuesta perdida, recarga y reutilizacion de clave, cabecera CSRF, rechazo comercial y bloqueo de cambios tras un resultado desconocido. Tambien paso `tests/catalogo_comun.cjs`; el endpoint local responde GET 405. Pasaron las revisiones de sintaxis PHP/JavaScript. No se probo el recorrido completo en navegador, la confirmacion/cancelacion de un pedido real de varias lineas, ni solicitudes simultaneas con claves distintas. La simulacion de dos conexiones verifica el bloqueo, no una prueba de carga. El navegador necesita crypto.subtle (HTTPS o localhost). No se enviaron correos ni se ejecuto SonarCloud y no se publicaron cambios en GitHub.

## Octavo bloque: limites de acceso, enlace de recuperacion y XSS del panel

Preparado y aplicado el 15 de octubre de 2026 con autorizacion del usuario: `database/migracion_limites_acceso.sql`. Los endpoints locales disponen de la tabla requerida. En otra base de despliegue debe aplicarse primero la migracion; sin ella, los POST de acceso y recuperacion responderan 503. El ultimo estado recibido del repositorio estaba limpio en `5bd97ce`; este bloque nuevo permanece sin commit ni publicacion.

Respaldo previo: `database/backups/antes_limites_acceso_20261015_154427.dump` (162599 bytes, nombre en UTC), legible con `pg_restore --list`. SHA-256: `23d4aa737eeb989a54cf57103f496af5f59b8f8d717593094c8d7a1147bb5118`. No se probo una restauracion completa. El respaldo esta excluido de Git y su acceso HTTP devuelve 403.

Se aplico la migracion dentro de una transaccion con limites de bloqueo y ejecucion. Se verificaron las tres columnas no nulas y sus tipos, la clave primaria unica, el indice de vencimiento y ambos CHECK validados. Se comprobo el rechazo de una clave invalida y un contador cero mediante SAVEPOINT con rollback, sin conservar filas. Se compararon cantidades y huellas de usuario_sistema y password_reset_token antes y despues, bloqueando escrituras durante la comprobacion: se conservaron los dos usuarios y los ocho registros de recuperacion, incluidas contrasenas y sesiones. La tabla de limites quedo con cero contadores. Los tres endpoints locales respondieron GET 405; no se iniciaron sesiones ni se enviaron solicitudes reales de recuperacion como prueba.

### Proteccion de acceso

`php/limite_acceso.php` comparte cuotas entre sesiones y procesos mediante una fila por accion e identidad con INSERT/ON CONFLICT atomico. Se comprueban cuenta e IP antes de validar captcha, contrasena o token. La cuota no depende de que el correo exista. Se cuenta toda solicitud admitida, incluidas las validas; iniciar sesion correctamente no borra los contadores.

| Accion | Por cuenta | Por IP | Ventana |
| --- | --- | --- | --- |
| Login | 5 | 30 | 5 minutos |
| Solicitar recuperacion | 3 | 10 | 15 minutos |
| Confirmar recuperacion | No aplica | 30 | 15 minutos |

Se mantiene la respuesta 429 y se incluye Retry-After con el tiempo restante. Una ventana empieza con la primera solicitud y no se prolonga por intentos bloqueados. Cambiar correo no evita el limite IP; cambiar IP no evita el de cuenta. La IP proviene de REMOTE_ADDR; no se acepta X-Forwarded-For arbitrario. Detras de un proxy debe configurarse la direccion real en el servidor. Redes compartidas pueden alcanzar la cuota IP entre varios usuarios. Un atacante puede consumir temporalmente la cuota de una cuenta conocida; no se implemento MFA ni una defensa completa contra ataques distribuidos. Las ventanas fijas permiten rafagas alrededor de su vencimiento.

Las claves almacenadas son SHA-256 de accion y cuenta/IP normalizadas; no se guardan esos identificadores en texto plano en la tabla. No son anonimizacion irreversible frente a un diccionario de valores conocidos. Se limpian hasta 100 filas vencidas hace mas de un dia por solicitud, sin bloquear filas que ya estan en uso. La limpieza depende del trafico y no constituye una cota absoluta del tamano de la tabla. Fallos de base o bloqueos impiden el acceso con 503; no se permite continuar con un limite solo en cookies.

Se eliminaron los contadores de sesion sustituidos y se validan entradas escalares. Login y confirmacion de recuperacion conservan los espacios de la contrasena. Para correos inexistentes se realiza una comprobacion bcrypt contra un hash auxiliar, sin crear una cuenta ni aceptar esa contrasena; esto no equivale a haber probado ausencia de enumeracion por tiempos en todas las respuestas.

### URL de recuperacion

`php/app_url.php` obtiene la base de `app_url` en configuracion o `ISA_APP_URL`, con respaldo explicito `http://localhost/isa_boutique` para este entorno. No utiliza Host, Origin ni cabeceras de proxy para construir el correo. Rechaza credenciales, query, fragmento, espacios, barras inversas y HTTP fuera de localhost/loopback. En produccion debe configurarse la URL HTTPS definitiva; no se publico ni se cambio configuracion externa del correo. La consulta real de solo lectura confirmo el respaldo localhost; la tabla nueva se creo posteriormente con autorizacion.

### Panel

- Inventario: se reemplazan los eventos que interpolaban nombre, estado y JSON de variantes por addEventListener y cierres sobre el objeto. Las filas se agregan como nodos para conservar sus eventos. Editar, activar/desactivar, eliminar y mostrar variantes mantienen sus argumentos sin convertir nombres en codigo.
- Se escapan atributos de imagen y textos de categoria, colores, tallas y estado del inventario. El selector de imagen rechaza esquemas ejecutables. Tambien se escapan textos de movimientos, trabajadores, pagos y mensajes de error que se insertaban como HTML en ambos paneles.
- Los identificadores dinamicos interpolados en acciones y atributos se convierten a Number. Se escapa el numero de factura en el boton del historial. Esto no convierte todos los innerHTML restantes del panel en seguros: quedan otros constructores de vistas y la revision global sigue pendiente.

### Verificacion y limites

`tests/limites_acceso.php` paso en PostgreSQL 18 temporal: cuotas de cuenta/IP, nueva sesion e IP, normalizacion de correo/IPv6, expiracion, limpieza acotada, ocho procesos concurrentes (exactamente cinco permitidos y tres bloqueados), bloqueo de fila y rollback. Se aplico dos veces la migracion en esa base temporal, sin error.

`tests/acceso_endpoints.php` ejecuto los archivos reales de login y recuperacion en procesos CLI separados, sustituyendo solo conexion y transporte/notificaciones por una base temporal y dobles de prueba. Comprobo que los captchas incorrectos consumen cuota, que cambiar sesion/IP no libera la cuenta, login valido con espacios, tres correos simulados y bloqueo del cuarto, Host malicioso excluido del enlace, confirmacion de contrasena, revocacion de sesion, token de un uso y respuesta 503 si falta la tabla. No se contactaron servidores de correo ni se utilizaron cuentas reales.

`node tests/panel_xss.cjs` paso con funciones reales extraidas de admin.html y DOM simulado: nombres con apostrofes, comillas y etiquetas, datos de variantes, atributos de imagen, acciones de dos filas conservadas, textos de movimientos/trabajadores/pagos y mensajes de error. No reemplaza la comprobacion visual en navegador ni una prueba de explotacion en todos los modulos. La verificacion de endpoints es CLI; no se probo el proxy/HTTPS de un despliegue publico ni una carga sostenida. Se retiraron las bases y archivos temporales. No se modificaron usuarios, contrasenas, tokens ni contadores en la base real; no se envio correo real. No se ejecuto SonarCloud ni se publicaron cambios en GitHub.

Referencias: [OWASP, autenticacion y limites de intentos](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html) y [OWASP, prevencion de XSS y contextos de atributos](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html).
