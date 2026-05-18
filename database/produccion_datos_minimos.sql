-- =====================================================
-- ISA BOUTIQUE - DATOS MINIMOS PARA PRODUCCION
-- Ejecutar despues de database/produccion_schema.sql
-- =====================================================

-- Usuarios internos iniciales.
-- Contrasenas temporales:
-- admin@isaboutique.com      / Admin12345
-- vendedora@isaboutique.com  / Vendedor12345
-- IMPORTANTE: cambiar ambas desde el panel Seguridad antes de publicar.

INSERT INTO usuario_sistema (nombre, correo, contrasena, rol, estado)
VALUES
(
    'Administrador Isa Boutique',
    'admin@isaboutique.com',
    '$2y$10$zdTLmfv9v8hDE3T1Yiby1O/9lhGonoGhRCx1f8.JzTgu3BESqfumO',
    'admin',
    TRUE
),
(
    'Vendedora Principal',
    'vendedora@isaboutique.com',
    '$2y$10$pnTDdvgrEis6ICAQqXp9FeieA4xQxHcca6EjQGjxKHq8Sf7OyIF0O',
    'vendedor',
    TRUE
)
ON CONFLICT (correo) DO NOTHING;

-- Categorias visibles en la tienda.

INSERT INTO categoria (nombre, descripcion, estado)
VALUES
('vestidos', 'Vestidos para ocasiones especiales', TRUE),
('blusas', 'Blusas y tops femeninos', TRUE),
('conjuntos', 'Conjuntos femeninos', TRUE),
('shorts', 'Shorts y prendas frescas', TRUE),
('jeans', 'Jeans y denim', TRUE),
('bodys', 'Bodys femeninos', TRUE),
('faldas', 'Faldas femeninas', TRUE),
('accesorios', 'Accesorios de moda femenina', TRUE)
ON CONFLICT (nombre) DO NOTHING;
