<?php

require_once "conexion.php";

try {
    $sql = "
        SELECT 
            p.id_producto,
            p.codigo,
            p.nombre,
            p.marca,
            c.nombre AS categoria,
            p.precio,
            p.cantidad,
            p.estado,
            p.imagen
        FROM producto p
        INNER JOIN categoria c
            ON p.id_categoria = c.id_categoria
        ORDER BY p.id_producto ASC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $productos = $consulta->fetchAll();

    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>