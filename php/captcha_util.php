<?php

function captcha_secreto() {
    global $config;

    $base = ($config["password"] ?? "") . "|" . ($config["database"] ?? "") . "|" . __DIR__;
    return hash("sha256", $base);
}

function crear_captcha_token($respuesta) {
    $payload = json_encode([
        "respuesta" => (string) $respuesta,
        "creado" => time(),
        "nonce" => bin2hex(random_bytes(12))
    ], JSON_UNESCAPED_UNICODE);

    $payloadBase64 = rtrim(strtr(base64_encode($payload), "+/", "-_"), "=");
    $firma = hash_hmac("sha256", $payloadBase64, captcha_secreto());

    return $payloadBase64 . "." . $firma;
}

function validar_captcha_token($token, $respuestaUsuario) {
    $partes = explode(".", (string) $token);

    if (count($partes) !== 2) {
        return false;
    }

    [$payloadBase64, $firmaRecibida] = $partes;
    $firmaEsperada = hash_hmac("sha256", $payloadBase64, captcha_secreto());

    if (!hash_equals($firmaEsperada, $firmaRecibida)) {
        return false;
    }

    $payload = base64_decode(strtr($payloadBase64, "-_", "+/"), true);

    if ($payload === false) {
        return false;
    }

    $datos = json_decode($payload, true);

    if (!is_array($datos)) {
        return false;
    }

    if (intval($datos["creado"] ?? 0) < time() - 300) {
        return false;
    }

    return hash_equals((string) ($datos["respuesta"] ?? ""), trim((string) $respuestaUsuario));
}

?>
