<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $pdo = db();
    $pdo->query('SELECT 1')->fetchColumn();
    $table = $pdo->query("SHOW TABLES LIKE 'ad_usuarios'")->fetchColumn();
    if (!$table) {
        ad_json_response([
            'status' => 'initializing',
            'database' => 'connected',
            'schema' => 'missing',
        ], 503);
    }
    ad_json_response([
        'status' => 'ok',
        'database' => 'connected',
        'schema' => 'ready',
    ]);
} catch (Throwable $e) {
    ad_json_response([
        'status' => 'error',
        'database' => 'disconnected',
        'message' => ad_config('environment') === 'testing' ? $e->getMessage() : 'Error de conexión.',
    ], 503);
}
