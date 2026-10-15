<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "finanzas_util.php";
header("Content-Type: application/json; charset=UTF-8");
try {
    exigir_roles(["admin"]);
    $movimientos = leer_finanzas_consistentes($conexion, function () use ($conexion) {
        return construir_movimientos_financieros($conexion);
    });
    echo json_encode($movimientos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error finanzas_listar: " . $e->getMessage());
    responder_json_error("No se pudieron cargar los movimientos financieros", 500);
}
