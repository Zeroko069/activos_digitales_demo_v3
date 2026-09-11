<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/module.php';
date_default_timezone_set($config['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('activos_digitales_demo');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require_once __DIR__ . '/src/Support/helpers.php';
require_once __DIR__ . '/src/Support/database.php';
require_once __DIR__ . '/src/Support/auth.php';
require_once __DIR__ . '/src/Services/AuditService.php';
require_once __DIR__ . '/src/Services/ReconciliationService.php';
