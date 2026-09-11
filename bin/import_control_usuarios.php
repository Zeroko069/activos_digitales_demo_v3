<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/src/Import/SimpleXlsxReader.php';
require_once dirname(__DIR__) . '/src/Import/ControlUsuariosImporter.php';

$file = $argv[1] ?? null;
$force = in_array('--force', $argv, true);
if (!$file) {
    fwrite(STDERR, "Uso: php bin/import_control_usuarios.php /ruta/Control_Usuarios.xlsx [--force]\n");
    exit(1);
}

try {
    $importer = new ControlUsuariosImporter(db());
    $result = $importer->import($file, $force);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
