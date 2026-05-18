<?php

require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

session_destroy();

echo json_encode([
    "error" => false,
    "mensaje" => "Sesión cerrada correctamente"
], JSON_UNESCAPED_UNICODE);

?>
