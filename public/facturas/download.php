<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
ad_require_permission('facturas.ver');
$pdo=db();$id=(int)($_GET['id']??0);
$stmt=$pdo->prepare('SELECT numero_factura,archivo_pdf FROM ad_facturas WHERE id=:id');$stmt->execute([':id'=>$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row||empty($row['archivo_pdf'])){http_response_code(404);exit('Archivo no encontrado.');}
$storageRoot=realpath((string)ad_config('storage_path'));
$file=realpath(rtrim((string)ad_config('storage_path'),'/').'/'.ltrim($row['archivo_pdf'],'/'));
if($storageRoot===false||$file===false||!str_starts_with($file,$storageRoot.DIRECTORY_SEPARATOR)||!is_file($file)){http_response_code(404);exit('Archivo no encontrado.');}
header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9_.-]/','_', $row['numero_factura']).'.pdf"');header('Content-Length: '.filesize($file));header('X-Content-Type-Options: nosniff');readfile($file);exit;
