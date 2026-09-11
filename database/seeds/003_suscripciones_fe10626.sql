SET @proveedor_id := (SELECT id FROM ad_proveedores WHERE nit='830044977-0' LIMIT 1);
SET @razon_id := (SELECT id FROM ad_razones_sociales WHERE codigo='CTV' LIMIT 1);

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L1','CO',136,16982.72,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|EXO_P1|16982.72',256) FROM ad_planes WHERE codigo='EXO_P1'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_exo := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L2','CO',26,26103.69,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|M365_BASIC|26103.69',256) FROM ad_planes WHERE codigo='M365_BASIC'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_basic_1 := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L3','CO',185,20249.93,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|M365_BASIC|20249.93',256) FROM ad_planes WHERE codigo='M365_BASIC'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_basic_2 := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L4','CO',12,64629.62,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|M365_STANDARD|64629.62',256) FROM ad_planes WHERE codigo='M365_STANDARD'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_standard_1 := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L5','CO',135,42541.88,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|M365_STANDARD|42541.88',256) FROM ad_planes WHERE codigo='M365_STANDARD'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_standard_2 := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L6','CO',1,37879.29,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|ODFB_P2|37879.29',256) FROM ad_planes WHERE codigo='ODFB_P2'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_od := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L7','CO',1,115816.00,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|PROJECT_P3|115816.00',256) FROM ad_planes WHERE codigo='PROJECT_P3'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_project := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L8','CO',1,81101.83,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|PBI_PPU|81101.83',256) FROM ad_planes WHERE codigo='PBI_PPU'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_ppu := LAST_INSERT_ID();

INSERT INTO ad_suscripciones
(proveedor_id, plan_id, razon_social_id, referencia_contrato, pais, cantidad_contratada, valor_unitario, moneda, periodicidad, fecha_inicio, estado, origen, hash_importacion)
SELECT @proveedor_id,id,@razon_id,'FE10626-L9','CO',3,54079.23,'COP','MENSUAL','2026-06-01','VIGENTE','FACTURA',SHA2('FE10626|PBI_PRO|54079.23',256) FROM ad_planes WHERE codigo='PBI_PRO'
ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(ad_suscripciones.id),cantidad_contratada=VALUES(cantidad_contratada),valor_unitario=VALUES(valor_unitario);
SET @s_pro := LAST_INSERT_ID();

SET @factura_id := (SELECT id FROM ad_facturas WHERE proveedor_id=@proveedor_id AND numero_factura='FE10626' LIMIT 1);
UPDATE ad_factura_detalles SET suscripcion_id=@s_exo WHERE factura_id=@factura_id AND valor_unitario=16982.72;
UPDATE ad_factura_detalles SET suscripcion_id=@s_basic_1 WHERE factura_id=@factura_id AND valor_unitario=26103.69;
UPDATE ad_factura_detalles SET suscripcion_id=@s_basic_2 WHERE factura_id=@factura_id AND valor_unitario=20249.93;
UPDATE ad_factura_detalles SET suscripcion_id=@s_standard_1 WHERE factura_id=@factura_id AND valor_unitario=64629.62;
UPDATE ad_factura_detalles SET suscripcion_id=@s_standard_2 WHERE factura_id=@factura_id AND valor_unitario=42541.88;
UPDATE ad_factura_detalles SET suscripcion_id=@s_od WHERE factura_id=@factura_id AND valor_unitario=37879.29;
UPDATE ad_factura_detalles SET suscripcion_id=@s_project WHERE factura_id=@factura_id AND valor_unitario=115816.00;
UPDATE ad_factura_detalles SET suscripcion_id=@s_ppu WHERE factura_id=@factura_id AND valor_unitario=81101.83;
UPDATE ad_factura_detalles SET suscripcion_id=@s_pro WHERE factura_id=@factura_id AND valor_unitario=54079.23;
