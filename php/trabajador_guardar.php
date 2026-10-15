<?php

require_once "conexion.php";
require_once "auth_guard.php";
require_once "nomina_util.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nombre = trim($_POST["nombre"] ?? "");
    $documento = trim($_POST["documento"] ?? "");
    $telefono = trim($_POST["telefono"] ?? "");
    $cargo = trim($_POST["cargo"] ?? "");
    $salario = validar_pago_fijo('salario', $_POST["salario_base"] ?? null);
    $fechaIngreso = trim($_POST["fecha_ingreso"] ?? date("Y-m-d"));

    if ($nombre === "" || $cargo === "" || $salario < 0 || $fechaIngreso === "") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o invalidos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        INSERT INTO trabajador (
            nombre,
            documento,
            telefono,
            cargo,
            salario_base,
            fecha_ingreso
        )
        VALUES (
            :nombre,
            :documento,
            :telefono,
            :cargo,
            :salario_base,
            :fecha_ingreso
        )
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":nombre" => $nombre,
        ":documento" => $documento,
        ":telefono" => $telefono,
        ":cargo" => $cargo,
        ":salario_base" => $salario,
        ":fecha_ingreso" => $fechaIngreso
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Trabajador registrado correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Error trabajador_guardar: ' . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => $e instanceof DomainException ? $e->getMessage() : 'No se pudo registrar el trabajador'
    ], JSON_UNESCAPED_UNICODE);
}

?>
