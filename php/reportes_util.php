<?php
require_once "finanzas_util.php";

function reporte_para_vendedor(array $reportes) {
    // Lista positiva: nuevos campos financieros no se publican por accidente.
    $resultado = array_intersect_key($reportes, array_flip([
        'error','ventas_hoy','devoluciones_hoy','neto_hoy','ventas_mes','neto_mes','producto_top'
    ]));
    $resultado['meta_diaria'] = array_intersect_key($reportes['meta_diaria'] ?? [], array_flip([
        'objetivo','avance','faltante','ventas_hoy','meta_mensual'
    ]));
    return $resultado;
}

function escalar_reporte($conexion, $sql, $params = []) {
    $consulta = $conexion->prepare($sql);
    $consulta->execute($params);
    return floatval($consulta->fetch()["total"] ?? 0);
}

function construir_reportes(PDO $conexion, $inicioSolicitado = "", $finSolicitado = "") {
    $hoy = date("Y-m-d");
    $inicioMes = date("Y-m-01");
    $fechaInicio = fecha_financiera($inicioSolicitado, $inicioMes);
    $fechaFin = fecha_financiera($finSolicitado, $hoy);

    if ($fechaInicio > $fechaFin) {
        $temporal = $fechaInicio;
        $fechaInicio = $fechaFin;
        $fechaFin = $temporal;
    }

    $finRangoExclusivo = (new DateTimeImmutable($fechaFin))->modify('+1 day')->format('Y-m-d');
    $finMes = (new DateTimeImmutable($inicioMes))->modify('first day of next month')->format('Y-m-d');
    $metricasMes = metricas_ventas_financieras($conexion, $inicioMes, $finMes);
    $metricasRango = metricas_ventas_financieras($conexion, $fechaInicio, $finRangoExclusivo);

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
        AND fecha >= (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date AND fecha < (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date + INTERVAL '1 day'
    ");

    $devolucionesHoy = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND fecha >= (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date AND fecha < (CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')::date + INTERVAL '1 day'
    ");

    $ventasMes = $metricasMes['ventas'];

    $devolucionesMes = $metricasMes['devoluciones'];

    $costoVendidoMes = $metricasMes['costo_vendido'];

    $costoDevueltoMes = $metricasMes['costo_devuelto'];

    $gastosMes = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ");

    $nominaMes = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
        WHERE tipo_pago <> 'deduccion'
        AND fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ");

    $ventasRango = $metricasRango['ventas'];

    $devolucionesRango = $metricasRango['devoluciones'];

    $costoVendidoRango = $metricasRango['costo_vendido'];

    $costoDevueltoRango = $metricasRango['costo_devuelto'];

    $gastosRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE fecha >= CAST(:fecha_inicio AS date) AND fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
    ", $paramsRango);

    $nominaRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
        WHERE tipo_pago <> 'deduccion'
        AND fecha >= CAST(:fecha_inicio AS date) AND fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
    ", $paramsRango);

    $inversionesRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
        WHERE fecha >= CAST(:fecha_inicio AS date) AND fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
    ", $paramsRango);

    $inversionesMes = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
        WHERE fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ");

    $comprasMercanciaRango = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
        WHERE fecha >= CAST(:fecha_inicio AS date) AND fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
    ", $paramsRango);

    $comprasMercanciaMes = escalar_reporte($conexion, "
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
        WHERE fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ");

    $productosTop = productos_reporte_financiero($conexion, $fechaInicio, $finRangoExclusivo);
    $menorRotacionLista = productos_reporte_financiero($conexion, $fechaInicio, $finRangoExclusivo, true);

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
            AND v.fecha >= CAST(:fecha_inicio AS date) AND v.fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
            GROUP BY v.fecha::date

            UNION ALL

            SELECT d.fecha::date AS fecha_dia, 0::numeric AS ventas, SUM(d.total_devuelto) AS devoluciones
            FROM devolucion d
            WHERE d.estado = 'aprobada'
            AND d.fecha >= CAST(:fecha_inicio AS date) AND d.fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
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
        AND fecha >= CAST(:fecha_inicio AS date) AND fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
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
        AND fecha >= CAST(:fecha_inicio AS date) AND fecha < CAST(:fecha_fin AS date) + INTERVAL '1 day'
        GROUP BY canal_venta
        ORDER BY total DESC
    ");
    $consultaCanalesVenta->execute($paramsRango);
    $canalesVenta = $consultaCanalesVenta->fetchAll();

    $consultaVentasUltimosMeses = $conexion->prepare("
        WITH meses AS (
            SELECT generate_series(
                DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') - INTERVAL '5 months',
                DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota'),
                INTERVAL '1 month'
            )::date AS mes
        ),
        ventas_mes AS (
            SELECT DATE_TRUNC('month', fecha)::date AS mes, COALESCE(SUM(total), 0) AS ventas
            FROM venta
            WHERE estado IN ('pagada', 'devuelta')
            GROUP BY DATE_TRUNC('month', fecha)::date
        ),
        devoluciones_mes AS (
            SELECT DATE_TRUNC('month', fecha)::date AS mes, COALESCE(SUM(total_devuelto), 0) AS devoluciones
            FROM devolucion
            WHERE estado = 'aprobada'
            GROUP BY DATE_TRUNC('month', fecha)::date
        )
        SELECT
            TO_CHAR(m.mes, 'YYYY-MM') AS periodo,
            COALESCE(v.ventas, 0) AS ventas,
            COALESCE(d.devoluciones, 0) AS devoluciones,
            COALESCE(v.ventas, 0) - COALESCE(d.devoluciones, 0) AS neto
        FROM meses m
        LEFT JOIN ventas_mes v
            ON v.mes = m.mes
        LEFT JOIN devoluciones_mes d
            ON d.mes = m.mes
        ORDER BY m.mes
    ");
    $consultaVentasUltimosMeses->execute();
    $ventasUltimosMeses = $consultaVentasUltimosMeses->fetchAll();

    $consultaVentasAnio = $conexion->prepare("
        WITH meses AS (
            SELECT generate_series(
                DATE_TRUNC('year', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota'),
                DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota'),
                INTERVAL '1 month'
            )::date AS mes
        ),
        ventas_mes AS (
            SELECT DATE_TRUNC('month', fecha)::date AS mes, COALESCE(SUM(total), 0) AS ventas
            FROM venta
            WHERE estado IN ('pagada', 'devuelta')
            AND fecha >= DATE_TRUNC('year', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('year', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 year'
            GROUP BY DATE_TRUNC('month', fecha)::date
        ),
        devoluciones_mes AS (
            SELECT DATE_TRUNC('month', fecha)::date AS mes, COALESCE(SUM(total_devuelto), 0) AS devoluciones
            FROM devolucion
            WHERE estado = 'aprobada'
            AND fecha >= DATE_TRUNC('year', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('year', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 year'
            GROUP BY DATE_TRUNC('month', fecha)::date
        )
        SELECT
            TO_CHAR(m.mes, 'YYYY-MM') AS periodo,
            COALESCE(v.ventas, 0) AS ventas,
            COALESCE(d.devoluciones, 0) AS devoluciones,
            COALESCE(v.ventas, 0) - COALESCE(d.devoluciones, 0) AS neto
        FROM meses m
        LEFT JOIN ventas_mes v
            ON v.mes = m.mes
        LEFT JOIN devoluciones_mes d
            ON d.mes = m.mes
        ORDER BY m.mes
    ");
    $consultaVentasAnio->execute();
    $ventasAnio = $consultaVentasAnio->fetchAll();

    $netoRango = $ventasRango - $devolucionesRango;
    $costoNetoRango = $costoVendidoRango - $costoDevueltoRango;
    $calculoRango = calcular_utilidades_financieras($metricasRango, $gastosRango, $nominaRango);
    $calculoMes = calcular_utilidades_financieras($metricasMes, $gastosMes, $nominaMes);
    $utilidadBrutaRango = $calculoRango['utilidad_bruta'];
    $gastosOperativosRango = $gastosRango + $nominaRango;
    $utilidadNetaRango = $utilidadBrutaRango - $gastosOperativosRango;
    $netoHoy = $ventasHoy - $devolucionesHoy;
    $netoMes = $ventasMes - $devolucionesMes;
    $costoNetoMes = $costoVendidoMes - $costoDevueltoMes;
    $utilidadBrutaMes = $calculoMes['utilidad_bruta'];
    $gastosOperativosMes = $gastosMes + $nominaMes;
    $margenBrutoMes = division_financiera($utilidadBrutaMes, $netoMes);
    $baseMetaMensual = $gastosOperativosMes;
    $gananciaObjetivoMes = max(1500000, $gastosOperativosMes * 0.30);
    $metaMensualConGanancia = meta_mensual_financiera($baseMetaMensual + $gananciaObjetivoMes, $margenBrutoMes);
    $diasMes = intval(date("t"));
    $metaDiaria = $diasMes > 0 ? $metaMensualConGanancia / $diasMes : 0;
    $avanceMetaDiaria = porcentaje_financiero($netoHoy, $metaDiaria);
    $faltanteMetaDiaria = max(0, $metaDiaria - $netoHoy);

    $productoTop = count($productosTop) > 0 ? $productosTop[0] : null;
    $menorRotacion = count($menorRotacionLista) > 0 ? $menorRotacionLista[0] : null;

    return [
        "error" => false,
        "total_neto" => floatval($totalVentas) - floatval($totalDevoluciones),
        "ventas_hoy" => $ventasHoy,
        "devoluciones_hoy" => $devolucionesHoy,
        "neto_hoy" => $netoHoy,
        "ventas_mes" => $ventasMes,
        "devoluciones_mes" => $devolucionesMes,
        "neto_mes" => $netoMes,
        "meta_diaria" => [
            "objetivo" => $metaDiaria,
            "avance" => min(100, $avanceMetaDiaria),
            "faltante" => $faltanteMetaDiaria,
            "ventas_hoy" => $netoHoy,
            "meta_mensual" => $metaMensualConGanancia
        ],
        "producto_top" => $productoTop ? $productoTop["producto"] . " (" . $productoTop["cantidad"] . ")" : "Sin ventas",
        "menor_rotacion" => $menorRotacion ? $menorRotacion["producto"] . " (" . $menorRotacion["cantidad"] . ")" : "Sin productos",
        "rango" => [
            "fecha_inicio" => $fechaInicio,
            "fecha_fin" => $fechaFin,
            "ventas_brutas" => $ventasRango,
            "devoluciones" => $devolucionesRango,
            "ventas_netas" => $netoRango,
            "iva_neto" => $calculoRango["iva"],
            "ingreso_sin_iva" => $calculoRango["ingreso_sin_iva"],
            "costo_mercancia" => $costoNetoRango,
            "utilidad_bruta" => $utilidadBrutaRango,
            "gastos_generales" => $gastosRango,
            "nomina" => $nominaRango,
            "gastos_operativos" => $gastosOperativosRango,
            "inversiones" => $inversionesRango,
            "compras_mercancia" => $comprasMercanciaRango,
            "utilidad_neta" => $utilidadNetaRango,
            "margen_ganancia" => porcentaje_financiero($utilidadNetaRango, $netoRango),
            "porcentaje_costo" => porcentaje_financiero($costoNetoRango, $netoRango),
            "porcentaje_gastos" => porcentaje_financiero($gastosOperativosRango, $netoRango),
            "saldo_caja_estimado" => $inversionesRango + $netoRango - $gastosOperativosRango - $comprasMercanciaRango,
            "productos_top" => $productosTop,
            "menor_rotacion_lista" => $menorRotacionLista,
            "ventas_por_dia" => $ventasPorDia,
            "medios_pago" => $mediosPago,
            "canales_venta" => $canalesVenta
        ],
        "graficos" => [
            "ventas_mes_dias" => $ventasPorDia,
            "ventas_ultimos_meses" => $ventasUltimosMeses,
            "ventas_anio" => $ventasAnio
        ]
    ];
}
