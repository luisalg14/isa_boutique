<?php

$host = "localhost";
$puerto = "5432";
$base_datos = "isa_boutiquevs";
$usuario = "postgres";
$contrasena = "123456";

try {
    $conexion = new PDO(
        "pgsql:host=$host;port=$puerto;dbname=$base_datos",
        $usuario,
        $contrasena
    );

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

?>