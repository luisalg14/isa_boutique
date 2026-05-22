<?php

require_once "conexion.php";

try {
    $consultaColor = $conexion->query("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'producto'
            AND column_name = 'color'
        ) AS existe
    ");
    $productoTieneColor = filter_var($consultaColor->fetch()["existe"], FILTER_VALIDATE_BOOLEAN);
    $campoColor = $productoTieneColor ? "p.color" : "'' AS color";
    $grupoColor = $productoTieneColor ? ", p.color" : "";

    $consultaCodigoBarras = $conexion->query("
        SELECT EXISTS (
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = 'producto_color_talla'
            AND column_name = 'codigo_barras'
        ) AS existe
    ");
    $tallaTieneCodigoBarras = filter_var($consultaCodigoBarras->fetch()["existe"], FILTER_VALIDATE_BOOLEAN);
    $campoCodigoBarrasTalla = $tallaTieneCodigoBarras
        ? "pct.codigo_barras"
        : "(p.codigo || '-V' || LPAD(pct.id_producto_color_talla::TEXT, 4, '0'))";

    $sql = "
        SELECT
            p.id_producto,
            p.codigo,
            p.nombre,
            p.marca,
            $campoColor,
            c.nombre AS categoria,
            p.precio,
            p.costo_unitario,
            p.cantidad,
            p.estado,
            p.imagen,
            COALESCE((
                SELECT json_agg(
                    json_build_object(
                        'ruta', pi.ruta,
                        'principal', pi.es_principal,
                        'orden', pi.orden
                    )
                    ORDER BY pi.es_principal DESC, pi.orden ASC, pi.id_producto_imagen ASC
                )
                FROM producto_imagen pi
                WHERE pi.id_producto = p.id_producto
            ), '[]') AS imagenes,
            COALESCE((
                SELECT json_agg(
                    json_build_object(
                        'id_producto_color', pc.id_producto_color,
                        'color', pc.nombre_color,
                        'hex', pc.codigo_hex,
                        'tallas', COALESCE((
                            SELECT json_agg(
                                json_build_object(
                                    'talla', pct.talla,
                                    'cantidad', pct.cantidad,
                                    'codigo_barras', $campoCodigoBarrasTalla
                                )
                                ORDER BY pct.talla
                            )
                            FROM producto_color_talla pct
                            WHERE pct.id_producto_color = pc.id_producto_color
                        ), '[]')
                    )
                    ORDER BY pc.orden ASC, pc.nombre_color ASC
                )
                FROM producto_color pc
                WHERE pc.id_producto = p.id_producto
            ), '[]') AS colores,
            COALESCE(
                json_agg(
                    json_build_object(
                        'talla', pt.talla,
                        'cantidad', pt.cantidad
                    )
                    ORDER BY pt.talla
                ) FILTER (WHERE pt.id_producto_talla IS NOT NULL),
                '[]'
            ) AS tallas
        FROM producto p
        INNER JOIN categoria c
            ON p.id_categoria = c.id_categoria
        LEFT JOIN producto_talla pt
            ON p.id_producto = pt.id_producto
        GROUP BY
            p.id_producto,
            p.codigo,
            p.nombre,
            p.marca,
            c.nombre,
            p.precio,
            p.costo_unitario,
            p.cantidad,
            p.estado,
            p.imagen
            $grupoColor
        ORDER BY p.id_producto ASC
    ";

    $consulta = $conexion->prepare($sql);
    $consulta->execute();

    $productos = $consulta->fetchAll();

    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    header("Content-Type: application/json; charset=UTF-8");
    error_log("Error productos_listar: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "mensaje" => "No se pudieron cargar los productos"
    ]);
}

?>
