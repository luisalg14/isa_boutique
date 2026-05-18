<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $sql = "
        SELECT
            id_usuario,
            nombre,
            correo,
            rol,
            estado
        FROM usuario_sistema
        ORDER BY rol ASC, nombre ASC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error usuarios_listar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudieron cargar los usuarios"
    ], JSON_UNESCAPED_UNICODE);
}

?>
