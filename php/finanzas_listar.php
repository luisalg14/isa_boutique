<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $sql = "
        SELECT
            'Gasto' AS clase,
            g.id_gasto AS id_movimiento,
            g.tipo,
            g.concepto,
            -g.valor AS valor,
            g.fecha,
            g.detalle,
            u.nombre AS usuario
        FROM gasto_negocio g
        LEFT JOIN usuario_sistema u
            ON g.id_usuario = u.id_usuario

        UNION ALL

        SELECT
            'Inversion' AS clase,
            i.id_inversion AS id_movimiento,
            i.tipo,
            i.concepto,
            i.valor,
            i.fecha,
            i.detalle,
            u.nombre AS usuario
        FROM inversion_negocio i
        LEFT JOIN usuario_sistema u
            ON i.id_usuario = u.id_usuario

        UNION ALL

        SELECT
            'Pago trabajador' AS clase,
            p.id_pago_trabajador AS id_movimiento,
            p.tipo_pago AS tipo,
            t.nombre || ' - ' || t.cargo AS concepto,
            -p.valor AS valor,
            p.fecha,
            p.detalle,
            u.nombre AS usuario
        FROM pago_trabajador p
        INNER JOIN trabajador t
            ON p.id_trabajador = t.id_trabajador
        LEFT JOIN usuario_sistema u
            ON p.id_usuario = u.id_usuario

        UNION ALL

        SELECT
            'Compra mercancia' AS clase,
            c.id_compra AS id_movimiento,
            'mercancia' AS tipo,
            COALESCE(pr.nombre, 'Sin proveedor') AS concepto,
            -c.total_compra AS valor,
            c.fecha,
            c.detalle,
            u.nombre AS usuario
        FROM compra_mercancia c
        LEFT JOIN proveedor pr
            ON c.id_proveedor = pr.id_proveedor
        LEFT JOIN usuario_sistema u
            ON c.id_usuario = u.id_usuario

        ORDER BY fecha DESC, id_movimiento DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
