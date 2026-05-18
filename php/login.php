<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Metodo no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $correo = strtolower(trim($_POST["correo"] ?? ""));
    $contrasena = trim($_POST["contrasena"] ?? "");

    if ($correo === "" || $contrasena === "") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Ingresa correo y contrasena"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT id_usuario, nombre, correo, contrasena, rol, estado
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
            "mensaje" => "Correo o contrasena incorrectos"
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

    session_regenerate_id(true);

    $_SESSION["usuario"] = [
        "id_usuario" => intval($usuario["id_usuario"]),
        "nombre" => $usuario["nombre"],
        "correo" => $usuario["correo"],
        "rol" => $usuario["rol"]
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
        "mensaje" => "No se pudo iniciar sesion en este momento"
    ], JSON_UNESCAPED_UNICODE);
}

?>
