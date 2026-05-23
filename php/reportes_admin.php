<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function fecha_parametro($valor, $respaldo) {
    $valor = trim($valor ?? "");

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        return $valor;
    }

    return $respaldo;
}

function escalar_reporte($conexion, $sql, $params = []) {
    $consulta = $conexion->prepare($sql);
    $consulta->execute($params);
    return floatval($consulta->fetch()["total"] ?? 0);
}

function porcentaje_reporte($valor, $base) {
    $base = floatval($base);

    if ($base == 0) {
        return 0;
    }

    return round((floatval($valor) / $base) * 100, 2);
}

try {
    exigir_roles(["admin", "vendedor"]);

    $hoy = date("Y-m-d");
    $inicioMes = date("Y-m-01");
    $fechaInicio = fecha_parametro($_GET["fecha_inicio"] ?? "", $inicioMes);
    $fechaFin = fecha_parametro($_GET["fecha_fin"] ?? "", $hoy);

    if ($fechaInicio > $fechaFin) {
        $temporal = $fechaInicio;
        $fechaInicio = $fechaFin;
        $fechaFin = $temporal;
    }

    $paramsRango = [
        ":fecha_inicio" => $fechaInicio,
        ":fecha_fin" => $fechaFin
    ];

    $totalVentas = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
    ");

    $totalDevoluciones = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
    ");

    $ventasHoy = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND fecha::date = (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date
    ");

    $devolucionesHoy = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND fecha::date = (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date
    ");

    $ventasMes = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ");

    $devolucionesMes = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ");

    $ventasRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND fecha::date BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $devolucionesRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND fecha::date BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $costoVendidoRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(dv.subtotal_costo), 0) AS total
        FROM detalle_venta dv
        INNER JOIN venta v
            ON dv.id_venta = v.id_venta
        WHERE v.estado IN ('pagada', 'devuelta')
        AND v.fecha::date BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $costoDevueltoRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(dd.subtotal_costo_devuelto), 0) AS total
        FROM detalle_devolucion dd
        INNER JOIN devolucion d
            ON dd.id_devolucion = d.id_devolucion
        WHERE d.estado = 'aprobada'
        AND d.fecha::date BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $gastosRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $nominaRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
        WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $inversionesRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
        WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $comprasMercanciaRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
        WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
    ", $paramsRango);

    $consultaProductoTop = $conexion->prepare("
        SELECT
            p.codigo,
            p.nombre AS producto,
            COALESCE(SUM(dv.cantidad), 0) AS cantidad,
            COALESCE(SUM(dv.subtotal), 0) AS ventas,
            COALESCE(SUM(dv.subtotal_costo), 0) AS costo,
            COALESCE(SUM(dv.subtotal - dv.subtotal_costo), 0) AS utilidad
        FROM detalle_venta dv
        INNER JOIN producto p
            ON dv.id_producto = p.id_producto
        INNER JOIN venta v
            ON dv.id_venta = v.id_venta
        WHERE v.estado IN ('pagada', 'devuelta')
        AND v.fecha::date BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY p.codigo, p.nombre
        ORDER BY cantidad DESC, ventas DESC
        LIMIT 5
    ");
    $consultaProductoTop->execute($paramsRango);
    $productosTop = $consultaProductoTop->fetchAll();

    $consultaMenorRotacion = $conexion->prepare("
        SELECT
            p.codigo,
            p.nombre AS producto,
            COALESCE(SUM(CASE WHEN v.id_venta IS NOT NULL THEN dv.cantidad ELSE 0 END), 0) AS cantidad,
            COALESCE(SUM(CASE WHEN v.id_venta IS NOT NULL THEN dv.subtotal ELSE 0 END), 0) AS ventas
        FROM producto p
        LEFT JOIN detalle_venta dv
            ON p.id_producto = dv.id_producto
        LEFT JOIN venta v
            ON dv.id_venta = v.id_venta
            AND v.estado IN ('pagada', 'devuelta')
            AND v.fecha::date BETWEEN :fecha_inicio AND :fecha_fin
        WHERE p.estado <> 'inactivo'
        GROUP BY p.codigo, p.nombre
        ORDER BY cantidad ASC, p.nombre ASC
        LIMIT 5
    ");
    $consultaMenorRotacion->execute($paramsRango);
    $menorRotacionLista = $consultaMenorRotacion->fetchAll();

    $consultaVentasDia = $conexion->prepare("
        SELECT
            fecha_dia,
            SUM(ventas) AS ventas,
            SUM(devoluciones) AS devoluciones,
            SUM(ventas) - SUM(devoluciones) AS neto
        FROM (
            SELECT v.fecha::date AS fecha_dia, SUM(v.total) AS ventas, 0::numeric AS devoluciones
            FROM venta v
            WHERE v.estado IN ('pagada', 'devuelta')
            AND v.fecha::date BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY v.fecha::date

            UNION ALL

            SELECT d.fecha::date AS fecha_dia, 0::numeric AS ventas, SUM(d.total_devuelto) AS devoluciones
            FROM devolucion d
            WHERE d.estado = 'aprobada'
            AND d.fecha::date BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY d.fecha::date
        ) resumen
        GROUP BY fecha_dia
        ORDER BY fecha_dia DESC
    ");
    $consultaVentasDia->execute($paramsRango);
    $ventasPorDia = $consultaVentasDia->fetchAll();

    $consultaMediosPago = $conexion->prepare("
        SELECT
            medio_pago::text AS medio_pago,
            COUNT(*) AS ventas,
            COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND fecha::date BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY medio_pago
        ORDER BY total DESC
    ");
    $consultaMediosPago->execute($paramsRango);
    $mediosPago = $consultaMediosPago->fetchAll();

    $consultaCanalesVenta = $conexion->prepare("
        SELECT
            canal_venta,
            COUNT(*) AS ventas,
            COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND fecha::date BETWEEN :fecha_inicio AND :fecha_fin
        GROUP BY canal_venta
        ORDER BY total DESC
    ");
    $consultaCanalesVenta->execute($paramsRango);
    $canalesVenta = $consultaCanalesVenta->fetchAll();

    $netoRango = $ventasRango - $devolucionesRango;
    $costoNetoRango = $costoVendidoRango - $costoDevueltoRango;
    $utilidadBrutaRango = $netoRango - $costoNetoRango;
    $gastosOperativosRango = $gastosRango + $nominaRango;
    $utilidadNetaRango = $utilidadBrutaRango - $gastosOperativosRango;

    $productoTop = count($productosTop) > 0 ? $productosTop[0] : null;
    $menorRotacion = count($menorRotacionLista) > 0 ? $menorRotacionLista[0] : null;

    echo json_encode([
        "error" => false,
        "total_neto" => floatval($totalVentas) - floatval($totalDevoluciones),
        "ventas_hoy" => $ventasHoy,
        "devoluciones_hoy" => $devolucionesHoy,
        "neto_hoy" => $ventasHoy - $devolucionesHoy,
        "ventas_mes" => $ventasMes,
        "devoluciones_mes" => $devolucionesMes,
        "neto_mes" => $ventasMes - $devolucionesMes,
        "producto_top" => $productoTop ? $productoTop["producto"] . " (" . $productoTop["cantidad"] . ")" : "Sin ventas",
        "menor_rotacion" => $menorRotacion ? $menorRotacion["producto"] . " (" . $menorRotacion["cantidad"] . ")" : "Sin productos",
        "rango" => [
            "fecha_inicio" => $fechaInicio,
            "fecha_fin" => $fechaFin,
            "ventas_brutas" => $ventasRango,
            "devoluciones" => $devolucionesRango,
            "ventas_netas" => $netoRango,
            "costo_mercancia" => $costoNetoRango,
            "utilidad_bruta" => $utilidadBrutaRango,
            "gastos_generales" => $gastosRango,
            "nomina" => $nominaRango,
            "gastos_operativos" => $gastosOperativosRango,
            "inversiones" => $inversionesRango,
            "compras_mercancia" => $comprasMercanciaRango,
            "utilidad_neta" => $utilidadNetaRango,
            "margen_ganancia" => porcentaje_reporte($utilidadNetaRango, $netoRango),
            "porcentaje_costo" => porcentaje_reporte($costoNetoRango, $netoRango),
            "porcentaje_gastos" => porcentaje_reporte($gastosOperativosRango, $netoRango),
            "saldo_caja_estimado" => $inversionesRango + $netoRango - $gastosOperativosRango - $comprasMercanciaRango,
            "productos_top" => $productosTop,
            "menor_rotacion_lista" => $menorRotacionLista,
            "ventas_por_dia" => $ventasPorDia,
            "medios_pago" => $mediosPago,
            "canales_venta" => $canalesVenta
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error reportes_admin: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudieron cargar los reportes"
    ], JSON_UNESCAPED_UNICODE);
}

?>
