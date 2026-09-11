<?php
declare(strict_types=1);

$title = $title ?? 'Activos Digitales';
$flashes = ad_pull_flashes();
$user = ad_current_user();
?>
<!doctype html>

<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#212529"
    >

    <title>
        <?= ad_e($title) ?> | Activos Digitales
    </title>

    <link
    rel="icon"
    type="image/png"
    sizes="512x512"
    href="<?= ad_e(
        ad_url('favicon.png?v=3')
    ) ?>"
>

<link
    rel="shortcut icon"
    type="image/png"
    href="<?= ad_e(
        ad_url('favicon.png?v=3')
    ) ?>"
>

<link
    rel="apple-touch-icon"
    href="<?= ad_e(
        ad_url('favicon.png?v=3')
    ) ?>"
>

    <!-- Ícono de la pestaña del navegador -->
    <link
        rel="icon"
        type="image/png"
        sizes="512x512"
        href="<?= ad_e(
            ad_url('assets/img/favicon.png')
        ) ?>"
    >

    <link
        rel="shortcut icon"
        type="image/png"
        href="<?= ad_e(
            ad_url('assets/img/favicon.png')
        ) ?>"
    >

    <link
        rel="apple-touch-icon"
        href="<?= ad_e(
            ad_url('assets/img/favicon.png')
        ) ?>"
    >

    <!-- Bootstrap -->
    <link
        rel="stylesheet"
        href="<?= ad_e(
            ad_url('assets/bootstrap.min.css')
        ) ?>"
    >

    <style>
        body {
            min-height: 100vh;
            background: #f4f6f9;
        }

        .navbar {
            min-height: 38px;
        }

        .card.card-body.mb-3{
         padding:1rem 1.1rem;
        }

        .form-label{
         margin-bottom:.45rem;
        font-weight:500;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        .navbar-logo {
            width: 120px;
            height: 120px;
            display: block;
            object-fit: contain;
            border-radius: 1px;
        }

        .navbar-brand-text {
            line-height: 1;
        }

        .env-badge {
            font-size: .68rem;
            letter-spacing: .08em;
            white-space: nowrap;
        }

        .navbar-nav .nav-link {
            padding-left: .75rem;
            padding-right: .75rem;
        }

        .current-user {
            white-space: nowrap;
        }

        .card-kpi {
            border: 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
        }

        .table thead th {
            white-space: nowrap;
        }

        .money {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        @media (max-width: 991.98px) {
            .env-badge {
                margin-top: 8px;
                margin-bottom: 8px;
            }

            .current-user {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 1px solid rgba(255, 255, 255, .15);
            }
        }

        .backup-summary{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    margin-top:12px;
}

.summary-box,
.backup-box{
    border-radius:10px;
    padding:12px 14px;
    border:1px solid #d9e2ef;
    min-height:84px;
}

.summary-box strong,
.backup-box strong{
    font-weight:600;
}

.backup-label{
    font-size:.72rem;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#5c6b7a;
    margin-bottom:6px;
    display:block;
}

.backup-value{
    font-size:1rem;
    font-weight:500;
    color:#1f2d3d;
}

/* Colores intercalados */
.bg-soft-blue{
    background:#eaf3ff;
    border-color:#cfe2ff;
}

.bg-soft-green{
    background:#eaf8ef;
    border-color:#cfe9d4;
}

.bg-soft-yellow{
    background:#fff8e6;
    border-color:#f5df9a;
}

.bg-soft-purple{
    background:#f2ecff;
    border-color:#ddd0ff;
}

.bg-soft-cyan{
    background:#e9fbff;
    border-color:#cceff7;
}

.bg-soft-pink{
    background:#fff0f4;
    border-color:#f6d3df;
}

.backup-item{
    border:1px solid #d6dbe3;
    border-radius:10px;
    margin-bottom:14px;
    background:#fff;
    overflow:hidden;
}

.backup-item summary{
    list-style:none;
    cursor:pointer;
    padding:12px 14px;
}

.backup-item summary::-webkit-details-marker{
    display:none;
}

.backup-item[open] summary{
    border-bottom:1px solid #e4e7eb;
    background:#fbfcfe;
}

.backup-details{
    padding:14px;
    background:#fff;
}

@media (max-width: 992px){
    .backup-summary{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media (max-width: 576px){
    .backup-summary{
        grid-template-columns:1fr;
    }
}

.backup-item{
    box-shadow:0 1px 4px rgba(0,0,0,.04);
    transition:all .2s ease;
}

.backup-item[open]{
    box-shadow:0 6px 18px rgba(0,0,0,.08);
}
    </style>
</head>

<body>

<nav
    class="navbar navbar-expand-lg navbar-dark bg-dark"
>
    <div class="container-fluid">

        <!-- Marca y logo -->
        <a
            class="navbar-brand"
            href="<?= ad_e(ad_url()) ?>"
        >
            <img
                src="<?= ad_e(
                    ad_url('assets/img/favicon.png')
                ) ?>"
                alt="Logo Activos Digitales"
                class="navbar-logo"
            >

            <span class="navbar-brand-text">
                Activos Digitales
            </span>
        </a>

        <!-- Indicador del ambiente -->
        <span
            class="badge text-bg-warning env-badge me-3"
        >
            PRUEBA INDEPENDIENTE
        </span>

        <!-- Botón responsive -->
        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNav"
            aria-controls="mainNav"
            aria-expanded="false"
            aria-label="Mostrar u ocultar menú"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div
            class="collapse navbar-collapse"
            id="mainNav"
        >
            <!-- Menú principal -->
            <div class="navbar-nav me-auto">

                <?php if (ad_can('cuentas.ver')): ?>
                    <a
                        class="nav-link"
                        href="<?= ad_e(
                            ad_url('cuentas/index.php')
                        ) ?>"
                    >
                        Cuentas
                    </a>
                <?php endif; ?>

                    <?php
    $headerRole = strtoupper(
        trim(
            (string)($user['rol'] ?? '')
        )
    );

    $headerCanViewNews =
        $headerRole === 'ADMIN'
        || ad_can('novedades.ver');
    ?>

    <?php if ($headerCanViewNews): ?>

                <a
    class="nav-link"
    href="<?= ad_e(
        ad_url('novedades/index.php')
    ) ?>"
>
    Novedades
</a>
<?php endif; ?>   

                <?php if (ad_can('respaldos.ver')): ?>
                    <a
                        class="nav-link"
                        href="<?= ad_e(
                            ad_url('respaldos/index.php')
                        ) ?>"
                    >
                        Backups
                    </a>
                <?php endif; ?>

                <?php if (ad_can('facturas.ver')): ?>
                    <a
                        class="nav-link"
                        href="<?= ad_e(
                            ad_url('facturas/index.php')
                        ) ?>"
                    >
                        Facturas
                    </a>
                <?php endif; ?>

            </div>

            <!-- Usuario autenticado -->
            <?php if ($user): ?>
                <div
                    class="d-flex align-items-center gap-3 text-white small current-user"
                >
                    <span>
                        <?= ad_e(
                            (string)($user['nombre'] ?? 'Usuario')
                        ) ?>

                        ·

                        <?= ad_e(
                            (string)($user['rol'] ?? '')
                        ) ?>
                    </span>

                    <form
                        method="post"
                        action="<?= ad_e(
                            ad_url('logout.php')
                        ) ?>"
                        class="m-0"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= ad_e(
                                ad_csrf_token()
                            ) ?>"
                        >

                        <button
                            class="btn btn-sm btn-outline-light"
                            type="submit"
                        >
                            Salir
                        </button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </div>
</nav>

<main class="container-fluid py-4">

    <?php foreach ($flashes as $flash): ?>
        <div
            class="alert alert-<?= ad_e(
                (string)($flash['type'] ?? 'info')
            ) ?> alert-dismissible fade show"
            role="alert"
        >
            <?= ad_e(
                (string)($flash['message'] ?? '')
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Cerrar"
            ></button>
        </div>
    <?php endforeach; ?>