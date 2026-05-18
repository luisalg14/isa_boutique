<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $sql = "
        SELECT
            id_proveedor,
            nombre,
            telefono,
            ciudad,
            producto_suministra,
            estado,
            fecha_registro
        FROM proveedor
        ORDER BY estado DESC, nombre ASC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(["error" => true, "mensaje" => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

?>
