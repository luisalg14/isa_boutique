<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nombre = trim($_POST["nombre"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $ciudad = trim($_POST["ciudad"] ?? "");
    $productoSuministra = trim($_POST["producto_suministra"] ?? "");

    if ($nombre === "") {
        echo json_encode(["error" => true, "mensaje" => "Ingresa el nombre del proveedor"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        INSERT INTO proveedor (
            nombre,
            telefono,
            ciudad,
            producto_suministra
        )
        VALUES (
            :nombre,
            :telefono,
            :ciudad,
            :producto_suministra
        )
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":nombre" => $nombre,
        ":telefono" => $telefono,
        ":ciudad" => $ciudad,
        ":producto_suministra" => $productoSuministra
    ]);

    echo json_encode(["error" => false, "mensaje" => "Proveedor registrado correctamente"], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(["error" => true, "mensaje" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
