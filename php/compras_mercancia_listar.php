<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $sql = "
        SELECT
            c.id_compra,
            c.fecha,
            COALESCE(pr.nombre, 'Sin proveedor') AS proveedor,
            p.codigo,
            p.nombre AS producto,
            d.talla,
            d.cantidad,
            d.costo_unitario,
            d.subtotal,
            c.costo_envio,
            c.total_compra,
            c.detalle,
            u.nombre AS usuario
        FROM compra_mercancia c
        INNER JOIN detalle_compra_mercancia d
            ON c.id_compra = d.id_compra
        INNER JOIN producto p
            ON d.id_producto = p.id_producto
        LEFT JOIN proveedor pr
            ON c.id_proveedor = pr.id_proveedor
        LEFT JOIN usuario_sistema u
            ON c.id_usuario = u.id_usuario
        ORDER BY c.fecha DESC, c.id_compra DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(["error" => true, "mensaje" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
