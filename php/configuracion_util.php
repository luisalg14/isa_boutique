<?php

function cargar_configuracion(): array {
    static $configuracion = null;
    if (is_array($configuracion)) {
        return $configuracion;
    }

    $configLocal = __DIR__.'/config.local.php';
    if (is_file($configLocal)) {
        $valores = require_once $configLocal;
        if (!is_array($valores)) {
            throw new RuntimeException('La configuracion local debe devolver un arreglo');
        }
        $configuracion = $valores;
    } else {
        $configuracion = [
            'app_env' => getenv('ISA_APP_ENV') ?: 'production',
            'host' => getenv('ISA_DB_HOST') ?: 'localhost',
            'port' => getenv('ISA_DB_PORT') ?: '5432',
            'database' => getenv('ISA_DB_NAME') ?: '',
            'user' => getenv('ISA_DB_USER') ?: '',
            'password' => getenv('ISA_DB_PASSWORD') ?: '',
            'sslmode' => getenv('ISA_DB_SSLMODE') ?: '',
            'options' => getenv('ISA_DB_OPTIONS') ?: ''
        ];
    }
    return $configuracion;
}
