<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "factura_util.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    if (!tabla_factura_existe($conexion)) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT
            f.id_factura,
            f.numero_factura,
            f.fecha,
            f.subtotal,
            f.descuento,
            f.base_gravable,
            f.iva,
            f.total,
            f.estado AS estado_factura,
            v.id_venta,
            v.estado AS estado_venta,
            v.medio_pago,
            v.canal_venta,
            cl.nombre AS cliente,
            cl.telefono
        FROM factura f
        INNER JOIN venta v
            ON f.id_venta = v.id_venta
        INNER JOIN cliente cl
            ON v.id_cliente = cl.id_cliente
        ORDER BY f.fecha DESC, f.id_factura DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error facturas_listar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudieron cargar las facturas"
    ], JSON_UNESCAPED_UNICODE);
}

?>
