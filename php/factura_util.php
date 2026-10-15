<?php
require_once "factura_lectura.php";

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
    if (!$conexion->inTransaction()) throw new LogicException("Emitir una factura requiere una transaccion");
    if (!factura_admite_datos_emision($conexion)) {
        throw new PDOException("Falta aplicar la migracion de datos de emision de facturas");
    }

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
        FOR UPDATE
    ");
    $consultaVenta->execute([":id_venta" => $idVenta]);
    $venta = $consultaVenta->fetch();

    if (!$venta || !in_array($venta["estado"], ["pagada", "devuelta"], true)) {
        return null;
    }

    $existente = $conexion->prepare("SELECT id_factura,numero_factura,fecha,subtotal,descuento,base_gravable,iva,tarifa_iva,precio_incluye_iva,total,estado FROM factura WHERE id_venta=?");
    $existente->execute([$idVenta]);
    $facturaAnterior = $existente->fetch();
    if ($facturaAnterior) return $facturaAnterior;

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
        RETURNING id_factura, numero_factura, fecha, subtotal, descuento, base_gravable, iva, tarifa_iva, precio_incluye_iva, total, estado
    ");

    $consultaFactura->execute([
        ":id_venta" => $idVenta,
        ":subtotal" => $venta["subtotal_bruto"],
        ":descuento" => $venta["descuento"],
        ":base_gravable" => $venta["base_gravable"],
        ":iva" => $venta["iva"],
        ":tarifa_iva" => $venta["tarifa_iva"],
        ":precio_incluye_iva" => filter_var($venta["precio_incluye_iva"], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
        ":total" => $venta["total"]
    ]);

    $factura = $consultaFactura->fetch();
    $datos = leer_factura_venta($conexion, $idVenta);
    if (!$datos) throw new PDOException("No se pudieron conservar los datos de emision");
    $guardarDatos = $conexion->prepare("UPDATE factura SET datos_emision=CAST(? AS jsonb) WHERE id_factura=? AND datos_emision IS NULL");
    $guardarDatos->execute([json_encode([
        'empresa'=>$datos['empresa'],'factura'=>$datos['factura'],'detalles'=>$datos['detalles']
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),$factura['id_factura']]);
    if ($guardarDatos->rowCount() !== 1) throw new PDOException("No se guardaron los datos de emision");
    return $factura;
}

?>
