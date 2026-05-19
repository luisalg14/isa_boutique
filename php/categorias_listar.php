<?php

require_once "conexion.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    $soloActivas = ($_GET["solo_activas"] ?? "") === "1";

    $sql = "
        SELECT
            c.id_categoria,
            c.nombre,
            c.descripcion,
            c.estado,
            COUNT(p.id_producto) AS total_productos
        FROM categoria c
        LEFT JOIN producto p
            ON c.id_categoria = p.id_categoria
    ";

    if ($soloActivas) {
        $sql .= " WHERE c.estado = TRUE ";
    }

    $sql .= "
        GROUP BY c.id_categoria, c.nombre, c.descripcion, c.estado
        ORDER BY c.nombre ASC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    echo json_encode($consulta->fetchAll(), JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error categorias_listar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudieron cargar las categorías"
    ], JSON_UNESCAPED_UNICODE);
}

?>
