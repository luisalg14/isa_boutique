<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function columna_existe_listar_compra(PDO $conexion, $tabla, $columna) {
    $consulta = $conexion->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = :tabla
            AND column_name = :columna
        ) AS existe
    ");
    $consulta->execute([
        ":tabla" => $tabla,
        ":columna" => $columna
    ]);

    return filter_var($consulta->fetch()["existe"], FILTER_VALIDATE_BOOLEAN);
}

try {
    exigir_roles(["admin"]);

    $detalleTieneFlujo = columna_existe_listar_compra($conexion, "detalle_compra_mercancia", "tipo_item")
        && columna_existe_listar_compra($conexion, "detalle_compra_mercancia", "categoria")
        && columna_existe_listar_compra($conexion, "detalle_compra_mercancia", "descripcion")
        && columna_existe_listar_compra($conexion, "detalle_compra_mercancia", "color")
        && columna_existe_listar_compra($conexion, "detalle_compra_mercancia", "estado_registro");
    $compraTieneFlujo = columna_existe_listar_compra($conexion, "compra_mercancia", "tipo_compra")
        && columna_existe_listar_compra($conexion, "compra_mercancia", "estado")
        && columna_existe_listar_compra($conexion, "compra_mercancia", "proveedor_referencia");

    $camposFlujoCompra = $compraTieneFlujo
        ? "c.tipo_compra, c.estado AS estado_compra, c.proveedor_referencia"
        : "'reposicion' AS tipo_compra, 'registrada' AS estado_compra, NULL AS proveedor_referencia";
    $camposFlujoDetalle = $detalleTieneFlujo
        ? "d.tipo_item, d.categoria, d.descripcion, d.color, d.estado_registro"
        : "'reposicion' AS tipo_item, NULL AS categoria, NULL AS descripcion, NULL AS color, 'registrado' AS estado_registro";
    $campoProveedor = $compraTieneFlujo
        ? "COALESCE(pr.nombre, c.proveedor_referencia, 'NN/Otro')"
        : "COALESCE(pr.nombre, 'Sin proveedor')";

    $sql = "
        SELECT
            c.id_compra,
            c.fecha,
            $camposFlujoCompra,
            $campoProveedor AS proveedor,
            p.codigo,
            COALESCE(p.nombre, d.descripcion, 'Mercancía sin clasificar') AS producto,
            $camposFlujoDetalle,
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
        LEFT JOIN producto p
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
