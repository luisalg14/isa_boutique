<?php

require_once "conexion.php";
require_once "auth_guard.php";

header("Content-Type: application/json; charset=UTF-8");

try {
    exigir_roles(["admin"]);

    $accion = trim($_GET["accion"] ?? "");
    $usuario = intval($_GET["usuario"] ?? 0);
    $desde = trim($_GET["desde"] ?? "");
    $hasta = trim($_GET["hasta"] ?? "");
    $buscar = trim($_GET["buscar"] ?? "");

    $condiciones = [];
    $parametros = [];

    if ($accion !== "") {
        $condiciones[] = "a.accion = :accion";
        $parametros[":accion"] = $accion;
    }

    if ($usuario > 0) {
        $condiciones[] = "a.id_usuario = :id_usuario";
        $parametros[":id_usuario"] = $usuario;
    }

    if ($desde !== "" && preg_match("/^\d{4}-\d{2}-\d{2}$/", $desde)) {
        $condiciones[] = "a.fecha >= :desde";
        $parametros[":desde"] = $desde . " 00:00:00";
    }

    if ($hasta !== "" && preg_match("/^\d{4}-\d{2}-\d{2}$/", $hasta)) {
        $condiciones[] = "a.fecha <= :hasta";
        $parametros[":hasta"] = $hasta . " 23:59:59";
    }

    if ($buscar !== "") {
        $condiciones[] = "(
            LOWER(COALESCE(a.accion, '')) LIKE :buscar OR
            LOWER(COALESCE(a.entidad, '')) LIKE :buscar OR
            LOWER(COALESCE(a.id_entidad, '')) LIKE :buscar OR
            LOWER(COALESCE(a.detalle::text, '')) LIKE :buscar OR
            LOWER(COALESCE(u.nombre, '')) LIKE :buscar OR
            LOWER(COALESCE(u.correo, '')) LIKE :buscar
        )";
        $parametros[":buscar"] = "%" . strtolower($buscar) . "%";
    }

    $where = count($condiciones) > 0 ? "WHERE " . implode(" AND ", $condiciones) : "";

    $sql = "
        SELECT
            a.fecha,
            a.accion,
            a.entidad,
            a.id_entidad,
            a.detalle,
            a.ip,
            a.user_agent,
            a.rol,
            a.id_usuario,
            COALESCE(u.nombre, 'Sistema') AS usuario_nombre,
            COALESCE(u.correo, '') AS usuario_correo
        FROM auditoria_evento a
        LEFT JOIN usuario_sistema u
            ON u.id_usuario = a.id_usuario
        $where
        ORDER BY a.fecha DESC
        LIMIT 300
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute($parametros);

    echo json_encode([
        "error" => false,
        "eventos" => $consulta->fetchAll()
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Error auditoria_listar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudo cargar la auditoria"
    ], JSON_UNESCAPED_UNICODE);
}

?>
