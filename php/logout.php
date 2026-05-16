<?php

require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

$_SESSION = [];
session_destroy();

echo json_encode([
    "error" => false,
    "mensaje" => "Sesión cerrada correctamente"
], JSON_UNESCAPED_UNICODE);

?>
