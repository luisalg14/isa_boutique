<?php

function devolucion_tiene_detalle(PDO $conexion) {
    return filter_var($conexion->query("SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = current_schema() AND table_name = 'detalle_devolucion'
        AND column_name = 'id_detalle_venta'
    )")->fetchColumn(), FILTER_VALIDATE_BOOLEAN);
}

function importe_devolucion($subtotal, $vendidas, $devueltas, $cantidad, $importeAnterior) {
    if ($vendidas <= 0 || $cantidad <= 0 || $devueltas < 0 || $devueltas + $cantidad > $vendidas) {
        throw new DomainException('La cantidad supera las unidades disponibles para devolucion');
    }
    $centavos = (int) round((float) $subtotal * 100);
    $anterior = (int) round((float) $importeAnterior * 100);
    $acumulado = (int) round($centavos * ($devueltas + $cantidad) / $vendidas);
    if ($anterior > $acumulado || $centavos < 0) {
        throw new DomainException('Las devoluciones anteriores requieren revision de sus importes');
    }
    return ($acumulado - $anterior) / 100.0;
}

// El llamador inicia y confirma la transaccion para incluir toda la operacion.
function guardar_devolucion(PDO $conexion, $idVenta, $idProducto, $idDetalle, $cantidad, $motivo) {
    if (!$conexion->inTransaction()) {
        throw new LogicException('La devolucion requiere una transaccion');
    }
    $consulta = $conexion->prepare('SELECT id_cliente, estado FROM venta WHERE id_venta = ? FOR UPDATE');
    $consulta->execute([$idVenta]);
    $venta = $consulta->fetch();
    if (!$venta || $venta['estado'] !== 'pagada') {
        throw new DomainException('Solo se pueden devolver unidades de una venta pagada');
    }

    $consulta = $conexion->prepare('SELECT * FROM detalle_venta WHERE id_venta = ? AND id_producto = ? ORDER BY id_detalle_venta');
    $consulta->execute([$idVenta, $idProducto]);
    $detalles = $consulta->fetchAll();
    $detalle = null;
    foreach ($detalles as $fila) {
        if ((int) $fila['id_detalle_venta'] === $idDetalle || ($idDetalle === 0 && count($detalles) === 1)) {
            $detalle = $fila;
        }
    }
    if (!$detalle) {
        throw new DomainException('Selecciona la linea exacta del producto vendido');
    }

    $tieneDetalle = devolucion_tiene_detalle($conexion);
    if (!$tieneDetalle && count($detalles) > 1) {
        throw new DomainException('Esta venta requiere la migracion de devoluciones por variante antes de registrar la devolucion');
    }
    if ($tieneDetalle && count($detalles) > 1) {
        $consulta = $conexion->prepare("SELECT COUNT(*) FROM detalle_devolucion dd
            JOIN devolucion d ON d.id_devolucion = dd.id_devolucion
            WHERE d.id_venta = ? AND dd.id_producto = ? AND d.estado = 'aprobada'
            AND dd.id_detalle_venta IS NULL");
        $consulta->execute([$idVenta, $idProducto]);
        if ((int) $consulta->fetchColumn() > 0) {
            throw new DomainException('Hay devoluciones antiguas sin variante identificada; revisalas antes de continuar');
        }
    }
    $filtro = $tieneDetalle ? ' AND (dd.id_detalle_venta = :detalle OR dd.id_detalle_venta IS NULL)' : '';
    $consulta = $conexion->prepare("SELECT COALESCE(SUM(dd.cantidad),0) AS cantidad,
        COALESCE(SUM(dd.subtotal_devuelto),0) AS importe,
        COALESCE(SUM(dd.subtotal_costo_devuelto),0) AS costo
        FROM detalle_devolucion dd JOIN devolucion d ON d.id_devolucion = dd.id_devolucion
        WHERE d.id_venta = :venta AND dd.id_producto = :producto AND d.estado = 'aprobada' $filtro");
    $parametros = [':venta'=>$idVenta, ':producto'=>$idProducto];
    if ($tieneDetalle) $parametros[':detalle'] = $detalle['id_detalle_venta'];
    $consulta->execute($parametros);
    $previo = $consulta->fetch();
    $importe = importe_devolucion($detalle['subtotal'], (int) $detalle['cantidad'], (int) $previo['cantidad'], $cantidad, $previo['importe']);
    $costo = importe_devolucion($detalle['subtotal_costo'], (int) $detalle['cantidad'], (int) $previo['cantidad'], $cantidad, $previo['costo']);

    // Bloquear el producto antes de sus variantes, como hacen ventas y reposiciones.
    $consulta = $conexion->prepare('SELECT id_producto FROM producto WHERE id_producto = ? FOR UPDATE');
    $consulta->execute([$idProducto]);
    if (!$consulta->fetch()) throw new DomainException('El producto vendido ya no existe');
    $consulta = $conexion->prepare("INSERT INTO devolucion (id_venta,id_cliente,motivo,total_devuelto,estado)
        VALUES (?,?,?,?,'aprobada') RETURNING id_devolucion");
    $consulta->execute([$idVenta, $venta['id_cliente'], $motivo, $importe]);
    $idDevolucion = $consulta->fetchColumn();
    $columna = $tieneDetalle ? ', id_detalle_venta' : '';
    $marcador = $tieneDetalle ? ', ?' : '';
    $consulta = $conexion->prepare("INSERT INTO detalle_devolucion
        (id_devolucion,id_producto,talla,cantidad,precio_unitario,costo_unitario,subtotal_devuelto,subtotal_costo_devuelto $columna)
        VALUES (?,?,?,?,?,?,?,? $marcador)");
    $parametros = [$idDevolucion,$idProducto,$detalle['talla'],$cantidad,$detalle['precio_unitario'],$detalle['costo_unitario'],$importe,$costo];
    if ($tieneDetalle) $parametros[] = $detalle['id_detalle_venta'];
    $consulta->execute($parametros); // El trigger repone solo el stock general.

    if ($detalle['talla'] !== null && $detalle['talla'] !== '') {
        if ((int) $detalle['id_producto_color'] > 0) {
            $consulta = $conexion->prepare('UPDATE producto_color_talla SET cantidad = cantidad + ? WHERE id_producto_color = ? AND UPPER(talla) = UPPER(?)');
            $consulta->execute([$cantidad, $detalle['id_producto_color'], $detalle['talla']]);
            if ($consulta->rowCount() !== 1) throw new DomainException('No se encontro la variante original para reponer el inventario');
        }
        $consulta = $conexion->prepare('UPDATE producto_talla SET cantidad = cantidad + ? WHERE id_producto = ? AND UPPER(talla) = UPPER(?)');
        $consulta->execute([$cantidad, $idProducto, $detalle['talla']]);
        if ($consulta->rowCount() !== 1) throw new DomainException('No se encontro la talla original para reponer el inventario');
    }
    $consulta = $conexion->prepare("SELECT
        (SELECT COALESCE(SUM(cantidad),0) FROM detalle_venta WHERE id_venta = :venta) AS vendidas,
        (SELECT COALESCE(SUM(dd.cantidad),0) FROM detalle_devolucion dd JOIN devolucion d ON d.id_devolucion=dd.id_devolucion
         WHERE d.id_venta = :venta AND d.estado='aprobada') AS devueltas");
    $consulta->execute([':venta'=>$idVenta]);
    $totales = $consulta->fetch();
    if ((int) $totales['vendidas'] === (int) $totales['devueltas']) {
        $consulta = $conexion->prepare("UPDATE venta SET estado='devuelta' WHERE id_venta = ?");
        $consulta->execute([$idVenta]);
    }
    return $importe;
}
