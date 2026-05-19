<?php

require_once "auth_guard.php";
require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$usuario = usuario_actual();

if ($usuario) {
    limpiar_sesion_usuario(
        $conexion,
        intval($usuario["id_usuario"]),
        $usuario["sesion_token"] ?? null
    );
}

cerrar_sesion_local();

echo json_encode([
    "error" => false,
    "mensaje" => "Sesión cerrada correctamente"
], JSON_UNESCAPED_UNICODE);

?>
