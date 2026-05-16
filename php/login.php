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
        SELECT id_usuario, nombre, correo, contrasena, rol, estado
        FROM usuario_sistema
        WHERE LOWER(correo) = :correo
        LIMIT 1
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":correo" => $correo
    ]);

    $usuario = $consulta->fetch();

    $contrasenaCorrecta = false;

    if ($usuario) {
        $hashGuardado = $usuario["contrasena"];
        $contrasenaCorrecta = password_verify($contrasena, $hashGuardado) || hash_equals($hashGuardado, $contrasena);
    }

    if (!$usuario || !$contrasenaCorrecta) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Correo o contraseña incorrectos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!$usuario["estado"]) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Este usuario está inactivo"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION["usuario"] = [
        "id_usuario" => intval($usuario["id_usuario"]),
        "nombre" => $usuario["nombre"],
        "correo" => $usuario["correo"],
        "rol" => $usuario["rol"]
    ];

    echo json_encode([
        "error" => false,
        "mensaje" => "Sesión iniciada correctamente",
        "usuario" => $_SESSION["usuario"]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
