<?php

require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    $sql = "
        SELECT
            cp.id_cambio_precio,
            cp.fecha,
            p.codigo,
            p.nombre AS producto,
            p.marca,
            cp.precio_anterior,
            cp.precio_nuevo,
            cp.detalle,
            u.nombre AS usuario
        FROM cambio_precio cp
        INNER JOIN producto p
            ON cp.id_producto = p.id_producto
        LEFT JOIN usuario_sistema u
            ON cp.id_usuario = u.id_usuario
        ORDER BY cp.fecha DESC, cp.id_cambio_precio DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $cambios = $consulta->fetchAll();

    echo json_encode($cambios, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>