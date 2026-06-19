<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "mailer_smtp.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $limite = max(1, min(20, intval($_POST["limite"] ?? $_GET["limite"] ?? 10)));

    $consulta = $conexion->prepare("
        SELECT id_notificacion, destinatario, asunto, cuerpo_html, cuerpo_texto
        FROM notificacion_correo
        WHERE estado = 'pendiente'
        ORDER BY fecha_creacion ASC
        LIMIT :limite
    ");
    $consulta->bindValue(":limite", $limite, PDO::PARAM_INT);
    $consulta->execute();
    $notificaciones = $consulta->fetchAll();
    $enviadas = 0;
    $fallidas = 0;

    foreach ($notificaciones as $notificacion) {
        try {
            enviar_correo_smtp(
                $notificacion["destinatario"],
                $notificacion["asunto"],
                $notificacion["cuerpo_html"],
                $notificacion["cuerpo_texto"]
            );

            $actualizar = $conexion->prepare("
                UPDATE notificacion_correo
                SET estado = 'enviada',
                    fecha_envio = NOW(),
                    intentos = intentos + 1,
                    error_ultimo = NULL
                WHERE id_notificacion = :id_notificacion
            ");
            $actualizar->execute([":id_notificacion" => intval($notificacion["id_notificacion"])]);
            $enviadas++;
        } catch (Throwable $e) {
            $actualizar = $conexion->prepare("
                UPDATE notificacion_correo
                SET intentos = intentos + 1,
                    error_ultimo = :error_ultimo,
                    estado = CASE WHEN intentos + 1 >= 3 THEN 'fallida' ELSE 'pendiente' END
                WHERE id_notificacion = :id_notificacion
            ");
            $actualizar->execute([
                ":error_ultimo" => substr($e->getMessage(), 0, 500),
                ":id_notificacion" => intval($notificacion["id_notificacion"])
            ]);
            $fallidas++;
        }
    }

    echo json_encode([
        "error" => false,
        "mensaje" => "Notificaciones procesadas",
        "enviadas" => $enviadas,
        "fallidas" => $fallidas,
        "pendientes" => max(0, count($notificaciones) - $enviadas - $fallidas)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error notificaciones_enviar_pendientes: " . $e->getMessage());
    responder_json_error("No se pudieron procesar las notificaciones.", 500);
}

?>
