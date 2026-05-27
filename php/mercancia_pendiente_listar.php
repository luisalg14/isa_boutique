<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $consulta = $conexion->prepare("
        SELECT
            d.id_detalle_compra,
            d.id_compra,
            c.fecha,
            COALESCE(pr.nombre, c.proveedor_referencia, 'NN/Otro') AS proveedor,
            d.categoria,
            d.descripcion,
            d.color,
            d.talla,
            d.cantidad,
            d.costo_unitario,
            d.subtotal
        FROM detalle_compra_mercancia d
        INNER JOIN compra_mercancia c
            ON d.id_compra = c.id_compra
        LEFT JOIN proveedor pr
            ON c.id_proveedor = pr.id_proveedor
        WHERE d.tipo_item = 'mercancia_nueva'
        AND d.estado_registro = 'pendiente'
        ORDER BY c.fecha DESC, d.id_compra DESC, d.descripcion ASC, d.talla ASC
    ");
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(["error" => true, "mensaje" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
