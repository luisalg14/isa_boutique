<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "factura_util.php";

header("Content-Type: application/json; charset=UTF-8");

function columna_existe_venta(PDO $conexion, $tabla, $columna) {
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
    $correo = trim($_POST["correo"] ?? "");
    $tipoDocumento = strtoupper(trim($_POST["tipo_documento"] ?? "CC"));
    $numeroDocumento = trim($_POST["numero_documento"] ?? "");
    $cantidad = intval($_POST["cantidad"] ?? 0);
    $descuentoTipo = trim($_POST["descuento_tipo"] ?? "valor");
    $descuentoValor = floatval($_POST["descuento_valor"] ?? 0);
    $medio_pago = trim($_POST["medio_pago"] ?? "");
    $idProductoColor = intval($_POST["id_producto_color"] ?? 0);
    $color = trim($_POST["color"] ?? "");
    $talla = strtoupper(trim($_POST["talla"] ?? ""));
    $codigoBarras = strtoupper(trim($_POST["codigo_barras"] ?? ""));
    $usuarioActual = usuario_actual();
    exigir_csrf_si_autenticado();
    exigir_origen_mismo_sitio();
    $canal_venta = trim($_POST["canal_venta"] ?? ($usuarioActual ? "tienda_fisica" : "pagina_web"));
    $tipo_entrega = trim($_POST["tipo_entrega"] ?? ($usuarioActual ? "recoger_tienda" : "envio_local"));
    $esPedidoWeb = $canal_venta === "pagina_web";
    $estadoVenta = ($usuarioActual && !$esPedidoWeb) ? "pagada" : "pendiente";

    $mediosPermitidos = [
        "efectivo",
        "transferencia",
        "tarjeta_debito",
        "tarjeta_credito"
    ];

    $canalesPermitidos = [
        "tienda_fisica",
        "pagina_web",
        "whatsapp",
        "instagram"
    ];

    $entregasPermitidas = [
        "recoger_tienda",
        "envio_local",
        "envio_nacional"
    ];
    $descuentosPermitidos = ["valor", "porcentaje"];
    $tiposDocumentoPermitidos = ["CC", "CE", "NIT", "PASAPORTE", "OTRO"];

    if ($codigoBarras !== "") {
        $campoCodigoBarras = columna_existe_venta($conexion, "producto_color_talla", "codigo_barras")
            ? "pct.codigo_barras"
            : "(p.codigo || '-V' || LPAD(pct.id_producto_color_talla::TEXT, 4, '0'))";

        $consultaVarianteCodigo = $conexion->prepare("
            SELECT
                p.id_producto,
                pc.id_producto_color,
                pc.nombre_color,
                pct.talla
            FROM producto_color_talla pct
            INNER JOIN producto_color pc
                ON pct.id_producto_color = pc.id_producto_color
            INNER JOIN producto p
                ON pc.id_producto = p.id_producto
            WHERE UPPER($campoCodigoBarras) = :codigo_barras
            LIMIT 1
        ");
        $consultaVarianteCodigo->execute([
            ":codigo_barras" => $codigoBarras
        ]);
        $varianteCodigo = $consultaVarianteCodigo->fetch();

        if ($varianteCodigo) {
            $id_producto = intval($varianteCodigo["id_producto"]);
            $idProductoColor = intval($varianteCodigo["id_producto_color"]);
            $color = $varianteCodigo["nombre_color"];
            $talla = strtoupper($varianteCodigo["talla"]);
        }
    }

    if (
        $id_producto <= 0 ||
        $cliente === "" ||
        $telefono === "" ||
        ($correo !== "" && !filter_var($correo, FILTER_VALIDATE_EMAIL)) ||
        !in_array($tipoDocumento, $tiposDocumentoPermitidos, true) ||
        $cantidad <= 0 ||
        !in_array($descuentoTipo, $descuentosPermitidos, true) ||
        $descuentoValor < 0 ||
        !in_array($medio_pago, $mediosPermitidos) ||
        !in_array($canal_venta, $canalesPermitidos) ||
        !in_array($tipo_entrega, $entregasPermitidas)
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
        SELECT id_producto, nombre, precio, costo_unitario, cantidad, estado
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

    $sqlColores = "
        SELECT pc.id_producto_color, pc.nombre_color
        FROM producto_color pc
        WHERE pc.id_producto = :id_producto
        ORDER BY pc.orden, pc.nombre_color
    ";

    $consultaColores = $conexion->prepare($sqlColores);
    $consultaColores->execute([
        ":id_producto" => $id_producto
    ]);

    $coloresProducto = $consultaColores->fetchAll();
    $colorSeleccionado = null;

    if (count($coloresProducto) > 0) {
        if ($idProductoColor <= 0 && count($coloresProducto) === 1) {
            $idProductoColor = intval($coloresProducto[0]["id_producto_color"]);
        }

        foreach ($coloresProducto as $itemColor) {
            if (intval($itemColor["id_producto_color"]) === $idProductoColor) {
                $colorSeleccionado = $itemColor;
                break;
            }
        }

        if (!$colorSeleccionado) {
            $conexion->rollBack();
            echo json_encode([
                "error" => true,
                "mensaje" => "Selecciona un color disponible"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $color = $colorSeleccionado["nombre_color"];
    }

    $tallasProducto = [];

    if ($idProductoColor > 0) {
        $sqlTallasColor = "
            SELECT talla, cantidad
            FROM producto_color_talla
            WHERE id_producto_color = :id_producto_color
            ORDER BY talla
        ";

        $consultaTallasColor = $conexion->prepare($sqlTallasColor);
        $consultaTallasColor->execute([
            ":id_producto_color" => $idProductoColor
        ]);

        $tallasProducto = $consultaTallasColor->fetchAll();
    }

    if (count($tallasProducto) === 0) {
        $sqlTallas = "
        SELECT talla, cantidad
        FROM producto_talla
        WHERE id_producto = :id_producto
        ORDER BY talla
        ";

        $consultaTallas = $conexion->prepare($sqlTallas);
        $consultaTallas->execute([
            ":id_producto" => $id_producto
        ]);

        $tallasProducto = $consultaTallas->fetchAll();
    }

    if (count($tallasProducto) > 0) {
        if ($talla === "") {
            $conexion->rollBack();
            echo json_encode([
                "error" => true,
                "mensaje" => "Selecciona una talla disponible"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tallaEncontrada = null;

        foreach ($tallasProducto as $itemTalla) {
            if (strtoupper($itemTalla["talla"]) === $talla) {
                $tallaEncontrada = $itemTalla;
                break;
            }
        }

        if (!$tallaEncontrada || intval($tallaEncontrada["cantidad"]) < $cantidad) {
            $conexion->rollBack();
            echo json_encode([
                "error" => true,
                "mensaje" => "Stock insuficiente para la talla " . $talla
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
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
    $costoUnitario = floatval($producto["costo_unitario"]);
    $subtotalBruto = $precioUnitario * $cantidad;
    $descuento = $descuentoTipo === "porcentaje"
        ? $subtotalBruto * ($descuentoValor / 100)
        : $descuentoValor;
    $descuento = min(max($descuento, 0), $subtotalBruto);
    $subtotal = $subtotalBruto - $descuento;
    $tarifaIva = 19.00;
    $baseGravable = round($subtotal / (1 + ($tarifaIva / 100)), 2);
    $iva = round($subtotal - $baseGravable, 2);
    $subtotalCosto = $costoUnitario * $cantidad;

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
        $consultaActualizarCliente = $conexion->prepare("
            UPDATE cliente
            SET
                nombre = :nombre,
                correo = COALESCE(NULLIF(:correo, ''), correo),
                tipo_documento = :tipo_documento,
                numero_documento = COALESCE(NULLIF(:numero_documento, ''), numero_documento)
            WHERE id_cliente = :id_cliente
        ");
        $consultaActualizarCliente->execute([
            ":nombre" => $cliente,
            ":correo" => $correo,
            ":tipo_documento" => $tipoDocumento,
            ":numero_documento" => $numeroDocumento,
            ":id_cliente" => $idCliente
        ]);
    } else {
        $sqlInsertCliente = "
            INSERT INTO cliente (nombre, telefono, correo, tipo_documento, numero_documento)
            VALUES (:nombre, :telefono, NULLIF(:correo, ''), :tipo_documento, NULLIF(:numero_documento, ''))
            RETURNING id_cliente
        ";

        $consultaInsertCliente = $conexion->prepare($sqlInsertCliente);
        $consultaInsertCliente->execute([
            ":nombre" => $cliente,
            ":telefono" => $telefono,
            ":correo" => $correo,
            ":tipo_documento" => $tipoDocumento,
            ":numero_documento" => $numeroDocumento
        ]);

        $idCliente = $consultaInsertCliente->fetch()["id_cliente"];
    }

    $idUsuario = $usuarioActual ? intval($usuarioActual["id_usuario"]) : 1;

    // Registrar venta
    $sqlVenta = "
        INSERT INTO venta (
            id_cliente,
            id_usuario,
            medio_pago,
            canal_venta,
            tipo_entrega,
            subtotal_bruto,
            descuento,
            base_gravable,
            iva,
            tarifa_iva,
            precio_incluye_iva,
            total,
            estado
        )
        VALUES (
            :id_cliente,
            :id_usuario,
            :medio_pago,
            :canal_venta,
            :tipo_entrega,
            :subtotal_bruto,
            :descuento,
            :base_gravable,
            :iva,
            :tarifa_iva,
            TRUE,
            :total,
            :estado
        )
        RETURNING id_venta
    ";

    $consultaVenta = $conexion->prepare($sqlVenta);
    $consultaVenta->execute([
        ":id_cliente" => $idCliente,
        ":id_usuario" => $idUsuario,
        ":medio_pago" => $medio_pago,
        ":canal_venta" => $canal_venta,
        ":tipo_entrega" => $tipo_entrega,
        ":subtotal_bruto" => $subtotalBruto,
        ":descuento" => $descuento,
        ":base_gravable" => $baseGravable,
        ":iva" => $iva,
        ":tarifa_iva" => $tarifaIva,
        ":total" => $subtotal,
        ":estado" => $estadoVenta
    ]);

    $idVenta = $consultaVenta->fetch()["id_venta"];

    // Registrar detalle de venta
    // El trigger de PostgreSQL descuenta el stock automáticamente
    $sqlDetalle = "
        INSERT INTO detalle_venta (
            id_venta,
            id_producto,
            id_producto_color,
            color,
            talla,
            cantidad,
            precio_unitario,
            costo_unitario,
            descuento,
            base_gravable,
            iva,
            tarifa_iva,
            precio_incluye_iva,
            subtotal,
            subtotal_costo
        )
        VALUES (
            :id_venta,
            :id_producto,
            :id_producto_color,
            :color,
            :talla,
            :cantidad,
            :precio_unitario,
            :costo_unitario,
            :descuento,
            :base_gravable,
            :iva,
            :tarifa_iva,
            TRUE,
            :subtotal,
            :subtotal_costo
        )
    ";

    $consultaDetalle = $conexion->prepare($sqlDetalle);
    $consultaDetalle->execute([
        ":id_venta" => $idVenta,
        ":id_producto" => $id_producto,
        ":id_producto_color" => $idProductoColor > 0 ? $idProductoColor : null,
        ":color" => $color !== "" ? $color : null,
        ":talla" => $talla !== "" ? $talla : null,
        ":cantidad" => $cantidad,
        ":precio_unitario" => $precioUnitario,
        ":costo_unitario" => $costoUnitario,
        ":descuento" => $descuento,
        ":base_gravable" => $baseGravable,
        ":iva" => $iva,
        ":tarifa_iva" => $tarifaIva,
        ":subtotal" => $subtotal,
        ":subtotal_costo" => $subtotalCosto
    ]);

    if (count($tallasProducto) > 0) {
        if ($idProductoColor > 0) {
            $sqlActualizarTallaColor = "
                UPDATE producto_color_talla
                SET cantidad = cantidad - :cantidad
                WHERE id_producto_color = :id_producto_color
                AND UPPER(talla) = :talla
            ";

            $consultaActualizarTallaColor = $conexion->prepare($sqlActualizarTallaColor);
            $consultaActualizarTallaColor->execute([
                ":cantidad" => $cantidad,
                ":id_producto_color" => $idProductoColor,
                ":talla" => $talla
            ]);
        }

        $sqlActualizarTalla = "
            UPDATE producto_talla
            SET cantidad = cantidad - :cantidad
            WHERE id_producto = :id_producto
            AND UPPER(talla) = :talla
        ";

        $consultaActualizarTalla = $conexion->prepare($sqlActualizarTalla);
        $consultaActualizarTalla->execute([
            ":cantidad" => $cantidad,
            ":id_producto" => $id_producto,
            ":talla" => $talla
        ]);
    }

    $factura = null;

    if ($estadoVenta === "pagada") {
        $factura = asegurar_factura_venta($conexion, $idVenta);
    }

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => $estadoVenta === "pagada" ? "Venta registrada correctamente" : "Pedido registrado correctamente. Queda pendiente de confirmación.",
        "id_venta" => $idVenta,
        "total" => $subtotal,
        "estado" => $estadoVenta,
        "tipo_entrega" => $tipo_entrega,
        "factura" => $factura
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error venta_registrar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo registrar la venta"
    ]);
}

?>
