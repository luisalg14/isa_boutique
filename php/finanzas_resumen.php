<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $ventas = $conexion->query("
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
    ")->fetch()["total"];

    $devoluciones = $conexion->query("
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
    ")->fetch()["total"];

    $gastos = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
    ")->fetch()["total"];

    $costoMercancia = $conexion->query("
        SELECT COALESCE(SUM(subtotal_costo), 0) AS total
        FROM detalle_venta
    ")->fetch()["total"];

    $costoDevuelto = $conexion->query("
        SELECT COALESCE(SUM(subtotal_costo_devuelto), 0) AS total
        FROM detalle_devolucion dd
        INNER JOIN devolucion d
            ON dd.id_devolucion = d.id_devolucion
        WHERE d.estado = 'aprobada'
    ")->fetch()["total"];

    $inversiones = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
    ")->fetch()["total"];

    $ventasMes = $conexion->query("
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ")->fetch()["total"];

    $devolucionesMes = $conexion->query("
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ")->fetch()["total"];

    $gastosMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ")->fetch()["total"];

    $costoMercanciaMes = $conexion->query("
        SELECT COALESCE(SUM(dv.subtotal_costo), 0) AS total
        FROM detalle_venta dv
        INNER JOIN venta v
            ON dv.id_venta = v.id_venta
        WHERE DATE_TRUNC('month', v.fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ")->fetch()["total"];

    $costoDevueltoMes = $conexion->query("
        SELECT COALESCE(SUM(dd.subtotal_costo_devuelto), 0) AS total
        FROM detalle_devolucion dd
        INNER JOIN devolucion d
            ON dd.id_devolucion = d.id_devolucion
        WHERE d.estado = 'aprobada'
        AND DATE_TRUNC('month', d.fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ")->fetch()["total"];

    $inversionesMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
        WHERE DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_DATE)
    ")->fetch()["total"];

    $netoVentas = floatval($ventas) - floatval($devoluciones);
    $netoVentasMes = floatval($ventasMes) - floatval($devolucionesMes);
    $costoNetoMercancia = floatval($costoMercancia) - floatval($costoDevuelto);
    $costoNetoMercanciaMes = floatval($costoMercanciaMes) - floatval($costoDevueltoMes);
    $utilidadBruta = $netoVentas - $costoNetoMercancia;
    $utilidadBrutaMes = $netoVentasMes - $costoNetoMercanciaMes;
    $utilidadNeta = $utilidadBruta - floatval($gastos);
    $utilidadNetaMes = $utilidadBrutaMes - floatval($gastosMes);

    $consultaGastosTipo = $conexion->query("
        SELECT tipo, COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        GROUP BY tipo
        ORDER BY tipo
    ");

    $gastosPorTipo = [];

    foreach ($consultaGastosTipo->fetchAll() as $gastoTipo) {
        $gastosPorTipo[$gastoTipo["tipo"]] = floatval($gastoTipo["total"]);
    }

    function porcentaje_financiero($valor, $base) {
        $base = floatval($base);

        if ($base == 0) {
            return 0;
        }

        return round((floatval($valor) / $base) * 100, 2);
    }

    echo json_encode([
        "error" => false,
        "ventas_netas" => $netoVentas,
        "costo_mercancia" => $costoNetoMercancia,
        "utilidad_bruta" => $utilidadBruta,
        "gastos" => floatval($gastos),
        "inversiones" => floatval($inversiones),
        "utilidad_estimada" => $utilidadNeta,
        "utilidad_neta" => $utilidadNeta,
        "saldo_estimado" => floatval($inversiones) + $utilidadNeta,
        "margen_ganancia" => porcentaje_financiero($utilidadNeta, $netoVentas),
        "porcentaje_costo_mercancia" => porcentaje_financiero($costoNetoMercancia, $netoVentas),
        "porcentaje_gastos" => porcentaje_financiero($gastos, $netoVentas),
        "porcentaje_inversiones" => porcentaje_financiero($inversiones, $netoVentas),
        "gastos_por_tipo" => $gastosPorTipo,
        "ventas_netas_mes" => $netoVentasMes,
        "costo_mercancia_mes" => $costoNetoMercanciaMes,
        "utilidad_bruta_mes" => $utilidadBrutaMes,
        "gastos_mes" => floatval($gastosMes),
        "inversiones_mes" => floatval($inversionesMes),
        "utilidad_estimada_mes" => $utilidadNetaMes,
        "utilidad_neta_mes" => $utilidadNetaMes,
        "saldo_estimado_mes" => floatval($inversionesMes) + $utilidadNetaMes,
        "margen_ganancia_mes" => porcentaje_financiero($utilidadNetaMes, $netoVentasMes)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
