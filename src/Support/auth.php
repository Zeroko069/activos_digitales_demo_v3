<?php
declare(strict_types=1);

function ad_current_user(): ?array
{
    $user = $_SESSION['ad_user'] ?? null;
    return is_array($user) ? $user : null;
}

function ad_is_authenticated(): bool
{
    return ad_current_user() !== null;
}

function ad_user_role(): string
{
    $user = ad_current_user();
    return $user ? mb_strtoupper(trim((string)$user['rol']), 'UTF-8') : 'SIN_ROL';
}

function ad_attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare(
        'SELECT id, nombre, email, password_hash, rol
         FROM ad_usuarios
         WHERE email=:email AND activo=1
         LIMIT 1'
    );
    $stmt->execute([':email' => mb_strtolower(trim($email), 'UTF-8')]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['ad_user'] = [
        'id' => (int)$user['id'],
        'nombre' => (string)$user['nombre'],
        'email' => (string)$user['email'],
        'rol' => (string)$user['rol'],
    ];

    $update = db()->prepare('UPDATE ad_usuarios SET ultimo_acceso=NOW() WHERE id=:id');
    $update->execute([':id' => (int)$user['id']]);
    return true;
}

function ad_logout(): void
{
    unset($_SESSION['ad_user']);
    session_regenerate_id(true);
}

function ad_require_auth(): void
{
    if (!ad_is_authenticated()) {
        $target = $_SERVER['REQUEST_URI'] ?? ad_url();
        $_SESSION['ad_intended_url'] = $target;
        header('Location: ' . ad_url('login.php'));
        exit;
    }
}

function ad_can(string $permission): bool
{
    if (!ad_is_authenticated()) {
        return false;
    }
    $role = ad_user_role();
    $roles = ad_config('roles') ?? [];
    $permissions = $roles[$role] ?? [];
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function ad_require_permission(string $permission): void
{
    ad_require_auth();
    if (!ad_can($permission)) {
        http_response_code(403);
        exit('No tiene permisos para realizar esta acción.');
    }
}
