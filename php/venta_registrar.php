<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "factura_util.php";
require_once "cliente_util.php";
require_once "venta_util.php";

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
    if ($usuarioActual) {
        $usuarioActual = exigir_roles(["admin", "vendedor"]);
    } elseif ($descuentoValor != 0) {
        responder_json_error("Los descuentos requieren un usuario autorizado", 403);
    }
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

    $detallePreparado = preparar_detalle_venta($conexion, $id_producto, $idProductoColor, $talla, $cantidad);
    $producto = $detallePreparado['producto'];
    $idProductoColor = $detallePreparado['id_producto_color'];
    $color = $detallePreparado['color'] ?? '';
    $talla = $detallePreparado['talla'] ?? '';

    $precioUnitario = floatval($producto["precio"]);
    $costoUnitario = floatval($producto["costo_unitario"]);
    $importes = calcular_importes_venta($precioUnitario,$costoUnitario,$cantidad,$descuentoTipo,$descuentoValor);
    $subtotalBruto = $importes['subtotal_bruto'];
    $descuento = $importes['descuento'];
    $subtotal = $importes['subtotal'];
    $tarifaIva = $importes['tarifa_iva'];
    $baseGravable = $importes['base_gravable'];
    $iva = $importes['iva'];
    $subtotalCosto = $importes['subtotal_costo'];

    $idCliente = cliente_para_venta($conexion, [
        'nombre'=>$cliente, 'telefono'=>$telefono, 'correo'=>$correo,
        'tipo_documento'=>$tipoDocumento, 'numero_documento'=>$numeroDocumento
    ]);

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

    descontar_tallas_venta($conexion, $detallePreparado);

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

} catch (Throwable $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error venta_registrar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => $e instanceof DomainException ? $e->getMessage() : "No se pudo registrar la venta"
    ]);
}

?>
