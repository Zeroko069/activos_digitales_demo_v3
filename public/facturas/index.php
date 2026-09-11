<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ad_require_permission('facturas.ver');
$pdo = db();

$estado = trim((string)($_GET['estado'] ?? ''));
$params=[];$where='1=1';
if ($estado!=='') {$where.=' AND f.estado=:estado';$params[':estado']=$estado;}
$stmt=$pdo->prepare("SELECT f.*,p.razon_social proveedor,rs.nombre razon_social
FROM ad_facturas f JOIN ad_proveedores p ON p.id=f.proveedor_id
LEFT JOIN ad_razones_sociales rs ON rs.id=f.razon_social_id
WHERE $where ORDER BY f.fecha_emision DESC,f.id DESC");
$stmt->execute($params);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
$title='Facturas';require dirname(__DIR__,2).'/views/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><div><h1 class="h3 mb-0">Facturas</h1><small class="text-muted">Facturación de proveedores de servicios digitales</small></div><?php if(ad_can('facturas.crear')):?><a class="btn btn-primary" href="<?=ad_e(ad_url('facturas/create.php'))?>">Registrar factura</a><?php endif;?></div>
<form class="card card-body mb-3" method="get"><div class="row g-2"><div class="col-md-4"><select class="form-select" name="estado"><option value="">Todos los estados</option><?php foreach(['BORRADOR','RECIBIDA','EN_VALIDACION','CON_NOVEDAD','PENDIENTE_APROBACION','APROBADA','CUENTAS_POR_PAGAR','PROGRAMADA','PAGADA','RECHAZADA','DEVUELTA','ANULADA'] as $item):?><option value="<?=$item?>" <?=$estado===$item?'selected':''?>><?=$item?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-dark w-100">Filtrar</button></div></div></form>
<div class="card card-kpi"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Factura</th><th>Proveedor</th><th>Periodo</th><th>Vencimiento</th><th class="money">Subtotal</th><th class="money">Total pagar</th><th>Estado</th><th></th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="8" class="p-4 text-muted">No hay facturas registradas.</td></tr><?php endif;?>
<?php foreach($rows as $row):?><tr><td><strong><?=ad_e($row['numero_factura'])?></strong><div class="small text-muted"><?=ad_e($row['fecha_emision'])?></div></td><td><?=ad_e($row['proveedor'])?><div class="small text-muted"><?=ad_e($row['razon_social'])?></div></td><td><?=ad_e($row['periodo_desde'])?> a <?=ad_e($row['periodo_hasta'])?></td><td><?=ad_e($row['fecha_vencimiento'])?></td><td class="money"><?=ad_e(ad_money($row['subtotal'],$row['moneda']))?></td><td class="money"><strong><?=ad_e(ad_money($row['total_pagar'],$row['moneda']))?></strong></td><td><span class="badge text-bg-<?=in_array($row['estado'],['APROBADA','PAGADA'],true)?'success':($row['estado']==='CON_NOVEDAD'?'danger':'secondary')?>"><?=ad_e($row['estado'])?></span></td><td><a class="btn btn-sm btn-outline-primary" href="<?=ad_e(ad_url('facturas/show.php?id='.(int)$row['id']))?>">Ver</a></td></tr><?php endforeach;?>
</tbody></table></div></div>
<?php require dirname(__DIR__,2).'/views/footer.php';?>
