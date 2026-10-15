<?php
require_once "facturacion_config.php";

function factura_admite_datos_emision(PDO $conexion) {
    return filter_var($conexion->query("SELECT EXISTS(SELECT 1 FROM information_schema.columns
        WHERE table_schema=current_schema() AND table_name='factura' AND column_name='datos_emision')")->fetchColumn(), FILTER_VALIDATE_BOOLEAN);
}

function leer_factura_venta(PDO $conexion, $idVenta) {
    $datosEmision = factura_admite_datos_emision($conexion) ? "f.datos_emision" : "NULL::jsonb AS datos_emision";
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
            $datosEmision,
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
            COALESCE(cl.tipo_documento, 'CC') AS tipo_documento,
            COALESCE(cl.numero_documento, '') AS numero_documento,
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

    if (!$encabezado) return null;
    $guardados = $encabezado["datos_emision"] ?? null;
    unset($encabezado["datos_emision"]);
    if ($guardados !== null) {
        $datos = is_array($guardados) ? $guardados : json_decode($guardados, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($datos["factura"], $datos["detalles"], $datos["empresa"])) throw new RuntimeException("Datos de emision incompletos");
        // Los importes y la identidad proceden de la emision; el estado sigue siendo consultable.
        $datos["factura"]["estado_factura"] = $encabezado["estado_factura"];
        $datos["factura"]["estado_venta"] = $encabezado["estado_venta"];
        return ["error"=>false,"empresa"=>$datos["empresa"],"factura"=>$datos["factura"],"detalles"=>$datos["detalles"],"datos_emision_conservados"=>true];
    }
    return ["error"=>false,"empresa"=>obtener_configuracion_facturacion($conexion),"factura"=>$encabezado,
        "detalles"=>$consultaDetalle->fetchAll(),"datos_emision_conservados"=>false];
}
