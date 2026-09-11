<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/bootstrap.php';

$file = $argv[1] ?? null;
$invoiceNumber = $argv[2] ?? null;
if (!$file || !$invoiceNumber) {
    fwrite(STDERR, "Uso: php bin/attach_invoice_pdf.php /ruta/factura.pdf NUMERO_FACTURA\n");
    exit(1);
}
if (!is_file($file)) {
    fwrite(STDERR, "No existe el archivo indicado.\n");
    exit(1);
}
$finfo = new finfo(FILEINFO_MIME_TYPE);
if ($finfo->file($file) !== 'application/pdf') {
    fwrite(STDERR, "El archivo no es un PDF válido.\n");
    exit(1);
}
if (filesize($file) > (int)ad_config('max_pdf_bytes')) {
    fwrite(STDERR, "El archivo supera el tamaño máximo configurado.\n");
    exit(1);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, archivo_pdf FROM ad_facturas WHERE numero_factura=:numero ORDER BY id DESC LIMIT 1');
$stmt->execute([':numero' => $invoiceNumber]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
$invoiceId = (int)($invoice['id'] ?? 0);
if ($invoiceId === 0) {
    fwrite(STDERR, "No se encontró la factura {$invoiceNumber}.\n");
    exit(1);
}

$existingRelative = (string)($invoice['archivo_pdf'] ?? '');
$existingFull = $existingRelative !== '' ? rtrim((string)ad_config('storage_path'), '/') . '/' . $existingRelative : '';
if ($existingFull !== '' && is_file($existingFull)) {
    fwrite(STDOUT, "La factura {$invoiceNumber} ya tiene un PDF asociado.\n");
    exit(0);
}

$year = date('Y');
$month = date('m');
$directory = rtrim((string)ad_config('storage_path'), '/') . '/facturas/' . $year . '/' . $month;
if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
    fwrite(STDERR, "No fue posible crear el directorio de destino.\n");
    exit(1);
}
$storedName = bin2hex(random_bytes(16)) . '.pdf';
$destination = $directory . '/' . $storedName;
if (!copy($file, $destination)) {
    fwrite(STDERR, "No fue posible copiar el PDF.\n");
    exit(1);
}
$relative = 'facturas/' . $year . '/' . $month . '/' . $storedName;
$stmt = $pdo->prepare('UPDATE ad_facturas SET archivo_pdf=:archivo WHERE id=:id');
$stmt->execute([':archivo' => $relative, ':id' => $invoiceId]);
AuditService::log($pdo, 'ad_facturas', $invoiceId, 'ACTUALIZAR', null, ['archivo_pdf' => $relative]);
fwrite(STDOUT, "PDF asociado correctamente a {$invoiceNumber}.\n");
