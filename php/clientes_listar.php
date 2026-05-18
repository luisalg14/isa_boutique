<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    $sql = "
        SELECT
            c.id_cliente,
            c.nombre,
            c.telefono,
            c.correo,
            c.direccion,
            c.fecha_registro,
            COUNT(DISTINCT v.id_venta) AS compras,
            COALESCE(SUM(CASE WHEN v.estado IN ('pagada', 'devuelta') THEN v.total ELSE 0 END), 0) AS total_comprado,
            MAX(v.fecha) AS ultima_compra
        FROM cliente c
        LEFT JOIN venta v
            ON c.id_cliente = v.id_cliente
        GROUP BY
            c.id_cliente,
            c.nombre,
            c.telefono,
            c.correo,
            c.direccion,
            c.fecha_registro
        ORDER BY ultima_compra DESC NULLS LAST, c.fecha_registro DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error clientes_listar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudieron cargar los clientes"
    ], JSON_UNESCAPED_UNICODE);
}

?>
