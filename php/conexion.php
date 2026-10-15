<?php

date_default_timezone_set("America/Bogota");

if (PHP_SAPI !== "cli" && !headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
}

require_once __DIR__ . "/configuracion.php";
$config = cargar_configuracion();

if (
    empty($config["database"]) ||
    empty($config["user"]) ||
    !array_key_exists("password", $config)
) {
    http_response_code(500);
    die("Configuracion de base de datos incompleta.");
}

try {
    $dsn = "pgsql:host={$config["host"]};port={$config["port"]};dbname={$config["database"]}";

    if (!empty($config["sslmode"])) {
        $dsn .= ";sslmode={$config["sslmode"]}";
    }

    if (!empty($config["options"])) {
        $dsn .= ";options={$config["options"]}";
    }

    $conexion = new PDO(
        $dsn,
        $config["user"],
        $config["password"]
    );

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    $conexion->exec("SET search_path TO public");
    $conexion->exec("SET TIME ZONE 'America/Bogota'");

} catch (PDOException $e) {
    error_log("Error de conexion PostgreSQL: " . $e->getMessage());
    http_response_code(500);
    die("No se pudo conectar con la base de datos.");
}

?>
