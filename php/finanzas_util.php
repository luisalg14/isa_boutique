<?php

function leer_finanzas_consistentes(PDO $conexion, callable $lectura) {
    $conexion->beginTransaction();
    try {
        $conexion->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $resultado = $lectura();
        $conexion->commit();
        return $resultado;
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $e;
    }
}

function cte_devoluciones_financieras() {
    return <<<'SQL'
WITH devoluciones_base AS (
    SELECT dd.id_detalle_devolucion, d.id_venta, dd.id_producto, dd.cantidad, d.fecha, dd.subtotal_devuelto, dd.subtotal_costo_devuelto,
        COALESCE(dd.id_detalle_venta::text, 'venta-'||d.id_venta||'-producto-'||dd.id_producto) AS linea,
        COALESCE(dv.iva / NULLIF(dv.subtotal,0), v.iva / NULLIF(v.total,0), 0) AS proporcion_iva
    FROM detalle_devolucion dd
    JOIN devolucion d ON d.id_devolucion=dd.id_devolucion
    JOIN venta v ON v.id_venta=d.id_venta
    LEFT JOIN detalle_venta dv ON dv.id_detalle_venta=dd.id_detalle_venta
    WHERE d.estado='aprobada' AND v.estado IN ('pagada','devuelta')
), devoluciones_acumuladas AS (
    SELECT *, SUM(subtotal_devuelto) OVER (PARTITION BY linea ORDER BY fecha,id_detalle_devolucion) AS acumulado
    FROM devoluciones_base
), devoluciones_importes AS (
    SELECT *, ROUND(acumulado*proporcion_iva,2)-ROUND((acumulado-subtotal_devuelto)*proporcion_iva,2) AS iva_devuelto
    FROM devoluciones_acumuladas
)
SQL;
}

// Las fechas se aplican al movimiento: una devolucion posterior revierte en su propio periodo.
function metricas_ventas_financieras(PDO $conexion, $inicio = null, $finExclusivo = null) {
    $consulta = $conexion->prepare(cte_devoluciones_financieras() . <<<'SQL'
, importes AS (
    SELECT v.fecha, v.total AS ventas, v.iva AS iva_ventas,
        COALESCE((SELECT SUM(dv.subtotal_costo) FROM detalle_venta dv WHERE dv.id_venta=v.id_venta),0) AS costo_vendido,
        0::numeric AS devoluciones, 0::numeric AS iva_devuelto, 0::numeric AS costo_devuelto
    FROM venta v WHERE v.estado IN ('pagada','devuelta')
    UNION ALL
    SELECT fecha, 0, 0, 0, subtotal_devuelto, iva_devuelto, subtotal_costo_devuelto
    FROM devoluciones_importes
)
SELECT COALESCE(SUM(ventas),0) AS ventas, COALESCE(SUM(iva_ventas),0) AS iva_ventas,
    COALESCE(SUM(costo_vendido),0) AS costo_vendido, COALESCE(SUM(devoluciones),0) AS devoluciones,
    COALESCE(SUM(iva_devuelto),0) AS iva_devuelto, COALESCE(SUM(costo_devuelto),0) AS costo_devuelto
FROM importes
WHERE (CAST(:inicio AS timestamp) IS NULL OR fecha >= CAST(:inicio AS timestamp))
AND (CAST(:fin AS timestamp) IS NULL OR fecha < CAST(:fin AS timestamp))
SQL);
    $consulta->execute([':inicio'=>$inicio, ':fin'=>$finExclusivo]);
    return array_map('floatval', $consulta->fetch());
}

function ventas_clasificadas_financieras(PDO $conexion, $inicio, $fin) {
    $consulta=$conexion->prepare(cte_devoluciones_financieras() . <<<'SQL'
, devoluciones_por_venta AS (
    SELECT id_venta, SUM(subtotal_devuelto) AS importe, SUM(iva_devuelto) AS iva, SUM(subtotal_costo_devuelto) AS costo
    FROM devoluciones_importes WHERE fecha < CAST(:fin AS timestamp) GROUP BY id_venta
), ventas_netas AS (
    SELECT v.id_venta,v.fecha,v.total-COALESCE(d.importe,0) AS total, v.iva-COALESCE(d.iva,0) AS iva,
        (SELECT COALESCE(SUM(subtotal_costo),0) FROM detalle_venta WHERE id_venta=v.id_venta)-COALESCE(d.costo,0) AS costo_mercancia
    FROM venta v LEFT JOIN devoluciones_por_venta d ON d.id_venta=v.id_venta
    WHERE v.estado IN ('pagada','devuelta') AND v.fecha >= CAST(:inicio AS timestamp) AND v.fecha < CAST(:fin AS timestamp)
)
SELECT *, total-iva-costo_mercancia AS utilidad_bruta FROM ventas_netas ORDER BY fecha DESC,id_venta DESC LIMIT 8
SQL);
    $consulta->execute([':inicio'=>$inicio,':fin'=>$fin]);
    return $consulta->fetchAll();
}

