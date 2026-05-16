<?php

require_once "conexion.php";

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
    $codigo = strtoupper(trim($_POST["codigo"] ?? ""));
    $nombre = trim($_POST["nombre"] ?? "");
    $marca = trim($_POST["marca"] ?? "");
    $categoria = trim($_POST["categoria"] ?? "");
    $cantidad = intval($_POST["cantidad"] ?? -1);
    $precio = floatval($_POST["precio"] ?? 0);

    if (
        $id_producto <= 0 ||
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
        ]);
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
        ]);
        exit;
    }

    $id_categoria = $categoriaEncontrada["id_categoria"];

    $sqlActualizar = "
        UPDATE producto
        SET
            codigo = :codigo,
            nombre = :nombre,
            marca = :marca,
            id_categoria = :id_categoria,
            cantidad = :cantidad,
            precio = :precio
        WHERE id_producto = :id_producto
    ";

    $consultaActualizar = $conexion->prepare($sqlActualizar);
    $consultaActualizar->execute([
        ":codigo" => $codigo,
        ":nombre" => $nombre,
        ":marca" => $marca,
        ":id_categoria" => $id_categoria,
        ":cantidad" => $cantidad,
        ":precio" => $precio,
        ":id_producto" => $id_producto
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Producto actualizado correctamente"
    ]);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>