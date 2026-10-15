<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "mailer_smtp.php";
require_once "notificaciones_util.php";
header("Content-Type: application/json; charset=UTF-8");
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    header("Allow: POST");
    responder_json_error("Metodo no permitido",405);
}
try {
    exigir_roles(["admin"]);
    $limite=filter_var($_POST["limite"] ?? 10,FILTER_VALIDATE_INT);
    if ($limite===false || $limite<1 || $limite>20) responder_json_error("El limite debe ser un entero entre 1 y 20",400);
    $resultado=procesar_notificaciones($conexion,$limite,function($notificacion){
        return enviar_correo_smtp($notificacion["destinatario"],$notificacion["asunto"],$notificacion["cuerpo_html"],$notificacion["cuerpo_texto"]);
    });
    echo json_encode($resultado,JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    error_log("Error notificaciones_enviar_pendientes: ".$e->getMessage());
    responder_json_error("No se pudo completar el procesamiento. Revisa los estados antes de volver a enviar.",500);
}
