<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    $sql = "
        SELECT
            v.id_venta,
            dv.id_producto,
            v.fecha,
            cl.nombre AS cliente,
            cl.telefono,
            u.nombre AS usuario,
            p.codigo,
            p.nombre AS producto,
            p.marca,
            dv.cantidad,
            dv.precio_unitario,
            dv.subtotal,
            v.medio_pago,
            v.canal_venta,
            v.total,
            v.estado
        FROM venta v
        INNER JOIN cliente cl
            ON v.id_cliente = cl.id_cliente
        INNER JOIN usuario_sistema u
            ON v.id_usuario = u.id_usuario
        INNER JOIN detalle_venta dv
            ON v.id_venta = dv.id_venta
        INNER JOIN producto p
            ON dv.id_producto = p.id_producto
        ORDER BY v.fecha DESC, v.id_venta DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $ventas = $consulta->fetchAll();

    echo json_encode($ventas, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>
