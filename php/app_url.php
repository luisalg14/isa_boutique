<?php

function url_base_configurada(array $config): string {
    $url = $config['app_url'] ?? (getenv('ISA_APP_URL') ?: 'http://localhost/isa_boutique');
    if (!is_string($url) || preg_match('/[\s\\\\]/', $url)) throw new RuntimeException('URL de aplicacion invalida');
    $partes = parse_url($url);
    if (!$partes || empty($partes['host']) || isset($partes['user']) || isset($partes['pass']) || isset($partes['query']) || isset($partes['fragment'])) {
        throw new RuntimeException('URL de aplicacion invalida');
    }
    $esLocal = in_array(strtolower($partes['host']), ['localhost','127.0.0.1','[::1]'], true);
    if (($partes['scheme'] ?? '') !== 'https' && !(($partes['scheme'] ?? '') === 'http' && $esLocal)) {
        throw new RuntimeException('La URL publica necesita HTTPS');
    }
    return rtrim($url, '/');
}
