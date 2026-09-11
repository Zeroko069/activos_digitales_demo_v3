<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
ad_require_permission('facturas.crear');
$pdo=db();
$providers=$pdo->query('SELECT id,razon_social,nit FROM ad_proveedores WHERE activo=1 ORDER BY razon_social')->fetchAll(PDO::FETCH_ASSOC);
$reasons=$pdo->query('SELECT id,nombre,pais FROM ad_razones_sociales WHERE activa=1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$plans=$pdo->query('SELECT id,nombre FROM ad_planes WHERE activo=1 ORDER BY nombre')->fetchAll(PDO::FETCH_ASSOC);
$title='Registrar factura';require dirname(__DIR__,2).'/views/header.php';
?>
<h1 class="h3 mb-3">Registrar factura</h1>
<form method="post" enctype="multipart/form-data" action="<?=ad_e(ad_url('facturas/store.php'))?>" class="card card-body">
<input type="hidden" name="_token" value="<?=ad_e(ad_csrf_token())?>">
<div class="row g-3">
<div class="col-md-4"><label class="form-label">Proveedor *</label><select class="form-select" name="proveedor_id" required><option value="">Seleccione</option><?php foreach($providers as $p):?><option value="<?=(int)$p['id']?>"><?=ad_e($p['razon_social'].' · '.$p['nit'])?></option><?php endforeach;?></select></div>
<div class="col-md-4"><label class="form-label">Razón social facturada</label><select class="form-select" name="razon_social_id"><option value="">Sin definir</option><?php foreach($reasons as $r):?><option value="<?=(int)$r['id']?>"><?=ad_e($r['nombre'].' · '.$r['pais'])?></option><?php endforeach;?></select></div>
<div class="col-md-2"><label class="form-label">Número *</label><input class="form-control" name="numero_factura" required></div>
<div class="col-md-2"><label class="form-label">Moneda</label><select class="form-select" name="moneda"><option>COP</option><option>USD</option><option>PEN</option></select></div>
<div class="col-md-3"><label class="form-label">Fecha emisión *</label><input type="date" class="form-control" name="fecha_emision" required value="<?=date('Y-m-d')?>"></div>
<div class="col-md-3"><label class="form-label">Fecha recepción</label><input type="date" class="form-control" name="fecha_recepcion" value="<?=date('Y-m-d')?>"></div>
<div class="col-md-3"><label class="form-label">Fecha vencimiento</label><input type="date" class="form-control" name="fecha_vencimiento"></div>
<div class="col-md-3"><label class="form-label">Orden de compra</label><input class="form-control" name="orden_compra"></div>
<div class="col-md-3"><label class="form-label">Periodo desde</label><input type="date" class="form-control" name="periodo_desde"></div>
<div class="col-md-3"><label class="form-label">Periodo hasta</label><input type="date" class="form-control" name="periodo_hasta"></div>
<div class="col-md-2"><label class="form-label">Descuentos</label><input type="number" step="0.01" min="0" class="form-control" name="descuentos" value="0"></div>
<div class="col-md-2"><label class="form-label">Impuestos</label><input type="number" step="0.01" min="0" class="form-control" name="impuestos" value="0"></div>
<div class="col-md-2"><label class="form-label">Retenciones</label><input type="number" step="0.01" min="0" class="form-control" name="retenciones" value="0"></div>
<div class="col-md-3"><label class="form-label">PDF de la factura *</label><input type="file" class="form-control" name="factura_pdf" accept="application/pdf" required></div>
<div class="col-12"><label class="form-label">CUFE</label><input class="form-control" name="cufe"></div>
</div>
<hr>
<div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Detalle</h2><button type="button" class="btn btn-sm btn-outline-primary" id="add-row">Agregar línea</button></div>
<div class="table-responsive"><table class="table table-bordered align-middle" id="details"><thead><tr><th style="min-width:260px">Plan / descripción</th><th>Cantidad</th><th>Valor unitario</th><th>Subtotal</th><th></th></tr></thead><tbody></tbody><tfoot><tr><th colspan="3" class="text-end">Subtotal</th><th><input readonly class="form-control" name="subtotal" id="subtotal" value="0.00"></th><th></th></tr><tr><th colspan="3" class="text-end">Total a pagar</th><th><input readonly class="form-control" name="total_pagar" id="total" value="0.00"></th><th></th></tr></tfoot></table></div>
<div class="mt-3"><button class="btn btn-primary">Guardar factura</button> <a class="btn btn-outline-secondary" href="<?=ad_e(ad_url('facturas/index.php'))?>">Cancelar</a></div>
</form>
<template id="row-template"><tr><td><select class="form-select plan" name="detalles[__INDEX__][plan_id]"><option value="">Sin asociar</option><?php foreach($plans as $plan):?><option value="<?=(int)$plan['id']?>"><?=ad_e($plan['nombre'])?></option><?php endforeach;?></select><input class="form-control mt-1 description" name="detalles[__INDEX__][descripcion]" placeholder="Descripción de la factura" required></td><td><input type="number" min="0" step="0.01" class="form-control quantity" name="detalles[__INDEX__][cantidad]" value="1" required></td><td><input type="number" min="0" step="0.0001" class="form-control unit" name="detalles[__INDEX__][valor_unitario]" value="0" required></td><td><input readonly class="form-control line-total" name="detalles[__INDEX__][subtotal]" value="0.00"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-row">×</button></td></tr></template>
<script>
let rowIndex=0;const tbody=document.querySelector('#details tbody');const tpl=document.querySelector('#row-template').innerHTML;
function addRow(){tbody.insertAdjacentHTML('beforeend',tpl.replaceAll('__INDEX__',rowIndex++));recalc();}
function recalc(){let subtotal=0;tbody.querySelectorAll('tr').forEach(tr=>{const q=parseFloat(tr.querySelector('.quantity').value||0);const u=parseFloat(tr.querySelector('.unit').value||0);const t=q*u;tr.querySelector('.line-total').value=t.toFixed(2);subtotal+=t;});document.querySelector('#subtotal').value=subtotal.toFixed(2);const discounts=parseFloat(document.querySelector('[name=descuentos]').value||0);const taxes=parseFloat(document.querySelector('[name=impuestos]').value||0);const ret=parseFloat(document.querySelector('[name=retenciones]').value||0);document.querySelector('#total').value=(subtotal-discounts+taxes-ret).toFixed(2);}
document.querySelector('#add-row').addEventListener('click',addRow);document.addEventListener('input',e=>{if(e.target.matches('.quantity,.unit,[name=descuentos],[name=impuestos],[name=retenciones]'))recalc();});document.addEventListener('click',e=>{if(e.target.matches('.remove-row')){e.target.closest('tr').remove();recalc();}});document.addEventListener('change',e=>{if(e.target.matches('.plan')){const text=e.target.options[e.target.selectedIndex].text;if(text!=='Sin asociar')e.target.closest('td').querySelector('.description').value=text;}});addRow();
</script>
<?php require dirname(__DIR__,2).'/views/footer.php';?>
