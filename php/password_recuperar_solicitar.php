<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "mailer_smtp.php";
require_once "limite_acceso.php";
require_once "app_url.php";

header("Content-Type: application/json; charset=UTF-8");

function responder_recuperacion_generica() {
    echo json_encode([
        "error" => false,
        "mensaje" => "Si el correo esta registrado, te enviaremos un enlace para restablecer la contrasena."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder_json_error("Metodo no permitido", 405);
    }

    exigir_origen_mismo_sitio();

    $entradaCorreo = $_POST["correo"] ?? "";
    $correo = is_string($entradaCorreo) ? strtolower(trim($entradaCorreo)) : "";
    limitar_solicitud_acceso($conexion, 'recuperar', substr($correo, 0, 254));

    if ($correo === "" || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responder_recuperacion_generica();
    }

    $consultaUsuario = $conexion->prepare("
        SELECT id_usuario, nombre, correo, estado
        FROM usuario_sistema
        WHERE LOWER(correo) = :correo
        LIMIT 1
    ");
    $consultaUsuario->execute([":correo" => $correo]);
    $usuario = $consultaUsuario->fetch();

    if (!$usuario || !$usuario["estado"]) {
        responder_recuperacion_generica();
    }

    $baseApp = url_base_configurada($config);
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash("sha256", $token);
    $ip = substr($_SERVER["REMOTE_ADDR"] ?? "", 0, 64);

    $conexion->beginTransaction();

    $invalidar = $conexion->prepare("
        UPDATE password_reset_token
        SET usado = TRUE,
            fecha_uso = NOW()
        WHERE id_usuario = :id_usuario
        AND usado = FALSE
    ");
    $invalidar->execute([":id_usuario" => intval($usuario["id_usuario"])]);

    $insertar = $conexion->prepare("
        INSERT INTO password_reset_token (
            id_usuario,
            token_hash,
            expira_en,
            ip_solicitud
        )
        VALUES (
            :id_usuario,
            :token_hash,
            NOW() + INTERVAL '30 minutes',
            :ip_solicitud
        )
    ");
    $insertar->execute([
        ":id_usuario" => intval($usuario["id_usuario"]),
        ":token_hash" => $tokenHash,
        ":ip_solicitud" => $ip
    ]);

    $conexion->commit();

    $enlace = $baseApp . "/html/restablecer_password.html?token=" . urlencode($token);
    $nombre = htmlspecialchars($usuario["nombre"], ENT_QUOTES, "UTF-8");
    $enlaceHtml = htmlspecialchars($enlace, ENT_QUOTES, "UTF-8");
    $asunto = "Restablecer contrasena - Isa Boutique";
    $html = "
        <p>Hola {$nombre},</p>
        <p>Recibimos una solicitud para restablecer tu contrasena de Isa Boutique.</p>
        <p><a href=\"{$enlaceHtml}\">Restablecer mi contrasena</a></p>
        <p>Este enlace vence en 30 minutos. Si no hiciste esta solicitud, puedes ignorar este correo.</p>
    ";
    $texto = "Hola {$usuario["nombre"]},\n\n"
        . "Recibimos una solicitud para restablecer tu contrasena de Isa Boutique.\n"
        . "Abre este enlace: {$enlace}\n\n"
        . "Este enlace vence en 30 minutos. Si no hiciste esta solicitud, puedes ignorar este correo.";

    try {
        enviar_correo_smtp($usuario["correo"], $asunto, $html, $texto);
    } catch (Throwable $e) {
        error_log("Error envio recuperacion password: " . $e->getMessage());
    }

    responder_recuperacion_generica();

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error password_recuperar_solicitar: " . $e->getMessage());
    responder_recuperacion_generica();
}

?>
