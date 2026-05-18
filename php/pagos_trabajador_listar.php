<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $sql = "
        SELECT
            p.id_pago_trabajador,
            t.nombre AS trabajador,
            t.cargo,
            p.tipo_pago,
            p.valor,
            p.fecha,
            p.detalle,
            u.nombre AS usuario
        FROM pago_trabajador p
        INNER JOIN trabajador t
            ON p.id_trabajador = t.id_trabajador
        LEFT JOIN usuario_sistema u
            ON p.id_usuario = u.id_usuario
        ORDER BY p.fecha DESC, p.id_pago_trabajador DESC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

?>
