<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "auditoria.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id_producto = intval($_POST["id_producto"] ?? 0);
    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $nombre = trim($_POST["nombre"] ?? "");
    $marca = trim($_POST["marca"] ?? "");
    $color = trim($_POST["color"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $cantidad = intval($_POST["cantidad"] ?? -1);
    $precio = floatval($_POST["precio"] ?? 0);
    $costo = floatval($_POST["costo"] ?? 0);
    $tallasTexto = trim($_POST["tallas"] ?? "");

    function normalizar_tallas($texto) {
        $tallas = [];

        if ($texto === "") {
            return $tallas;
        }

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

    $tallas = normalizar_tallas($tallasTexto);

    if (count($tallas) > 0) {
        $cantidad = array_sum($tallas);
    }

    if (
        $id_producto <= 0 ||
        $codigo === "" ||
        $nombre === "" ||
        $marca === "" ||
        $categoria === "" ||
        $cantidad < 0 ||
        $precio <= 0 ||
        $costo < 0
    ) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o inválidos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlCodigo = "
        SELECT id_producto
        FROM producto
        WHERE codigo = :codigo
        AND id_producto <> :id_producto
        LIMIT 1
    ";

    $consultaCodigo = $conexion->prepare($sqlCodigo);
    $consultaCodigo->execute([
        ":codigo" => $codigo,
        ":id_producto" => $id_producto
    ]);

    if ($consultaCodigo->fetch()) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Ya existe otro producto con ese código"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlCategoria = "
        SELECT id_categoria
        FROM categoria
        WHERE nombre = :categoria
        LIMIT 1
    ";

    $consultaCategoria = $conexion->prepare($sqlCategoria);
    $consultaCategoria->execute([
        ":categoria" => $categoria
    ]);

    $categoriaEncontrada = $consultaCategoria->fetch();

    if (!$categoriaEncontrada) {
        echo json_encode([
            "error" => true,
            "mensaje" => "La categoría no existe en la base de datos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $id_categoria = $categoriaEncontrada["id_categoria"];

    $consultaColor = $conexion->query("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'producto'
            AND column_name = 'color'
        ) AS existe
    ");
    $productoTieneColor = filter_var($consultaColor->fetch()["existe"], FILTER_VALIDATE_BOOLEAN);
    $actualizarColor = $productoTieneColor ? ", color = :color" : "";

    $sqlActualizar = "
        UPDATE producto
        SET
            codigo = :codigo,
            nombre = :nombre,
            marca = :marca,
            id_categoria = :id_categoria,
            cantidad = :cantidad,
            costo_unitario = :costo_unitario,
            precio = :precio
            $actualizarColor
        WHERE id_producto = :id_producto
    ";

    $conexion->beginTransaction();

    $parametrosActualizar = [
        ":codigo" => $codigo,
        ":nombre" => $nombre,
        ":marca" => $marca,
        ":id_categoria" => $id_categoria,
        ":cantidad" => $cantidad,
        ":costo_unitario" => $costo,
        ":precio" => $precio,
        ":id_producto" => $id_producto
    ];

    if ($productoTieneColor) {
        $parametrosActualizar[":color"] = $color;
    }

    $consultaActualizar = $conexion->prepare($sqlActualizar);
    $consultaActualizar->execute($parametrosActualizar);

    if (count($tallas) > 0) {
        $conexion->prepare("DELETE FROM producto_talla WHERE id_producto = :id_producto")
            ->execute([":id_producto" => $id_producto]);

        $consultaTalla = $conexion->prepare("
            INSERT INTO producto_talla (id_producto, talla, cantidad)
            VALUES (:id_producto, :talla, :cantidad)
        ");

        foreach ($tallas as $talla => $cantidadTalla) {
            $consultaTalla->execute([
                ":id_producto" => $id_producto,
                ":talla" => $talla,
                ":cantidad" => $cantidadTalla
            ]);
        }
    }

    $conexion->prepare("DELETE FROM producto_color WHERE id_producto = :id_producto")
        ->execute([":id_producto" => $id_producto]);

    $nombreColor = $color !== "" ? $color : "Único";
    $consultaColorProducto = $conexion->prepare("
        INSERT INTO producto_color (id_producto, nombre_color, codigo_hex, orden)
        VALUES (:id_producto, :nombre_color, NULL, 0)
        RETURNING id_producto_color
    ");
    $consultaColorProducto->execute([
        ":id_producto" => $id_producto,
        ":nombre_color" => $nombreColor
    ]);
    $idProductoColor = $consultaColorProducto->fetch()["id_producto_color"];

    if (count($tallas) > 0) {
        $consultaTallaColor = $conexion->prepare("
            INSERT INTO producto_color_talla (id_producto_color, talla, cantidad)
            VALUES (:id_producto_color, :talla, :cantidad)
        ");

        foreach ($tallas as $talla => $cantidadTalla) {
            $consultaTallaColor->execute([
                ":id_producto_color" => $idProductoColor,
                ":talla" => $talla,
                ":cantidad" => $cantidadTalla
            ]);
        }
    }

    registrar_auditoria($conexion, "producto_actualizado", "producto", $id_producto, [
        "codigo" => $codigo,
        "nombre" => $nombre,
        "cantidad" => $cantidad,
        "precio" => $precio
    ]);

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Producto actualizado correctamente"
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
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
