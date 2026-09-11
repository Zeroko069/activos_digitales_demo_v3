CREATE DATABASE IF NOT EXISTS activos_digitales_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'activos_test'@'%' IDENTIFIED BY 'ActivosTest2026!';
ALTER USER 'activos_test'@'%' IDENTIFIED BY 'ActivosTest2026!';
GRANT ALL PRIVILEGES ON activos_digitales_test.* TO 'activos_test'@'%';
FLUSH PRIVILEGES;
