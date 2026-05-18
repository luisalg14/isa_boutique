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
            cl.nombre AS cliente,
            cl.telefono,
            'Venta' AS tipo,
            p.codigo,
            p.nombre AS producto,
            p.marca,
            dv.talla,
            dv.cantidad,
            dv.precio_unitario,
            dv.subtotal AS total,
            dv.subtotal AS subtotal,
            v.medio_pago,
            v.fecha,
            NULL AS motivo
        FROM venta v
        INNER JOIN cliente cl
            ON v.id_cliente = cl.id_cliente
        INNER JOIN detalle_venta dv
            ON v.id_venta = dv.id_venta
        INNER JOIN producto p
            ON dv.id_producto = p.id_producto

        UNION ALL

        SELECT
            d.id_venta,
            dd.id_producto,
            cl.nombre AS cliente,
            cl.telefono,
            'Devolución' AS tipo,
            p.codigo,
            p.nombre AS producto,
            p.marca,
            dd.talla,
            dd.cantidad,
            dd.precio_unitario,
            -dd.subtotal_devuelto AS total,
            -dd.subtotal_devuelto AS subtotal,
            v.medio_pago,
            d.fecha,
            d.motivo
        FROM devolucion d
        INNER JOIN cliente cl
            ON d.id_cliente = cl.id_cliente
        INNER JOIN venta v
            ON d.id_venta = v.id_venta
        INNER JOIN detalle_devolucion dd
            ON d.id_devolucion = dd.id_devolucion
        INNER JOIN producto p
            ON dd.id_producto = p.id_producto

        ORDER BY fecha DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $historial = $consulta->fetchAll();

    echo json_encode($historial, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>
