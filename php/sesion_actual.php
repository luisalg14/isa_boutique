<?php

require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

$usuario = usuario_actual();

echo json_encode([
    "autenticado" => $usuario !== null,
    "usuario" => $usuario
], JSON_UNESCAPED_UNICODE);

?>
