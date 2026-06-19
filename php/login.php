<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "notificaciones_seguridad.php";
require_once "captcha_util.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    exigir_origen_mismo_sitio();

    $bloqueadoHasta = intval($_SESSION["login_bloqueado_hasta"] ?? 0);

    if ($bloqueadoHasta > time()) {
        http_response_code(429);
        echo json_encode([
            "error" => true,
            "mensaje" => "Demasiados intentos. Espera unos minutos e intenta de nuevo."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = trim($_POST["contrasena"] ?? "");
    $captchaRespuesta = trim($_POST["captcha_respuesta"] ?? "");
    $captchaToken = trim($_POST["captcha_token"] ?? "");

    if ($correo === "" || $contrasena === "") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Ingresa correo y contraseña"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $captcha = $_SESSION["login_captcha"] ?? null;
    $captchaExpirado = !$captcha || intval($captcha["creado"] ?? 0) < time() - 300;
    $captchaSesionCorrecto = !$captchaExpirado && hash_equals((string) ($captcha["respuesta"] ?? ""), $captchaRespuesta);
    $captchaTokenCorrecto = $captchaToken !== "" && validar_captcha_token($captchaToken, $captchaRespuesta);
    $captchaCorrecto = $captchaSesionCorrecto || $captchaTokenCorrecto;

    if (!$captchaCorrecto) {
        unset($_SESSION["login_captcha"]);
        echo json_encode([
            "error" => true,
            "mensaje" => "Verificacion humana incorrecta. Intenta con una nueva pregunta."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    unset($_SESSION["login_captcha"]);

    $sql = "
        SELECT id_usuario, nombre, correo, contrasena, rol, estado, sesion_token, sesion_actualizada
        FROM usuario_sistema
        WHERE LOWER(correo) = :correo
        LIMIT 1
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([":correo" => $correo]);
    $usuario = $consulta->fetch();

    if (!$usuario || !password_verify($contrasena, $usuario["contrasena"])) {
        $_SESSION["login_intentos"] = intval($_SESSION["login_intentos"] ?? 0) + 1;

        if ($_SESSION["login_intentos"] >= 5) {
            $_SESSION["login_bloqueado_hasta"] = time() + 300;
        }

        echo json_encode([
            "error" => true,
            "mensaje" => "Correo o contraseña incorrectos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$usuario["estado"]) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Este usuario esta inactivo"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($usuario["rol"] === "vendedor" && !vendedor_en_horario_laboral()) {
        echo json_encode([
            "error" => true,
            "mensaje" => mensaje_horario_vendedor()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (
        !empty($usuario["sesion_token"]) &&
        !empty($usuario["sesion_actualizada"]) &&
        strtotime($usuario["sesion_actualizada"]) > strtotime("-8 hours")
    ) {
        notificar_intento_otro_dispositivo($usuario);

        echo json_encode([
            "error" => true,
            "mensaje" => "Este usuario ya tiene una sesión activa. Cierra sesión en el otro dispositivo o espera a que expire."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    session_regenerate_id(true);
    $_SESSION["login_intentos"] = 0;
    unset($_SESSION["login_bloqueado_hasta"]);

    $tokenSesion = bin2hex(random_bytes(32));
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

    $consultaSesion = $conexion->prepare("
        UPDATE usuario_sistema
        SET sesion_token = :token,
            sesion_actualizada = NOW()
        WHERE id_usuario = :id_usuario
    ");
    $consultaSesion->execute([
        ":token" => $tokenSesion,
        ":id_usuario" => intval($usuario["id_usuario"])
    ]);

    $_SESSION["usuario"] = [
        "id_usuario" => intval($usuario["id_usuario"]),
        "nombre" => $usuario["nombre"],
        "correo" => $usuario["correo"],
        "rol" => $usuario["rol"],
        "sesion_token" => $tokenSesion
    ];

    notificar_inicio_sesion($usuario);

    echo json_encode([
        "error" => false,
        "mensaje" => "Sesion iniciada correctamente",
        "usuario" => $_SESSION["usuario"],
        "csrf_token" => csrf_token_actual()
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo iniciar sesión en este momento"
    ], JSON_UNESCAPED_UNICODE);
}

?>
