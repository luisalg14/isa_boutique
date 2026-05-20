<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "factura_util.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    $idVenta = intval($_GET["id_venta"] ?? 0);

    if ($idVenta <= 0) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Venta no válida"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $factura = asegurar_factura_venta($conexion, $idVenta);

    if (!$factura) {
        echo json_encode([
            "error" => true,
            "mensaje" => "No se puede emitir factura para esta venta"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $consultaEncabezado = $conexion->prepare("
        SELECT
            f.id_factura,
            f.numero_factura,
            f.fecha AS fecha_factura,
            f.subtotal,
            f.descuento,
            f.base_gravable,
            f.iva,
            f.tarifa_iva,
            f.precio_incluye_iva,
            f.total,
            f.estado AS estado_factura,
            v.id_venta,
            v.fecha AS fecha_venta,
            v.medio_pago,
            v.canal_venta,
            v.tipo_entrega,
            v.estado AS estado_venta,
            cl.nombre AS cliente,
            cl.telefono,
            COALESCE(cl.correo, '') AS correo,
            COALESCE(cl.direccion, '') AS direccion,
            u.nombre AS atendido_por
        FROM factura f
        INNER JOIN venta v
            ON f.id_venta = v.id_venta
        INNER JOIN cliente cl
            ON v.id_cliente = cl.id_cliente
        INNER JOIN usuario_sistema u
            ON v.id_usuario = u.id_usuario
        WHERE f.id_venta = :id_venta
        LIMIT 1
    ");
    $consultaEncabezado->execute([":id_venta" => $idVenta]);
    $encabezado = $consultaEncabezado->fetch();

    $consultaDetalle = $conexion->prepare("
        SELECT
            p.codigo,
            p.nombre AS producto,
            p.marca,
            COALESCE(dv.color, p.color, '') AS color,
            dv.talla,
            dv.cantidad,
            dv.precio_unitario,
            dv.descuento,
            dv.base_gravable,
            dv.iva,
            dv.tarifa_iva,
            dv.subtotal
        FROM detalle_venta dv
        INNER JOIN producto p
            ON dv.id_producto = p.id_producto
        WHERE dv.id_venta = :id_venta
        ORDER BY dv.id_detalle_venta ASC
    ");
    $consultaDetalle->execute([":id_venta" => $idVenta]);

    echo json_encode([
        "error" => false,
        "factura" => $encabezado,
        "detalles" => $consultaDetalle->fetchAll()
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error factura_obtener: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo cargar la factura"
    ], JSON_UNESCAPED_UNICODE);
}

?>
