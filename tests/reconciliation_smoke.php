<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = db();
$invoiceId = (int)$pdo->query("SELECT id FROM ad_facturas WHERE numero_factura='FE10626' LIMIT 1")->fetchColumn();
if ($invoiceId === 0) {
    fwrite(STDERR, "No se encontró FE10626. Ejecute bin/install.php.\n");
    exit(1);
}
$result = (new ReconciliationService($pdo))->reconcileInvoice($invoiceId);
if (count($result['por_linea'] ?? []) !== 9) {
    fwrite(STDERR, 'Se esperaban 9 líneas de tarifa.' . PHP_EOL);
    exit(1);
}
if (count($result['por_plan'] ?? []) !== 7) {
    fwrite(STDERR, 'Se esperaban 7 planes conciliados.' . PHP_EOL);
    exit(1);
}
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
