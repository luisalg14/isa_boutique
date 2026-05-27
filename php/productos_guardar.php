<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function normalizar_tallas_producto($texto) {
    $tallas = [];

    if ($texto === "") return $tallas;

    $partes = preg_split("/[,;\n]+/", $texto);

    foreach ($partes as $parte) {
        $parte = trim($parte);
        if ($parte === "") continue;

        $datos = explode(":", $parte);

        if (count($datos) !== 2) {
            throw new Exception("Formato de tallas inválido. Usa: S:2, M:3, L:1");
        }

        $talla = strtoupper(trim($datos[0]));
        $cantidadTalla = intval(trim($datos[1]));

        if ($talla === "" || $cantidadTalla < 0) {
            throw new Exception("Las tallas deben tener nombre y cantidad válida");
        }

        $tallas[$talla] = ($tallas[$talla] ?? 0) + $cantidadTalla;
    }

    return $tallas;
}

function normalizar_variantes_producto($texto, $color, $tallas) {
    $variantes = [];

    if ($texto !== "") {
        $datos = json_decode($texto, true);

        if (!is_array($datos)) {
            throw new Exception("Las variantes de color no tienen un formato válido");
        }

        foreach ($datos as $variante) {
            $nombreColor = trim($variante["color"] ?? "");
            $hex = trim($variante["hex"] ?? "");
            $tallasVariante = $variante["tallas"] ?? [];

            if ($nombreColor === "" || !is_array($tallasVariante)) continue;

            foreach ($tallasVariante as $talla => $cantidad) {
                $talla = strtoupper(trim($talla));
                $cantidad = intval($cantidad);

                if ($talla === "" || $cantidad < 0) continue;

                if (!isset($variantes[$nombreColor])) {
                    $variantes[$nombreColor] = [
                        "color" => $nombreColor,
                        "hex" => $hex,
                        "tallas" => []
                    ];
                }

                $variantes[$nombreColor]["tallas"][$talla] = ($variantes[$nombreColor]["tallas"][$talla] ?? 0) + $cantidad;
            }
        }
    }

    if (count($variantes) === 0 && count($tallas) > 0) {
        $nombreColor = $color !== "" ? $color : "Único";
        $variantes[$nombreColor] = [
            "color" => $nombreColor,
            "hex" => "",
            "tallas" => $tallas
        ];
    }

    return array_values($variantes);
}

