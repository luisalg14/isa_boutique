<?php

function crear_captcha_token($respuesta) {
    $token = bin2hex(random_bytes(32));
    $_SESSION["login_captcha"] = [
        "token" => $token,
        "respuesta" => (string) $respuesta,
        "creado" => time()
    ];
    return $token;
}

function validar_captcha_token($token, $respuestaUsuario) {
    $captcha = $_SESSION["login_captcha"] ?? null;
    // Cada pregunta admite un intento, incluso con una respuesta incorrecta.
    unset($_SESSION["login_captcha"]);
    if (!is_array($captcha) || !is_string($token) || !is_string($respuestaUsuario)) {
        return false;
    }
    $creado = intval($captcha["creado"] ?? 0);
    if ($creado < time() - 300 || $creado > time()) {
        return false;
    }
    return hash_equals($captcha["token"], $token)
        && hash_equals($captcha["respuesta"], trim($respuestaUsuario));
}
