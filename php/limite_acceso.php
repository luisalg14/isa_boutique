<?php

function reglas_limite_acceso(string $accion, string $correo, array $servidor): array {
    $politicas = [
        'login' => ['segundos'=>300, 'cuenta'=>5, 'ip'=>30],
        'recuperar' => ['segundos'=>900, 'cuenta'=>3, 'ip'=>10],
        'confirmar' => ['segundos'=>900, 'ip'=>30]
    ];
    if (!isset($politicas[$accion])) throw new InvalidArgumentException('Accion de acceso desconocida');
    $politica = $politicas[$accion];
    // No confiar en X-Forwarded-For enviado por el cliente. Configurar el proxy en el servidor.
    $ip = $servidor['REMOTE_ADDR'] ?? '';
    $ip = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? bin2hex(inet_pton($ip)) : 'sin-direccion';
    $reglas = [['clave'=>hash('sha256', $accion.':ip:'.$ip), 'limite'=>$politica['ip'], 'segundos'=>$politica['segundos']]];
    $correo = strtolower(trim($correo));
    if (isset($politica['cuenta']) && $correo !== '') {
        $reglas[] = ['clave'=>hash('sha256', $accion.':cuenta:'.$correo), 'limite'=>$politica['cuenta'], 'segundos'=>$politica['segundos']];
    }
    usort($reglas, function ($a, $b) { return strcmp($a['clave'], $b['clave']); });
    return $reglas;
}

function consumir_limite_acceso(PDO $conexion, string $accion, string $correo, array $servidor): array {
    if ($conexion->inTransaction()) throw new LogicException('El limite necesita una transaccion propia');
    $reglas = reglas_limite_acceso($accion, $correo, $servidor);
    $conexion->beginTransaction();
    try {
        $conexion->exec("SET LOCAL lock_timeout='3s'; SET LOCAL statement_timeout='10s'");
        // Limpieza acotada; no bloquea filas que estan usando otras solicitudes.
        $conexion->exec("DELETE FROM limite_acceso WHERE clave IN (
            SELECT clave FROM limite_acceso WHERE vence_en < NOW() - INTERVAL '1 day'
            ORDER BY vence_en LIMIT 100 FOR UPDATE SKIP LOCKED)");
        $q = $conexion->prepare("INSERT INTO limite_acceso(clave,intentos,vence_en)
            VALUES(:clave,1,NOW()+CAST(:duracion AS INTEGER)*INTERVAL '1 second')
            ON CONFLICT(clave) DO UPDATE SET
                intentos=CASE WHEN limite_acceso.vence_en<=NOW() THEN 1 ELSE LEAST(limite_acceso.intentos+1,CAST(:tope AS INTEGER)) END,
                vence_en=CASE WHEN limite_acceso.vence_en<=NOW() THEN EXCLUDED.vence_en ELSE limite_acceso.vence_en END
            RETURNING intentos,CEIL(EXTRACT(EPOCH FROM vence_en-NOW()))::INTEGER AS espera");
        $espera = 0;
        foreach ($reglas as $regla) {
            $q->execute([':clave'=>$regla['clave'], ':duracion'=>$regla['segundos'], ':tope'=>$regla['limite']+1]);
            $fila = $q->fetch(PDO::FETCH_ASSOC);
            if ((int)$fila['intentos'] > $regla['limite']) $espera = max($espera, (int)$fila['espera']);
        }
        // Confirmar tambien las solicitudes bloqueadas: cambiar cookies no libera la cuota.
        $conexion->commit();
        return ['permitido'=>$espera===0, 'espera'=>$espera];
    } catch (Throwable $e) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $e;
    }
}

function limitar_solicitud_acceso(PDO $conexion, string $accion, string $correo): void {
    try {
        $limite = consumir_limite_acceso($conexion, $accion, $correo, $_SERVER);
    } catch (Throwable $e) {
        error_log('No se pudo comprobar el limite de acceso: '.get_class($e));
        responder_json_error('No se pudo comprobar el acceso. Intenta mas tarde.', 503);
    }
    if (!$limite['permitido']) {
        header('Retry-After: '.$limite['espera']);
        responder_json_error('Demasiados intentos. Espera unos minutos e intenta de nuevo.', 429);
    }
}
