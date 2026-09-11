<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (ad_is_authenticated()) {
    ad_redirect();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ad_verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Ingrese el correo y la contraseña.';
    } elseif (ad_attempt_login($email, $password)) {
        $target = $_SESSION['ad_intended_url'] ?? ad_url();
        unset($_SESSION['ad_intended_url']);
        header('Location: ' . $target);
        exit;
    } else {
        $error = 'Las credenciales no son válidas.';
        usleep(300000);
    }
}
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ingreso | Activos Digitales</title>
  <link rel="stylesheet" href="<?= ad_e(ad_url('assets/bootstrap.min.css')) ?>">
  <style>
    body{min-height:100vh;background:#eef1f5;display:flex;align-items:center}.login-card{max-width:430px;margin:auto;border:0;box-shadow:0 12px 40px rgba(0,0,0,.10)}
    .test-badge{letter-spacing:.08em}
  </style>
</head>
<body>
<div class="container py-5">
  <div class="card login-card">
    <div class="card-body p-4 p-md-5">
      <span class="badge text-bg-warning test-badge mb-3">AMBIENTE DE PRUEBA</span>
      <h1 class="h3">GESTOR DE USUARIOS</h1>
      <p class="text-muted"> Microsoft, Google y Cpanel</p>
      <?php if ($error): ?><div class="alert alert-danger"><?= ad_e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <input type="hidden" name="_token" value="<?= ad_e(ad_csrf_token()) ?>">
        <div class="mb-3">
          <label class="form-label" for="email">Correo</label>
          <input class="form-control" id="email" name="email" type="email" value="<?= ad_e($_POST['email'] ?? 'admin@prueba.local') ?>" autocomplete="username" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password">Contraseña</label>
          <input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-dark w-100" type="submit">Ingresar</button>
      </form>
      <div class="alert alert-light border mt-4 mb-0 small">
        <strong>Usuario de prueba:</strong> admin@prueba.local<br>
        <strong>Contraseña:</strong> Prueba2026
      </div>
    </div>
  </div>
</div>
</body>
</html>
