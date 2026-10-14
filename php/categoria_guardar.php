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

    $nombre = normalizar_categoria($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");

    if ($nombre === "") {
        echo json_encode(["error" => true, "mensaje" => "Ingresa un nombre de categoría válido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        INSERT INTO categoria (nombre, descripcion, estado)
        VALUES (:nombre, :descripcion, TRUE)
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":nombre" => $nombre,
        ":descripcion" => $descripcion
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Categoria guardada correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($e->getCode() === "23505") {
        echo json_encode(["error" => true, "mensaje" => "Ya existe una categoría con ese nombre"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    error_log("Error categoria_guardar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo guardar la categoría"
    ], JSON_UNESCAPED_UNICODE);
}

?>
