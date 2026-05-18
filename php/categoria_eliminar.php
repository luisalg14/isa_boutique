<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Metodo no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idCategoria = intval($_POST["id_categoria"] ?? 0);

    if ($idCategoria <= 0) {
        echo json_encode(["error" => true, "mensaje" => "Categoria invalida"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $consultaProductos = $conexion->prepare("
        SELECT COUNT(*) AS total
        FROM producto
        WHERE id_categoria = :id_categoria
    ");
    $consultaProductos->execute([":id_categoria" => $idCategoria]);

    if (intval($consultaProductos->fetch()["total"]) > 0) {
        echo json_encode([
            "error" => true,
            "mensaje" => "No se puede eliminar una categoria que tiene productos. Puedes desactivarla cuando no tenga productos activos."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $consulta = $conexion->prepare("
        DELETE FROM categoria
        WHERE id_categoria = :id_categoria
    ");
    $consulta->execute([":id_categoria" => $idCategoria]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Categoria eliminada correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error categoria_eliminar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo eliminar la categoria"
    ], JSON_UNESCAPED_UNICODE);
}

?>