function generar_codigo_producto(PDO $conexion) {
    $consulta = $conexion->query("
        SELECT codigo
        FROM producto
        WHERE codigo ~ '^[0-9]{2}-[A-Z]$'
    ");

    $mayorIndice = 0;

    foreach ($consulta as $fila) {
        if (!preg_match("/^([0-9]{2})-([A-Z])$/", $fila["codigo"], $partes)) {
            continue;
        }

        $numero = intval($partes[1]);
        $letra = ord($partes[2]) - ord("A");

        if ($numero < 1 || $numero > 99 || $letra < 0 || $letra > 25) {
            continue;
        }

        $mayorIndice = max($mayorIndice, ($letra * 99) + $numero);
    }

    $siguienteIndice = $mayorIndice + 1;

    if ($siguienteIndice > 99 * 26) {
        throw new Exception("Se agotó la secuencia de códigos de producto.");
    }

    $letraIndice = intdiv($siguienteIndice - 1, 99);
    $numero = (($siguienteIndice - 1) % 99) + 1;
    $letra = chr(ord("A") + $letraIndice);

    return str_pad((string) $numero, 2, "0", STR_PAD_LEFT) . "-" . $letra;
}

function columna_existe(PDO $conexion, $tabla, $columna) {
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

function generar_codigo_barras_variante($codigoProducto, $idProductoColorTalla) {
    return strtoupper(trim($codigoProducto)) . "-V" . str_pad((string) $idProductoColorTalla, 4, "0", STR_PAD_LEFT);
}

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $nombre = trim($_POST["nombre"] ?? "");
    $marca = trim($_POST["marca"] ?? "");
    $color = trim($_POST["color"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $precio = floatval($_POST["precio"] ?? 0);
    $costo = floatval($_POST["costo"] ?? 0);
    $tallasTexto = trim($_POST["tallas"] ?? "");
    $variantesTexto = trim($_POST["variantes"] ?? "");
    $detallesCompraTexto = trim($_POST["detalles_compra_mercancia"] ?? "[]");
    $detallesCompra = json_decode($detallesCompraTexto, true);

    if (!is_array($detallesCompra)) {
        $detallesCompra = [];
    }

    $detallesCompra = array_values(array_unique(array_filter(array_map("intval", $detallesCompra), function($idDetalle) {
        return $idDetalle > 0;
    })));

    $tallas = normalizar_tallas_producto($tallasTexto);
    $variantes = normalizar_variantes_producto($variantesTexto, $color, $tallas);

    $cantidad = 0;
    foreach ($variantes as $variante) {
        $cantidad += array_sum($variante["tallas"]);
    }

    if (
        $nombre === "" ||
        $marca === "" ||
        $categoria === "" ||
        $cantidad <= 0 ||
        $precio <= 0 ||
        $costo < 0
    ) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o inválidos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($codigo !== "" && !preg_match("/^[0-9]{2}-[A-Z]$/", $codigo)) {
        echo json_encode([
            "error" => true,
            "mensaje" => "El código debe tener el formato 01-A."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $imagenesGuardadas = [];

    if (isset($_FILES["imagenes"]) && is_array($_FILES["imagenes"]["name"])) {
        $carpetaDestino = "../img/productos/";

        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0777, true);
        }

        foreach ($_FILES["imagenes"]["name"] as $indice => $nombreOriginal) {
            if ($_FILES["imagenes"]["error"][$indice] !== UPLOAD_ERR_OK) continue;

            $extension = pathinfo(basename($nombreOriginal), PATHINFO_EXTENSION);
            $nombreArchivo = uniqid("producto_") . "." . strtolower($extension);
            $rutaServidor = $carpetaDestino . $nombreArchivo;
            $rutaGuardarBD = "img/productos/" . $nombreArchivo;

            if (move_uploaded_file($_FILES["imagenes"]["tmp_name"][$indice], $rutaServidor)) {
                $imagenesGuardadas[] = $rutaGuardarBD;
            }
        }
    } elseif (isset($_FILES["imagen"]) && $_FILES["imagen"]["error"] === UPLOAD_ERR_OK) {
        $carpetaDestino = "../img/productos/";

        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0777, true);
        }

        $extension = pathinfo(basename($_FILES["imagen"]["name"]), PATHINFO_EXTENSION);
        $nombreArchivo = uniqid("producto_") . "." . strtolower($extension);
        $rutaServidor = $carpetaDestino . $nombreArchivo;
        $rutaGuardarBD = "img/productos/" . $nombreArchivo;

        if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $rutaServidor)) {
            $imagenesGuardadas[] = $rutaGuardarBD;
        }
    }

    if (count($imagenesGuardadas) === 0) {
        $imagenesGuardadas[] = "img/logo.png.png";
    }

    $consultaCategoria = $conexion->prepare("
        SELECT id_categoria
        FROM categoria
        WHERE nombre = :categoria
        LIMIT 1
    ");
    $consultaCategoria->execute([":categoria" => $categoria]);
    $categoriaEncontrada = $consultaCategoria->fetch();

    if (!$categoriaEncontrada) {
        echo json_encode([
            "error" => true,
            "mensaje" => "La categoría no existe en la base de datos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idCategoria = $categoriaEncontrada["id_categoria"];
    $colorPrincipal = count($variantes) > 0 ? $variantes[0]["color"] : ($color !== "" ? $color : "Único");

    $conexion->beginTransaction();
    $conexion->query("SELECT pg_advisory_xact_lock(20260521)");

    if ($codigo === "") {
        $codigo = generar_codigo_producto($conexion);
    }

    $consultaCodigo = $conexion->prepare("
        SELECT id_producto
        FROM producto
        WHERE codigo = :codigo
        LIMIT 1
    ");
    $consultaCodigo->execute([":codigo" => $codigo]);

    if ($consultaCodigo->fetch()) {
        throw new Exception("Ya existe un producto con ese código. Actualiza el código sugerido e intenta nuevamente.");
    }

    $consultaInsertar = $conexion->prepare("
        INSERT INTO producto (
            codigo,
            nombre,
            marca,
            color,
            id_categoria,
            precio,
            costo_unitario,
            cantidad,
            imagen
        )
        VALUES (
            :codigo,
            :nombre,
            :marca,
            :color,
            :id_categoria,
            :precio,
            :costo_unitario,
            :cantidad,
            :imagen
        )
        RETURNING id_producto
    ");
    $consultaInsertar->execute([
        ":codigo" => $codigo,
        ":nombre" => $nombre,
        ":marca" => $marca,
        ":color" => $colorPrincipal,
        ":id_categoria" => $idCategoria,
        ":precio" => $precio,
        ":costo_unitario" => $costo,
        ":cantidad" => $cantidad,
        ":imagen" => $imagenesGuardadas[0]
    ]);

    $idProducto = $consultaInsertar->fetch()["id_producto"];

    if (count($detallesCompra) > 0) {
        $marcadoresDetalle = implode(",", array_fill(0, count($detallesCompra), "?"));
        $consultaMarcarMercancia = $conexion->prepare("
            UPDATE detalle_compra_mercancia
            SET estado_registro = 'registrado',
                id_producto = ?
            WHERE id_detalle_compra IN ($marcadoresDetalle)
            AND tipo_item = 'mercancia_nueva'
            AND estado_registro = 'pendiente'
        ");
        $consultaMarcarMercancia->execute(array_merge([$idProducto], $detallesCompra));

        $consultaActualizarCompra = $conexion->prepare("
            UPDATE compra_mercancia c
            SET estado = CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM detalle_compra_mercancia d
                    WHERE d.id_compra = c.id_compra
                    AND d.tipo_item = 'mercancia_nueva'
                    AND d.estado_registro = 'pendiente'
                ) THEN 'parcial'
                ELSE 'registrada'
            END
            WHERE c.id_compra IN (
                SELECT DISTINCT id_compra
                FROM detalle_compra_mercancia
                WHERE id_detalle_compra IN ($marcadoresDetalle)
            )
        ");
        $consultaActualizarCompra->execute($detallesCompra);
    }

    $consultaImagen = $conexion->prepare("
        INSERT INTO producto_imagen (id_producto, ruta, es_principal, orden)
        VALUES (:id_producto, :ruta, :es_principal, :orden)
    ");

    foreach ($imagenesGuardadas as $indice => $rutaImagen) {
        $consultaImagen->execute([
            ":id_producto" => $idProducto,
            ":ruta" => $rutaImagen,
            ":es_principal" => $indice === 0 ? "true" : "false",
            ":orden" => $indice
        ]);
    }

    $consultaColor = $conexion->prepare("
        INSERT INTO producto_color (id_producto, nombre_color, codigo_hex, orden)
        VALUES (:id_producto, :nombre_color, :codigo_hex, :orden)
        RETURNING id_producto_color
    ");
    $consultaColorTalla = $conexion->prepare("
        INSERT INTO producto_color_talla (id_producto_color, talla, cantidad)
        VALUES (:id_producto_color, :talla, :cantidad)
        RETURNING id_producto_color_talla
    ");
    $tallaTieneCodigoBarras = columna_existe($conexion, "producto_color_talla", "codigo_barras");
    $consultaActualizarCodigoVariante = $tallaTieneCodigoBarras
        ? $conexion->prepare("
            UPDATE producto_color_talla
            SET codigo_barras = :codigo_barras
            WHERE id_producto_color_talla = :id_producto_color_talla
        ")
        : null;
    $consultaTalla = $conexion->prepare("
        INSERT INTO producto_talla (id_producto, talla, cantidad)
        VALUES (:id_producto, :talla, :cantidad)
        ON CONFLICT (id_producto, talla)
        DO UPDATE SET cantidad = producto_talla.cantidad + EXCLUDED.cantidad
    ");

    foreach ($variantes as $indice => $variante) {
        $consultaColor->execute([
            ":id_producto" => $idProducto,
            ":nombre_color" => $variante["color"],
            ":codigo_hex" => $variante["hex"],
            ":orden" => $indice
        ]);

        $idProductoColor = $consultaColor->fetch()["id_producto_color"];

        foreach ($variante["tallas"] as $talla => $cantidadTalla) {
            $consultaColorTalla->execute([
                ":id_producto_color" => $idProductoColor,
                ":talla" => $talla,
                ":cantidad" => $cantidadTalla
            ]);
            $idProductoColorTalla = $consultaColorTalla->fetch()["id_producto_color_talla"];

            if ($consultaActualizarCodigoVariante) {
                $consultaActualizarCodigoVariante->execute([
                    ":codigo_barras" => generar_codigo_barras_variante($codigo, $idProductoColorTalla),
                    ":id_producto_color_talla" => $idProductoColorTalla
                ]);
            }

            $consultaTalla->execute([
                ":id_producto" => $idProducto,
                ":talla" => $talla,
                ":cantidad" => $cantidadTalla
            ]);
        }
    }

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Producto guardado correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if (isset($conexion) && $conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
