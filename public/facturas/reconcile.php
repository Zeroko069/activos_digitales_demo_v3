<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';ad_require_permission('facturas.conciliar');ad_verify_csrf();$id=(int)($_POST['id']??0);
try{(new ReconciliationService(db()))->reconcileInvoice($id);ad_flash('success','Conciliación ejecutada correctamente.');}catch(Throwable $e){ad_flash('danger','No fue posible conciliar: '.$e->getMessage());}
ad_redirect('facturas/show.php?id='.$id);
