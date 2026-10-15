<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "devolucion_util.php";
header("Content-Type: application/json; charset=UTF-8");
try {
    exigir_roles(["admin", "vendedor"]);
    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") responder_json_error("Metodo no permitido", 405);
    $idVenta = filter_var($_POST["id_venta"] ?? null, FILTER_VALIDATE_INT);
    $idProducto = filter_var($_POST["id_producto"] ?? null, FILTER_VALIDATE_INT);
    $idDetalle = filter_var($_POST["id_detalle_venta"] ?? 0, FILTER_VALIDATE_INT);
    $cantidad = filter_var($_POST["cantidad"] ?? null, FILTER_VALIDATE_INT);
    $motivo = trim($_POST["motivo"] ?? "");
    if ($idVenta <= 0 || $idProducto <= 0 || $cantidad <= 0 || $idDetalle === false || $idDetalle < 0 || $motivo === "") {
        responder_json_error("Datos incompletos o invalidos", 400);
    }
    $conexion->beginTransaction();
    $importe = guardar_devolucion($conexion, $idVenta, $idProducto, $idDetalle, $cantidad, $motivo);
    $conexion->commit();
    echo json_encode(["error"=>false,"mensaje"=>"Devolucion registrada correctamente","total_devuelto"=>$importe], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conexion) && $conexion->inTransaction()) $conexion->rollBack();
    if ($e instanceof DomainException) responder_json_error($e->getMessage(), 409);
    error_log("Error devolucion_registrar: " . $e->getMessage());
    responder_json_error("No se pudo registrar la devolucion", 500);
}
