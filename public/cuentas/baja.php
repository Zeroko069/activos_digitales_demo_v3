<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.editar');

$pdo = db();

$cuentaId = (int)($_GET['id'] ?? 0);

if ($cuentaId <= 0) {
    ad_flash(
        'danger',
        'La cuenta indicada no es válida.'
    );

    ad_redirect('cuentas/index.php');
}

/*
 * Consultar la cuenta y su asignación activa actual.
 */
$stmt = $pdo->prepare(
    'SELECT
        c.id,
        c.correo,
        c.estado,
        c.pais,
        c.ciudad,
        c.fecha_creacion,
        c.fecha_eliminacion,
        c.observacion_baja,

        p.numero_documento,
        p.nombre_completo,
        p.cargo

     FROM ad_cuentas c

     LEFT JOIN ad_asignaciones_cuenta ac
        ON ac.id = (
            SELECT MAX(ac2.id)

            FROM ad_asignaciones_cuenta ac2

            WHERE ac2.cuenta_id = c.id
              AND ac2.estado = "ACTIVA"
        )

     LEFT JOIN ad_personas p
        ON p.id = ac.persona_id

     WHERE c.id = :id

     LIMIT 1'
);

$stmt->execute([
    ':id' => $cuentaId,
]);

$cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cuenta) {
    ad_flash(
        'danger',
        'No se encontró la cuenta.'
    );

    ad_redirect('cuentas/index.php');
}

$fechaBaja = trim(
    (string)($cuenta['fecha_eliminacion'] ?? '')
);

if ($fechaBaja === '') {
    $fechaBaja = date('Y-m-d');
}

$title = 'Gestionar baja';

require dirname(__DIR__, 2)
    . '/views/header.php';
?>

<div class="mb-3">
    <h1 class="h3 mb-1">
        Gestionar baja
    </h1>

    <div class="text-muted">
        Cambiar la cuenta a gestión de baja y registrar
        la observación correspondiente.
    </div>
</div>

<div class="alert alert-warning">
    Esta gestión solamente cambia el estado de la cuenta.
    No genera una novedad ni crea un registro de backup.
</div>

<form
    method="post"
    action="<?= ad_e(
        ad_url('cuentas/baja_save.php')
    ) ?>"
>
    <input
        type="hidden"
        name="_token"
        value="<?= ad_e(ad_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="cuenta_id"
        value="<?= (int)$cuenta['id'] ?>"
    >

    <div class="card card-body mb-3">
        <div class="row g-3">

            <div class="col-lg-6">
                <label class="form-label">
                    Dirección de correo
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= ad_e(
                        (string)$cuenta['correo']
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Estado actual
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= $cuenta['estado'] === 'ACTIVA'
                        ? 'Activa'
                        : 'Gestión de baja' ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Nuevo estado
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="Gestión de baja"
                    readonly
                >
            </div>

            <div class="col-lg-6">
                <label class="form-label">
                    Funcionario asignado
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= ad_e(
                        (string)(
                            $cuenta['nombre_completo']
                            ?: 'Sin funcionario asignado'
                        )
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Documento
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= ad_e(
                        (string)(
                            $cuenta['numero_documento']
                            ?? ''
                        )
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Cargo
                </label>

                <input
                    type="text"
                    class="form-control"
                    value="<?= ad_e(
                        (string)(
                            $cuenta['cargo']
                            ?? ''
                        )
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-4">
                <label class="form-label">
                    Fecha de baja *
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="fecha_baja"
                    value="<?= ad_e($fechaBaja) ?>"
                    required
                >
            </div>

            <div class="col-lg-8">
                <label class="form-label">
                    Observaciones *
                </label>

                <textarea
                    class="form-control"
                    name="observacion_baja"
                    rows="4"
                    required
                    placeholder="Indique el motivo o detalle de la gestión de baja."
                ><?= ad_e(
                    (string)(
                        $cuenta['observacion_baja']
                        ?? ''
                    )
                ) ?></textarea>
            </div>

        </div>
    </div>

    <button
        type="submit"
        class="btn btn-danger"
    >
        Guardar gestión de baja
    </button>

    <a
        class="btn btn-outline-secondary"
        href="<?= ad_e(
            ad_url('cuentas/index.php')
        ) ?>"
    >
        Cancelar
    </a>
</form>

<?php
require dirname(__DIR__, 2)
    . '/views/footer.php';
?>