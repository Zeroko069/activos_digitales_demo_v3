<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('respaldos.editar');

$pdo = db();

$backupId = (int)($_GET['id'] ?? 0);

if ($backupId <= 0) {
    ad_flash(
        'danger',
        'El backup indicado no es válido.'
    );

    ad_redirect('respaldos/index.php');
}

$stmt = $pdo->prepare(
    'SELECT
        r.*,
        c.correo

     FROM ad_respaldos r

     LEFT JOIN ad_cuentas c
        ON c.id = r.cuenta_id

     WHERE r.id = :id

     LIMIT 1'
);

$stmt->execute([
    ':id' => $backupId,
]);

$backup = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$backup) {
    ad_flash(
        'danger',
        'No se encontró el backup.'
    );

    ad_redirect('respaldos/index.php');
}

$title = 'Editar backup';

require dirname(__DIR__, 2)
    . '/views/header.php';
?>

<div class="mb-3">
    <h1 class="h3 mb-1">
        Editar backup
    </h1>

    <div class="text-muted">
        Corregir la ubicación y los datos del respaldo.
    </div>
</div>

<form
    method="post"
    action="<?= ad_e(
        ad_url('respaldos/save.php')
    ) ?>"
>
    <input
        type="hidden"
        name="_token"
        value="<?= ad_e(ad_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= (int)$backup['id'] ?>"
    >

<div class="card card-body">
    <div class="row g-3">

        <div class="col-lg-8">
            <label class="form-label">
                Cuenta
            </label>

            <input
                type="text"
                class="form-control"
                value="<?= ad_e(
                    $backup['correo']
                    ?: 'Sin cuenta vinculada'
                ) ?>"
                readonly
            >
        </div>

        <div class="col-lg-4">
            <label class="form-label">
                Fecha del backup
            </label>

            <input
                type="date"
                class="form-control"
                name="fecha_carga"
                value="<?= ad_e(
                    (string)$backup['fecha_carga']
                ) ?>"
                required
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Servidor
            </label>

            <input
                type="text"
                class="form-control"
                name="servidor"
                value="<?= ad_e(
                    (string)$backup['servidor']
                ) ?>"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Disco
            </label>

            <input
                type="text"
                class="form-control"
                name="carpeta"
                value="<?= ad_e(
                    (string)$backup['carpeta']
                ) ?>"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Subcarpeta 1
            </label>

            <input
                type="text"
                class="form-control"
                name="subcarpeta_1"
                value="<?= ad_e(
                    (string)$backup['subcarpeta_1']
                ) ?>"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Subcarpeta 2
            </label>

            <input
                type="text"
                class="form-control"
                name="subcarpeta_2"
                value="<?= ad_e(
                    (string)$backup['subcarpeta_2']
                ) ?>"
            >
        </div>

        <div class="col-lg-6">
            <label class="form-label">
                Nombre del archivo
            </label>

            <input
                type="text"
                class="form-control"
                name="archivo"
                value="<?= ad_e(
                    (string)$backup['archivo']
                ) ?>"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Tamaño PST
            </label>

            <input
                type="text"
                class="form-control"
                name="tamano_pst"
                value="<?= ad_e(
                    (string)$backup['tamano_pst']
                ) ?>"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Tamaño OneDrive
            </label>

            <input
                type="text"
                class="form-control"
                name="tamano_onedrive"
                value="<?= ad_e(
                    (string)$backup['tamano_onedrive']
                ) ?>"
            >
        </div>

    </div>
</div>

    <div class="mt-3">
        <button class="btn btn-primary">
            Guardar cambios
        </button>

        <a
            class="btn btn-outline-secondary"
            href="<?= ad_e(
                ad_url('respaldos/index.php')
            ) ?>"
        >
            Cancelar
        </a>
    </div>
</form>

<?php
require dirname(__DIR__, 2)
    . '/views/footer.php';
?>