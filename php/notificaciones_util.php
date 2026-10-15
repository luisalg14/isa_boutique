<?php
require_once 'correo_error.php';

function reservar_notificaciones(PDO $conexion, $limite) {
    if ($conexion->inTransaction()) throw new LogicException('El procesador necesita una conexion sin transaccion activa');
    $conexion->beginTransaction();
    try {
        $conexion->exec("UPDATE notificacion_correo SET estado='fallida' WHERE estado='pendiente' AND intentos>=3");
        $consulta=$conexion->prepare("WITH elegidas AS (
            SELECT id_notificacion FROM notificacion_correo
            WHERE estado='pendiente' AND intentos<3
            ORDER BY fecha_creacion,id_notificacion LIMIT :limite FOR UPDATE SKIP LOCKED
        ) UPDATE notificacion_correo n SET estado='procesando',intentos=n.intentos+1,
            error_ultimo='Envio reservado. Si el proceso se interrumpe, revisar antes de reenviar.'
        FROM elegidas e WHERE n.id_notificacion=e.id_notificacion
        RETURNING n.id_notificacion,n.destinatario,n.asunto,n.cuerpo_html,n.cuerpo_texto,n.intentos");
        $consulta->bindValue(':limite',max(1,min(20,(int)$limite)),PDO::PARAM_INT);
        $consulta->execute();
        $lote=$consulta->fetchAll();
        $conexion->commit(); // La reserva queda persistida antes de cualquier llamada externa.
        return $lote;
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $e;
    }
}

function procesar_notificaciones(PDO $conexion, $limite, callable $enviar) {
    $lote=reservar_notificaciones($conexion,$limite);
    $enviadas=0;$fallidas=0;$revision=0;
    foreach ($lote as $notificacion) {
        $estado='enviada';$error=null;
        try {
            if ($enviar($notificacion)!==true) throw new RuntimeException('No se obtuvo confirmacion de aceptacion del correo');
        } catch (CorreoNoAceptadoException $e) {
            $estado=$e->esReintentable() && (int)$notificacion['intentos']<3 ? 'pendiente' : 'fallida';
            $error=substr($e->getMessage(),0,500);
            $fallidas++;
        } catch (Throwable $e) {
            $estado='revision';
            $error='Resultado de envio incierto. Comprobar con el proveedor antes de reenviar. '.substr($e->getMessage(),0,350);
            $revision++;
        }
        // Un fallo al guardar despues del envio NO vuelve a poner el mensaje en pendiente.
        $consulta=$conexion->prepare("UPDATE notificacion_correo SET estado=:estado,error_ultimo=:error,
            fecha_envio=CASE WHEN :estado='enviada' THEN NOW() ELSE fecha_envio END
            WHERE id_notificacion=:id AND estado='procesando'");
        $consulta->execute([':estado'=>$estado,':error'=>$error,':id'=>$notificacion['id_notificacion']]);
        if ($consulta->rowCount()!==1) throw new RuntimeException('No se pudo confirmar el resultado de una notificacion reservada');
        if ($estado==='enviada') $enviadas++;
    }
    $totales=$conexion->query("SELECT COUNT(*) FILTER(WHERE estado='pendiente') AS pendientes,
        COUNT(*) FILTER(WHERE estado='procesando') AS en_proceso,
        COUNT(*) FILTER(WHERE estado='revision') AS requieren_revision,
        COUNT(*) FILTER(WHERE estado='fallida') AS fallidas_total FROM notificacion_correo")->fetch();
    return ['error'=>false,'mensaje'=>'Notificaciones procesadas','enviadas'=>$enviadas,'fallidas'=>$fallidas,
        'revision'=>$revision]+array_map('intval',$totales);
}
