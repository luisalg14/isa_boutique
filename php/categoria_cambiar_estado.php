<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["error" => true, "mensaje" => "Método no permitido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idCategoria = intval($_POST["id_categoria"] ?? 0);
    $estado = trim($_POST["estado"] ?? "");

    if ($idCategoria <= 0 || !in_array($estado, ["1", "0"], true)) {
        echo json_encode(["error" => true, "mensaje" => "Datos invalidos"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($estado === "0") {
        $consultaProductos = $conexion->prepare("
            SELECT COUNT(*) AS total
            FROM producto
            WHERE id_categoria = :id_categoria
            AND estado <> 'inactivo'
        ");
        $consultaProductos->execute([":id_categoria" => $idCategoria]);

        if (intval($consultaProductos->fetch()["total"]) > 0) {
            echo json_encode([
                "error" => true,
                "mensaje" => "No se puede desactivar una categoría con productos activos"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $consulta = $conexion->prepare("
        UPDATE categoria
        SET estado = :estado
        WHERE id_categoria = :id_categoria
    ");
    $consulta->execute([
        ":estado" => $estado === "1",
        ":id_categoria" => $idCategoria
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => $estado === "1" ? "Categoria activada correctamente" : "Categoria desactivada correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error categoria_cambiar_estado: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo cambiar el estado de la categoría"
    ], JSON_UNESCAPED_UNICODE);
}

?>
