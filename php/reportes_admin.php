<?php

require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    // Total ventas brutas
    $sqlTotalVentas = "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
    ";
    $totalVentas = $conexion->query($sqlTotalVentas)->fetch()["total"];

    // Total devoluciones
    $sqlTotalDevoluciones = "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
    ";
    $totalDevoluciones = $conexion->query($sqlTotalDevoluciones)->fetch()["total"];

    // Ventas de hoy
    $sqlVentasHoy = "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND fecha::date = CURRENT_DATE
    ";
    $ventasHoy = $conexion->query($sqlVentasHoy)->fetch()["total"];

    // Devoluciones de hoy
    $sqlDevolucionesHoy = "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND fecha::date = CURRENT_DATE
    ";
    $devolucionesHoy = $conexion->query($sqlDevolucionesHoy)->fetch()["total"];

    // Ventas del mes
    $sqlVentasMes = "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ";
    $ventasMes = $conexion->query($sqlVentasMes)->fetch()["total"];

    // Devoluciones del mes
    $sqlDevolucionesMes = "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ";
    $devolucionesMes = $conexion->query($sqlDevolucionesMes)->fetch()["total"];

    // Producto más vendido
    $sqlProductoTop = "
        SELECT 
            p.nombre AS producto,
            COALESCE(SUM(dv.cantidad), 0) AS cantidad
        FROM detalle_venta dv
        INNER JOIN producto p
            ON dv.id_producto = p.id_producto
        INNER JOIN venta v
            ON dv.id_venta = v.id_venta
        WHERE v.estado IN ('pagada', 'devuelta')
        GROUP BY p.nombre
        ORDER BY cantidad DESC
        LIMIT 1
    ";
    $productoTop = $conexion->query($sqlProductoTop)->fetch();

    // Menor rotación
    $sqlMenorRotacion = "
        SELECT 
            p.nombre AS producto,
            COALESCE(SUM(dv.cantidad), 0) AS cantidad
        FROM producto p
        LEFT JOIN detalle_venta dv
            ON p.id_producto = dv.id_producto
        GROUP BY p.nombre
        ORDER BY cantidad ASC
        LIMIT 1
    ";
    $menorRotacion = $conexion->query($sqlMenorRotacion)->fetch();

    echo json_encode([
        "error" => false,
        "total_neto" => floatval($totalVentas) - floatval($totalDevoluciones),
        "ventas_hoy" => floatval($ventasHoy),
        "devoluciones_hoy" => floatval($devolucionesHoy),
        "neto_hoy" => floatval($ventasHoy) - floatval($devolucionesHoy),
        "ventas_mes" => floatval($ventasMes),
        "devoluciones_mes" => floatval($devolucionesMes),
        "neto_mes" => floatval($ventasMes) - floatval($devolucionesMes),
        "producto_top" => $productoTop ? $productoTop["producto"] . " (" . $productoTop["cantidad"] . ")" : "Sin ventas",
        "menor_rotacion" => $menorRotacion ? $menorRotacion["producto"] . " (" . $menorRotacion["cantidad"] . ")" : "Sin productos"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}

?>