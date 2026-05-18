-- Migracion de seguridad: reemplaza claves de prueba por hashes.
-- Cambia estas contrasenas antes de publicar el sitio.

UPDATE usuario_sistema
SET contrasena = '$2y$10$HmR7pPGkUHD.X9tPVgdo2OQfKQdZZsQV2PIFZ03QuIGWBDkenEAOG'
WHERE correo = 'admin@isaboutique.com'
AND contrasena = '123456';

UPDATE usuario_sistema
SET contrasena = '$2y$10$E3k6Q4/e43fy3o.w6oUhMONgNXxBvgq/YJUAre3QlNHqFfUEFFnYm'
WHERE correo = 'vendedora@isaboutique.com'
AND contrasena = '123456';
