<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "finanzas_resumen_util.php";
header("Content-Type: application/json; charset=UTF-8");
try {
    exigir_roles(["admin"]);
    $resumen = leer_finanzas_consistentes($conexion, function () use ($conexion) {
        return construir_resumen_finanzas($conexion);
    });
    echo json_encode($resumen, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error finanzas_resumen: " . $e->getMessage());
    responder_json_error("No se pudo cargar el resumen financiero", 500);
}
