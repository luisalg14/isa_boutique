<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function columna_existe_compra(PDO $conexion, $tabla, $columna) {
    $consulta = $conexion->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = :tabla
            AND column_name = :columna
        ) AS existe
    ");
    $consulta->execute([
        ":tabla" => $tabla,
        ":columna" => $columna
    ]);

    return filter_var($consulta->fetch()["existe"], FILTER_VALIDATE_BOOLEAN);
}

try {
    $usuario = exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idProveedor = intval($_POST["id_proveedor"] ?? 0);
    $proveedorReferencia = trim($_POST["proveedor_referencia"] ?? "");
    $costoEnvio = floatval($_POST["costo_envio"] ?? 0);
    $fecha = trim($_POST["fecha"] ?? date("Y-m-d"));
    $detalle = trim($_POST["detalle"] ?? "");
    $itemsTexto = trim($_POST["items"] ?? "");

    if ($costoEnvio < 0 || $fecha === "") {
        echo json_encode(["error" => true, "mensaje" => "Datos incompletos o inválidos"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $items = $itemsTexto !== "" ? json_decode($itemsTexto, true) : [];

    if (!is_array($items) || count($items) === 0) {
        echo json_encode(["error" => true, "mensaje" => "Agrega al menos una mercancía a la compra"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $totalProductos = 0;
    $totalUnidades = 0;
    $itemsValidados = [];
    $tiposCompra = [];

    foreach ($items as $item) {
        $tipoItem = trim($item["tipo_item"] ?? "reposicion");
        $idProducto = intval($item["id_producto"] ?? 0);
        $categoria = trim($item["categoria"] ?? "");
        $descripcion = trim($item["descripcion"] ?? "");
        $color = trim($item["color"] ?? "");
        $talla = strtoupper(trim($item["talla"] ?? ""));
        $cantidad = intval($item["cantidad"] ?? 0);
        $costoUnitario = floatval($item["costo_unitario"] ?? 0);

        if (!in_array($tipoItem, ["mercancia_nueva", "reposicion"], true)) {
            echo json_encode(["error" => true, "mensaje" => "Tipo de mercancía inválido"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($tipoItem === "reposicion" && $idProducto <= 0) {
            echo json_encode(["error" => true, "mensaje" => "Selecciona el producto existente para la reposición"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($tipoItem === "mercancia_nueva" && ($categoria === "" || $descripcion === "")) {
            echo json_encode(["error" => true, "mensaje" => "Completa categoría y descripción de la mercancía nueva"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($tipoItem === "mercancia_nueva" && $color === "") {
            $color = "Unico";
        }

        if ($cantidad <= 0 || $costoUnitario < 0) {
            echo json_encode(["error" => true, "mensaje" => "Hay mercancía con cantidad o costo inválido"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $subtotal = $cantidad * $costoUnitario;
        $totalProductos += $subtotal;
        $totalUnidades += $cantidad;
        $tiposCompra[$tipoItem] = true;

        $itemsValidados[] = [
            "tipo_item" => $tipoItem,
            "id_producto" => $tipoItem === "reposicion" ? $idProducto : null,
            "categoria" => $tipoItem === "mercancia_nueva" ? $categoria : null,
            "descripcion" => $tipoItem === "mercancia_nueva" ? $descripcion : null,
            "color" => $tipoItem === "mercancia_nueva" ? $color : null,
            "talla" => $talla,
            "cantidad" => $cantidad,
            "costo_unitario" => $costoUnitario,
            "subtotal" => $subtotal
        ];
    }

    if ($totalUnidades <= 0) {
        echo json_encode(["error" => true, "mensaje" => "La compra debe tener unidades válidas"], JSON_UNESCAPED_UNICODE);
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
        if ($proveedorReferencia === "") {
            $proveedorReferencia = "NN/Otro";
        }
    }

    $detalleTieneFlujo = columna_existe_compra($conexion, "detalle_compra_mercancia", "tipo_item")
        && columna_existe_compra($conexion, "detalle_compra_mercancia", "categoria")
        && columna_existe_compra($conexion, "detalle_compra_mercancia", "descripcion")
        && columna_existe_compra($conexion, "detalle_compra_mercancia", "color")
        && columna_existe_compra($conexion, "detalle_compra_mercancia", "estado_registro");
    $compraTieneFlujo = columna_existe_compra($conexion, "compra_mercancia", "tipo_compra")
        && columna_existe_compra($conexion, "compra_mercancia", "estado")
        && columna_existe_compra($conexion, "compra_mercancia", "proveedor_referencia");

    if (!$detalleTieneFlujo || !$compraTieneFlujo) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Falta ejecutar la migración database/migracion_compras_mercancia_flujo.sql en PostgreSQL"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tipoCompra = count($tiposCompra) > 1 ? "mixta" : array_key_first($tiposCompra);
    $estadoCompra = isset($tiposCompra["mercancia_nueva"])
        ? (isset($tiposCompra["reposicion"]) ? "parcial" : "pendiente_clasificar")
        : "registrada";
    $totalCompra = $totalProductos + $costoEnvio;

    $consultaCompra = $conexion->prepare("
        INSERT INTO compra_mercancia (
            id_proveedor,
            id_usuario,
            fecha,
            tipo_compra,
            estado,
            proveedor_referencia,
            costo_envio,
            total_productos,
            total_compra,
            detalle
        )
        VALUES (
            :id_proveedor,
            :id_usuario,
            :fecha,
            :tipo_compra,
            :estado,
            :proveedor_referencia,
            :costo_envio,
            :total_productos,
            :total_compra,
            :detalle
        )
        RETURNING id_compra
    ");
    $consultaCompra->execute([
        ":id_proveedor" => $idProveedor,
        ":id_usuario" => $usuario["id_usuario"],
        ":fecha" => $fecha,
        ":tipo_compra" => $tipoCompra,
        ":estado" => $estadoCompra,
        ":proveedor_referencia" => $proveedorReferencia,
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
            tipo_item,
            categoria,
            descripcion,
            color,
            estado_registro,
            talla,
            cantidad,
            costo_unitario,
            subtotal
        )
        VALUES (
            :id_compra,
            :id_producto,
            :tipo_item,
            :categoria,
            :descripcion,
            :color,
            :estado_registro,
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

    $unidadesReposicion = array_reduce($itemsValidados, function($total, $item) {
        return $total + ($item["tipo_item"] === "reposicion" ? $item["cantidad"] : 0);
    }, 0);
    $envioPorUnidadReposicion = $unidadesReposicion > 0 ? $costoEnvio / $unidadesReposicion : 0;

    foreach ($itemsValidados as $item) {
        $consultaDetalle->execute([
            ":id_compra" => $idCompra,
            ":id_producto" => $item["id_producto"],
            ":tipo_item" => $item["tipo_item"],
            ":categoria" => $item["categoria"],
            ":descripcion" => $item["descripcion"],
            ":color" => $item["color"],
            ":estado_registro" => $item["tipo_item"] === "mercancia_nueva" ? "pendiente" : "registrado",
            ":talla" => $item["talla"] !== "" ? $item["talla"] : null,
            ":cantidad" => $item["cantidad"],
            ":costo_unitario" => $item["costo_unitario"],
            ":subtotal" => $item["subtotal"]
        ]);

        if ($item["tipo_item"] !== "reposicion") {
            continue;
        }

        $consultaProducto->execute([":id_producto" => $item["id_producto"]]);
        $producto = $consultaProducto->fetch();

        if (!$producto) {
            $conexion->rollBack();
            echo json_encode(["error" => true, "mensaje" => "Producto no encontrado en la reposición"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $cantidadActual = intval($producto["cantidad"]);
        $costoActual = floatval($producto["costo_unitario"]);
        $nuevaCantidad = $cantidadActual + $item["cantidad"];
        $costoUnitarioInventario = $item["costo_unitario"] + $envioPorUnidadReposicion;
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

        $detalleMovimiento = "Reposición por compra de mercancía";

        if ($item["talla"] !== "") {
            $detalleMovimiento .= " talla " . $item["talla"];
        }

        if ($costoEnvio > 0) {
            $detalleMovimiento .= ". Envío repartido por unidad: " . number_format($envioPorUnidadReposicion, 2, ".", "");
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
        "mensaje" => $estadoCompra === "pendiente_clasificar"
            ? "Compra registrada como mercancía nueva pendiente de clasificar"
            : "Compra de mercancía registrada correctamente",
        "id_compra" => $idCompra,
        "total_productos" => $totalProductos,
        "total_compra" => $totalCompra,
        "estado" => $estadoCompra,
        "cantidad_items" => count($itemsValidados)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode(["error" => true, "mensaje" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