function productos_reporte_financiero(PDO $conexion, $inicio, $fin, $menorRotacion = false) {
    $orden = $menorRotacion ? 'cantidad ASC, p.nombre ASC' : 'cantidad DESC, ventas DESC, p.nombre ASC';
    $filtro = $menorRotacion ? "WHERE p.estado <> 'inactivo'" : '';
    $consulta=$conexion->prepare(cte_devoluciones_financieras() . <<<'SQL'
, movimientos AS (
    SELECT dv.id_producto,v.fecha,dv.cantidad,dv.subtotal AS ventas,dv.subtotal_costo AS costo,dv.iva
    FROM detalle_venta dv JOIN venta v ON v.id_venta=dv.id_venta WHERE v.estado IN ('pagada','devuelta')
    UNION ALL
    SELECT id_producto,fecha,-cantidad,-subtotal_devuelto,-subtotal_costo_devuelto,-iva_devuelto FROM devoluciones_importes
), productos_periodo AS (
    SELECT id_producto,SUM(cantidad) AS cantidad,SUM(ventas) AS ventas,SUM(costo) AS costo,SUM(ventas-iva-costo) AS utilidad
    FROM movimientos WHERE fecha >= CAST(:inicio AS timestamp) AND fecha < CAST(:fin AS timestamp) GROUP BY id_producto
)
SELECT p.codigo,p.nombre AS producto,COALESCE(m.cantidad,0) AS cantidad,COALESCE(m.ventas,0) AS ventas,
    COALESCE(m.costo,0) AS costo,COALESCE(m.utilidad,0) AS utilidad
FROM producto p
SQL
        . ($menorRotacion ? ' LEFT JOIN ' : ' JOIN ') . "productos_periodo m ON m.id_producto=p.id_producto $filtro ORDER BY $orden LIMIT 5");
    $consulta->execute([':inicio'=>$inicio,':fin'=>$fin]);
    return $consulta->fetchAll();
}

function calcular_utilidades_financieras(array $ventas, $gastos, $nomina) {
    $netas = $ventas['ventas'] - $ventas['devoluciones'];
    $iva = $ventas['iva_ventas'] - $ventas['iva_devuelto'];
    $costos = $ventas['costo_vendido'] - $ventas['costo_devuelto'];
    $bruta = round($netas - $iva - $costos, 2);
    return ['ventas_netas'=>$netas, 'iva'=>$iva, 'ingreso_sin_iva'=>round($netas-$iva,2),
        'costo_mercancia'=>$costos, 'utilidad_bruta'=>$bruta,
        'utilidad_neta'=>round($bruta-$gastos-$nomina,2)];
}

function fecha_financiera($valor, $respaldo) {
    if (!is_string($valor)) return $respaldo;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $fecha && $fecha->format('Y-m-d') === $valor ? $valor : $respaldo;
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

function construir_movimientos_financieros(PDO $conexion) {
    $sql = "
        SELECT
            'Venta' AS clase,
            v.id_venta AS id_movimiento,
            v.canal_venta AS tipo,
            'Venta #' || v.id_venta || ' - ' || c.nombre AS concepto,
            v.total AS valor,
            v.fecha,
            'Ingreso por venta ' || v.medio_pago AS detalle,
            u.nombre AS usuario
        FROM venta v
        INNER JOIN cliente c
            ON v.id_cliente = c.id_cliente
        LEFT JOIN usuario_sistema u
            ON v.id_usuario = u.id_usuario
        WHERE v.estado IN ('pagada', 'devuelta')

        UNION ALL

        SELECT
            'Devolucion' AS clase,
            d.id_devolucion AS id_movimiento,
            d.estado::text AS tipo,
            'Devolucion venta #' || d.id_venta AS concepto,
            -d.total_devuelto AS valor,
            d.fecha,
            d.motivo AS detalle,
            NULL AS usuario
        FROM devolucion d
        WHERE d.estado = 'aprobada'

        UNION ALL

        SELECT
            'Gasto' AS clase,
            g.id_gasto AS id_movimiento,
            g.tipo,
            g.concepto,
            -g.valor AS valor,
            g.fecha,
            g.detalle,
            u.nombre AS usuario
        FROM gasto_negocio g
        LEFT JOIN usuario_sistema u
            ON g.id_usuario = u.id_usuario

        UNION ALL

        SELECT
            'Inversion' AS clase,
            i.id_inversion AS id_movimiento,
            i.tipo,
            i.concepto,
            i.valor,
            i.fecha,
            i.detalle,
            u.nombre AS usuario
        FROM inversion_negocio i
        LEFT JOIN usuario_sistema u
            ON i.id_usuario = u.id_usuario

        UNION ALL

        SELECT
            'Pago trabajador' AS clase,
            p.id_pago_trabajador AS id_movimiento,
            p.tipo_pago AS tipo,
            t.nombre || ' - ' || t.cargo AS concepto,
            CASE WHEN p.tipo_pago = 'deduccion' THEN 0 ELSE -p.valor END AS valor,
            p.fecha,
            p.detalle,
            u.nombre AS usuario
        FROM pago_trabajador p
        INNER JOIN trabajador t
            ON p.id_trabajador = t.id_trabajador
        LEFT JOIN usuario_sistema u
            ON p.id_usuario = u.id_usuario

        UNION ALL

        SELECT
            'Compra mercancia' AS clase,
            c.id_compra AS id_movimiento,
            'mercancia' AS tipo,
            COALESCE(pr.nombre, 'Sin proveedor') AS concepto,
            -c.total_compra AS valor,
            c.fecha,
            c.detalle,
            u.nombre AS usuario
        FROM compra_mercancia c
        LEFT JOIN proveedor pr
            ON c.id_proveedor = pr.id_proveedor
        LEFT JOIN usuario_sistema u
            ON c.id_usuario = u.id_usuario

        ORDER BY fecha DESC, id_movimiento DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    return $consulta->fetchAll();
}
