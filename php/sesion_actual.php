<?php

require_once "auth_guard.php";
require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$usuario = usuario_actual();

if ($usuario) {
    validar_sesion_unica($conexion, $usuario);

    if (($usuario["rol"] ?? "") === "vendedor" && !vendedor_en_horario_laboral()) {
        limpiar_sesion_usuario(
            $conexion,
            intval($usuario["id_usuario"]),
            $usuario["sesion_token"] ?? null
        );
        cerrar_sesion_local();
        $usuario = null;
    }
}

echo json_encode([
    "autenticado" => $usuario !== null,
    "usuario" => $usuario,
    "csrf_token" => $usuario !== null ? csrf_token_actual() : "",
    "mensaje" => $usuario === null ? mensaje_horario_vendedor() : ""
], JSON_UNESCAPED_UNICODE);

?>
