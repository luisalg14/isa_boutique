<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "password_util.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder_json_error("Metodo no permitido", 405);
    }

    exigir_origen_mismo_sitio();

    $token = trim($_POST["token"] ?? "");
    $passwordNueva = trim($_POST["password_nueva"] ?? "");
    $passwordConfirmar = trim($_POST["password_confirmar"] ?? "");

    if ($token === "" || !preg_match("/^[a-f0-9]{64}$/", $token)) {
        responder_json_error("El enlace de recuperacion no es valido o ya vencio.", 400);
    }

    if ($passwordNueva === "" || $passwordConfirmar === "") {
        responder_json_error("Completa la nueva contrasena y su confirmacion.", 400);
    }

    if ($passwordNueva !== $passwordConfirmar) {
        responder_json_error("Las contrasenas nuevas no coinciden.", 400);
    }

    $mensajeValidacion = validar_password_seguro($passwordNueva);

    if ($mensajeValidacion !== "") {
        responder_json_error($mensajeValidacion, 400);
    }

    $tokenHash = hash("sha256", $token);
    $conexion->beginTransaction();

    $consultaToken = $conexion->prepare("
        SELECT id_reset, id_usuario
        FROM password_reset_token
        WHERE token_hash = :token_hash
        AND usado = FALSE
        AND expira_en > NOW()
        LIMIT 1
        FOR UPDATE
    ");
    $consultaToken->execute([":token_hash" => $tokenHash]);
    $registro = $consultaToken->fetch();

    if (!$registro) {
        $conexion->rollBack();
        responder_json_error("El enlace de recuperacion no es valido o ya vencio.", 400);
    }

    $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    $actualizarUsuario = $conexion->prepare("
        UPDATE usuario_sistema
        SET contrasena = :contrasena,
            sesion_token = NULL,
            sesion_actualizada = NULL
        WHERE id_usuario = :id_usuario
    ");
    $actualizarUsuario->execute([
        ":contrasena" => $hash,
        ":id_usuario" => intval($registro["id_usuario"])
    ]);

    $usarToken = $conexion->prepare("
        UPDATE password_reset_token
        SET usado = TRUE,
            fecha_uso = NOW()
        WHERE id_reset = :id_reset
    ");
    $usarToken->execute([":id_reset" => intval($registro["id_reset"])]);

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Contrasena actualizada correctamente. Ya puedes iniciar sesion."
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error password_recuperar_confirmar: " . $e->getMessage());
    responder_json_error("No se pudo actualizar la contrasena.", 500);
}

?>
