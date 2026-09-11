<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
ad_require_permission('facturas.crear');
ad_verify_csrf();
$pdo=db();
$details=$_POST['detalles']??[];
if(!is_array($details)||$details===[]){ad_flash('danger','Debe registrar al menos una línea.');ad_redirect('facturas/create.php');}

$normalizedDetails=[];$calculatedSubtotal=0.0;
foreach($details as $detail){
    $description=trim((string)($detail['descripcion']??''));
    $qty=(float)($detail['cantidad']??0);
    $unit=(float)($detail['valor_unitario']??0);
    if($description===''||$qty<=0||$unit<0){ad_flash('danger','Hay líneas incompletas o con valores inválidos.');ad_redirect('facturas/create.php');}
    $lineSubtotal=round($qty*$unit,2);
    $calculatedSubtotal+=$lineSubtotal;
    $normalizedDetails[]=['plan_id'=>($detail['plan_id']??'')!==''?(int)$detail['plan_id']:null,'descripcion'=>$description,'cantidad'=>$qty,'valor_unitario'=>$unit,'subtotal'=>$lineSubtotal];
}
$calculatedSubtotal=round($calculatedSubtotal,2);
$discounts=max(0.0,(float)($_POST['descuentos']??0));
$taxes=max(0.0,(float)($_POST['impuestos']??0));
$retentions=max(0.0,(float)($_POST['retenciones']??0));
$totalPayable=round($calculatedSubtotal-$discounts+$taxes-$retentions,2);
if($totalPayable<0){ad_flash('danger','El total a pagar no puede ser negativo.');ad_redirect('facturas/create.php');}

$file=$_FILES['factura_pdf']??null;
if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){ad_flash('danger','Debe adjuntar el PDF de la factura.');ad_redirect('facturas/create.php');}
if((int)$file['size']>(int)ad_config('max_pdf_bytes')){ad_flash('danger','El PDF supera el tamaño máximo permitido.');ad_redirect('facturas/create.php');}
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);
if($mime!=='application/pdf'){ad_flash('danger','El archivo adjunto no es un PDF válido.');ad_redirect('facturas/create.php');}
$year=date('Y');$month=date('m');$dir=rtrim((string)ad_config('storage_path'),'/').'/facturas/'.$year.'/'.$month;
if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir)){throw new RuntimeException('No se pudo crear el directorio de almacenamiento.');}
$storedName=bin2hex(random_bytes(16)).'.pdf';$fullPath=$dir.'/'.$storedName;
if(!move_uploaded_file($file['tmp_name'],$fullPath)){ad_flash('danger','No se pudo almacenar el PDF.');ad_redirect('facturas/create.php');}
$relative='facturas/'.$year.'/'.$month.'/'.$storedName;

$pdo->beginTransaction();
try{
  $stmt=$pdo->prepare('INSERT INTO ad_facturas (proveedor_id,razon_social_id,numero_factura,fecha_emision,fecha_recepcion,fecha_vencimiento,periodo_desde,periodo_hasta,moneda,subtotal,descuentos,impuestos,retenciones,total_pagar,orden_compra,cufe,metodo_pago,estado,archivo_pdf,created_by) VALUES (:proveedor,:razon,:numero,:emision,:recepcion,:vencimiento,:desde,:hasta,:moneda,:subtotal,:descuentos,:impuestos,:retenciones,:total,:oc,:cufe,"CREDITO","RECIBIDA",:archivo,:usuario)');
  $stmt->execute([
    ':proveedor'=>(int)$_POST['proveedor_id'],':razon'=>($_POST['razon_social_id']??'')!==''?(int)$_POST['razon_social_id']:null,
    ':numero'=>trim((string)$_POST['numero_factura']),':emision'=>$_POST['fecha_emision'],':recepcion'=>($_POST['fecha_recepcion']??'')?:null,
    ':vencimiento'=>($_POST['fecha_vencimiento']??'')?:null,':desde'=>($_POST['periodo_desde']??'')?:null,':hasta'=>($_POST['periodo_hasta']??'')?:null,
    ':moneda'=>$_POST['moneda']??'COP',':subtotal'=>$calculatedSubtotal,':descuentos'=>$discounts,':impuestos'=>$taxes,
    ':retenciones'=>$retentions,':total'=>$totalPayable,':oc'=>trim((string)($_POST['orden_compra']??''))?:null,
    ':cufe'=>trim((string)($_POST['cufe']??''))?:null,':archivo'=>$relative,':usuario'=>ad_current_user_id(),
  ]);
  $invoiceId=(int)$pdo->lastInsertId();
  $detailStmt=$pdo->prepare('INSERT INTO ad_factura_detalles (factura_id,plan_id,descripcion,descripcion_normalizada,cantidad,valor_unitario,subtotal,total) VALUES (:factura,:plan,:descripcion,:normalizada,:cantidad,:unitario,:subtotal,:total)');
  foreach($normalizedDetails as $detail){
    $detailStmt->execute([':factura'=>$invoiceId,':plan'=>$detail['plan_id'],':descripcion'=>$detail['descripcion'],':normalizada'=>ad_normalize_text($detail['descripcion']),':cantidad'=>$detail['cantidad'],':unitario'=>$detail['valor_unitario'],':subtotal'=>$detail['subtotal'],':total'=>$detail['subtotal']]);
  }
  AuditService::log($pdo,'ad_facturas',$invoiceId,'CREAR',null,['numero_factura'=>$_POST['numero_factura'],'subtotal'=>$calculatedSubtotal,'descuentos'=>$discounts,'impuestos'=>$taxes,'retenciones'=>$retentions,'total_pagar'=>$totalPayable]);
  $pdo->commit();ad_flash('success','Factura registrada correctamente.');ad_redirect('facturas/show.php?id='.$invoiceId);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();@unlink($fullPath);ad_flash('danger','No fue posible guardar la factura: '.$e->getMessage());ad_redirect('facturas/create.php');}
