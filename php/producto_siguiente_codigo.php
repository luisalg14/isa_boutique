<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

function siguiente_codigo_producto(PDO $conexion) {
    $consulta = $conexion->query("
        SELECT codigo
        FROM producto
        WHERE codigo ~ '^[0-9]{2}-[A-Z]$'
    ");

    $mayorIndice = 0;

    foreach ($consulta as $fila) {
        if (!preg_match("/^([0-9]{2})-([A-Z])$/", $fila["codigo"], $partes)) {
            continue;
        }

        $numero = intval($partes[1]);
        $letra = ord($partes[2]) - ord("A");

        if ($numero < 1 || $numero > 99 || $letra < 0 || $letra > 25) {
            continue;
        }

        $indice = ($letra * 99) + $numero;
        $mayorIndice = max($mayorIndice, $indice);
    }

    $siguienteIndice = $mayorIndice + 1;

    if ($siguienteIndice > 99 * 26) {
        throw new Exception("Se agotó la secuencia de códigos de producto.");
    }

    $letraIndice = intdiv($siguienteIndice - 1, 99);
    $numero = (($siguienteIndice - 1) % 99) + 1;
    $letra = chr(ord("A") + $letraIndice);

    return str_pad((string) $numero, 2, "0", STR_PAD_LEFT) . "-" . $letra;
}

try {
    exigir_roles(["admin"]);

    echo json_encode([
        "error" => false,
        "codigo" => siguiente_codigo_producto($conexion)
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
