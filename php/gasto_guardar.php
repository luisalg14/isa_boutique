<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    $usuario = exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Método no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tipo = trim($_POST["tipo"] ?? "");
    $concepto = trim($_POST["concepto"] ?? "");
    $valor = floatval($_POST["valor"] ?? 0);
    $fecha = trim($_POST["fecha"] ?? date("Y-m-d"));
    $detalle = trim($_POST["detalle"] ?? "");

    $tiposPermitidos = [
        "primario",
        "secundario",
        "servicio",
        "nomina",
        "transporte",
        "publicidad",
        "empaque",
        "mantenimiento",
        "otro"
    ];

    if (!in_array($tipo, $tiposPermitidos, true) || $concepto === "" || $valor <= 0 || $fecha === "") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o invalidos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        INSERT INTO gasto_negocio (
            id_usuario,
            tipo,
            concepto,
            valor,
            fecha,
            detalle
        )
        VALUES (
            :id_usuario,
            :tipo,
            :concepto,
            :valor,
            :fecha,
            :detalle
        )
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":id_usuario" => $usuario["id_usuario"],
        ":tipo" => $tipo,
        ":concepto" => $concepto,
        ":valor" => $valor,
        ":fecha" => $fecha,
        ":detalle" => $detalle
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Gasto registrado correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
