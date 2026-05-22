-- ============================================================
-- ISA BOUTIQUE - OBJETOS AVANZADOS PARA EXPOSICION POSTGRESQL
-- Base de datos: PostgreSQL
-- Proposito: demostrar relaciones, validaciones, vistas,
-- funciones de ventana, funciones, procedimientos, cursores,
-- triggers y tabla de auditoria.
-- ============================================================

-- ============================================================
-- 1. CONSULTAS DE ESQUEMA, RELACIONES Y VALIDACIONES
-- ============================================================

CREATE OR REPLACE VIEW vw_diccionario_tablas AS
SELECT
    c.table_name AS tabla,
    c.column_name AS columna,
    c.data_type AS tipo_dato,
    c.is_nullable AS permite_nulo,
    c.column_default AS valor_por_defecto
FROM information_schema.columns c
WHERE c.table_schema = 'public'
ORDER BY c.table_name, c.ordinal_position;

CREATE OR REPLACE VIEW vw_relaciones_foraneas AS
SELECT
    tc.table_name AS tabla_origen,
    kcu.column_name AS columna_origen,
    ccu.table_name AS tabla_destino,
    ccu.column_name AS columna_destino,
    tc.constraint_name AS nombre_relacion
FROM information_schema.table_constraints tc
INNER JOIN information_schema.key_column_usage kcu
    ON tc.constraint_name = kcu.constraint_name
    AND tc.table_schema = kcu.table_schema
INNER JOIN information_schema.constraint_column_usage ccu
    ON ccu.constraint_name = tc.constraint_name
    AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY'
AND tc.table_schema = 'public'
ORDER BY tc.table_name, kcu.column_name;

CREATE OR REPLACE VIEW vw_validaciones_check AS
SELECT
    tc.table_name AS tabla,
    tc.constraint_name AS validacion,
    cc.check_clause AS regla
FROM information_schema.table_constraints tc
INNER JOIN information_schema.check_constraints cc
    ON tc.constraint_name = cc.constraint_name
WHERE tc.constraint_type = 'CHECK'
AND tc.table_schema = 'public'
ORDER BY tc.table_name, tc.constraint_name;

-- Consultas para mostrar:
-- SELECT * FROM vw_diccionario_tablas;
-- SELECT * FROM vw_relaciones_foraneas;
-- SELECT * FROM vw_validaciones_check;


-- ============================================================
-- 2. VISTAS DE NEGOCIO
-- ============================================================

CREATE OR REPLACE VIEW vw_inventario_variantes AS
SELECT
    p.id_producto,
    p.codigo,
    p.nombre AS producto,
    p.marca,
    c.nombre AS categoria,
    pc.nombre_color AS color,
    pct.talla,
    pct.cantidad AS stock_talla_color,
    p.cantidad AS stock_total_producto,
    p.precio,
    p.costo_unitario,
    (p.precio - p.costo_unitario) AS ganancia_unitaria,
    p.estado
FROM producto p
INNER JOIN categoria c
    ON p.id_categoria = c.id_categoria
LEFT JOIN producto_color pc
    ON p.id_producto = pc.id_producto
LEFT JOIN producto_color_talla pct
    ON pc.id_producto_color = pct.id_producto_color;

CREATE OR REPLACE VIEW vw_ventas_con_factura AS
SELECT
    v.id_venta,
    v.fecha,
    cl.nombre AS cliente,
    cl.telefono,
    COALESCE(cl.correo, '') AS correo,
    u.nombre AS atendido_por,
    v.medio_pago,
    v.canal_venta,
    v.tipo_entrega,
    v.subtotal_bruto,
    v.descuento,
    v.base_gravable,
    v.iva,
    v.total,
    v.estado,
    f.numero_factura,
    f.estado AS estado_factura
FROM venta v
INNER JOIN cliente cl
    ON v.id_cliente = cl.id_cliente
INNER JOIN usuario_sistema u
    ON v.id_usuario = u.id_usuario
LEFT JOIN factura f
    ON v.id_venta = f.id_venta;

CREATE OR REPLACE VIEW vw_utilidad_por_producto AS
SELECT
    p.codigo,
    p.nombre AS producto,
    SUM(dv.cantidad) AS unidades_vendidas,
    SUM(dv.subtotal) AS ventas_netas,
    SUM(dv.subtotal_costo) AS costo_total,
    SUM(dv.subtotal - dv.subtotal_costo) AS utilidad_bruta,
    ROUND(
        CASE
            WHEN SUM(dv.subtotal) > 0
            THEN (SUM(dv.subtotal - dv.subtotal_costo) / SUM(dv.subtotal)) * 100
            ELSE 0
        END,
        2
    ) AS margen_porcentaje
FROM detalle_venta dv
INNER JOIN producto p
    ON dv.id_producto = p.id_producto
