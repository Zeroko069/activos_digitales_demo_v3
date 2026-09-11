INSERT INTO ad_usuarios (nombre, email, password_hash, rol, activo)
VALUES (
    'Administrador de prueba',
    'admin@prueba.local',
    '$2y$10$UFgtIEIBGZLCyfm1fcpHveia1ZuuNW2ACXqONFZIysFj.0nzXmB2a',
    'ADMIN',
    1
)
ON DUPLICATE KEY UPDATE
    nombre=VALUES(nombre), rol=VALUES(rol), activo=1;
