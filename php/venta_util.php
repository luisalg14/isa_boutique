<?php

function preparar_detalle_venta(PDO $conexion, $idProducto, $idColor, $talla, $cantidad) {
    if (!$conexion->inTransaction()) throw new LogicException('La venta requiere una transaccion');
    $q=$conexion->prepare('SELECT id_producto,nombre,precio,costo_unitario,cantidad,estado FROM producto WHERE id_producto=? FOR UPDATE');
    $q->execute([$idProducto]);$producto=$q->fetch();
    if (!$producto || $producto['estado']==='inactivo') throw new DomainException('Producto no disponible');
    if ((int)$producto['cantidad']<$cantidad) throw new DomainException('Stock insuficiente para '.$producto['nombre']);
    $q=$conexion->prepare('SELECT id_producto_color,nombre_color FROM producto_color WHERE id_producto=? ORDER BY id_producto_color');
    $q->execute([$idProducto]);$colores=$q->fetchAll();$color=null;
    if ($colores) {
        if (!$idColor && count($colores)===1) $idColor=(int)$colores[0]['id_producto_color'];
        foreach($colores as $fila) if((int)$fila['id_producto_color']===$idColor) $color=$fila['nombre_color'];
        if ($color===null) throw new DomainException('El color seleccionado no pertenece al producto');
    } elseif ($idColor>0) throw new DomainException('El color seleccionado no pertenece al producto');
    $q=$conexion->prepare('SELECT talla,cantidad FROM producto_talla WHERE id_producto=? ORDER BY talla');
    $q->execute([$idProducto]);$tallas=$q->fetchAll();$tallaGeneral=null;
    foreach($tallas as $fila) if(strtoupper($fila['talla'])===$talla) $tallaGeneral=$fila;
    if ($idColor>0) {
        $q=$conexion->prepare('SELECT talla,cantidad FROM producto_color_talla WHERE id_producto_color=? AND UPPER(talla)=?');
        $q->execute([$idColor,$talla]);$variante=$q->fetch();
        if (!$variante || (int)$variante['cantidad']<$cantidad) throw new DomainException('Stock insuficiente para el color y talla seleccionados');
        if (!$tallaGeneral) throw new DomainException('El inventario agregado de la talla requiere revision');
    }
    if ($tallas && (!$tallaGeneral || (int)$tallaGeneral['cantidad']<$cantidad)) throw new DomainException('Stock insuficiente para la talla seleccionada');
    if (!$tallas && $talla!=='') throw new DomainException('El producto no tiene la talla seleccionada');
    return ['producto'=>$producto,'id_producto_color'=>$idColor,'color'=>$color,'talla'=>$tallas?$talla:null,'cantidad'=>$cantidad];
}

function descontar_tallas_venta(PDO $conexion, array $detalle) {
    if (!$conexion->inTransaction()) throw new LogicException('La venta requiere una transaccion');
    if ($detalle['talla']===null) return;
    if ($detalle['id_producto_color']>0) {
        $q=$conexion->prepare('UPDATE producto_color_talla SET cantidad=cantidad-? WHERE id_producto_color=? AND UPPER(talla)=? AND cantidad>=?');
        $q->execute([$detalle['cantidad'],$detalle['id_producto_color'],$detalle['talla'],$detalle['cantidad']]);
        if ($q->rowCount()!==1) throw new DomainException('No hay stock suficiente en la variante');
    }
    $q=$conexion->prepare('UPDATE producto_talla SET cantidad=cantidad-? WHERE id_producto=? AND UPPER(talla)=? AND cantidad>=?');
    $q->execute([$detalle['cantidad'],$detalle['producto']['id_producto'],$detalle['talla'],$detalle['cantidad']]);
    if ($q->rowCount()!==1) throw new DomainException('No hay stock suficiente en la talla');
}

function calcular_importes_venta($precio, $costo, $cantidad, $tipoDescuento='valor', $valorDescuento=0) {
    $bruto=round((float)$precio*$cantidad,2);
    $descuento=round(min(max($tipoDescuento==='porcentaje'?$bruto*$valorDescuento/100:$valorDescuento,0),$bruto),2);
    $subtotal=round($bruto-$descuento,2);
    $tarifa=19.00; // Mantiene la tarifa que utiliza el flujo existente.
    $base=round($subtotal/(1+$tarifa/100),2);
    return ['subtotal_bruto'=>$bruto,'descuento'=>$descuento,'subtotal'=>$subtotal,'base_gravable'=>$base,
        'iva'=>round($subtotal-$base,2),'tarifa_iva'=>$tarifa,'subtotal_costo'=>round((float)$costo*$cantidad,2)];
}
