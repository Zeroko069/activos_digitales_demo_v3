SET @proveedor_id := (SELECT id FROM ad_proveedores WHERE nit='830044977-0' LIMIT 1);
SET @razon_id := (SELECT id FROM ad_razones_sociales WHERE codigo='CTV' LIMIT 1);

INSERT INTO ad_facturas (
    proveedor_id, razon_social_id, numero_factura, fecha_emision, fecha_recepcion,
    fecha_vencimiento, periodo_desde, periodo_hasta, moneda, subtotal,
    retenciones, total_pagar, cufe, metodo_pago, estado, observaciones
) VALUES (
    @proveedor_id, @razon_id, 'FE10626', '2026-07-09', '2026-07-09',
    '2026-08-09', '2026-06-01', '2026-06-30', 'COP', 13650326.96,
    477761.45, 13172565.51,
    'c1a5ee689efecdedec8ab88c533d958959b08f025ec438397f88dc55f698183c8d2e22b7193db88aada77821c4f5f1ab',
    'CREDITO', 'RECIBIDA', 'Factura de licenciamiento correspondiente a junio de 2026.'
)
ON DUPLICATE KEY UPDATE
    fecha_vencimiento=VALUES(fecha_vencimiento), subtotal=VALUES(subtotal),
    retenciones=VALUES(retenciones), total_pagar=VALUES(total_pagar), cufe=VALUES(cufe);

SET @factura_id := (SELECT id FROM ad_facturas WHERE proveedor_id=@proveedor_id AND numero_factura='FE10626' LIMIT 1);
DELETE FROM ad_factura_detalles WHERE factura_id=@factura_id;

INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'EXCHANGE ONLINE PLAN 1', 'EXCHANGE ONLINE PLAN 1', 136, 16982.72, 2309649.92, 2309649.92 FROM ad_planes WHERE codigo='EXO_P1';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'MICROSOFT 365 BUSINESS BASIC', 'MICROSOFT 365 BUSINESS BASIC', 26, 26103.69, 678695.94, 678695.94 FROM ad_planes WHERE codigo='M365_BASIC';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'MICROSOFT 365 BUSINESS BASIC', 'MICROSOFT 365 BUSINESS BASIC', 185, 20249.93, 3746237.05, 3746237.05 FROM ad_planes WHERE codigo='M365_BASIC';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'MICROSOFT 365 BUSINESS STANDARD', 'MICROSOFT 365 BUSINESS STANDARD', 12, 64629.62, 775555.44, 775555.44 FROM ad_planes WHERE codigo='M365_STANDARD';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'MICROSOFT 365 BUSINESS STANDARD', 'MICROSOFT 365 BUSINESS STANDARD', 135, 42541.88, 5743153.80, 5743153.80 FROM ad_planes WHERE codigo='M365_STANDARD';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'ONEDRIVE FOR BUSINESS PLAN 2', 'ONEDRIVE FOR BUSINESS PLAN 2', 1, 37879.29, 37879.29, 37879.29 FROM ad_planes WHERE codigo='ODFB_P2';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'PLANNER AND PROJECT PLAN 3', 'PLANNER AND PROJECT PLAN 3', 1, 115816.00, 115816.00, 115816.00 FROM ad_planes WHERE codigo='PROJECT_P3';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'POWER BI PREMIUM PER USER', 'POWER BI PREMIUM PER USER', 1, 81101.83, 81101.83, 81101.83 FROM ad_planes WHERE codigo='PBI_PPU';
INSERT INTO ad_factura_detalles
(factura_id, plan_id, descripcion, descripcion_normalizada, cantidad, valor_unitario, subtotal, total)
SELECT @factura_id, id, 'POWER BI PRO', 'POWER BI PRO', 3, 54079.23, 162237.69, 162237.69 FROM ad_planes WHERE codigo='PBI_PRO';
