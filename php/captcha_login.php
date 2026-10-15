<?php

require_once __DIR__ . "/configuracion.php";
$config = cargar_configuracion();
require_once "auth_guard.php";
require_once "captcha_util.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("X-Content-Type-Options: nosniff");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "GET") {
        http_response_code(405);
        echo json_encode([
            "error" => true,
            "mensaje" => "Metodo no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $numeroA = random_int(2, 9);
    $numeroB = random_int(1, 9);

    $respuesta = (string) ($numeroA + $numeroB);

    echo json_encode([
        "error" => false,
        "pregunta" => $numeroA . " + " . $numeroB,
        "token" => crear_captcha_token($respuesta)
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Error al generar captcha: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo generar la verificacion"
    ], JSON_UNESCAPED_UNICODE);
}

?>
