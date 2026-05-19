<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = trim($_POST["contrasena"] ?? "");

    if ($correo === "" || $contrasena === "") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Ingresa correo y contraseña"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT id_usuario, nombre, correo, contrasena, rol, estado, sesion_token, sesion_actualizada
        FROM usuario_sistema
        WHERE LOWER(correo) = :correo
        LIMIT 1
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([":correo" => $correo]);
    $usuario = $consulta->fetch();

    if (!$usuario || !password_verify($contrasena, $usuario["contrasena"])) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Correo o contraseña incorrectos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$usuario["estado"]) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Este usuario esta inactivo"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($usuario["rol"] === "vendedor" && !vendedor_en_horario_laboral()) {
        echo json_encode([
            "error" => true,
            "mensaje" => mensaje_horario_vendedor()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (
        !empty($usuario["sesion_token"]) &&
        !empty($usuario["sesion_actualizada"]) &&
        strtotime($usuario["sesion_actualizada"]) > strtotime("-8 hours")
    ) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Este usuario ya tiene una sesión activa. Cierra sesión en el otro dispositivo o espera a que expire."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    session_regenerate_id(true);

    $tokenSesion = bin2hex(random_bytes(32));

    $consultaSesion = $conexion->prepare("
        UPDATE usuario_sistema
        SET sesion_token = :token,
            sesion_actualizada = NOW()
        WHERE id_usuario = :id_usuario
    ");
    $consultaSesion->execute([
        ":token" => $tokenSesion,
        ":id_usuario" => intval($usuario["id_usuario"])
    ]);

    $_SESSION["usuario"] = [
        "id_usuario" => intval($usuario["id_usuario"]),
        "nombre" => $usuario["nombre"],
        "correo" => $usuario["correo"],
        "rol" => $usuario["rol"],
        "sesion_token" => $tokenSesion
    ];

    echo json_encode([
        "error" => false,
        "mensaje" => "Sesion iniciada correctamente",
        "usuario" => $_SESSION["usuario"]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo iniciar sesión en este momento"
    ], JSON_UNESCAPED_UNICODE);
}

?>
