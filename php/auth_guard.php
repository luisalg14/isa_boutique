<?php

if (session_status() === PHP_SESSION_NONE) {
    $esHttps = (
        (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
        (($_SERVER["SERVER_PORT"] ?? "") === "443")
    );

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => $esHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}

function usuario_actual() {
    return $_SESSION["usuario"] ?? null;
}

function responder_json_error($mensaje, $codigo = 400) {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code($codigo);
    echo json_encode([
        "error" => true,
        "mensaje" => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function responder_no_autorizado($mensaje = "Debes iniciar sesion para realizar esta accion") {
    responder_json_error($mensaje, 401);
}

function exigir_sesion() {
    $usuario = usuario_actual();

    if (!$usuario) {
        responder_no_autorizado();
    }

    return $usuario;
}

function exigir_roles($rolesPermitidos) {
    $usuario = exigir_sesion();

    if (!in_array($usuario["rol"], $rolesPermitidos, true)) {
        responder_json_error("Tu rol no tiene permiso para realizar esta accion", 403);
    }

    return $usuario;
}

?>
