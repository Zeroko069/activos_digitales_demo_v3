<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';ad_require_permission('dashboard.ver');$pdo=db();
$data=[
 'cuentas_activas'=>(int)$pdo->query("SELECT COUNT(*) FROM ad_cuentas WHERE estado='ACTIVA'")->fetchColumn(),
 'licencias_activas'=>(int)$pdo->query("SELECT COUNT(*) FROM ad_asignaciones_licencia WHERE estado='ACTIVA'")->fetchColumn(),
 'facturas_pendientes'=>(int)$pdo->query("SELECT COUNT(*) FROM ad_facturas WHERE estado IN ('RECIBIDA','EN_VALIDACION','CON_NOVEDAD','PENDIENTE_APROBACION')")->fetchColumn(),
 'licencias_por_plan'=>$pdo->query("SELECT p.nombre,COUNT(*) cantidad FROM ad_asignaciones_licencia al JOIN ad_suscripciones s ON s.id=al.suscripcion_id JOIN ad_planes p ON p.id=s.plan_id WHERE al.estado='ACTIVA' GROUP BY p.id,p.nombre ORDER BY cantidad DESC")->fetchAll(PDO::FETCH_ASSOC),
];ad_json_response(['ok'=>true,'data'=>$data]);
