<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
ad_require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
ad_verify_csrf();
ad_logout();
header('Location: ' . ad_url('login.php'));
exit;
