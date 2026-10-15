<?php

function reponer_variante(PDO $conexion, $idProducto, $idColor, $nombreColor, $talla, $cantidad) {
    if (!$conexion->inTransaction()) throw new LogicException('La reposicion requiere una transaccion');
    $consulta = $conexion->prepare('SELECT id_producto_color, nombre_color FROM producto_color WHERE id_producto = ? ORDER BY id_producto_color');
    $consulta->execute([$idProducto]);
    $colores = $consulta->fetchAll();
    $color = null;
    foreach ($colores as $fila) {
        if (($idColor > 0 && (int) $fila['id_producto_color'] === $idColor)
            || ($idColor === 0 && $nombreColor !== '' && strcasecmp($nombreColor, $fila['nombre_color']) === 0)
            || ($idColor === 0 && $nombreColor === '' && count($colores) === 1)) {
            $color = $fila;
        }
    }
    if ($colores && !$color) throw new DomainException('Selecciona un color existente del producto para la reposicion');
    if (!$colores && ($idColor > 0 || $nombreColor !== '')) throw new DomainException('El color no pertenece al producto');
    if ($color) {
        $consulta = $conexion->prepare('SELECT talla FROM producto_color_talla WHERE id_producto_color = ? ORDER BY talla');
        $consulta->execute([$color['id_producto_color']]);
        $tallas = $consulta->fetchAll(PDO::FETCH_COLUMN);
        if ($talla === '' && count($tallas) === 1 && strtoupper($tallas[0]) === 'UNICA') $talla = $tallas[0];
        $consulta = $conexion->prepare('UPDATE producto_color_talla SET cantidad = cantidad + ? WHERE id_producto_color = ? AND UPPER(talla) = UPPER(?)');
        $consulta->execute([$cantidad, $color['id_producto_color'], $talla]);
        if ($consulta->rowCount() !== 1) throw new DomainException('Selecciona una talla existente del color que vas a reponer');
    }
    if ($talla !== '') {
        $consulta = $conexion->prepare('INSERT INTO producto_talla (id_producto,talla,cantidad) VALUES (?,?,?)
            ON CONFLICT (id_producto,talla) DO UPDATE SET cantidad = producto_talla.cantidad + EXCLUDED.cantidad');
        $consulta->execute([$idProducto, strtoupper($talla), $cantidad]);
    }
    return ['color'=>$color['nombre_color'] ?? null, 'talla'=>$talla];
}

function conservar_variantes_edicion(PDO $conexion, array $producto, $cantidad, $color, array $tallas) {
    $idProducto = (int) $producto['id_producto'];
    $consulta = $conexion->prepare('SELECT id_producto_color,nombre_color FROM producto_color WHERE id_producto = ? ORDER BY id_producto_color');
    $consulta->execute([$idProducto]);
    $colores = $consulta->fetchAll();
    if (count($colores) > 1) {
        if ($cantidad !== (int) $producto['cantidad'] || $tallas || $color !== ($producto['color'] ?? '')) {
            throw new DomainException('Con varios colores, conserva color y stock al editar. Usa compras y devoluciones para modificar cada variante');
        }
        return;
    }
    if (!$colores) {
        // Productos antiguos sin variantes: mantener su estructura y actualizar tallas explicitas.
        if (!$tallas && $cantidad !== (int) $producto['cantidad']) {
            $consulta = $conexion->prepare('SELECT talla FROM producto_talla WHERE id_producto = ? ORDER BY talla');
            $consulta->execute([$idProducto]);
            $existentes = $consulta->fetchAll(PDO::FETCH_COLUMN);
            if (count($existentes) > 1) throw new DomainException('El stock debe ajustarse por talla mediante compras o devoluciones');
            if ($existentes) $tallas = [$existentes[0]=>$cantidad];
        }
        if (!$tallas) return;
        $consulta = $conexion->prepare('UPDATE producto_talla SET cantidad = 0 WHERE id_producto = ?');
        $consulta->execute([$idProducto]);
    } else {
        $idColor = $colores[0]['id_producto_color'];
        $consulta = $conexion->prepare('SELECT talla FROM producto_color_talla WHERE id_producto_color = ? ORDER BY talla');
        $consulta->execute([$idColor]);
        $existentes = $consulta->fetchAll(PDO::FETCH_COLUMN);
        if (!$tallas && $cantidad !== (int) $producto['cantidad']) {
            if (count($existentes) !== 1) throw new DomainException('El stock debe ajustarse por talla mediante compras o devoluciones');
            $tallas = [$existentes[0]=>$cantidad];
        }
        $consulta = $conexion->prepare('UPDATE producto_color SET nombre_color = ? WHERE id_producto_color = ?');
        $consulta->execute([$color !== '' ? $color : $colores[0]['nombre_color'], $idColor]);
        if (!$tallas) return;
        $consulta = $conexion->prepare('UPDATE producto_color_talla SET cantidad = 0 WHERE id_producto_color = ?');
        $consulta->execute([$idColor]);
        $consulta = $conexion->prepare('UPDATE producto_talla SET cantidad = 0 WHERE id_producto = ?');
        $consulta->execute([$idProducto]);
        foreach ($tallas as $talla=>$unidades) {
            $consulta = $conexion->prepare('INSERT INTO producto_color_talla (id_producto_color,talla,cantidad) VALUES (?,?,?)
                ON CONFLICT (id_producto_color,talla) DO UPDATE SET cantidad = EXCLUDED.cantidad');
            $consulta->execute([$idColor,$talla,$unidades]);
        }
    }
    foreach ($tallas as $talla=>$unidades) {
        $consulta = $conexion->prepare('INSERT INTO producto_talla (id_producto,talla,cantidad) VALUES (?,?,?)
            ON CONFLICT (id_producto,talla) DO UPDATE SET cantidad = EXCLUDED.cantidad');
        $consulta->execute([$idProducto,$talla,$unidades]);
    }
}
