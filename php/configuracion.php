<?php

$configLocal = __DIR__ . "/config.local.php";

if (file_exists($configLocal)) {
    return require $configLocal;
}

return [
    "host" => getenv("ISA_DB_HOST") ?: "localhost",
    "port" => getenv("ISA_DB_PORT") ?: "5432",
    "database" => getenv("ISA_DB_NAME") ?: "",
    "user" => getenv("ISA_DB_USER") ?: "",
    "password" => getenv("ISA_DB_PASSWORD") ?: "",
    "sslmode" => getenv("ISA_DB_SSLMODE") ?: "",
    "options" => getenv("ISA_DB_OPTIONS") ?: ""
];
