<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ]);
        exit;
    }

    $id_venta = intval($_POST["id_venta"] ?? 0);
    $id_producto = intval($_POST["id_producto"] ?? 0);
    $cantidad = intval($_POST["cantidad"] ?? 0);
    $motivo = trim($_POST["motivo"] ?? "");

    if (
        $id_venta <= 0 ||
        $id_producto <= 0 ||
        $cantidad <= 0 ||
        $motivo === ""
    ) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o inválidos"
        ]);
        exit;
    }

    $conexion->beginTransaction();

    // Buscar venta, cliente y producto vendido
    $sqlVenta = "
        SELECT 
            v.id_venta,
            v.id_cliente,
            dv.id_producto,
            dv.talla,
            dv.cantidad AS cantidad_vendida,
            dv.precio_unitario,
            dv.costo_unitario
        FROM venta v
        INNER JOIN detalle_venta dv
            ON v.id_venta = dv.id_venta
        WHERE v.id_venta = :id_venta
        AND dv.id_producto = :id_producto
        LIMIT 1
    ";

    $consultaVenta = $conexion->prepare($sqlVenta);
    $consultaVenta->execute([
        ":id_venta" => $id_venta,
        ":id_producto" => $id_producto
    ]);

    $venta = $consultaVenta->fetch();

    if (!$venta) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "No se encontró la venta o el producto vendido"
        ]);
        exit;
    }

    // Calcular cantidad ya devuelta de ese producto en esa venta
    $sqlDevuelto = "
        SELECT COALESCE(SUM(dd.cantidad), 0) AS cantidad_devuelta
        FROM devolucion d
        INNER JOIN detalle_devolucion dd
            ON d.id_devolucion = dd.id_devolucion
        WHERE d.id_venta = :id_venta
        AND dd.id_producto = :id_producto
        AND d.estado = 'aprobada'
    ";

    $consultaDevuelto = $conexion->prepare($sqlDevuelto);
    $consultaDevuelto->execute([
        ":id_venta" => $id_venta,
        ":id_producto" => $id_producto
    ]);

    $cantidadDevuelta = intval($consultaDevuelto->fetch()["cantidad_devuelta"]);

    $cantidadDisponible = intval($venta["cantidad_vendida"]) - $cantidadDevuelta;

    if ($cantidad > $cantidadDisponible) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "No puedes devolver más unidades de las vendidas. Disponibles para devolución: " . $cantidadDisponible
        ]);
        exit;
    }

    $precioUnitario = floatval($venta["precio_unitario"]);
    $costoUnitario = floatval($venta["costo_unitario"]);
    $subtotalDevuelto = $precioUnitario * $cantidad;
    $subtotalCostoDevuelto = $costoUnitario * $cantidad;
    $talla = $venta["talla"];

    // Registrar devolución
    $sqlDevolucion = "
        INSERT INTO devolucion (
            id_venta,
            id_cliente,
            motivo,
            total_devuelto,
            estado
        )
        VALUES (
            :id_venta,
            :id_cliente,
            :motivo,
            :total_devuelto,
            'aprobada'
        )
        RETURNING id_devolucion
    ";

    $consultaDevolucion = $conexion->prepare($sqlDevolucion);
    $consultaDevolucion->execute([
        ":id_venta" => $id_venta,
        ":id_cliente" => $venta["id_cliente"],
        ":motivo" => $motivo,
        ":total_devuelto" => $subtotalDevuelto
    ]);

    $idDevolucion = $consultaDevolucion->fetch()["id_devolucion"];

    // Registrar detalle devolución
    // El trigger de PostgreSQL aumenta el stock automáticamente
    $sqlDetalleDevolucion = "
        INSERT INTO detalle_devolucion (
            id_devolucion,
            id_producto,
            talla,
            cantidad,
            precio_unitario,
            costo_unitario,
            subtotal_devuelto,
            subtotal_costo_devuelto
        )
        VALUES (
            :id_devolucion,
            :id_producto,
            :talla,
            :cantidad,
            :precio_unitario,
            :costo_unitario,
            :subtotal_devuelto,
            :subtotal_costo_devuelto
        )
    ";

    $consultaDetalle = $conexion->prepare($sqlDetalleDevolucion);
    $consultaDetalle->execute([
        ":id_devolucion" => $idDevolucion,
        ":id_producto" => $id_producto,
        ":talla" => $talla,
        ":cantidad" => $cantidad,
        ":precio_unitario" => $precioUnitario,
        ":costo_unitario" => $costoUnitario,
        ":subtotal_devuelto" => $subtotalDevuelto,
        ":subtotal_costo_devuelto" => $subtotalCostoDevuelto
    ]);

    if ($talla !== null && $talla !== "") {
        $sqlActualizarTalla = "
            UPDATE producto_talla
            SET cantidad = cantidad + :cantidad
            WHERE id_producto = :id_producto
            AND UPPER(talla) = UPPER(:talla)
        ";

        $consultaActualizarTalla = $conexion->prepare($sqlActualizarTalla);
        $consultaActualizarTalla->execute([
            ":cantidad" => $cantidad,
            ":id_producto" => $id_producto,
            ":talla" => $talla
        ]);
    }

    // Marcar venta como devuelta si se devolvió todo ese producto
    if ($cantidad == $cantidadDisponible) {
        $sqlActualizarVenta = "
            UPDATE venta
            SET estado = 'devuelta'
            WHERE id_venta = :id_venta
        ";

        $consultaActualizarVenta = $conexion->prepare($sqlActualizarVenta);
        $consultaActualizarVenta->execute([
            ":id_venta" => $id_venta
        ]);
    }

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Devolución registrada correctamente",
        "total_devuelto" => $subtotalDevuelto
    ]);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>
