<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "notificaciones_seguridad.php";
require_once "auditoria.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder_json_error("Metodo no permitido", 405);
    }

    $idUsuario = intval($_POST["id_usuario"] ?? 0);
    $correo = strtolower(trim($_POST["correo"] ?? ""));

    if ($idUsuario <= 0) {
        responder_json_error("Selecciona un usuario.", 400);
    }

    if ($correo === "" || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        responder_json_error("Ingresa un correo valido.", 400);
    }

    $consultaExiste = $conexion->prepare("
        SELECT id_usuario
        FROM usuario_sistema
        WHERE LOWER(correo) = :correo
        AND id_usuario <> :id_usuario
        LIMIT 1
    ");
    $consultaExiste->execute([
        ":correo" => $correo,
        ":id_usuario" => $idUsuario
    ]);

    if ($consultaExiste->fetch()) {
        responder_json_error("Ese correo ya esta asignado a otro usuario.", 409);
    }

    $actualizar = $conexion->prepare("
        UPDATE usuario_sistema
        SET correo = :correo,
            sesion_token = NULL,
            sesion_actualizada = NULL
        WHERE id_usuario = :id_usuario
    ");
    $actualizar->execute([
        ":correo" => $correo,
        ":id_usuario" => $idUsuario
    ]);

    if ($actualizar->rowCount() === 0) {
        responder_json_error("Usuario no encontrado.", 404);
    }

    $consultaUsuario = $conexion->prepare("
        SELECT nombre, correo
        FROM usuario_sistema
        WHERE id_usuario = :id_usuario
        LIMIT 1
    ");
    $consultaUsuario->execute([":id_usuario" => $idUsuario]);
    $usuarioActualizado = $consultaUsuario->fetch();

    if ($usuarioActualizado) {
        notificar_correo_registrado($usuarioActualizado);
    }

    registrar_auditoria($conexion, "usuario_correo_actualizado", "usuario_sistema", $idUsuario, [
        "correo_nuevo" => $correo
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Correo actualizado correctamente. El usuario debera iniciar sesion con el nuevo correo."
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error usuario_correo_actualizar: " . $e->getMessage());
    responder_json_error("No se pudo actualizar el correo.", 500);
}

?>
