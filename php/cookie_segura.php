<?php

function opciones_cookie_sesion(array $servidor, bool $esCli=false, string $entorno='production'): array {
    $https=(!empty($servidor['HTTPS']) && strtolower((string)$servidor['HTTPS'])!=='off')
        || (string)($servidor['SERVER_PORT']??'')==='443';
    $opciones=['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>true,'httponly'=>true,'samesite'=>'Lax'];
    if ($https) return $opciones;
    // Solo analizar el nombre recibido, sin construir una URL con protocolo HTTP.
    $host=parse_url('//'.($servidor['HTTP_HOST']??''),PHP_URL_HOST);
    $local=in_array($servidor['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
        && in_array(strtolower((string)$host),['localhost','127.0.0.1','[::1]'],true);
    if (!$esCli && !($entorno==='local' && $local)) throw new DomainException('El acceso requiere HTTPS fuera del desarrollo local autorizado');
    // Se conserva Secure tambien en local. Navegadores compatibles permiten
    // cookies Secure en localhost; otros entornos de desarrollo requieren HTTPS.
    return $opciones;
}

function opciones_cookie_actuales(): array {
    if (PHP_SAPI==='cli') return opciones_cookie_sesion($_SERVER,true);
    require_once __DIR__.'/configuracion.php';
    $configCookie=cargar_configuracion();
    $entorno=$configCookie['app_env']??(getenv('ISA_APP_ENV')?:'production');
    return opciones_cookie_sesion($_SERVER,false,is_string($entorno)?$entorno:'production');
}
