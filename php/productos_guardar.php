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

    if (
        $codigo === "" ||
        $nombre === "" ||
        $marca === "" ||
        $categoria === "" ||
        $cantidad < 0 ||
        $precio <= 0
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
            cantidad,
            imagen
        )
        VALUES (
            :codigo,
            :nombre,
            :marca,
            :id_categoria,
            :precio,
            :cantidad,
            :imagen
        )
    ";

    $consultaInsertar = $conexion->prepare($sqlInsertar);
    $consultaInsertar->execute([
        ":codigo" => $codigo,
        ":nombre" => $nombre,
        ":marca" => $marca,
        ":id_categoria" => $idCategoria,
        ":precio" => $precio,
        ":cantidad" => $cantidad,
        ":imagen" => $imagenRuta
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Producto guardado correctamente"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>
