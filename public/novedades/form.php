<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('novedades.editar');

$pdo = db();

$noveltyId = (int)($_GET['id'] ?? 0);

if ($noveltyId <= 0) {
    ad_flash(
        'danger',
        'La novedad indicada no es válida.'
    );

    ad_redirect('novedades/index.php');
}

$stmt = $pdo->prepare(
    'SELECT
        n.*,
        c.correo

     FROM ad_novedades n

     LEFT JOIN ad_cuentas c
        ON c.id = n.cuenta_id

     WHERE n.id = :id

     LIMIT 1'
);

$stmt->execute([
    ':id' => $noveltyId,
]);

$novelty = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$novelty) {
    ad_flash(
        'danger',
        'No se encontró la novedad.'
    );

    ad_redirect('novedades/index.php');
}

$originData = json_decode(
    (string)($novelty['datos_origen'] ?? ''),
    true
);

$originData = is_array($originData)
    ? $originData
    : [];

$observations = trim(
    (string)(
        $originData['observaciones']
        ?? $novelty['descripcion']
        ?? ''
    )
);

$typeLabel = ucwords(
    strtolower(
        str_replace(
            '_',
            ' ',
            (string)$novelty['tipo']
        )
    )
);

$title = 'Editar novedad';

require dirname(__DIR__, 2)
    . '/views/header.php';
?>

<div class="mb-3">
    <h1 class="h3 mb-1">
        Editar novedad
    </h1>

    <div class="text-muted">
        Corregir la fecha o las observaciones registradas.
    </div>
</div>

<div class="alert alert-info">
    Esta edición no modifica nuevamente la asignación,
    las licencias ni el estado de la cuenta.
</div>

<form
    method="post"
    action="<?= ad_e(
        ad_url('novedades/save.php')
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
        value="<?= (int)$novelty['id'] ?>"
    >

    <div class="card card-body">
        <div class="row g-3">

            <div class="col-lg-6">
                <label class="form-label">
                    Cuenta
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e(
                        $novelty['correo']
                        ?: 'Sin cuenta vinculada'
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Tipo de gestión
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e($typeLabel) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Fecha
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="fecha_novedad"
                    value="<?= ad_e(
                        (string)$novelty['fecha_novedad']
                    ) ?>"
                    required
                >
            </div>

            <div class="col-12">
                <label class="form-label">
                    Observaciones
                </label>

                <textarea
                    class="form-control"
                    name="observaciones"
                    rows="5"
                ><?= ad_e($observations) ?></textarea>
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
                ad_url('novedades/index.php')
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