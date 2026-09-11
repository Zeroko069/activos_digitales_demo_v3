<?php
declare(strict_types=1);

function ad_config(?string $key = null): mixed
{
    static $config;
    if ($config === null) {
        $config = require dirname(__DIR__, 2) . '/config/module.php';
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function ad_url(string $path = ''): string
{
    $base = rtrim((string)ad_config('base_url'), '/');
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }
    return ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
}

function ad_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ad_csrf_token(): string
{
    if (empty($_SESSION['ad_csrf'])) {
        $_SESSION['ad_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ad_csrf'];
}

function ad_verify_csrf(): void
{
    $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(ad_csrf_token(), $token)) {
        http_response_code(419);
        exit('Token CSRF inválido.');
    }
}

function ad_redirect(string $path): never
{
    header('Location: ' . ad_url($path));
    exit;
}

function ad_flash(string $type, string $message): void
{
    $_SESSION['ad_flash'][] = ['type' => $type, 'message' => $message];
}

function ad_pull_flashes(): array
{
    $items = $_SESSION['ad_flash'] ?? [];
    unset($_SESSION['ad_flash']);
    return is_array($items) ? $items : [];
}

function ad_normalize_text(?string $value): string
{
    $value = trim((string)$value);
    $value = strtr($value, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N',
    ]);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return mb_strtoupper($value, 'UTF-8');
}

function ad_email_normalize(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

function ad_money(mixed $value, string $currency = 'COP'): string
{
    return $currency . ' ' . number_format((float)$value, 2, ',', '.');
}

function ad_current_user_id(): ?int
{
    $user = ad_current_user();
    return $user ? (int)$user['id'] : null;
}

function ad_json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ad_excel_serial_to_date(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_numeric($value)) {
        $days = (int)floor((float)$value);
        $date = (new DateTimeImmutable('1899-12-30'))->modify('+' . $days . ' days');
        return $date->format('Y-m-d');
    }
    $parsed = date_create_immutable((string)$value);
    return $parsed ? $parsed->format('Y-m-d') : null;
}
