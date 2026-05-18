<?php

$configLocal = __DIR__ . "/config.local.php";

if (file_exists($configLocal)) {
    $config = require $configLocal;
} else {
    $config = [
        "host" => getenv("ISA_DB_HOST") ?: "localhost",
        "port" => getenv("ISA_DB_PORT") ?: "5432",
        "database" => getenv("ISA_DB_NAME") ?: "",
        "user" => getenv("ISA_DB_USER") ?: "",
        "password" => getenv("ISA_DB_PASSWORD") ?: ""
    ];
}

if (
    empty($config["database"]) ||
    empty($config["user"]) ||
    !array_key_exists("password", $config)
) {
    http_response_code(500);
    die("Configuracion de base de datos incompleta.");
}

try {
    $conexion = new PDO(
        "pgsql:host={$config["host"]};port={$config["port"]};dbname={$config["database"]}",
        $config["user"],
        $config["password"]
    );

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error de conexion PostgreSQL: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar con la base de datos.");
}

?>
