<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "auditoria.php";

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

    $id_producto = intval($_POST["id_producto"] ?? 0);

    if ($id_producto <= 0) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Producto inválido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conexion->beginTransaction();

    $sqlProducto = "
        SELECT id_producto, nombre
        FROM producto
        WHERE id_producto = :id_producto
        FOR UPDATE
    ";

    $consultaProducto = $conexion->prepare($sqlProducto);
    $consultaProducto->execute([
        ":id_producto" => $id_producto
    ]);

    $producto = $consultaProducto->fetch();

    if (!$producto) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "Producto no encontrado"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlRelaciones = "
        SELECT
            (SELECT COUNT(*) FROM detalle_venta WHERE id_producto = :id_producto) AS ventas,
            (SELECT COUNT(*) FROM detalle_devolucion WHERE id_producto = :id_producto) AS devoluciones
    ";

    $consultaRelaciones = $conexion->prepare($sqlRelaciones);
    $consultaRelaciones->execute([
        ":id_producto" => $id_producto
    ]);

    $relaciones = $consultaRelaciones->fetch();

    if (intval($relaciones["ventas"]) > 0 || intval($relaciones["devoluciones"]) > 0) {
        $conexion->rollBack();
        echo json_encode([
            "error" => true,
            "mensaje" => "No se puede eliminar porque el producto tiene ventas o devoluciones. Puedes desactivarlo para ocultarlo de la tienda."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlLimpiarCambios = "
        DELETE FROM cambio_precio
        WHERE id_producto = :id_producto
    ";

    $consultaLimpiarCambios = $conexion->prepare($sqlLimpiarCambios);
    $consultaLimpiarCambios->execute([
        ":id_producto" => $id_producto
    ]);

    $sqlLimpiarMovimientos = "
        DELETE FROM movimiento_inventario
        WHERE id_producto = :id_producto
    ";

    $consultaLimpiarMovimientos = $conexion->prepare($sqlLimpiarMovimientos);
    $consultaLimpiarMovimientos->execute([
        ":id_producto" => $id_producto
    ]);

    $sqlEliminar = "
        DELETE FROM producto
        WHERE id_producto = :id_producto
    ";

    $consultaEliminar = $conexion->prepare($sqlEliminar);
    $consultaEliminar->execute([
        ":id_producto" => $id_producto
    ]);

    registrar_auditoria($conexion, "producto_eliminado", "producto", $id_producto, [
        "nombre" => $producto["nombre"],
        "ventas_relacionadas" => intval($relaciones["ventas"]),
        "devoluciones_relacionadas" => intval($relaciones["devoluciones"])
    ]);

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => "Producto eliminado correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    error_log("Error producto_eliminar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo eliminar el producto"
    ], JSON_UNESCAPED_UNICODE);
}

?>
