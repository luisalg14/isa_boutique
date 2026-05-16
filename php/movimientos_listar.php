<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    $sql = "
        SELECT
            mi.id_movimiento,
            mi.fecha,
            mi.tipo,
            mi.cantidad,
            mi.detalle,
            p.codigo,
            p.nombre AS producto,
            p.marca,
            u.nombre AS usuario
        FROM movimiento_inventario mi
        INNER JOIN producto p
            ON mi.id_producto = p.id_producto
        LEFT JOIN usuario_sistema u
            ON mi.id_usuario = u.id_usuario
        ORDER BY mi.fecha DESC, mi.id_movimiento DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $movimientos = $consulta->fetchAll();

    echo json_encode($movimientos, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>
