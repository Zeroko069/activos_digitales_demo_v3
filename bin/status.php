<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = db();
$queries = [
    'Usuarios de acceso' => 'SELECT COUNT(*) FROM ad_usuarios WHERE activo=1',
    'Personas' => 'SELECT COUNT(*) FROM ad_personas',
    'Cuentas' => 'SELECT COUNT(*) FROM ad_cuentas',
    'Asignaciones de licencia' => 'SELECT COUNT(*) FROM ad_asignaciones_licencia',
    'Facturas' => 'SELECT COUNT(*) FROM ad_facturas',
    'Importaciones' => 'SELECT COUNT(*) FROM ad_importaciones',
    'Errores de importación' => 'SELECT COUNT(*) FROM ad_importacion_errores',
];
foreach ($queries as $label => $sql) {
    printf("%-28s %s\n", $label . ':', number_format((int)$pdo->query($sql)->fetchColumn(), 0, ',', '.'));
}
