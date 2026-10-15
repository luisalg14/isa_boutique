<?php
require_once "finanzas_util.php";

function construir_resumen_finanzas(PDO $conexion) {
    $ahora = new DateTimeImmutable('now', new DateTimeZone('America/Bogota'));
    $inicioMes = $ahora->format('Y-m-01');
    $finMes = $ahora->modify('first day of next month')->format('Y-m-d');
    $metricas = metricas_ventas_financieras($conexion);
    $metricasMes = metricas_ventas_financieras($conexion, $inicioMes, $finMes);

    $ventas = $metricas['ventas'];

    $devoluciones = $metricas['devoluciones'];

    $gastos = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
    ")->fetch()["total"];

    $pagosTrabajadores = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
        WHERE tipo_pago <> 'deduccion'
    ")->fetch()["total"];

    $costoMercancia = $metricas['costo_vendido'];

    $costoDevuelto = $metricas['costo_devuelto'];

    $inversiones = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
    ")->fetch()["total"];

    $comprasMercancia = $conexion->query("
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
    ")->fetch()["total"];

    $ventasMes = $metricasMes['ventas'];

    $devolucionesMes = $metricasMes['devoluciones'];

    $gastosMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $pagosTrabajadoresMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
        WHERE tipo_pago <> 'deduccion'
        AND fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $costoMercanciaMes = $metricasMes['costo_vendido'];

    $costoDevueltoMes = $metricasMes['costo_devuelto'];

    $inversionesMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
        WHERE fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $comprasMercanciaMes = $conexion->query("
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
        WHERE fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $ivaVentas = $metricas['iva_ventas']-$metricas['iva_devuelto'];

    $ivaVentasMes = $metricasMes['iva_ventas']-$metricasMes['iva_devuelto'];

    $serviciosYRentaMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE tipo IN ('servicio', 'primario')
        AND fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $operacionVentaMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE tipo IN ('empaque', 'publicidad', 'transporte')
        AND fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $otrosGastosMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE tipo NOT IN ('servicio', 'primario', 'empaque', 'publicidad', 'transporte', 'nomina')
        AND fecha >= DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'
    ")->fetch()["total"];

    $netoVentas = floatval($ventas) - floatval($devoluciones);
    $netoVentasMes = floatval($ventasMes) - floatval($devolucionesMes);
    $costoNetoMercancia = floatval($costoMercancia) - floatval($costoDevuelto);
    $costoNetoMercanciaMes = floatval($costoMercanciaMes) - floatval($costoDevueltoMes);
    $calculo = calcular_utilidades_financieras($metricas, floatval($gastos), floatval($pagosTrabajadores));
    $calculoMes = calcular_utilidades_financieras($metricasMes, floatval($gastosMes), floatval($pagosTrabajadoresMes));
    $utilidadBruta = $calculo['utilidad_bruta'];
    $utilidadBrutaMes = $calculoMes['utilidad_bruta'];
    $totalGastosOperativos = floatval($gastos) + floatval($pagosTrabajadores);
    $totalGastosOperativosMes = floatval($gastosMes) + floatval($pagosTrabajadoresMes);
    $utilidadNeta = $utilidadBruta - $totalGastosOperativos;
    $utilidadNetaMes = $utilidadBrutaMes - $totalGastosOperativosMes;

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

    if (floatval($pagosTrabajadores) > 0) {
        $gastosPorTipo["nomina"] = ($gastosPorTipo["nomina"] ?? 0) + floatval($pagosTrabajadores);
    }

    $margenBrutoRatioMes = division_financiera($utilidadBrutaMes, $netoVentasMes);
    $baseOperativaMes = $totalGastosOperativosMes;
    $puntoEquilibrioMes = meta_mensual_financiera($baseOperativaMes, $margenBrutoRatioMes);
    $gananciaObjetivoMes = max(1500000, $totalGastosOperativosMes * 0.30);
    $metaVentasConGananciaMes = meta_mensual_financiera($baseOperativaMes + $gananciaObjetivoMes, $margenBrutoRatioMes);
    $avancePuntoEquilibrioMes = porcentaje_financiero($netoVentasMes, $puntoEquilibrioMes);
    $avanceMetaGananciaMes = porcentaje_financiero($netoVentasMes, $metaVentasConGananciaMes);
    $faltanteEquilibrioMes = max(0, $puntoEquilibrioMes - $netoVentasMes);
    $faltanteMetaGananciaMes = max(0, $metaVentasConGananciaMes - $netoVentasMes);
    $diasDelMes = intval(date("t"));
    $diaActual = intval(date("j"));
    $diasRestantes = max(1, $diasDelMes - $diaActual + 1);
    $ventaDiariaNecesariaEquilibrio = $faltanteEquilibrioMes / $diasRestantes;
    $ventaDiariaNecesariaMeta = $faltanteMetaGananciaMes / $diasRestantes;
    $saldoCajaRealEstimado = floatval($inversiones) + $netoVentas - $totalGastosOperativos - floatval($comprasMercancia);
    $saldoCajaMesEstimado = floatval($inversionesMes) + $netoVentasMes - $totalGastosOperativosMes - floatval($comprasMercanciaMes);
    $gananciaLibreMes = $utilidadNetaMes;
    $nominaGastosMes = $conexion->query("SELECT COALESCE(SUM(valor),0) FROM gasto_negocio
        WHERE tipo='nomina' AND fecha >= DATE_TRUNC('month',CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') AND fecha < DATE_TRUNC('month',CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota') + INTERVAL '1 month'")->fetchColumn();

    $ventasClasificadas = ventas_clasificadas_financieras($conexion, $inicioMes, $finMes);

    return [
        "error" => false,
        "ventas_netas" => $netoVentas,
        "iva_neto" => $ivaVentas,
        "ingreso_sin_iva" => $calculo["ingreso_sin_iva"],
        "costo_mercancia" => $costoNetoMercancia,
        "utilidad_bruta" => $utilidadBruta,
        "gastos" => $totalGastosOperativos,
        "gastos_generales" => floatval($gastos),
        "pagos_trabajadores" => floatval($pagosTrabajadores),
        "inversiones" => floatval($inversiones),
        "compras_mercancia" => floatval($comprasMercancia),
        "utilidad_estimada" => $utilidadNeta,
        "utilidad_neta" => $utilidadNeta,
        "saldo_estimado" => floatval($inversiones) + $utilidadNeta,
        "saldo_caja_estimado" => $saldoCajaRealEstimado,
        "margen_ganancia" => porcentaje_financiero($utilidadNeta, $netoVentas),
        "porcentaje_costo_mercancia" => porcentaje_financiero($costoNetoMercancia, $netoVentas),
        "porcentaje_gastos" => porcentaje_financiero($totalGastosOperativos, $netoVentas),
        "porcentaje_inversiones" => porcentaje_financiero($inversiones, $netoVentas),
        "porcentaje_compras_mercancia" => porcentaje_financiero($comprasMercancia, $netoVentas),
        "gastos_por_tipo" => $gastosPorTipo,
        "ventas_netas_mes" => $netoVentasMes,
        "iva_neto_mes" => $ivaVentasMes,
        "ingreso_sin_iva_mes" => $calculoMes["ingreso_sin_iva"],
        "costo_mercancia_mes" => $costoNetoMercanciaMes,
        "utilidad_bruta_mes" => $utilidadBrutaMes,
        "gastos_mes" => $totalGastosOperativosMes,
        "gastos_generales_mes" => floatval($gastosMes),
        "pagos_trabajadores_mes" => floatval($pagosTrabajadoresMes),
        "inversiones_mes" => floatval($inversionesMes),
        "compras_mercancia_mes" => floatval($comprasMercanciaMes),
        "utilidad_estimada_mes" => $utilidadNetaMes,
        "utilidad_neta_mes" => $utilidadNetaMes,
        "saldo_estimado_mes" => floatval($inversionesMes) + $utilidadNetaMes,
        "saldo_caja_estimado_mes" => $saldoCajaMesEstimado,
        "clasificacion_mes" => [
            "ventas_netas" => $netoVentasMes,
            "iva" => floatval($ivaVentasMes),
            "para_mercancia_vendida" => $costoNetoMercanciaMes,
            "compras_mercancia_mes" => floatval($comprasMercanciaMes),
            "trabajadores" => floatval($pagosTrabajadoresMes) + floatval($nominaGastosMes),
            "servicios_renta" => floatval($serviciosYRentaMes),
            "bolsas_publicidad_envios" => floatval($operacionVentaMes),
            "otros_gastos" => floatval($otrosGastosMes),
            "ganancia_libre_estimada" => $gananciaLibreMes,
            "saldo_caja" => $saldoCajaMesEstimado
        ],
        "ventas_clasificadas_mes" => $ventasClasificadas,
        "margen_ganancia_mes" => porcentaje_financiero($utilidadNetaMes, $netoVentasMes),
        "margen_bruto_mes" => porcentaje_financiero($utilidadBrutaMes, $netoVentasMes),
        "punto_equilibrio_mes" => $puntoEquilibrioMes,
        "ganancia_objetivo_mes" => $gananciaObjetivoMes,
        "meta_ventas_ganancia_mes" => $metaVentasConGananciaMes,
        "avance_equilibrio_mes" => min(100, $avancePuntoEquilibrioMes),
        "avance_meta_ganancia_mes" => min(100, $avanceMetaGananciaMes),
        "faltante_equilibrio_mes" => $faltanteEquilibrioMes,
        "faltante_meta_ganancia_mes" => $faltanteMetaGananciaMes,
        "venta_diaria_equilibrio_mes" => $ventaDiariaNecesariaEquilibrio,
        "venta_diaria_meta_mes" => $ventaDiariaNecesariaMeta,
        "dias_restantes_mes" => $diasRestantes
    ];
}
