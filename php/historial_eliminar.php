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
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $confirmacion = trim($_POST["confirmacion"] ?? "");

    if ($confirmacion !== "ELIMINAR HISTORIAL") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Confirmación inválida"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conexion->beginTransaction();

    $conexion->exec("DELETE FROM devolucion");
    $conexion->exec("DELETE FROM venta");

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Historial de ventas y devoluciones eliminado correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error historial_eliminar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo eliminar el historial"
    ], JSON_UNESCAPED_UNICODE);
}

?>
