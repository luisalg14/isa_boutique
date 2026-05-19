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

function vendedor_en_horario_laboral() {
    $zonaAnterior = date_default_timezone_get();
    date_default_timezone_set("America/Bogota");
    $horaActual = intval(date("Hi"));
    date_default_timezone_set($zonaAnterior);

    return $horaActual >= 800 && $horaActual <= 2100;
}

function mensaje_horario_vendedor() {
    return "El acceso del vendedor esta permitido solo en horario laboral de 8:00 a.m. a 9:00 p.m.";
}

function cerrar_sesion_local() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }

    session_destroy();
}

function limpiar_sesion_usuario($conexion, $idUsuario, $token = null) {
    if (!$idUsuario) {
        return;
    }

    if ($token) {
        $consulta = $conexion->prepare("
            UPDATE usuario_sistema
            SET sesion_token = NULL,
                sesion_actualizada = NULL
            WHERE id_usuario = :id_usuario
            AND sesion_token = :token
        ");
        $consulta->execute([
            ":id_usuario" => $idUsuario,
            ":token" => $token
        ]);
        return;
    }

    $consulta = $conexion->prepare("
        UPDATE usuario_sistema
        SET sesion_token = NULL,
            sesion_actualizada = NULL
        WHERE id_usuario = :id_usuario
    ");
    $consulta->execute([
        ":id_usuario" => $idUsuario
    ]);
}

function validar_sesion_unica($conexion, $usuario) {
    $token = $usuario["sesion_token"] ?? "";

    if ($token === "") {
        cerrar_sesion_local();
        responder_no_autorizado("La sesion no es valida. Inicia sesion nuevamente.");
    }

    $consulta = $conexion->prepare("
        SELECT sesion_token
        FROM usuario_sistema
        WHERE id_usuario = :id_usuario
        LIMIT 1
    ");
    $consulta->execute([
        ":id_usuario" => intval($usuario["id_usuario"])
    ]);

    $usuarioBD = $consulta->fetch();

    if (!$usuarioBD || !hash_equals($usuarioBD["sesion_token"] ?? "", $token)) {
        cerrar_sesion_local();
        responder_no_autorizado("Tu usuario inicio sesion en otro dispositivo o la sesion ya no esta activa.");
    }

    $actualizar = $conexion->prepare("
        UPDATE usuario_sistema
        SET sesion_actualizada = NOW()
        WHERE id_usuario = :id_usuario
        AND sesion_token = :token
    ");
    $actualizar->execute([
        ":id_usuario" => intval($usuario["id_usuario"]),
        ":token" => $token
    ]);
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
    global $conexion;

    $usuario = exigir_sesion();

    if (!in_array($usuario["rol"], $rolesPermitidos, true)) {
        responder_json_error("Tu rol no tiene permiso para realizar esta accion", 403);
    }

    if (isset($conexion)) {
        validar_sesion_unica($conexion, $usuario);
    }

    if ($usuario["rol"] === "vendedor" && !vendedor_en_horario_laboral()) {
        if (isset($conexion)) {
            limpiar_sesion_usuario($conexion, intval($usuario["id_usuario"]), $usuario["sesion_token"] ?? null);
        }
        cerrar_sesion_local();
        responder_json_error(mensaje_horario_vendedor(), 403);
    }

    return $usuario;
}

?>
