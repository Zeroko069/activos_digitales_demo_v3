INSERT INTO ad_razones_sociales (codigo, nombre, pais, nit)
VALUES
('CTV', 'CONECTAR TV SAS', 'CO', '830134971-3'),
('TELECOM_PERU', 'CONECTAR TELECOMUNICACIONES SAC', 'PE', NULL)
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo), nit = COALESCE(VALUES(nit), nit);

INSERT INTO ad_proveedores (razon_social, nit, pais, correo_contacto, moneda_principal, condicion_pago)
VALUES ('XSYSTEM SAS', '830044977-0', 'CO', 'info@xsystemla.com', 'COP', 'CREDITO')
ON DUPLICATE KEY UPDATE razon_social = VALUES(razon_social), correo_contacto = VALUES(correo_contacto);

INSERT INTO ad_planes (codigo, nombre, fabricante, categoria) VALUES
('EXO_P1', 'EXCHANGE ONLINE PLAN 1', 'MICROSOFT', 'CORREO'),
('M365_BASIC', 'MICROSOFT 365 BUSINESS BASIC', 'MICROSOFT', 'PRODUCTIVIDAD'),
('M365_STANDARD', 'MICROSOFT 365 BUSINESS STANDARD', 'MICROSOFT', 'PRODUCTIVIDAD'),
('ODFB_P2', 'ONEDRIVE FOR BUSINESS PLAN 2', 'MICROSOFT', 'ALMACENAMIENTO'),
('PROJECT_P3', 'PLANNER AND PROJECT PLAN 3', 'MICROSOFT', 'PROYECTOS'),
('PBI_PPU', 'POWER BI PREMIUM PER USER', 'MICROSOFT', 'BI'),
('PBI_PRO', 'POWER BI PRO', 'MICROSOFT', 'BI'),
('GWS_STARTER', 'GOOGLE WORKSPACE BUSINESS STARTER', 'GOOGLE', 'PRODUCTIVIDAD')
ON DUPLICATE KEY UPDATE fabricante = VALUES(fabricante), categoria = VALUES(categoria);

INSERT INTO ad_plan_aliases (plan_id, alias, alias_normalizado)
SELECT id, 'Exchange Online (Plan 1)', 'EXCHANGE ONLINE PLAN 1' FROM ad_planes WHERE codigo='EXO_P1'
ON DUPLICATE KEY UPDATE alias=VALUES(alias);
INSERT INTO ad_plan_aliases (plan_id, alias, alias_normalizado)
SELECT id, 'Microsoft 365 Empresa Básico', 'MICROSOFT 365 EMPRESA BASICO' FROM ad_planes WHERE codigo='M365_BASIC'
ON DUPLICATE KEY UPDATE alias=VALUES(alias);
INSERT INTO ad_plan_aliases (plan_id, alias, alias_normalizado)
SELECT id, 'Microsoft 365 Empresa Estándar', 'MICROSOFT 365 EMPRESA ESTANDAR' FROM ad_planes WHERE codigo='M365_STANDARD'
ON DUPLICATE KEY UPDATE alias=VALUES(alias);
INSERT INTO ad_plan_aliases (plan_id, alias, alias_normalizado)
SELECT id, 'Power BI Premium por usuario', 'POWER BI PREMIUM POR USUARIO' FROM ad_planes WHERE codigo='PBI_PPU'
ON DUPLICATE KEY UPDATE alias=VALUES(alias);
INSERT INTO ad_plan_aliases (plan_id, alias, alias_normalizado)
SELECT id, 'Power BI Pro', 'POWER BI PRO' FROM ad_planes WHERE codigo='PBI_PRO'
ON DUPLICATE KEY UPDATE alias=VALUES(alias);
INSERT INTO ad_plan_aliases (plan_id, alias, alias_normalizado)
SELECT id, 'Google Workspace Business Starter', 'GOOGLE WORKSPACE BUSINESS STARTER' FROM ad_planes WHERE codigo='GWS_STARTER'
ON DUPLICATE KEY UPDATE alias=VALUES(alias);
