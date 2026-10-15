<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/../php/usuario_inicial_util.php';
try {
    $password=getenv('ISA_USUARIO_PASSWORD');
    if (!is_string($password) || $password==='') throw new DomainException('Define ISA_USUARIO_PASSWORD solo para esta ejecucion');
    $usuario=preparar_usuario_inicial($argv[1]??'',$argv[2]??'',$argv[3]??'',$password);
    unset($password);
    require __DIR__.'/../php/conexion.php';
    $id=crear_usuario_inicial($conexion,$usuario);
    echo 'Usuario inicial creado o habilitado. ID: '.$id.PHP_EOL;
} catch (DomainException $e) {
    fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);
} catch (Throwable $e) {
    fwrite(STDERR,'No se pudo crear el usuario inicial; no se guardaron cambios.'.PHP_EOL);exit(1);
}
