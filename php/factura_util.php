<?php

function tabla_factura_existe($conexion) {
    $consulta = $conexion->query("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'factura'
        ) AS existe
    ");

    return filter_var($consulta->fetch()["existe"], FILTER_VALIDATE_BOOLEAN);
}

function asegurar_factura_venta($conexion, $idVenta) {
    if (!tabla_factura_existe($conexion)) {
        return null;
    }

    $consultaVenta = $conexion->prepare("
        SELECT
            id_venta,
            total,
            estado,
            subtotal_bruto,
            descuento,
            base_gravable,
            iva,
            tarifa_iva,
            precio_incluye_iva
        FROM venta
        WHERE id_venta = :id_venta
        LIMIT 1
    ");
    $consultaVenta->execute([":id_venta" => $idVenta]);
    $venta = $consultaVenta->fetch();

    if (!$venta || !in_array($venta["estado"], ["pagada", "devuelta"], true)) {
        return null;
    }

    $consultaFactura = $conexion->prepare("
        INSERT INTO factura (
            id_venta,
            subtotal,
            descuento,
            base_gravable,
            iva,
            tarifa_iva,
            precio_incluye_iva,
            total,
            estado
        )
        VALUES (
            :id_venta,
            :subtotal,
            :descuento,
            :base_gravable,
            :iva,
            :tarifa_iva,
            :precio_incluye_iva,
            :total,
            'emitida'
        )
        ON CONFLICT (id_venta)
        DO UPDATE SET
            subtotal = EXCLUDED.subtotal,
            descuento = EXCLUDED.descuento,
            base_gravable = EXCLUDED.base_gravable,
            iva = EXCLUDED.iva,
            tarifa_iva = EXCLUDED.tarifa_iva,
            precio_incluye_iva = EXCLUDED.precio_incluye_iva,
            total = EXCLUDED.total
        RETURNING id_factura, numero_factura, fecha, subtotal, descuento, base_gravable, iva, tarifa_iva, precio_incluye_iva, total, estado
    ");

    $consultaFactura->execute([
        ":id_venta" => $idVenta,
        ":subtotal" => $venta["subtotal_bruto"],
        ":descuento" => $venta["descuento"],
        ":base_gravable" => $venta["base_gravable"],
        ":iva" => $venta["iva"],
        ":tarifa_iva" => $venta["tarifa_iva"],
        ":precio_incluye_iva" => $venta["precio_incluye_iva"],
        ":total" => $venta["total"]
    ]);

    return $consultaFactura->fetch();
}

?>
