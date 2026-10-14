<?php

require_once __DIR__ . "/../php/categoria_util.php";
require_once __DIR__ . "/../php/mailer_brevo.php";

$comprobaciones = 0;
function comprobar($condicion) {
    global $comprobaciones;
    if (!$condicion) {
        throw new RuntimeException("Prueba de backend fallida.");
    }
    $comprobaciones++;
}

comprobar(normalizar_categoria("  Blusas   Nuevas ") === "blusas-nuevas");
comprobar(normalizar_categoria("<script>") === "script");
comprobar(normalizar_categoria("   ") === "");

$correo = ["from_name" => "Isa Boutique", "from_email" => "tienda@example.com"];
$mensaje = brevo_preparar_mensaje($correo, "cliente@example.com", "Recuperacion", "<p>Enlace</p>", "Enlace");
comprobar($mensaje["sender"]["email"] === "tienda@example.com");
comprobar(isset($mensaje["htmlContent"]) && !isset($mensaje["textContent"]));
comprobar(brevo_validar_respuesta(201, '{"messageId":"prueba"}') === true);

foreach ([[401, '{}'], [403, '{}'], [429, '{}'], [500, '{}'], [201, '{}'], [201, 'no-json']] as [$estado, $respuesta]) {
    $rechazada = false;
    try {
        brevo_validar_respuesta($estado, $respuesta);
    } catch (RuntimeException $error) {
        $rechazada = true;
    }
    comprobar($rechazada);
}

echo $comprobaciones . " comprobaciones de backend correctas. Sin conexion a la base ni envio de correos." . PHP_EOL;
