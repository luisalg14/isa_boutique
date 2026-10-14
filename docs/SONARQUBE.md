# Preparacion para SonarQube

El archivo `sonar-project.properties` incluye todo el codigo PHP, JavaScript,
HTML y CSS de la aplicacion, ademas de `index.php`. Las pruebas se encuentran en
`tests/`. Solo se excluye `php/config.local.php`, que contiene credenciales privadas.
Las imagenes, la documentacion y los SQL de instalacion estan fuera del alcance
de este analisis de codigo de la aplicacion.

No se han agregado exclusiones de duplicacion ni se han desactivado reglas.

## Verificacion local

Desde la raiz del proyecto:

```powershell
node --test tests/frontend-comun.test.cjs
& 'C:\xampp\php\php.exe' tests/backend-comun.php
```

Las pruebas comprueban listas y carrito corruptos, imagenes principales, escape
de atributos HTML, paginacion, stock acumulado, finalizacion de compra simulada,
impresion de facturas, normalizacion de categorias y respuestas de Brevo.
No escriben en la base de datos ni envian correos.

## Analisis con el servidor

1. Instala SonarScanner CLI y configura tu servidor SonarQube.
2. Crea un proyecto con clave `isa_boutique`, o cambia `sonar.projectKey` para
   que coincida con el proyecto de tu servidor.
3. Define `SONAR_HOST_URL` y `SONAR_TOKEN` en tu terminal usando la URL del
   servidor y un token de analisis. No guardes el token en este repositorio.
4. Ejecuta `sonar-scanner` desde la raiz del proyecto.
5. Revisa en SonarQube el resultado, las incidencias y la puerta de calidad.

Referencia oficial:
https://docs.sonarsource.com/sonarqube-server/analyzing-source-code/scanners/sonarscanner

La revision local no reemplaza el analisis del servidor. En esta sesion no habia
SonarScanner ni un servidor SonarQube configurado, por lo que no se ha obtenido
un resultado ni se ha confirmado una puerta de calidad aprobada.