INNER JOIN venta v
    ON dv.id_venta = v.id_venta
WHERE v.estado IN ('pagada', 'devuelta')
GROUP BY p.codigo, p.nombre;

-- Consultas para mostrar:
-- SELECT * FROM vw_inventario_variantes;
-- SELECT * FROM vw_ventas_con_factura;
-- SELECT * FROM vw_utilidad_por_producto;


-- ============================================================
-- 3. FUNCIONES DE VENTANA
-- ============================================================

CREATE OR REPLACE VIEW vw_ranking_clientes_compras AS
SELECT
    cl.id_cliente,
    cl.nombre AS cliente,
    cl.telefono,
    COUNT(v.id_venta) AS total_compras,
    COALESCE(SUM(v.total), 0) AS total_comprado,
    RANK() OVER (ORDER BY COALESCE(SUM(v.total), 0) DESC) AS ranking_cliente,
    DENSE_RANK() OVER (ORDER BY COUNT(v.id_venta) DESC) AS ranking_frecuencia
FROM cliente cl
LEFT JOIN venta v
    ON cl.id_cliente = v.id_cliente
    AND v.estado IN ('pagada', 'devuelta')
GROUP BY cl.id_cliente, cl.nombre, cl.telefono;

CREATE OR REPLACE VIEW vw_ventas_diarias_acumuladas AS
SELECT
    fecha_venta,
    ventas_dia,
    cantidad_ventas,
    SUM(ventas_dia) OVER (
        ORDER BY fecha_venta
        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
    ) AS ventas_acumuladas,
    LAG(ventas_dia) OVER (ORDER BY fecha_venta) AS ventas_dia_anterior,
    ventas_dia - COALESCE(LAG(ventas_dia) OVER (ORDER BY fecha_venta), 0) AS diferencia_vs_dia_anterior
FROM (
    SELECT
        DATE(v.fecha) AS fecha_venta,
        SUM(v.total) AS ventas_dia,
        COUNT(v.id_venta) AS cantidad_ventas
    FROM venta v
    WHERE v.estado IN ('pagada', 'devuelta')
    GROUP BY DATE(v.fecha)
) resumen_diario;

CREATE OR REPLACE VIEW vw_ranking_productos_por_categoria AS
SELECT
    categoria,
    codigo,
    producto,
    unidades_vendidas,
    ventas_netas,
    ROW_NUMBER() OVER (
        PARTITION BY categoria
        ORDER BY unidades_vendidas DESC, ventas_netas DESC
    ) AS posicion_en_categoria
FROM (
    SELECT
        c.nombre AS categoria,
        p.codigo,
        p.nombre AS producto,
        SUM(dv.cantidad) AS unidades_vendidas,
        SUM(dv.subtotal) AS ventas_netas
    FROM detalle_venta dv
    INNER JOIN producto p
        ON dv.id_producto = p.id_producto
    INNER JOIN categoria c
        ON p.id_categoria = c.id_categoria
    INNER JOIN venta v
        ON dv.id_venta = v.id_venta
    WHERE v.estado IN ('pagada', 'devuelta')
    GROUP BY c.nombre, p.codigo, p.nombre
) productos_categoria;

-- Consultas para mostrar:
-- SELECT * FROM vw_ranking_clientes_compras;
-- SELECT * FROM vw_ventas_diarias_acumuladas;
-- SELECT * FROM vw_ranking_productos_por_categoria;


-- ============================================================
-- 4. FUNCIONES
-- ============================================================

CREATE OR REPLACE FUNCTION fn_calcular_precio_venta(
    p_costo NUMERIC,
    p_margen_objetivo NUMERIC DEFAULT 35,
    p_iva NUMERIC DEFAULT 19
)
RETURNS TABLE (
    costo NUMERIC,
    margen_objetivo NUMERIC,
    precio_sugerido_con_iva NUMERIC,
    base_sin_iva NUMERIC,
    valor_iva NUMERIC,
    ganancia_estimada NUMERIC
) AS $$
DECLARE
    margen_decimal NUMERIC;
