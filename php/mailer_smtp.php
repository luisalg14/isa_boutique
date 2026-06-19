<?php

function mail_configuracion() {
    global $config;

    $mail = $config["mail"] ?? [];

    return [
        "host" => $mail["host"] ?? getenv("ISA_MAIL_HOST") ?: "",
        "port" => intval($mail["port"] ?? getenv("ISA_MAIL_PORT") ?: 587),
        "username" => $mail["username"] ?? getenv("ISA_MAIL_USERNAME") ?: "",
        "password" => $mail["password"] ?? getenv("ISA_MAIL_PASSWORD") ?: "",
        "encryption" => strtolower($mail["encryption"] ?? getenv("ISA_MAIL_ENCRYPTION") ?: "tls"),
        "from_email" => $mail["from_email"] ?? getenv("ISA_MAIL_FROM_EMAIL") ?: ($mail["username"] ?? getenv("ISA_MAIL_USERNAME") ?: ""),
        "from_name" => $mail["from_name"] ?? getenv("ISA_MAIL_FROM_NAME") ?: "Isa Boutique",
        "timeout" => intval($mail["timeout"] ?? getenv("ISA_MAIL_TIMEOUT") ?: 6)
    ];
}

function smtp_leer($socket) {
    $respuesta = "";

    while (($linea = fgets($socket, 515)) !== false) {
        $respuesta .= $linea;

        if (strlen($linea) >= 4 && $linea[3] === " ") {
            break;
        }
    }

    return $respuesta;
}

function smtp_codigo($respuesta) {
    return intval(substr($respuesta, 0, 3));
}

function smtp_comando($socket, $comando, $codigosEsperados) {
    fwrite($socket, $comando . "\r\n");
    $respuesta = smtp_leer($socket);
    $codigo = smtp_codigo($respuesta);

    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException("Respuesta SMTP inesperada: " . trim($respuesta));
    }

    return $respuesta;
}

function smtp_codificar_header($texto) {
    return mb_encode_mimeheader($texto, "UTF-8", "B", "\r\n");
}

function enviar_correo_smtp($para, $asunto, $html, $textoPlano = "") {
    $mail = mail_configuracion();

    if ($mail["host"] === "" || $mail["username"] === "" || $mail["password"] === "" || $mail["from_email"] === "") {
        throw new RuntimeException("Configuracion SMTP incompleta.");
    }

    $hostConexion = $mail["encryption"] === "ssl"
        ? "ssl://" . $mail["host"]
        : $mail["host"];

    $socket = stream_socket_client(
        $hostConexion . ":" . $mail["port"],
        $errno,
        $errstr,
        $mail["timeout"],
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException("No se pudo conectar al servidor SMTP: " . $errstr);
    }

    stream_set_timeout($socket, $mail["timeout"]);
    $respuesta = smtp_leer($socket);

    if (smtp_codigo($respuesta) !== 220) {
        fclose($socket);
        throw new RuntimeException("Servidor SMTP no disponible.");
    }

    $hostname = $_SERVER["SERVER_NAME"] ?? "localhost";
    smtp_comando($socket, "EHLO " . $hostname, [250]);

    if ($mail["encryption"] === "tls") {
        smtp_comando($socket, "STARTTLS", [220]);

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            throw new RuntimeException("No se pudo iniciar TLS con el servidor SMTP.");
        }

        smtp_comando($socket, "EHLO " . $hostname, [250]);
    }

    smtp_comando($socket, "AUTH LOGIN", [334]);
    smtp_comando($socket, base64_encode($mail["username"]), [334]);
    smtp_comando($socket, base64_encode($mail["password"]), [235]);
    smtp_comando($socket, "MAIL FROM:<" . $mail["from_email"] . ">", [250]);
    smtp_comando($socket, "RCPT TO:<" . $para . ">", [250, 251]);
    smtp_comando($socket, "DATA", [354]);

    $textoPlano = $textoPlano !== "" ? $textoPlano : strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $html));
    $boundary = "isa_" . bin2hex(random_bytes(12));
    $from = smtp_codificar_header($mail["from_name"]) . " <" . $mail["from_email"] . ">";

    $mensaje = "";
    $mensaje .= "From: " . $from . "\r\n";
    $mensaje .= "To: <" . $para . ">\r\n";
    $mensaje .= "Subject: " . smtp_codificar_header($asunto) . "\r\n";
    $mensaje .= "MIME-Version: 1.0\r\n";
    $mensaje .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n\r\n";
    $mensaje .= "--" . $boundary . "\r\n";
    $mensaje .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mensaje .= $textoPlano . "\r\n\r\n";
    $mensaje .= "--" . $boundary . "\r\n";
    $mensaje .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mensaje .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $mensaje .= $html . "\r\n\r\n";
    $mensaje .= "--" . $boundary . "--\r\n";
    $mensaje .= ".";

    fwrite($socket, $mensaje . "\r\n");
    $respuesta = smtp_leer($socket);

    if (!in_array(smtp_codigo($respuesta), [250], true)) {
        fclose($socket);
        throw new RuntimeException("No se pudo enviar el correo: " . trim($respuesta));
    }

    smtp_comando($socket, "QUIT", [221]);
    fclose($socket);

    return true;
}

?>
