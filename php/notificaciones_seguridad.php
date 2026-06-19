<?php

require_once "mailer_smtp.php";

function encolar_notificacion_correo($correo, $asunto, $html, $texto) {
    global $conexion;

    if (!isset($conexion)) {
        return false;
    }

    try {
        $consulta = $conexion->prepare("
            INSERT INTO notificacion_correo (
                destinatario,
                asunto,
                cuerpo_html,
                cuerpo_texto
            )
            VALUES (
                :destinatario,
                :asunto,
                :cuerpo_html,
                :cuerpo_texto
            )
        ");
        $consulta->execute([
            ":destinatario" => $correo,
            ":asunto" => $asunto,
            ":cuerpo_html" => $html,
            ":cuerpo_texto" => $texto
        ]);

        return true;
    } catch (Throwable $e) {
        error_log("No se pudo encolar notificacion de correo: " . $e->getMessage());
        return false;
    }
}

function contexto_acceso_seguridad() {
    return [
        "ip" => $_SERVER["REMOTE_ADDR"] ?? "No disponible",
        "navegador" => substr($_SERVER["HTTP_USER_AGENT"] ?? "No disponible", 0, 220),
        "fecha" => date("Y-m-d H:i:s")
    ];
}

function enviar_notificacion_seguridad($correo, $nombre, $asunto, $titulo, $mensaje) {
    if (!$correo || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $contexto = contexto_acceso_seguridad();
    $nombreSeguro = htmlspecialchars($nombre ?: "Usuario Isa Boutique", ENT_QUOTES, "UTF-8");
    $tituloSeguro = htmlspecialchars($titulo, ENT_QUOTES, "UTF-8");
    $mensajeSeguro = nl2br(htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8"));
    $ipSeguro = htmlspecialchars($contexto["ip"], ENT_QUOTES, "UTF-8");
    $navegadorSeguro = htmlspecialchars($contexto["navegador"], ENT_QUOTES, "UTF-8");
    $fechaSeguro = htmlspecialchars($contexto["fecha"], ENT_QUOTES, "UTF-8");

    $html = "
        <div style=\"font-family:Arial,sans-serif;color:#2f2f2f;line-height:1.5\">
            <h2 style=\"color:#954053;margin-bottom:8px\">{$tituloSeguro}</h2>
            <p>Hola {$nombreSeguro},</p>
            <p>{$mensajeSeguro}</p>
            <div style=\"margin:18px 0;padding:14px;border:1px solid #ead8d2;border-radius:8px;background:#fff8f5\">
                <strong>Detalles del acceso</strong><br>
                Fecha: {$fechaSeguro}<br>
                IP: {$ipSeguro}<br>
                Dispositivo/Navegador: {$navegadorSeguro}
            </div>
            <p>Si fuiste tu, no necesitas hacer nada. Si no reconoces este movimiento, cambia tu contrasena de inmediato.</p>
        </div>
    ";
    $texto = "Hola " . ($nombre ?: "Usuario Isa Boutique") . ",\n\n"
        . $mensaje . "\n\n"
        . "Detalles del acceso:\n"
        . "Fecha: " . $contexto["fecha"] . "\n"
        . "IP: " . $contexto["ip"] . "\n"
        . "Dispositivo/Navegador: " . $contexto["navegador"] . "\n\n"
        . "Si fuiste tu, no necesitas hacer nada. Si no reconoces este movimiento, cambia tu contrasena de inmediato.";

    return encolar_notificacion_correo($correo, $asunto, $html, $texto);
}

function notificar_correo_registrado($usuario) {
    return enviar_notificacion_seguridad(
        $usuario["correo"] ?? "",
        $usuario["nombre"] ?? "",
        "Correo de ingreso actualizado - Isa Boutique",
        "Correo de ingreso actualizado",
        "Este correo fue registrado como correo de ingreso para tu cuenta interna de Isa Boutique."
    );
}

function notificar_inicio_sesion($usuario) {
    return enviar_notificacion_seguridad(
        $usuario["correo"] ?? "",
        $usuario["nombre"] ?? "",
        "Inicio de sesion en Isa Boutique",
        "Nuevo inicio de sesion",
        "Se inicio sesion correctamente en tu cuenta interna de Isa Boutique."
    );
}

function notificar_intento_otro_dispositivo($usuario) {
    return enviar_notificacion_seguridad(
        $usuario["correo"] ?? "",
        $usuario["nombre"] ?? "",
        "Intento de acceso en otro dispositivo - Isa Boutique",
        "Intento de acceso detectado",
        "Alguien ingreso tu correo y contrasena mientras tu cuenta ya tenia una sesion activa en otro dispositivo."
    );
}

?>
