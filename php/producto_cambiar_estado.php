<?php

require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ]);
        exit;
    }

    $id_producto = intval($_POST["id_producto"] ?? 0);
    $estado = trim($_POST["estado"] ?? "");

    $estadosPermitidos = ["activo", "inactivo", "agotado"];

    if ($id_producto <= 0 || !in_array($estado, $estadosPermitidos)) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos inválidos"
        ]);
        exit;
    }

    $sql = "
        UPDATE producto
        SET estado = :estado
        WHERE id_producto = :id_producto
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":estado" => $estado,
        ":id_producto" => $id_producto
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Estado del producto actualizado correctamente"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>