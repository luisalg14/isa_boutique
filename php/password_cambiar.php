<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function validar_password_seguro($password) {
    if (strlen($password) < 8) {
        return "La nueva contraseña debe tener mínimo 8 caracteres";
    }

    if (!preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password)) {
        return "La nueva contraseña debe incluir letras y números";
    }

    return "";
}

try {
    $usuarioActual = exigir_sesion();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $modo = trim($_POST["modo"] ?? "propia");
    $passwordNueva = trim($_POST["password_nueva"] ?? "");
    $passwordConfirmar = trim($_POST["password_confirmar"] ?? "");

    if ($passwordNueva === "" || $passwordConfirmar === "") {
        echo json_encode(["error" => true, "mensaje" => "Completa la nueva contraseña y su confirmación"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($passwordNueva !== $passwordConfirmar) {
        echo json_encode(["error" => true, "mensaje" => "Las contraseñas nuevas no coinciden"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mensajeValidacion = validar_password_seguro($passwordNueva);

    if ($mensajeValidacion !== "") {
        echo json_encode(["error" => true, "mensaje" => $mensajeValidacion], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($usuarioActual["rol"] ?? "") !== "admin") {
        echo json_encode(["error" => true, "mensaje" => "Solo el administrador puede administrar contraseñas"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($modo === "restablecer") {
        $idUsuario = intval($_POST["id_usuario"] ?? 0);

        if ($idUsuario <= 0) {
            echo json_encode(["error" => true, "mensaje" => "Selecciona un usuario"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $idUsuario = intval($usuarioActual["id_usuario"]);
        $passwordActual = trim($_POST["password_actual"] ?? "");

        if ($passwordActual === "") {
            echo json_encode(["error" => true, "mensaje" => "Ingresa tu contraseña actual"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $consultaActual = $conexion->prepare("
            SELECT contrasena
            FROM usuario_sistema
            WHERE id_usuario = :id_usuario
            LIMIT 1
        ");
        $consultaActual->execute([":id_usuario" => $idUsuario]);
        $usuario = $consultaActual->fetch();

        if (!$usuario || !password_verify($passwordActual, $usuario["contrasena"])) {
            echo json_encode(["error" => true, "mensaje" => "La contraseña actual no es correcta"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $hash = password_hash($passwordNueva, PASSWORD_DEFAULT);
    $consulta = $conexion->prepare("
        UPDATE usuario_sistema
        SET contrasena = :contrasena
        WHERE id_usuario = :id_usuario
    ");
    $consulta->execute([
        ":contrasena" => $hash,
        ":id_usuario" => $idUsuario
    ]);

    if ($consulta->rowCount() === 0) {
        echo json_encode(["error" => true, "mensaje" => "Usuario no encontrado"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        "error" => false,
        "mensaje" => "Contraseña actualizada correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error password_cambiar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo actualizar la contraseña"
    ], JSON_UNESCAPED_UNICODE);
}

?>
