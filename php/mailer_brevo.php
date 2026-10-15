<?php
require_once 'correo_error.php';

function brevo_preparar_mensaje($mail, $para, $asunto, $html, $textoPlano) {
    if (!filter_var($mail["from_email"] ?? "", FILTER_VALIDATE_EMAIL) || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        throw new CorreoNoAceptadoException("El remitente o destinatario del correo no es valido.");
    }

    $mensaje = [
        "sender" => ["name" => $mail["from_name"] ?: "Isa Boutique", "email" => $mail["from_email"]],
        "to" => [["email" => $para]],
        "subject" => $asunto
    ];

    if ($html !== "") {
        $mensaje["htmlContent"] = $html;
    } elseif ($textoPlano !== "") {
        $mensaje["textContent"] = $textoPlano;
    } else {
        throw new CorreoNoAceptadoException("El correo no contiene un mensaje.");
    }

    return $mensaje;
}

function brevo_validar_respuesta($estado, $respuesta) {
    if ($estado === 401) {
        throw new CorreoNoAceptadoException("Brevo rechazo la clave API. Comprueba que sea una clave API activa.");
    }
    if ($estado === 403) {
        throw new CorreoNoAceptadoException("Brevo rechazo el acceso. Revisa los permisos y las IP autorizadas de la cuenta.");
    }
    if ($estado === 429) {
        throw new CorreoNoAceptadoException("Brevo alcanzo el limite de solicitudes. Intenta nuevamente mas tarde.", true);
    }
    if ($estado !== 201) {
        if (in_array($estado,[400,404,413,415,422],true)) {
            throw new CorreoNoAceptadoException("Brevo no acepto el correo (HTTP " . $estado . "). Revisa el remitente y el contenido.");
        }
        throw new RuntimeException("Brevo no acepto el correo (HTTP " . $estado . "). Revisa el remitente verificado y el estado de la cuenta.");
    }

    $datos = json_decode($respuesta, true);
    if (!is_array($datos) || !is_string($datos["messageId"] ?? null) || $datos["messageId"] === "") {
        throw new RuntimeException("Brevo no devolvio la confirmacion del envio.");
    }

    return true;
}

function enviar_correo_brevo($mail, $para, $asunto, $html, $textoPlano = "") {
    $clave = trim($mail["api_key"] ?? "");
    if ($clave === "" || preg_match('/[\r\n]/', $clave)) {
        throw new CorreoNoAceptadoException("Falta configurar una clave API valida de Brevo.");
    }
    if (!function_exists("curl_init")) {
        throw new CorreoNoAceptadoException("El envio por Brevo requiere la extension cURL de PHP.");
    }

    $mensaje = brevo_preparar_mensaje($mail, $para, $asunto, $html, $textoPlano);
    $socket = curl_init("https://api.brevo.com/v3/smtp/email");
    if ($socket === false) {
        throw new CorreoNoAceptadoException("No se pudo iniciar la conexion con Brevo.");
    }

    try {
        curl_setopt_array($socket, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(15, min(60, intval($mail["timeout"] ?? 15))),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ["accept: application/json", "content-type: application/json", "api-key: " . $clave],
            CURLOPT_POSTFIELDS => json_encode($mensaje, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        ]);

        $respuesta = curl_exec($socket);
        if ($respuesta === false) {
            throw new RuntimeException("No se pudo conectar con Brevo (cURL " . curl_errno($socket) . ").");
        }

        return brevo_validar_respuesta(intval(curl_getinfo($socket, CURLINFO_HTTP_CODE)), $respuesta);
    } finally {
        curl_close($socket);
    }
}
