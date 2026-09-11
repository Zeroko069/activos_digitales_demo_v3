<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
ad_require_permission('facturas.ver');
$pdo=db();
$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare('SELECT f.*,p.razon_social proveedor,p.nit proveedor_nit,rs.nombre razon_social FROM ad_facturas f JOIN ad_proveedores p ON p.id=f.proveedor_id LEFT JOIN ad_razones_sociales rs ON rs.id=f.razon_social_id WHERE f.id=:id');
$stmt->execute([':id'=>$id]);
$invoice=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$invoice){http_response_code(404);exit('Factura no encontrada.');}

$stmt=$pdo->prepare('SELECT d.*,pl.nombre plan_nombre,c.cantidad_contratada,c.tarifa_contratada,c.diferencia_tarifa,c.estado conciliacion_estado,c.clasificacion,c.justificacion FROM ad_factura_detalles d LEFT JOIN ad_planes pl ON pl.id=d.plan_id LEFT JOIN ad_conciliaciones c ON c.factura_detalle_id=d.id WHERE d.factura_id=:id ORDER BY d.id');
$stmt->execute([':id'=>$id]);
$details=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$pdo->prepare('SELECT cp.*,p.nombre plan_nombre FROM ad_conciliaciones_plan cp JOIN ad_planes p ON p.id=cp.plan_id WHERE cp.factura_id=:id ORDER BY p.nombre');
$stmt->execute([':id'=>$id]);
$planConciliations=$stmt->fetchAll(PDO::FETCH_ASSOC);

$title='Factura '.$invoice['numero_factura'];
require dirname(__DIR__,2).'/views/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h1 class="h3 mb-0">Factura <?=ad_e($invoice['numero_factura'])?></h1><small class="text-muted"><?=ad_e($invoice['proveedor'])?> · <?=ad_e($invoice['estado'])?></small></div>
  <div>
    <?php if(ad_can('facturas.conciliar')):?><form class="d-inline" method="post" action="<?=ad_e(ad_url('facturas/reconcile.php'))?>"><input type="hidden" name="_token" value="<?=ad_e(ad_csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><button class="btn btn-primary">Conciliar</button></form><?php endif;?>
    <?php if(!empty($invoice['archivo_pdf'])):?><a class="btn btn-outline-primary" href="<?=ad_e(ad_url('facturas/download.php?id='.$id))?>">Ver PDF</a><?php endif;?>
    <a class="btn btn-outline-secondary" href="<?=ad_e(ad_url('facturas/index.php'))?>">Volver</a>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card card-kpi"><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><small class="text-muted">Razón social facturada</small><div><?=ad_e($invoice['razon_social'])?></div></div>
    <div class="col-md-4"><small class="text-muted">Fecha de emisión</small><div><?=ad_e($invoice['fecha_emision'])?></div></div>
    <div class="col-md-4"><small class="text-muted">Vencimiento</small><div><?=ad_e($invoice['fecha_vencimiento'])?></div></div>
    <div class="col-md-4"><small class="text-muted">Periodo</small><div><?=ad_e($invoice['periodo_desde'])?> a <?=ad_e($invoice['periodo_hasta'])?></div></div>
    <div class="col-md-8"><small class="text-muted">CUFE</small><div class="small text-break"><?=ad_e($invoice['cufe'])?></div></div>
  </div></div></div></div>
  <div class="col-lg-4"><div class="card card-kpi"><div class="card-body">
    <div class="d-flex justify-content-between"><span>Subtotal</span><strong><?=ad_e(ad_money($invoice['subtotal'],$invoice['moneda']))?></strong></div>
    <div class="d-flex justify-content-between"><span>Descuentos</span><strong><?=ad_e(ad_money($invoice['descuentos'],$invoice['moneda']))?></strong></div>
    <div class="d-flex justify-content-between"><span>Impuestos</span><strong><?=ad_e(ad_money($invoice['impuestos'],$invoice['moneda']))?></strong></div>
    <div class="d-flex justify-content-between"><span>Retenciones</span><strong><?=ad_e(ad_money($invoice['retenciones'],$invoice['moneda']))?></strong></div>
    <hr><div class="d-flex justify-content-between fs-5"><span>Total</span><strong><?=ad_e(ad_money($invoice['total_pagar'],$invoice['moneda']))?></strong></div>
  </div></div></div>
</div>

<div class="card card-kpi mb-3">
  <div class="card-header fw-semibold">Conciliación de cantidades por plan</div>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Plan</th><th class="money">Contratadas</th><th class="money">Facturadas</th><th class="money">Asignadas</th><th class="money">Facturadas - asignadas</th><th>Resultado</th></tr></thead><tbody>
  <?php if(!$planConciliations):?><tr><td colspan="6" class="p-3 text-muted">Ejecute la conciliación para generar el resultado por plan.</td></tr><?php endif;?>
  <?php foreach($planConciliations as $row):?><tr><td><?=ad_e($row['plan_nombre'])?></td><td class="money"><?=$row['cantidad_contratada']!==null?number_format((float)$row['cantidad_contratada'],2,',','.'):'—'?></td><td class="money"><?=number_format((float)$row['cantidad_facturada'],2,',','.')?></td><td class="money"><?=number_format((float)$row['cantidad_asignada'],2,',','.')?></td><td class="money"><?=number_format((float)$row['diferencia_facturado_asignado'],2,',','.')?></td><td><span class="badge text-bg-<?=$row['estado']==='OK'?'success':($row['estado']==='CON_NOVEDAD'?'danger':'warning')?>"><?=ad_e($row['estado'])?></span></td></tr><?php endforeach;?>
  </tbody></table></div>
</div>

<div class="card card-kpi">
  <div class="card-header fw-semibold">Validación de tarifas por línea</div>
  <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Plan / descripción</th><th class="money">Cantidad línea</th><th class="money">Tarifa facturada</th><th class="money">Tarifa contractual</th><th class="money">Diferencia tarifa</th><th>Resultado</th></tr></thead><tbody>
  <?php foreach($details as $row):?><tr><td><?=ad_e($row['plan_nombre']?:$row['descripcion'])?></td><td class="money"><?=number_format((float)$row['cantidad'],2,',','.')?></td><td class="money"><?=ad_e(ad_money($row['valor_unitario'],$invoice['moneda']))?></td><td class="money"><?=$row['tarifa_contratada']!==null?ad_e(ad_money($row['tarifa_contratada'],$invoice['moneda'])):'—'?></td><td class="money"><?=$row['diferencia_tarifa']!==null?ad_e(ad_money($row['diferencia_tarifa'],$invoice['moneda'])):'—'?></td><td><?php if($row['conciliacion_estado']):?><span class="badge text-bg-<?=$row['conciliacion_estado']==='OK'?'success':($row['conciliacion_estado']==='CON_NOVEDAD'?'danger':'warning')?>"><?=ad_e($row['conciliacion_estado'])?></span><?php else:?><span class="text-muted">Sin conciliar</span><?php endif;?></td></tr><?php endforeach;?>
  </tbody></table></div>
</div>
<?php require dirname(__DIR__,2).'/views/footer.php';?>
