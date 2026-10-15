<?php

function cliente_para_venta(PDO $conexion, array $datos) {
    if (!$conexion->inTransaction()) throw new LogicException('El cliente de la venta requiere una transaccion');
    // El telefono es un dato de contacto, nunca una prueba de identidad.
    $parametros = [$datos['nombre'], $datos['telefono'], $datos['correo'], $datos['tipo_documento'], $datos['numero_documento']];
    $consulta = $conexion->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?,0))');
    $consulta->execute(['cliente-contacto:'.$datos['telefono']]);
    $consulta = $conexion->prepare("SELECT id_cliente FROM cliente WHERE nombre=? AND telefono=?
        AND COALESCE(correo,'')=? AND COALESCE(tipo_documento,'CC')=? AND COALESCE(numero_documento,'')=?
        ORDER BY id_cliente LIMIT 1");
    $consulta->execute($parametros);
    $id = $consulta->fetchColumn();
    if ($id !== false) return (int)$id;
    $consulta = $conexion->prepare("INSERT INTO cliente(nombre,telefono,correo,tipo_documento,numero_documento)
        VALUES(?,?,NULLIF(?,''),?,NULLIF(?,'')) RETURNING id_cliente");
    $consulta->execute($parametros);
    return (int)$consulta->fetchColumn();
}
