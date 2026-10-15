<?php
require_once "conexion.php";
require_once "auth_guard.php";
require_once "catalogo_util.php";
require_once "finanzas_util.php";
header("Content-Type: application/json; charset=UTF-8");
try {
    $productos = leer_finanzas_consistentes($conexion, function () use ($conexion) {
        return listar_productos_visibles($conexion, usuario_actual());
    });
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error productos_listar: " . $e->getMessage());
    responder_json_error("No se pudieron cargar los productos", 500);
}
