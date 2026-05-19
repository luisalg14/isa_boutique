<?php

require_once "auth_guard.php";
require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$usuario = usuario_actual();

if ($usuario) {
    validar_sesion_unica($conexion, $usuario);
}

echo json_encode([
    "autenticado" => $usuario !== null,
    "usuario" => $usuario
], JSON_UNESCAPED_UNICODE);

?>
