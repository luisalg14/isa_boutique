<?php
require_once __DIR__.'/password_util.php';

function preparar_usuario_inicial(string $nombre, string $correo, string $rol, string $password): array {
    $nombre=trim($nombre);$correo=strtolower(trim($correo));
    if ($nombre==='' || mb_strlen($nombre)>100 || strlen($correo)>100 || !filter_var($correo,FILTER_VALIDATE_EMAIL)
        || !in_array($rol,['admin','vendedor'],true)) throw new DomainException('Nombre, correo o rol invalido');
    if (strlen($password)<12 || strlen($password)>72 || validar_password_seguro($password)!=='') {
        throw new DomainException('Elige una contrasena de 12 a 72 bytes con letras y numeros');
    }
    return ['nombre'=>$nombre,'correo'=>$correo,'rol'=>$rol,'contrasena'=>password_hash($password,PASSWORD_DEFAULT)];
}

function crear_usuario_inicial(PDO $db, array $usuario): int {
    if ($db->inTransaction()) throw new LogicException('La creacion necesita una transaccion propia');
    $db->beginTransaction();
    try {
        $db->exec("SET LOCAL lock_timeout='5s'; SET LOCAL statement_timeout='15s'");
        $q=$db->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?,0))');$q->execute(['usuario-inicial:'.$usuario['correo']]);
        $q=$db->prepare('SELECT id_usuario,estado,contrasena,rol FROM usuario_sistema WHERE LOWER(correo)=? FOR UPDATE');
        $q->execute([$usuario['correo']]);$existente=$q->fetch(PDO::FETCH_ASSOC);
        if ($existente) {
            // Solo se habilitan los marcadores inactivos de los datos iniciales; nunca una cuenta normal.
            if (filter_var($existente['estado'],FILTER_VALIDATE_BOOLEAN) || $existente['contrasena']!=='!SIN_ACCESO_INICIAL!' || $existente['rol']!==$usuario['rol']) {
                throw new DomainException('La cuenta ya existe. Usa el flujo de cambio o recuperacion de contrasena');
            }
            $q=$db->prepare('UPDATE usuario_sistema SET contrasena=?,estado=TRUE WHERE id_usuario=? RETURNING id_usuario');
            $q->execute([$usuario['contrasena'],$existente['id_usuario']]);
        } else {
            $q=$db->prepare('INSERT INTO usuario_sistema(nombre,correo,contrasena,rol,estado) VALUES(?,?,?,?,TRUE) RETURNING id_usuario');
            $q->execute([$usuario['nombre'],$usuario['correo'],$usuario['contrasena'],$usuario['rol']]);
        }
        $id=(int)$q->fetchColumn();$db->commit();return $id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
