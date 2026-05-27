<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function columna_existe_finanzas(PDO $conexion, $tabla, $columna) {
    $consulta = $conexion->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = :tabla
            AND column_name = :columna
        ) AS existe
    ");

    $consulta->execute([
        ":tabla" => $tabla,
        ":columna" => $columna
    ]);

    return filter_var($consulta->fetch()["existe"] ?? false, FILTER_VALIDATE_BOOLEAN);
}

try {
    exigir_roles(["admin"]);

    $ventaTieneIva = columna_existe_finanzas($conexion, "venta", "iva");

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

    $pagosTrabajadores = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
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

    $comprasMercancia = $conexion->query("
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
    ")->fetch()["total"];

    $ventasMes = $conexion->query("
        SELECT COALESCE(SUM(total), 0) AS total
        FROM venta
        WHERE estado IN ('pagada', 'devuelta')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $devolucionesMes = $conexion->query("
        SELECT COALESCE(SUM(total_devuelto), 0) AS total
        FROM devolucion
        WHERE estado = 'aprobada'
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $gastosMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $pagosTrabajadoresMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM pago_trabajador
        WHERE DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $costoMercanciaMes = $conexion->query("
        SELECT COALESCE(SUM(dv.subtotal_costo), 0) AS total
        FROM detalle_venta dv
        INNER JOIN venta v
            ON dv.id_venta = v.id_venta
        WHERE DATE_TRUNC('month', v.fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $costoDevueltoMes = $conexion->query("
        SELECT COALESCE(SUM(dd.subtotal_costo_devuelto), 0) AS total
        FROM detalle_devolucion dd
        INNER JOIN devolucion d
            ON dd.id_devolucion = d.id_devolucion
        WHERE d.estado = 'aprobada'
        AND DATE_TRUNC('month', d.fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $inversionesMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM inversion_negocio
        WHERE DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $comprasMercanciaMes = $conexion->query("
        SELECT COALESCE(SUM(total_compra), 0) AS total
        FROM compra_mercancia
        WHERE DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $ivaVentas = $ventaTieneIva
        ? $conexion->query("
            SELECT COALESCE(SUM(iva), 0) AS total
            FROM venta
            WHERE estado IN ('pagada', 'devuelta')
        ")->fetch()["total"]
        : 0;

    $ivaVentasMes = $ventaTieneIva
        ? $conexion->query("
            SELECT COALESCE(SUM(iva), 0) AS total
            FROM venta
            WHERE estado IN ('pagada', 'devuelta')
            AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
        ")->fetch()["total"]
        : 0;

    $serviciosYRentaMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE tipo IN ('servicio', 'primario')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $operacionVentaMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE tipo IN ('empaque', 'publicidad', 'transporte')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $otrosGastosMes = $conexion->query("
        SELECT COALESCE(SUM(valor), 0) AS total
        FROM gasto_negocio
        WHERE tipo NOT IN ('servicio', 'primario', 'empaque', 'publicidad', 'transporte', 'nomina')
        AND DATE_TRUNC('month', fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
    ")->fetch()["total"];

    $netoVentas = floatval($ventas) - floatval($devoluciones);
    $netoVentasMes = floatval($ventasMes) - floatval($devolucionesMes);
    $costoNetoMercancia = floatval($costoMercancia) - floatval($costoDevuelto);
    $costoNetoMercanciaMes = floatval($costoMercanciaMes) - floatval($costoDevueltoMes);
    $utilidadBruta = $netoVentas - $costoNetoMercancia;
    $utilidadBrutaMes = $netoVentasMes - $costoNetoMercanciaMes;
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

    function porcentaje_financiero($valor, $base) {
        $base = floatval($base);

        if ($base == 0) {
            return 0;
        }

        return round((floatval($valor) / $base) * 100, 2);
    }

    function division_financiera($valor, $base) {
        $base = floatval($base);

        if ($base <= 0) {
            return 0;
        }

        return floatval($valor) / $base;
    }

    function meta_mensual_financiera($base, $margenBruto) {
        $metaMinimaMensual = 7000000;
        $margenReferencia = $margenBruto > 0 ? $margenBruto : 0.45;
        $metaCalculada = $base > 0 ? $base / $margenReferencia : 0;

        return max($metaMinimaMensual, $metaCalculada);
    }

    $margenBrutoRatioMes = division_financiera($utilidadBrutaMes, $netoVentasMes);
    $baseOperativaMes = $totalGastosOperativosMes + floatval($comprasMercanciaMes) + floatval($inversionesMes);
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
    $gananciaLibreMes = $netoVentasMes
        - floatval($ivaVentasMes)
        - $costoNetoMercanciaMes
        - floatval($pagosTrabajadoresMes)
        - floatval($serviciosYRentaMes)
        - floatval($operacionVentaMes)
        - floatval($otrosGastosMes);

    $consultaVentasClasificadas = $conexion->query("
        SELECT
            v.id_venta,
            v.fecha,
            v.total,
            " . ($ventaTieneIva ? "COALESCE(v.iva, 0)" : "0") . " AS iva,
            COALESCE(SUM(dv.subtotal_costo), 0) AS costo_mercancia,
            v.total - " . ($ventaTieneIva ? "COALESCE(v.iva, 0)" : "0") . " - COALESCE(SUM(dv.subtotal_costo), 0) AS utilidad_bruta
        FROM venta v
        LEFT JOIN detalle_venta dv
            ON v.id_venta = dv.id_venta
        WHERE v.estado IN ('pagada', 'devuelta')
        AND DATE_TRUNC('month', v.fecha) = DATE_TRUNC('month', CURRENT_TIMESTAMP AT TIME ZONE 'America/Bogota')
        GROUP BY v.id_venta, v.fecha, v.total" . ($ventaTieneIva ? ", v.iva" : "") . "
        ORDER BY v.fecha DESC, v.id_venta DESC
        LIMIT 8
    ");

    echo json_encode([
        "error" => false,
        "ventas_netas" => $netoVentas,
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
            "trabajadores" => floatval($pagosTrabajadoresMes),
            "servicios_renta" => floatval($serviciosYRentaMes),
            "bolsas_publicidad_envios" => floatval($operacionVentaMes),
            "otros_gastos" => floatval($otrosGastosMes),
            "ganancia_libre_estimada" => $gananciaLibreMes,
            "saldo_caja" => $saldoCajaMesEstimado
        ],
        "ventas_clasificadas_mes" => $consultaVentasClasificadas->fetchAll(),
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
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
