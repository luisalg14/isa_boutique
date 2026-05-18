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

    $id_producto = intval($_POST["id_producto"] ?? 0);
    $forzar = ($_POST["forzar"] ?? "") === "1";

    if ($id_producto <= 0) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Producto inválido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlProducto = "
        SELECT id_producto, nombre
        FROM producto
        WHERE id_producto = :id_producto
        LIMIT 1
    ";

    $consultaProducto = $conexion->prepare($sqlProducto);
    $consultaProducto->execute([
        ":id_producto" => $id_producto
    ]);

    $producto = $consultaProducto->fetch();

    if (!$producto) {
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

    if (!$forzar && (intval($relaciones["ventas"]) > 0 || intval($relaciones["devoluciones"]) > 0)) {
        echo json_encode([
            "error" => true,
            "mensaje" => "No se puede eliminar porque el producto tiene ventas o devoluciones. Puedes desactivarlo para ocultarlo de la tienda."
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conexion->beginTransaction();

    if ($forzar) {
        $sqlVentasProducto = "
            SELECT DISTINCT id_venta
            FROM detalle_venta
            WHERE id_producto = :id_producto
        ";

        $consultaVentasProducto = $conexion->prepare($sqlVentasProducto);
        $consultaVentasProducto->execute([
            ":id_producto" => $id_producto
        ]);

        $idsVentas = $consultaVentasProducto->fetchAll(PDO::FETCH_COLUMN);

        $sqlEliminarDevolucionesProducto = "
            DELETE FROM devolucion
            WHERE id_devolucion IN (
                SELECT d.id_devolucion
                FROM devolucion d
                INNER JOIN detalle_devolucion dd
                    ON d.id_devolucion = dd.id_devolucion
                WHERE dd.id_producto = :id_producto
            )
        ";

        $consultaEliminarDevolucionesProducto = $conexion->prepare($sqlEliminarDevolucionesProducto);
        $consultaEliminarDevolucionesProducto->execute([
            ":id_producto" => $id_producto
        ]);

        if (!empty($idsVentas)) {
            $marcadores = [];
            $parametros = [];

            foreach ($idsVentas as $indice => $idVenta) {
                $clave = ":id_venta_" . $indice;
                $marcadores[] = $clave;
                $parametros[$clave] = intval($idVenta);
            }

            $sqlEliminarDevolucionesVentas = "
                DELETE FROM devolucion
                WHERE id_venta IN (" . implode(",", $marcadores) . ")
            ";

            $consultaEliminarDevolucionesVentas = $conexion->prepare($sqlEliminarDevolucionesVentas);
            $consultaEliminarDevolucionesVentas->execute($parametros);

            $sqlEliminarVentas = "
                DELETE FROM venta
                WHERE id_venta IN (" . implode(",", $marcadores) . ")
            ";

            $consultaEliminarVentas = $conexion->prepare($sqlEliminarVentas);
            $consultaEliminarVentas->execute($parametros);
        }
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

    $conexion->commit();

    echo json_encode([
        "error" => false,
        "mensaje" => $forzar
            ? "Producto de prueba eliminado junto con sus ventas/devoluciones relacionadas"
            : "Producto eliminado correctamente"
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
