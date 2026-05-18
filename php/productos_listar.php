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
            p.costo_unitario,
            p.cantidad,
            p.estado,
            p.imagen,
            COALESCE(
                json_agg(
                    json_build_object(
                        'talla', pt.talla,
                        'cantidad', pt.cantidad
                    )
                    ORDER BY pt.talla
                ) FILTER (WHERE pt.id_producto_talla IS NOT NULL),
                '[]'
            ) AS tallas
        FROM producto p
        INNER JOIN categoria c
            ON p.id_categoria = c.id_categoria
        LEFT JOIN producto_talla pt
            ON p.id_producto = pt.id_producto
        GROUP BY
            p.id_producto,
            p.codigo,
            p.nombre,
            p.marca,
            c.nombre,
            p.precio,
            p.costo_unitario,
            p.cantidad,
            p.estado,
            p.imagen
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
