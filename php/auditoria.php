<?php

function registrar_auditoria($conexion, $accion, $entidad = null, $idEntidad = null, $detalle = []) {
    try {
        $usuario = usuario_actual();

        $consulta = $conexion->prepare("
            INSERT INTO auditoria_evento (
                id_usuario,
                rol,
                accion,
                entidad,
                id_entidad,
                detalle,
                ip,
                user_agent
            )
            VALUES (
                :id_usuario,
                :rol,
                :accion,
                :entidad,
                :id_entidad,
                :detalle,
                :ip,
                :user_agent
            )
        ");

        $consulta->execute([
            ":id_usuario" => $usuario ? intval($usuario["id_usuario"]) : null,
            ":rol" => $usuario["rol"] ?? null,
            ":accion" => $accion,
            ":entidad" => $entidad,
            ":id_entidad" => $idEntidad,
            ":detalle" => json_encode($detalle, JSON_UNESCAPED_UNICODE),
            ":ip" => $_SERVER["REMOTE_ADDR"] ?? null,
            ":user_agent" => substr($_SERVER["HTTP_USER_AGENT"] ?? "", 0, 500)
        ]);
    } catch (Throwable $e) {
        error_log("Error auditoria: " . $e->getMessage());
    }
}

?>