BEGIN
    IF p_costo IS NULL OR p_costo <= 0 THEN
        RAISE EXCEPTION 'El costo debe ser mayor a cero';
    END IF;

    IF p_margen_objetivo <= 0 OR p_margen_objetivo >= 95 THEN
        RAISE EXCEPTION 'El margen objetivo debe estar entre 1 y 94';
    END IF;

    margen_decimal := p_margen_objetivo / 100;

    RETURN QUERY
    SELECT
        p_costo,
        p_margen_objetivo,
        ROUND((p_costo / (1 - margen_decimal)) * (1 + (p_iva / 100)), 2),
        ROUND(p_costo / (1 - margen_decimal), 2),
        ROUND(((p_costo / (1 - margen_decimal)) * (p_iva / 100)), 2),
        ROUND((p_costo / (1 - margen_decimal)) - p_costo, 2);
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION fn_stock_producto_detallado(p_id_producto INT)
RETURNS TABLE (
    codigo VARCHAR,
    producto VARCHAR,
    color VARCHAR,
    talla VARCHAR,
    cantidad INT
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        p.codigo,
        p.nombre,
        pc.nombre_color,
        pct.talla,
        pct.cantidad
    FROM producto p
    INNER JOIN producto_color pc
        ON p.id_producto = pc.id_producto
    INNER JOIN producto_color_talla pct
        ON pc.id_producto_color = pct.id_producto_color
    WHERE p.id_producto = p_id_producto
    ORDER BY pc.nombre_color, pct.talla;
END;
$$ LANGUAGE plpgsql;

-- Consultas para mostrar:
-- SELECT * FROM fn_calcular_precio_venta(50000, 35, 19);
-- SELECT * FROM fn_stock_producto_detallado(1);


-- ============================================================
-- 5. PROCEDIMIENTOS ALMACENADOS
-- ============================================================

CREATE OR REPLACE PROCEDURE sp_registrar_ajuste_stock(
    p_id_producto INT,
    p_id_usuario INT,
    p_cantidad INT,
    p_detalle TEXT DEFAULT 'Ajuste manual desde procedimiento'
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_cantidad = 0 THEN
        RAISE EXCEPTION 'La cantidad de ajuste no puede ser cero';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM producto WHERE id_producto = p_id_producto) THEN
        RAISE EXCEPTION 'El producto no existe';
    END IF;

    IF (SELECT cantidad + p_cantidad FROM producto WHERE id_producto = p_id_producto) < 0 THEN
        RAISE EXCEPTION 'El ajuste no puede dejar el stock negativo';
    END IF;

    UPDATE producto
    SET cantidad = cantidad + p_cantidad
    WHERE id_producto = p_id_producto;

    INSERT INTO movimiento_inventario (
        id_producto,
        id_usuario,
        tipo,
        cantidad,
        detalle
    )
    VALUES (
        p_id_producto,
        p_id_usuario,
        'ajuste_stock',
        ABS(p_cantidad),
        p_detalle
    );
END;
$$;

-- Consulta para mostrar:
-- CALL sp_registrar_ajuste_stock(1, 1, 2, 'Ajuste positivo para exposicion');


-- ============================================================
-- 6. CURSORES
-- ============================================================

CREATE OR REPLACE FUNCTION fn_cursor_productos_bajo_stock(p_limite INT DEFAULT 3)
RETURNS REFCURSOR AS $$
DECLARE
    cursor_bajo_stock REFCURSOR := 'cursor_productos_bajo_stock';
BEGIN
    OPEN cursor_bajo_stock FOR
        SELECT
            p.id_producto,
            p.codigo,
            p.nombre AS producto,
            p.cantidad,
            p.estado
        FROM producto p
        WHERE p.cantidad <= p_limite
        OR p.estado = 'agotado'
        ORDER BY p.cantidad ASC, p.nombre ASC;

    RETURN cursor_bajo_stock;
END;
$$ LANGUAGE plpgsql;

-- Ejemplo para mostrar cursores en PostgreSQL:
-- BEGIN;
-- SELECT fn_cursor_productos_bajo_stock(3);
-- FETCH ALL FROM cursor_productos_bajo_stock;
-- COMMIT;


-- ============================================================
-- 7. TABLA DE AUDITORIA Y TRIGGERS
-- ============================================================

CREATE TABLE IF NOT EXISTS auditoria_evento (
    id_auditoria_evento SERIAL PRIMARY KEY,
    tabla_afectada VARCHAR(100) NOT NULL,
    operacion VARCHAR(20) NOT NULL CHECK (operacion IN ('INSERT', 'UPDATE', 'DELETE')),
    llave_registro TEXT,
    datos_anteriores JSONB,
    datos_nuevos JSONB,
    usuario_bd VARCHAR(100) NOT NULL DEFAULT CURRENT_USER,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION fn_auditar_evento()
RETURNS TRIGGER AS $$
DECLARE
    llave TEXT;
BEGIN
    IF TG_OP = 'INSERT' THEN
        llave := COALESCE(
            (to_jsonb(NEW)->>'id_producto'),
            (to_jsonb(NEW)->>'id_venta'),
            (to_jsonb(NEW)->>'id_gasto'),
            (to_jsonb(NEW)->>'id_compra')
        );

        INSERT INTO auditoria_evento (
            tabla_afectada,
            operacion,
            llave_registro,
            datos_anteriores,
            datos_nuevos
        )
        VALUES (TG_TABLE_NAME, TG_OP, llave, NULL, to_jsonb(NEW));
        RETURN NEW;
    ELSIF TG_OP = 'UPDATE' THEN
        llave := COALESCE(
            (to_jsonb(NEW)->>'id_producto'),
            (to_jsonb(NEW)->>'id_venta'),
            (to_jsonb(NEW)->>'id_gasto'),
            (to_jsonb(NEW)->>'id_compra'),
            (to_jsonb(OLD)->>'id_producto'),
            (to_jsonb(OLD)->>'id_venta'),
            (to_jsonb(OLD)->>'id_gasto'),
            (to_jsonb(OLD)->>'id_compra')
        );

        INSERT INTO auditoria_evento (
            tabla_afectada,
            operacion,
            llave_registro,
            datos_anteriores,
            datos_nuevos
        )
        VALUES (TG_TABLE_NAME, TG_OP, llave, to_jsonb(OLD), to_jsonb(NEW));
        RETURN NEW;
    ELSIF TG_OP = 'DELETE' THEN
        llave := COALESCE(
            (to_jsonb(OLD)->>'id_producto'),
            (to_jsonb(OLD)->>'id_venta'),
            (to_jsonb(OLD)->>'id_gasto'),
            (to_jsonb(OLD)->>'id_compra')
        );

        INSERT INTO auditoria_evento (
            tabla_afectada,
            operacion,
            llave_registro,
            datos_anteriores,
            datos_nuevos
        )
        VALUES (TG_TABLE_NAME, TG_OP, llave, to_jsonb(OLD), NULL);
        RETURN OLD;
    END IF;

    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_auditoria_evento_producto ON producto;
CREATE TRIGGER trg_auditoria_evento_producto
AFTER INSERT OR UPDATE OR DELETE ON producto
FOR EACH ROW
EXECUTE FUNCTION fn_auditar_evento();

DROP TRIGGER IF EXISTS trg_auditoria_evento_venta ON venta;
CREATE TRIGGER trg_auditoria_evento_venta
AFTER INSERT OR UPDATE OR DELETE ON venta
FOR EACH ROW
EXECUTE FUNCTION fn_auditar_evento();

DROP TRIGGER IF EXISTS trg_auditoria_evento_gasto ON gasto_negocio;
CREATE TRIGGER trg_auditoria_evento_gasto
AFTER INSERT OR UPDATE OR DELETE ON gasto_negocio
FOR EACH ROW
EXECUTE FUNCTION fn_auditar_evento();

DROP TRIGGER IF EXISTS trg_auditoria_evento_compra ON compra_mercancia;
CREATE TRIGGER trg_auditoria_evento_compra
AFTER INSERT OR UPDATE OR DELETE ON compra_mercancia
FOR EACH ROW
EXECUTE FUNCTION fn_auditar_evento();


-- ============================================================
-- 8. TRIGGER DE VALIDACION DE PRECIO
-- ============================================================

CREATE OR REPLACE FUNCTION fn_validar_precio_producto()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.precio <= 0 THEN
        RAISE EXCEPTION 'El precio de venta debe ser mayor a cero';
    END IF;

    IF NEW.costo_unitario < 0 THEN
        RAISE EXCEPTION 'El costo unitario no puede ser negativo';
    END IF;

    IF NEW.precio < NEW.costo_unitario THEN
        RAISE EXCEPTION 'El precio de venta no puede ser menor al costo de compra';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_validar_precio_producto ON producto;
CREATE TRIGGER trg_validar_precio_producto
BEFORE INSERT OR UPDATE OF precio, costo_unitario ON producto
FOR EACH ROW
EXECUTE FUNCTION fn_validar_precio_producto();


-- ============================================================
-- 9. CONSULTA DE OBJETOS IMPLEMENTADOS PARA LA EXPOSICION
-- ============================================================

CREATE OR REPLACE VIEW vw_objetos_exposicion AS
SELECT 'Vista' AS tipo_objeto, table_name AS nombre_objeto
FROM information_schema.views
WHERE table_schema = 'public'
AND table_name LIKE 'vw_%'

UNION ALL

SELECT 'Funcion' AS tipo_objeto, routine_name AS nombre_objeto
FROM information_schema.routines
WHERE specific_schema = 'public'
AND routine_type = 'FUNCTION'
AND routine_name LIKE 'fn_%'

UNION ALL

SELECT 'Procedimiento' AS tipo_objeto, routine_name AS nombre_objeto
FROM information_schema.routines
WHERE specific_schema = 'public'
AND routine_type = 'PROCEDURE'
AND routine_name LIKE 'sp_%'

UNION ALL

SELECT 'Trigger' AS tipo_objeto, trigger_name AS nombre_objeto
FROM information_schema.triggers
WHERE trigger_schema = 'public'
AND trigger_name LIKE 'trg_%'

ORDER BY tipo_objeto, nombre_objeto;

-- Consulta final para mostrar:
-- SELECT * FROM vw_objetos_exposicion;
