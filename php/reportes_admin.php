<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "reportes_util.php";
header("Content-Type: application/json; charset=UTF-8");
try {
    $usuario = exigir_roles(["admin", "vendedor"]);
    $reportes = leer_finanzas_consistentes($conexion, function () use ($conexion) {
        return construir_reportes($conexion, $_GET["fecha_inicio"] ?? "", $_GET["fecha_fin"] ?? "");
    });
    if ($usuario["rol"] === "vendedor") $reportes = reporte_para_vendedor($reportes);
    echo json_encode($reportes, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error reportes_admin: " . $e->getMessage());
    responder_json_error("No se pudieron cargar los reportes", 500);
}
