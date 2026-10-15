<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "factura_lectura.php";
require_once "finanzas_util.php";
header("Content-Type: application/json; charset=UTF-8");
try {
    exigir_roles(["admin", "vendedor"]);
    $idVenta = filter_var($_GET["id_venta"] ?? null, FILTER_VALIDATE_INT);
    if ($idVenta <= 0) responder_json_error("Venta no valida", 400);
    $datos = leer_finanzas_consistentes($conexion, function () use ($conexion, $idVenta) {
        return leer_factura_venta($conexion, $idVenta);
    });
    if (!$datos) responder_json_error("Esta venta no tiene una factura emitida", 404);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error factura_obtener: " . $e->getMessage());
    responder_json_error("No se pudo cargar la factura", 500);
}
