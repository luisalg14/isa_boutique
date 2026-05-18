<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    $usuario = exigir_roles(["admin"]);

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Metodo no permitido"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idTrabajador = intval($_POST["id_trabajador"] ?? 0);
    $tipoPago = trim($_POST["tipo_pago"] ?? "");
    $valor = floatval($_POST["valor"] ?? 0);
    $fecha = trim($_POST["fecha"] ?? date("Y-m-d"));
    $detalle = trim($_POST["detalle"] ?? "");

    $tiposPermitidos = ["salario", "comision", "adelanto", "bono", "deduccion", "otro"];

    if ($idTrabajador <= 0 || !in_array($tipoPago, $tiposPermitidos, true) || $valor <= 0 || $fecha === "") {
        echo json_encode([
            "error" => true,
            "mensaje" => "Datos incompletos o invalidos"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlTrabajador = "
        SELECT id_trabajador
        FROM trabajador
        WHERE id_trabajador = :id_trabajador
        AND estado = TRUE
        LIMIT 1
    ";

    $consultaTrabajador = $conexion->prepare($sqlTrabajador);
    $consultaTrabajador->execute([
        ":id_trabajador" => $idTrabajador
    ]);

    if (!$consultaTrabajador->fetch()) {
        echo json_encode([
            "error" => true,
            "mensaje" => "Trabajador no encontrado o inactivo"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        INSERT INTO pago_trabajador (
            id_trabajador,
            id_usuario,
            tipo_pago,
            valor,
            fecha,
            detalle
        )
        VALUES (
            :id_trabajador,
            :id_usuario,
            :tipo_pago,
            :valor,
            :fecha,
            :detalle
        )
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute([
        ":id_trabajador" => $idTrabajador,
        ":id_usuario" => $usuario["id_usuario"],
        ":tipo_pago" => $tipoPago,
        ":valor" => $valor,
        ":fecha" => $fecha,
        ":detalle" => $detalle
    ]);

    echo json_encode([
        "error" => false,
        "mensaje" => "Pago registrado correctamente"
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
