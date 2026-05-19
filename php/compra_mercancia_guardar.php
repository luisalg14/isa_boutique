<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    $usuario = exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idProveedor = intval($_POST["id_proveedor"] ?? 0);
    $costoEnvio = floatval($_POST["costo_envio"] ?? 0);
    $fecha = trim($_POST["fecha"] ?? date("Y-m-d"));
    $detalle = trim($_POST["detalle"] ?? "");
    $itemsTexto = trim($_POST["items"] ?? "");

    if ($costoEnvio < 0 || $fecha === "") {
        echo json_encode(["error" => true, "mensaje" => "Datos incompletos o invalidos"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $items = [];

    if ($itemsTexto !== "") {
        $items = json_decode($itemsTexto, true);
    }

    if (!is_array($items) || count($items) === 0) {
        $idProducto = intval($_POST["id_producto"] ?? 0);
        $cantidad = intval($_POST["cantidad"] ?? 0);
        $costoUnitario = floatval($_POST["costo_unitario"] ?? 0);

        if ($idProducto > 0) {
            $items[] = [
                "id_producto" => $idProducto,
                "talla" => strtoupper(trim($_POST["talla"] ?? "")),
                "cantidad" => $cantidad,
                "costo_unitario" => $costoUnitario
            ];
        }
    }

    if (count($items) === 0) {
        echo json_encode(["error" => true, "mensaje" => "Agrega al menos un producto a la compra"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $totalProductos = 0;
    $totalUnidades = 0;
    $itemsValidados = [];

    foreach ($items as $item) {
        $idProducto = intval($item["id_producto"] ?? 0);
        $talla = strtoupper(trim($item["talla"] ?? ""));
        $cantidad = intval($item["cantidad"] ?? 0);
        $costoUnitario = floatval($item["costo_unitario"] ?? 0);

        if ($idProducto <= 0 || $cantidad <= 0 || $costoUnitario < 0) {
            echo json_encode(["error" => true, "mensaje" => "Hay productos con datos invalidos en la compra"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $subtotal = $cantidad * $costoUnitario;
        $totalProductos += $subtotal;
        $totalUnidades += $cantidad;

        $itemsValidados[] = [
            "id_producto" => $idProducto,
            "talla" => $talla,
            "cantidad" => $cantidad,
            "costo_unitario" => $costoUnitario,
            "subtotal" => $subtotal
        ];
    }

    if ($totalUnidades <= 0) {
        echo json_encode(["error" => true, "mensaje" => "La compra debe tener unidades validas"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conexion->beginTransaction();

    if ($idProveedor > 0) {
        $consultaProveedor = $conexion->prepare("SELECT id_proveedor FROM proveedor WHERE id_proveedor = :id_proveedor LIMIT 1");
        $consultaProveedor->execute([":id_proveedor" => $idProveedor]);

        if (!$consultaProveedor->fetch()) {
            $conexion->rollBack();
            echo json_encode(["error" => true, "mensaje" => "Proveedor no encontrado"], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        $idProveedor = null;
    }

    $totalCompra = $totalProductos + $costoEnvio;

    $sqlCompra = "
        INSERT INTO compra_mercancia (
            id_proveedor,
            id_usuario,
            fecha,
            costo_envio,
            total_productos,
            total_compra,
            detalle
        )
        VALUES (
            :id_proveedor,
            :id_usuario,
            :fecha,
            :costo_envio,
            :total_productos,
            :total_compra,
            :detalle
        )
        RETURNING id_compra
    ";

    $consultaCompra = $conexion->prepare($sqlCompra);
    $consultaCompra->execute([
        ":id_proveedor" => $idProveedor,
        ":id_usuario" => $usuario["id_usuario"],
        ":fecha" => $fecha,
        ":costo_envio" => $costoEnvio,
        ":total_productos" => $totalProductos,
        ":total_compra" => $totalCompra,
        ":detalle" => $detalle
    ]);

    $idCompra = $consultaCompra->fetch()["id_compra"];

    $consultaProducto = $conexion->prepare("
        SELECT id_producto, cantidad, costo_unitario
        FROM producto
        WHERE id_producto = :id_producto
        LIMIT 1
    ");

    $consultaDetalle = $conexion->prepare("
        INSERT INTO detalle_compra_mercancia (
            id_compra,
            id_producto,
            talla,
            cantidad,
            costo_unitario,
            subtotal
        )
        VALUES (
            :id_compra,
            :id_producto,
            :talla,
            :cantidad,
            :costo_unitario,
            :subtotal
        )
    ");

    $consultaActualizarProducto = $conexion->prepare("
        UPDATE producto
        SET
            cantidad = cantidad + :cantidad,
            costo_unitario = :costo_unitario
        WHERE id_producto = :id_producto
    ");

    $consultaTalla = $conexion->prepare("
        INSERT INTO producto_talla (id_producto, talla, cantidad)
        VALUES (:id_producto, :talla, :cantidad)
        ON CONFLICT (id_producto, talla)
        DO UPDATE SET cantidad = producto_talla.cantidad + EXCLUDED.cantidad
    ");

    $consultaMovimiento = $conexion->prepare("
        INSERT INTO movimiento_inventario (
            id_producto,
            id_usuario,
            tipo,
            cantidad,
            detalle
        )
        VALUES (
            :id_producto,
            :id_usuario,
            'ingreso_stock',
            :cantidad,
            :detalle
        )
    ");

    $envioPorUnidad = $costoEnvio / $totalUnidades;

    foreach ($itemsValidados as $item) {
        $consultaProducto->execute([":id_producto" => $item["id_producto"]]);
        $producto = $consultaProducto->fetch();

        if (!$producto) {
            $conexion->rollBack();
            echo json_encode(["error" => true, "mensaje" => "Producto no encontrado en la compra"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $consultaDetalle->execute([
            ":id_compra" => $idCompra,
            ":id_producto" => $item["id_producto"],
            ":talla" => $item["talla"] !== "" ? $item["talla"] : null,
            ":cantidad" => $item["cantidad"],
            ":costo_unitario" => $item["costo_unitario"],
            ":subtotal" => $item["subtotal"]
        ]);

        $cantidadActual = intval($producto["cantidad"]);
        $costoActual = floatval($producto["costo_unitario"]);
        $nuevaCantidad = $cantidadActual + $item["cantidad"];
        $costoUnitarioInventario = $item["costo_unitario"] + $envioPorUnidad;
        $nuevoCosto = $nuevaCantidad > 0
            ? (($costoActual * $cantidadActual) + ($costoUnitarioInventario * $item["cantidad"])) / $nuevaCantidad
            : $costoUnitarioInventario;

        $consultaActualizarProducto->execute([
            ":cantidad" => $item["cantidad"],
            ":costo_unitario" => $nuevoCosto,
            ":id_producto" => $item["id_producto"]
        ]);

        if ($item["talla"] !== "") {
            $consultaTalla->execute([
                ":id_producto" => $item["id_producto"],
                ":talla" => $item["talla"],
                ":cantidad" => $item["cantidad"]
            ]);
        }

        $detalleMovimiento = "Compra de mercancia";

        if ($item["talla"] !== "") {
            $detalleMovimiento .= " talla " . $item["talla"];
        }

        if ($costoEnvio > 0) {
            $detalleMovimiento .= ". Envio repartido por unidad: " . number_format($envioPorUnidad, 2, ".", "");
        }

        if ($detalle !== "") {
            $detalleMovimiento .= ". " . $detalle;
        }

        $consultaMovimiento->execute([
            ":id_producto" => $item["id_producto"],
            ":id_usuario" => $usuario["id_usuario"],
            ":cantidad" => $item["cantidad"],
            ":detalle" => $detalleMovimiento
        ]);
    }

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Compra de mercancia registrada correctamente",
        "id_compra" => $idCompra,
        "total_productos" => $totalProductos,
        "total_compra" => $totalCompra,
        "cantidad_items" => count($itemsValidados)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode(["error" => true, "mensaje" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
