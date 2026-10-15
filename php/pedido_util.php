<?php
require_once 'cliente_util.php';
require_once 'venta_util.php';

class PedidoConflictoException extends DomainException {}

function texto_pedido(array $entrada,$campo,$maximo,$respaldo='') {
    $valor=$entrada[$campo]??$respaldo;
    if (!is_string($valor) || mb_strlen($valor)>$maximo) throw new DomainException('Datos del cliente invalidos');
    return trim($valor);
}

function normalizar_pedido_carrito(array $entrada) {
    $cliente=['nombre'=>texto_pedido($entrada,'cliente',100),'telefono'=>texto_pedido($entrada,'telefono',20),
        'correo'=>strtolower(texto_pedido($entrada,'correo',150)),'tipo_documento'=>strtoupper(texto_pedido($entrada,'tipo_documento',20,'CC')),
        'numero_documento'=>texto_pedido($entrada,'numero_documento',30)];
    $medio=texto_pedido($entrada,'medio_pago',30);$entrega=texto_pedido($entrada,'tipo_entrega',30);
    if ($cliente['nombre']==='' || $cliente['telefono']==='' || ($cliente['correo']!==''&&!filter_var($cliente['correo'],FILTER_VALIDATE_EMAIL))
        || !in_array($cliente['tipo_documento'],['CC','CE','NIT','PASAPORTE','OTRO'],true)
        || !in_array($medio,['efectivo','transferencia','tarjeta_debito','tarjeta_credito'],true)
        || !in_array($entrega,['recoger_tienda','envio_local','envio_nacional'],true)) throw new DomainException('Completa los datos del pedido');
    $items=$entrada['items']??[];
    if (is_string($items)) {
        if (strlen($items)>65536) throw new DomainException('El carrito supera el limite permitido');
        try {$items=json_decode($items,true,64,JSON_THROW_ON_ERROR);} catch(JsonException $e) {throw new DomainException('El carrito no es valido');}
    }
    if (!is_array($items)||!array_is_list($items)||count($items)<1||count($items)>100) throw new DomainException('El carrito debe tener entre 1 y 100 entradas');
    $lineas=[];
    foreach($items as $item) {
        if(!is_array($item)) throw new DomainException('Producto de carrito invalido');
        $id=filter_var($item['id_producto']??null,FILTER_VALIDATE_INT);
        $color=filter_var(($item['id_producto_color']??'')===''?0:$item['id_producto_color'],FILTER_VALIDATE_INT);
        $cantidad=filter_var($item['cantidad']??null,FILTER_VALIDATE_INT);
        $talla=strtoupper(texto_pedido($item,'talla',10));
        if($id===false||$id<1||$color===false||$color<0||$cantidad===false||$cantidad<1||$cantidad>2147483647) throw new DomainException('Cantidad o producto invalido');
        $clave=$id.'|'.$color.'|'.$talla;
        if(!isset($lineas[$clave])) $lineas[$clave]=['id_producto'=>$id,'id_producto_color'=>$color,'talla'=>$talla,'cantidad'=>0];
        $lineas[$clave]['cantidad']+=$cantidad;
        if($lineas[$clave]['cantidad']>2147483647) throw new DomainException('Cantidad fuera de rango');
    }
    $lineas=array_values($lineas);
    usort($lineas,function($a,$b){return [$a['id_producto'],$a['id_producto_color'],$a['talla']]<=>[$b['id_producto'],$b['id_producto_color'],$b['talla']];});
    return ['cliente'=>$cliente,'medio_pago'=>$medio,'tipo_entrega'=>$entrega,'items'=>$lineas];
}

function respuesta_pedido_carrito(array $venta,$reutilizado) {
    return ['error'=>false,'id_venta'=>(int)$venta['id_venta'],'total'=>(float)$venta['total'],'estado'=>$venta['estado'],
        'tipo_entrega'=>$venta['tipo_entrega'],'reutilizado'=>$reutilizado,
        'mensaje'=>'Pedido #'.$venta['id_venta'].' registrado. '.($venta['estado']==='pendiente'?'Queda pendiente de confirmacion.':'Estado: '.$venta['estado'].'.')];
}

