<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function normalizar_categoria($texto) {
    $texto = strtolower(trim($texto));
    $texto = preg_replace('/\s+/', '-', $texto);
    $texto = preg_replace('/[^a-z0-9_-]/', '', $texto);
    return $texto;
}

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idCategoria = intval($_POST["id_categoria"] ?? 0);
    $nombre = normalizar_categoria($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

    if ($idCategoria <= 0 || $nombre === "") {
        echo json_encode(["error" => true, "mensaje" => "Datos incompletos"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        UPDATE categoria
        SET
            nombre = :nombre,
            descripcion = :descripcion
        WHERE id_categoria = :id_categoria
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":nombre" => $nombre,
        ":descripcion" => $descripcion,
        ":id_categoria" => $idCategoria
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Categoria actualizada correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($e->getCode() === "23505") {
        echo json_encode(["error" => true, "mensaje" => "Ya existe otra categoría con ese nombre"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    error_log("Error categoria_editar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo actualizar la categoría"
    ], JSON_UNESCAPED_UNICODE);
}

?>
