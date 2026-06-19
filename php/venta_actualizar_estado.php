<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "factura_util.php";
require_once "auditoria.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin", "vendedor"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idVenta = intval($_POST["id_venta"] ?? 0);
    $estadoNuevo = trim($_POST["estado"] ?? "");
    $estadosPermitidos = ["pagada", "cancelada"];

    if ($idVenta <= 0 || !in_array($estadoNuevo, $estadosPermitidos, true)) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o invalidos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conexion->beginTransaction();

    $consultaVenta = $conexion->prepare("
        SELECT id_venta, estado
        FROM venta
        WHERE id_venta = :id_venta
        FOR UPDATE
    ");
    $consultaVenta->execute([
        ":id_venta" => $idVenta
    ]);

    $venta = $consultaVenta->fetch();

    if (!$venta) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Venta no encontrada"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($venta["estado"] !== "pendiente") {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Solo se pueden cerrar pedidos pendientes"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($estadoNuevo === "cancelada") {
        $consultaDetalles = $conexion->prepare("
            SELECT id_producto, id_producto_color, talla, cantidad
            FROM detalle_venta
            WHERE id_venta = :id_venta
        ");
        $consultaDetalles->execute([
            ":id_venta" => $idVenta
        ]);

        $detalles = $consultaDetalles->fetchAll();

        $actualizarProducto = $conexion->prepare("
            UPDATE producto
            SET cantidad = cantidad + :cantidad
            WHERE id_producto = :id_producto
        ");

        $actualizarTalla = $conexion->prepare("
            UPDATE producto_talla
            SET cantidad = cantidad + :cantidad
            WHERE id_producto = :id_producto
            AND UPPER(talla) = UPPER(:talla)
        ");

        $actualizarTallaColor = $conexion->prepare("
            UPDATE producto_color_talla
            SET cantidad = cantidad + :cantidad
            WHERE id_producto_color = :id_producto_color
            AND UPPER(talla) = UPPER(:talla)
        ");

        foreach ($detalles as $detalle) {
            $actualizarProducto->execute([
                ":cantidad" => $detalle["cantidad"],
                ":id_producto" => $detalle["id_producto"]
            ]);

            if ($detalle["talla"] !== null && $detalle["talla"] !== "") {
                if (!empty($detalle["id_producto_color"])) {
                    $actualizarTallaColor->execute([
                        ":cantidad" => $detalle["cantidad"],
                        ":id_producto_color" => $detalle["id_producto_color"],
                        ":talla" => $detalle["talla"]
                    ]);
                }

                $actualizarTalla->execute([
                    ":cantidad" => $detalle["cantidad"],
                    ":id_producto" => $detalle["id_producto"],
                    ":talla" => $detalle["talla"]
                ]);
            }
        }
    }

    $consultaActualizar = $conexion->prepare("
        UPDATE venta
        SET estado = :estado
        WHERE id_venta = :id_venta
    ");
    $consultaActualizar->execute([
        ":estado" => $estadoNuevo,
        ":id_venta" => $idVenta
    ]);

    $factura = null;
    if ($estadoNuevo === "pagada") {
        $factura = asegurar_factura_venta($conexion, $idVenta);
    }

    registrar_auditoria($conexion, "venta_estado_actualizado", "venta", $idVenta, [
        "estado_anterior" => $venta["estado"],
        "estado_nuevo" => $estadoNuevo
    ]);

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => $estadoNuevo === "pagada"
            ? "Pedido confirmado como pagado"
            : "Pedido cancelado y stock restaurado",
        "factura" => $factura
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error venta_actualizar_estado: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo actualizar el pedido"
    ], JSON_UNESCAPED_UNICODE);
}

?>
