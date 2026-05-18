<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ]);
        exit;
    }

    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $nombre = trim($_POST["nombre"] ?? "");
    $marca = trim($_POST["marca"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $cantidad = intval($_POST["cantidad"] ?? 0);
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
                throw new Exception("Formato de tallas invalido. Usa: S:2, M:3, L:1");
            }

            $talla = strtoupper(trim($datos[0]));
            $cantidadTalla = intval(trim($datos[1]));

            if ($talla === "" || $cantidadTalla < 0) {
                throw new Exception("Las tallas deben tener nombre y cantidad valida");
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
        ]);
        exit;
    }

    $imagenRuta = "";

    if (isset($_FILES["imagen"]) && $_FILES["imagen"]["error"] === UPLOAD_ERR_OK) {
        $carpetaDestino = "../img/productos/";

        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0777, true);
        }

        $nombreOriginal = basename($_FILES["imagen"]["name"]);
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
        $nombreArchivo = uniqid("producto_") . "." . strtolower($extension);

        $rutaServidor = $carpetaDestino . $nombreArchivo;
        $rutaGuardarBD = "img/productos/" . $nombreArchivo;

        if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $rutaServidor)) {
            $imagenRuta = $rutaGuardarBD;
        }
    }

    if ($imagenRuta === "") {
        $imagenRuta = "img/logo.png.png";
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
        ]);
        exit;
    }

    $idCategoria = $categoriaEncontrada["id_categoria"];

    $sqlInsertar = "
        INSERT INTO producto (
            codigo,
            nombre,
            marca,
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
            :id_categoria,
            :precio,
            :costo_unitario,
            :cantidad,
            :imagen
        )
        RETURNING id_producto
    ";

    $conexion->beginTransaction();

    $consultaInsertar = $conexion->prepare($sqlInsertar);
    $consultaInsertar->execute([
        ":codigo" => $codigo,
        ":nombre" => $nombre,
        ":marca" => $marca,
        ":id_categoria" => $idCategoria,
        ":precio" => $precio,
        ":costo_unitario" => $costo,
        ":cantidad" => $cantidad,
        ":imagen" => $imagenRuta
    ]);

    $idProducto = $consultaInsertar->fetch()["id_producto"];

    if (count($tallas) === 0 && $cantidad > 0) {
        $tallas = ["UNICA" => $cantidad];
    }

    $sqlTalla = "
        INSERT INTO producto_talla (id_producto, talla, cantidad)
        VALUES (:id_producto, :talla, :cantidad)
    ";

    $consultaTalla = $conexion->prepare($sqlTalla);

    foreach ($tallas as $talla => $cantidadTalla) {
        $consultaTalla->execute([
            ":id_producto" => $idProducto,
            ":talla" => $talla,
            ":cantidad" => $cantidadTalla
        ]);
    }

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Producto guardado correctamente"
    ]);

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
    ]);
}

?>
