<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ]);
        exit;
    }

    $id_producto = intval($_POST["id_producto"] ?? 0);
    $cliente = trim($_POST["cliente"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $cantidad = intval($_POST["cantidad"] ?? 0);
    $medio_pago = trim($_POST["medio_pago"] ?? "");

    $mediosPermitidos = [
        "efectivo",
        "transferencia",
        "tarjeta_debito",
        "tarjeta_credito"
    ];

    if (
        $id_producto <= 0 ||
        $cliente === "" ||
        $telefono === "" ||
        $cantidad <= 0 ||
        !in_array($medio_pago, $mediosPermitidos)
    ) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o inválidos"
        ]);
        exit;
    }

    $conexion->beginTransaction();

    // Buscar producto
    $sqlProducto = "
        SELECT id_producto, nombre, precio, cantidad, estado
        FROM producto
        WHERE id_producto = :id_producto
        LIMIT 1
    ";

    $consultaProducto = $conexion->prepare($sqlProducto);
    $consultaProducto->execute([
        ":id_producto" => $id_producto
    ]);

    $producto = $consultaProducto->fetch();

    if (!$producto) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Producto no encontrado"
        ]);
        exit;
    }

    if ($producto["estado"] === "inactivo") {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Este producto está inactivo y no se puede vender"
        ]);
        exit;
    }

    if (intval($producto["cantidad"]) < $cantidad) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Stock insuficiente. Solo hay " . $producto["cantidad"] . " unidades disponibles"
        ]);
        exit;
    }

    $precioUnitario = floatval($producto["precio"]);
    $subtotal = $precioUnitario * $cantidad;

    // Buscar cliente por teléfono
    $sqlCliente = "
        SELECT id_cliente
        FROM cliente
        WHERE telefono = :telefono
        LIMIT 1
    ";

    $consultaCliente = $conexion->prepare($sqlCliente);
    $consultaCliente->execute([
        ":telefono" => $telefono
    ]);

    $clienteEncontrado = $consultaCliente->fetch();

    if ($clienteEncontrado) {
        $idCliente = $clienteEncontrado["id_cliente"];
    } else {
        $sqlInsertCliente = "
            INSERT INTO cliente (nombre, telefono)
            VALUES (:nombre, :telefono)
            RETURNING id_cliente
        ";

        $consultaInsertCliente = $conexion->prepare($sqlInsertCliente);
        $consultaInsertCliente->execute([
            ":nombre" => $cliente,
            ":telefono" => $telefono
        ]);

        $idCliente = $consultaInsertCliente->fetch()["id_cliente"];
    }

    $usuarioActual = usuario_actual();
    $idUsuario = $usuarioActual ? intval($usuarioActual["id_usuario"]) : 1;

    // Registrar venta
    $sqlVenta = "
        INSERT INTO venta (
            id_cliente,
            id_usuario,
            medio_pago,
            total,
            estado
        )
        VALUES (
            :id_cliente,
            :id_usuario,
            :medio_pago,
            :total,
            'pagada'
        )
        RETURNING id_venta
    ";

    $consultaVenta = $conexion->prepare($sqlVenta);
    $consultaVenta->execute([
        ":id_cliente" => $idCliente,
        ":id_usuario" => $idUsuario,
        ":medio_pago" => $medio_pago,
        ":total" => $subtotal
    ]);

    $idVenta = $consultaVenta->fetch()["id_venta"];

    // Registrar detalle de venta
    // El trigger de PostgreSQL descuenta el stock automáticamente
    $sqlDetalle = "
        INSERT INTO detalle_venta (
            id_venta,
            id_producto,
            cantidad,
            precio_unitario,
            subtotal
        )
        VALUES (
            :id_venta,
            :id_producto,
            :cantidad,
            :precio_unitario,
            :subtotal
        )
    ";

    $consultaDetalle = $conexion->prepare($sqlDetalle);
    $consultaDetalle->execute([
        ":id_venta" => $idVenta,
        ":id_producto" => $id_producto,
        ":cantidad" => $cantidad,
        ":precio_unitario" => $precioUnitario,
        ":subtotal" => $subtotal
    ]);

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Venta registrada correctamente",
        "id_venta" => $idVenta,
        "total" => $subtotal
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