function registrar_pedido_carrito(PDO $conexion,array $entrada,$clave,$idUsuario=null) {
    if(!is_string($clave)||!preg_match('/^[a-f0-9]{32,64}$/D',$clave)) throw new DomainException('Falta una clave valida para confirmar el pedido');
    $pedido=normalizar_pedido_carrito($entrada);
    $huella=hash('sha256',json_encode($pedido,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    if($conexion->inTransaction()) throw new LogicException('El pedido necesita una transaccion propia');
    $conexion->beginTransaction();
    try {
        $conexion->exec("SET LOCAL lock_timeout='5s'");
        $conexion->exec("SET LOCAL statement_timeout='30s'");
        $q=$conexion->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?,0))');$q->execute(['pedido-carrito:'.$clave]);
        $q=$conexion->prepare('SELECT id_venta,total,estado,tipo_entrega,huella_pedido FROM venta WHERE clave_pedido=?');
        $q->execute([$clave]);$previa=$q->fetch();
        if($previa) {
            if(!hash_equals($previa['huella_pedido'],$huella)) throw new PedidoConflictoException('Esta clave corresponde a otro contenido de pedido. Revisa el pedido anterior antes de continuar');
            $conexion->commit();return respuesta_pedido_carrito($previa,true);
        }
        // Bloquear todos los productos en el mismo orden que compras y ventas internas.
        $ids=array_values(array_unique(array_column($pedido['items'],'id_producto')));
        $q=$conexion->prepare('SELECT id_producto FROM producto WHERE id_producto IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id_producto FOR UPDATE');
        $q->execute($ids);$q->fetchAll();
        $detalles=[];$reservas=[];$totales=['subtotal_bruto'=>0,'base_gravable'=>0,'iva'=>0,'subtotal'=>0];
        foreach($pedido['items'] as $item) {
            $d=preparar_detalle_venta($conexion,$item['id_producto'],$item['id_producto_color'],$item['talla'],$item['cantidad']);
            $reservas[$item['id_producto']]=($reservas[$item['id_producto']]??0)+$item['cantidad'];
            if($reservas[$item['id_producto']]>(int)$d['producto']['cantidad']) throw new DomainException('El carrito supera el stock total del producto');
            $d['importes']=calcular_importes_venta($d['producto']['precio'],$d['producto']['costo_unitario'],$d['cantidad']);
            foreach($totales as $campo=>$valor) $totales[$campo]=round($valor+$d['importes'][$campo],2);
            $detalles[]=$d;
        }
        if($idUsuario===null) {
            $idUsuario=$conexion->query("SELECT id_usuario FROM usuario_sistema WHERE rol='admin' AND estado=TRUE ORDER BY id_usuario LIMIT 1")->fetchColumn();
            if($idUsuario===false) throw new RuntimeException('No hay responsable configurado para pedidos web');
        }
        $idCliente=cliente_para_venta($conexion,$pedido['cliente']);
        $q=$conexion->prepare("INSERT INTO venta(id_cliente,id_usuario,medio_pago,canal_venta,tipo_entrega,subtotal_bruto,descuento,base_gravable,iva,tarifa_iva,precio_incluye_iva,total,estado,clave_pedido,huella_pedido)
            VALUES(?,?,?,'pagina_web',?,?,0,?,?,?,TRUE,?,'pendiente',?,?) RETURNING id_venta,total,estado,tipo_entrega");
        $q->execute([$idCliente,$idUsuario,$pedido['medio_pago'],$pedido['tipo_entrega'],$totales['subtotal_bruto'],
            $totales['base_gravable'],$totales['iva'],$detalles[0]['importes']['tarifa_iva'],$totales['subtotal'],$clave,$huella]);$venta=$q->fetch();
        $q=$conexion->prepare('INSERT INTO detalle_venta(id_venta,id_producto,id_producto_color,color,talla,cantidad,precio_unitario,costo_unitario,descuento,base_gravable,iva,tarifa_iva,precio_incluye_iva,subtotal,subtotal_costo)
            VALUES(?,?,?,?,?,?,?,?,0,?,?,?,TRUE,?,?)');
        foreach($detalles as $d) {
            $i=$d['importes'];
            $q->execute([$venta['id_venta'],$d['producto']['id_producto'],$d['id_producto_color']?:null,$d['color'],$d['talla'],$d['cantidad'],
                $d['producto']['precio'],$d['producto']['costo_unitario'],$i['base_gravable'],$i['iva'],$i['tarifa_iva'],$i['subtotal'],$i['subtotal_costo']]);
            // El trigger existente descuenta el stock general; aqui solo las tallas.
            descontar_tallas_venta($conexion,$d);
        }
        $conexion->commit();return respuesta_pedido_carrito($venta,false);
    } catch(Throwable $e) {
        if($conexion->inTransaction()) $conexion->rollBack();
        throw $e;
    }
}
