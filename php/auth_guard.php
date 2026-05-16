<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuario_actual() {
    return $_SESSION["usuario"] ?? null;
}

function responder_no_autorizado($mensaje = "Debes iniciar sesión para realizar esta acción") {
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(401);
    echo json_encode([
        "error" => true,
        "mensaje" => $mensaje
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
        header("Content-Type: application/json; charset=UTF-8");
        http_response_code(403);
        echo json_encode([
            "error" => true,
            "mensaje" => "Tu rol no tiene permiso para realizar esta acción"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return $usuario;
}

?>
