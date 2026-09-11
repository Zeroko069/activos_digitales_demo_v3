<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.editar');

$pdo = db();
$duplicateEmail = trim(
    (string)(
        $_SESSION['cuenta_duplicada_correo']
        ?? ''
    )
);

/*
 * La variable se elimina para que la alerta
 * solamente aparezca una vez.
 */
unset(
    $_SESSION['cuenta_duplicada_correo']
);
$id = (int)($_GET['id'] ?? 0);

$account = [
    'id' => 0,
    'correo' => '',
    'tipo' => 'PERSONAL',
    'estado' => 'ACTIVA',
    'pais' => 'CO',
    'ciudad' => '',
    'razon_social_id' => '',
    'observaciones' => '',
    'fecha_creacion' => date('Y-m-d'),
];

$assignedPerson = [
    'id' => '',
    'numero_documento' => '',
    'nombre_completo' => '',
    'cargo' => '',
    'ciudad' => '',
    'proceso' => '',
    'cliente' => '',
    'proyecto' => '',
    'razon_social' => '',
    'fuente' => '',
];

$selectedSubscriptionIds = [];

if ($id > 0) {
    /* Cargar datos de la cuenta */
    $stmt = $pdo->prepare(
        'SELECT * FROM ad_cuentas WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC)
        ?: $account;

    /* Cargar funcionario asignado */
    $stmt = $pdo->prepare(
        'SELECT
            p.id,
            p.numero_documento,
            p.nombre_completo,
            p.cargo,
            p.ciudad,
            p.proceso,
            p.cliente,
            p.proyecto,
            p.fuente,
            rs.nombre AS razon_social
         FROM ad_asignaciones_cuenta ac
         INNER JOIN ad_personas p
           ON p.id = ac.persona_id
         LEFT JOIN ad_razones_sociales rs
           ON rs.id = p.razon_social_id
         WHERE ac.cuenta_id = :cuenta
           AND ac.estado = "ACTIVA"
         ORDER BY ac.id DESC
         LIMIT 1'
    );
    $stmt->execute([':cuenta' => $id]);
    $assignedPerson = $stmt->fetch(PDO::FETCH_ASSOC)
        ?: $assignedPerson;

    /* Licencias actualmente asignadas a esta cuenta */
    $stmt = $pdo->prepare(
        'SELECT suscripcion_id
         FROM ad_asignaciones_licencia
         WHERE cuenta_id = :cuenta
           AND estado = "ACTIVA"'
    );
    $stmt->execute([':cuenta' => $id]);
    $selectedSubscriptionIds = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

$reasons = $pdo->query(
    'SELECT id, nombre, pais
     FROM ad_razones_sociales
     WHERE activa = 1
     ORDER BY pais, nombre'
)->fetchAll(PDO::FETCH_ASSOC);

/*
 * Suscripciones vigentes agrupadas por tipo de licencia (plan).
 * Cada fila es un tipo de licencia único con:
 *   - licencia: nombre del plan
 *   - id: id representativo (la primera suscripción vigente del plan)
 *   - valor_usd: valor unitario mínimo entre las suscripciones del plan
 *   - total_en_uso: suma de asignaciones activas de todas las suscripciones del plan
 *   - es_ilimitada: 1 si alguna suscripción del plan tiene cantidad_contratada NULL
 *   - cantidad_disponible: total contratado − total en uso (NULL si es ilimitada)
 */
$subscriptions = $pdo->query('
    SELECT
        x.licencia,
        MIN(x.id) AS id,
        MIN(x.valor_usd) AS valor_usd,
        MAX(x.total_contratada) AS total_contratada,
        COUNT(DISTINCT x.cuenta_asignada_id) AS total_en_uso,

        CASE
            WHEN MAX(x.total_contratada_is_null) = 1
            THEN 1
            ELSE 0
        END AS es_ilimitada,

        CASE
    WHEN MAX(x.total_contratada_is_null) = 1
    THEN NULL

    ELSE GREATEST(
        CAST(MAX(x.total_contratada) AS SIGNED)
        -
        CAST(COUNT(DISTINCT x.cuenta_asignada_id) AS SIGNED),
        0
    )

END AS cantidad_disponible

    FROM (
        SELECT
            s.id,
            s.valor_unitario AS valor_usd,
            s.cantidad_contratada AS total_contratada,
            CASE
                WHEN s.cantidad_contratada IS NULL THEN 1 ELSE 0
            END AS total_contratada_is_null,
            al.cuenta_id AS cuenta_asignada_id,

            CASE
                WHEN UPPER(TRIM(p.nombre)) LIKE "%EXCHANGE ONLINE PLAN 1%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%EXCHANGE ONLINE (PLAN 1)%"
                THEN "EXCHANGE ONLINE PLAN 1"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%BUSINESS BASIC%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%EMPRESA BASICO%"
                THEN "MICROSOFT 365 BUSINESS BASIC"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%BUSINESS STANDARD%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%EMPRESA ESTANDAR%"
                THEN "MICROSOFT 365 BUSINESS STANDARD"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%PROJECT PLAN 3%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%PLANNER AND PROJECT PLAN 3%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%PLANNER Y PROJECT PLAN 3%"
                THEN "PLANNER AND PROJECT PLAN 3"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%ONEDRIVE FOR BUSINESS PLAN 2%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%ONEDRIVE PARA LA EMPRESA (PLAN 2)%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%ONEDRIVE PARA LA EMPRESA PLAN 2%"
                THEN "ONEDRIVE FOR BUSINESS PLAN 2"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%POWER BI PREMIUM%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%POWER BI PREMIUM POR USUARIO%"
                THEN "POWER BI PREMIUM PER USER"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%POWER BI PRO%"
                THEN "POWER BI PRO"

                ELSE UPPER(TRIM(p.nombre))
            END AS licencia

        FROM ad_suscripciones s

        INNER JOIN ad_planes p
            ON p.id = s.plan_id

        LEFT JOIN ad_asignaciones_licencia al
            ON al.suscripcion_id = s.id
           AND al.estado = "ACTIVA"

        WHERE s.estado = "VIGENTE"
          AND UPPER(TRIM(p.nombre)) NOT LIKE "%ONEDRIVE FOR BUSINESS PLAN 2%"
          AND UPPER(TRIM(p.nombre)) NOT LIKE "%ONEDRIVE PARA LA EMPRESA (PLAN 2)%"
          AND UPPER(TRIM(p.nombre)) NOT LIKE "%ONEDRIVE PARA LA EMPRESA PLAN 2%"

        GROUP BY
            s.id,
            s.valor_unitario,
            s.cantidad_contratada,
            al.cuenta_id,
            p.nombre
    ) x

    WHERE licencia IS NOT NULL
    GROUP BY licencia
    ORDER BY licencia
')->fetchAll(PDO::FETCH_ASSOC);

/*
 * Nombres de licencia ya asignadas a esta cuenta.
 * Se consulta directo en la BD para que funcione
 * correctamente con la agrupación por plan.
 */
$selectedLicenseKeys = [];

if ($id > 0) {
    $stmtSel = $pdo->prepare('
        SELECT DISTINCT
            CASE
                WHEN UPPER(TRIM(p.nombre)) LIKE "%EXCHANGE ONLINE PLAN 1%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%EXCHANGE ONLINE (PLAN 1)%"
                THEN "EXCHANGE ONLINE PLAN 1"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%BUSINESS BASIC%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%EMPRESA BASICO%"
                THEN "MICROSOFT 365 BUSINESS BASIC"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%BUSINESS STANDARD%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%EMPRESA ESTANDAR%"
                THEN "MICROSOFT 365 BUSINESS STANDARD"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%PROJECT PLAN 3%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%PLANNER AND PROJECT PLAN 3%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%PLANNER Y PROJECT PLAN 3%"
                THEN "PLANNER AND PROJECT PLAN 3"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%ONEDRIVE FOR BUSINESS PLAN 2%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%ONEDRIVE PARA LA EMPRESA (PLAN 2)%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%ONEDRIVE PARA LA EMPRESA PLAN 2%"
                THEN "ONEDRIVE FOR BUSINESS PLAN 2"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%POWER BI PREMIUM%"
                  OR UPPER(TRIM(p.nombre)) LIKE "%POWER BI PREMIUM POR USUARIO%"
                THEN "POWER BI PREMIUM PER USER"

                WHEN UPPER(TRIM(p.nombre)) LIKE "%POWER BI PRO%"
                THEN "POWER BI PRO"

                ELSE UPPER(TRIM(p.nombre))
            END
        FROM ad_asignaciones_licencia al
        INNER JOIN ad_suscripciones s
            ON s.id = al.suscripcion_id
        INNER JOIN ad_planes p
            ON p.id = s.plan_id
        WHERE al.cuenta_id = :cuenta
          AND al.estado = "ACTIVA"
    ');
    $stmtSel->execute([':cuenta' => $id]);
    $selectedLicenseKeys = $stmtSel->fetchAll(
        PDO::FETCH_COLUMN
    );
}

$title = $id > 0
    ? 'Editar cuenta'
    : 'Nueva cuenta';

require dirname(__DIR__, 2)
    . '/views/header.php';
?>

<?php if ($duplicateEmail !== ''): ?>
<div
    class="modal fade"
    id="duplicateEmailModal"
    tabindex="-1"
    aria-labelledby="duplicateEmailModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header border-0 pb-0">
                <h5
                    class="modal-title fw-bold"
                    id="duplicateEmailModalLabel"
                >
                    Cuenta ya registrada
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                ></button>
            </div>

            <div class="modal-body pt-3">
                <div
                    class="d-flex align-items-start gap-3"
                >
                    <div
                        class="d-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning-emphasis flex-shrink-0"
                        style="width: 48px; height: 48px; font-size: 24px;"
                    >
                        !
                    </div>

                    <div>
                        <p class="mb-2">
                            No es posible crear la cuenta porque
                            el correo ya se encuentra registrado.
                        </p>

                        <div
                            class="border rounded-3 bg-light p-3 fw-semibold"
                            style="overflow-wrap: anywhere;"
                        >
                            <?= ad_e($duplicateEmail) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0">
                <button
                    type="button"
                    class="btn btn-dark px-4"
                    data-bs-dismiss="modal"
                >
                    Entendido
                </button>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener(
    'DOMContentLoaded',
    function () {
        const modalElement = document.getElementById(
            'duplicateEmailModal'
        );

        if (
            modalElement
            && typeof bootstrap !== 'undefined'
        ) {
            const duplicateModal =
                new bootstrap.Modal(modalElement);

            duplicateModal.show();
        }
    }
);
</script>
<?php endif; ?>

<?php if ($duplicateEmail !== ''): ?>

<style>
    .duplicate-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, 0.62);
        backdrop-filter: blur(3px);
    }

    .duplicate-modal-card {
        width: min(480px, 100%);
        overflow: hidden;
        border: 0;
        border-radius: 16px;
        background: #ffffff;
        box-shadow:
            0 24px 60px rgba(0, 0, 0, 0.28);
        animation: duplicateModalOpen 0.2s ease-out;
    }

    .duplicate-modal-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 22px 24px 14px;
    }

    .duplicate-modal-icon {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #fff3cd;
        color: #856404;
        font-size: 28px;
        font-weight: 800;
    }

    .duplicate-modal-title {
        margin: 0;
        color: #212529;
        font-size: 1.25rem;
        font-weight: 700;
    }

    .duplicate-modal-body {
        padding: 4px 24px 22px;
        color: #495057;
    }

    .duplicate-modal-email {
        margin-top: 14px;
        padding: 13px 15px;
        border: 1px solid #f1aeb5;
        border-radius: 10px;
        background: #f8d7da;
        color: #842029;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .duplicate-modal-footer {
        display: flex;
        justify-content: flex-end;
        padding: 16px 24px;
        border-top: 1px solid #e9ecef;
        background: #f8f9fa;
    }

    @keyframes duplicateModalOpen {
        from {
            opacity: 0;
            transform: translateY(12px) scale(0.97);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>

<div
    class="duplicate-modal-overlay"
    id="duplicateModalOverlay"
    role="dialog"
    aria-modal="true"
    aria-labelledby="duplicateModalTitle"
>
    <div class="duplicate-modal-card">

        <div class="duplicate-modal-header">
            <div
                class="duplicate-modal-icon"
                aria-hidden="true"
            >
                !
            </div>

            <div>
                <h2
                    class="duplicate-modal-title"
                    id="duplicateModalTitle"
                >
                    Cuenta ya registrada
                </h2>

                <div class="text-muted small mt-1">
                    No se realizó ningún cambio.
                </div>
            </div>
        </div>

        <div class="duplicate-modal-body">
            No es posible crear la cuenta porque la dirección
            de correo ya se encuentra registrada en el sistema.

            <div class="duplicate-modal-email">
                <?= ad_e($duplicateEmail) ?>
            </div>
        </div>

        <div class="duplicate-modal-footer">
            <button
                type="button"
                class="btn btn-dark px-4"
                id="closeDuplicateModal"
            >
                Entendido
            </button>
        </div>

    </div>
</div>

<script>
(function () {
    const overlay = document.getElementById(
        'duplicateModalOverlay'
    );

    const closeButton = document.getElementById(
        'closeDuplicateModal'
    );

    if (!overlay || !closeButton) {
        return;
    }

    function closeDuplicateModal() {
        overlay.remove();
    }

    closeButton.addEventListener(
        'click',
        closeDuplicateModal
    );

    overlay.addEventListener(
        'click',
        function (event) {
            if (event.target === overlay) {
                closeDuplicateModal();
            }
        }
    );

    document.addEventListener(
        'keydown',
        function closeOnEscape(event) {
            if (event.key === 'Escape') {
                closeDuplicateModal();

                document.removeEventListener(
                    'keydown',
                    closeOnEscape
                );
            }
        }
    );

    closeButton.focus();
})();
</script>

<?php endif; ?>

<h1 class="h3 mb-3">
    <?= ad_e($title) ?>
</h1>

<form
    method="post"
    action="<?= ad_e(ad_url('cuentas/save.php')) ?>"
>
    <input
        type="hidden"
        name="_token"
        value="<?= ad_e(ad_csrf_token()) ?>"
    >

    <input
        type="hidden"
        name="id"
        value="<?= (int)$account['id'] ?>"
    >

    <input
        type="hidden"
        name="persona_id"
        id="persona_id"
        value="<?= ad_e($assignedPerson['id']) ?>"
    >

    <div class="card card-body mb-3">
        <h2 class="h5">
            1. Consultar funcionario
        </h2>

        <p class="text-muted">
            Los datos se consultan desde
            MatrizHDUniclass o PLANTA PERU.
        </p>

        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">
                    País
                </label>

                <select
                    class="form-select"
                    name="pais"
                    id="pais"
                >
                    <option
                        value="CO"
                        <?= $account['pais'] === 'CO'
                            ? 'selected'
                            : '' ?>
                    >
                        Colombia
                    </option>

                    <option
                        value="PE"
                        <?= $account['pais'] === 'PE'
                            ? 'selected'
                            : '' ?>
                    >
                        Perú
                    </option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">
                    Número de documento
                </label>

                <div class="input-group">
                    <input
                        class="form-control"
                        id="numero_documento"
                        value="<?= ad_e(
                            $assignedPerson[
                                'numero_documento'
                            ]
                        ) ?>"
                    >

                    <button
                        class="btn btn-dark"
                        type="button"
                        id="buscar_funcionario"
                    >
                        Consultar
                    </button>
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">
                    Resultado
                </label>

                <div
                    class="alert alert-secondary py-2 mb-0"
                    id="mensaje_funcionario"
                >
                    Digite la cédula y presione
                    Consultar.
                </div>
            </div>

            <div class="col-md-6">
                <label class="form-label">
                    Nombre completo
                </label>

                <input
                    class="form-control"
                    id="nombre_completo"
                    readonly
                    value="<?= ad_e(
                        $assignedPerson['nombre_completo']
                    ) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">
                    Cargo
                </label>

                <input
                    class="form-control"
                    id="cargo"
                    readonly
                    value="<?= ad_e(
                        $assignedPerson['cargo']
                    ) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">
                    Ciudad
                </label>

                <input
                    class="form-control"
                    id="persona_ciudad"
                    readonly
                    value="<?= ad_e(
                        $assignedPerson['ciudad']
                    ) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">
                    Proceso
                </label>

                <input
                    class="form-control"
                    id="proceso"
                    readonly
                    value="<?= ad_e(
                        $assignedPerson['proceso']
                    ) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">
                    Cliente
                </label>

                <input
                    class="form-control"
                    id="cliente"
                    readonly
                    value="<?= ad_e(
                        $assignedPerson['cliente']
                    ) ?>"
                >
            </div>

            <div class="col-md-3">
                <label class="form-label">
                    Proyecto
                </label>

                <input
                    class="form-control"
                    id="proyecto"
                    readonly
                    value="<?= ad_e(
                        $assignedPerson['proyecto']
                    ) ?>"
                >
            </div>
        </div>
    </div>

    <div class="card card-body mb-3">
    <h2 class="h5">
        2. Datos de la cuenta
    </h2>

<div class="col-md-6">
    <label class="form-label">
        Correo *
    </label>

    <input
        type="email"
        class="form-control"
        name="correo"
        required
        value="<?= ad_e($account['correo']) ?>"
    >
</div>

<div class="col-md-3">
    <label class="form-label">
        Estado
    </label>

    <select
        class="form-select"
        name="estado"
        required
    >
        <option
            value="ACTIVA"
            <?= $account['estado'] === 'ACTIVA'
                ? 'selected'
                : '' ?>
        >
            Activa
        </option>

        <option
            value="GESTION_DE_BAJA"
            <?= $account['estado'] === 'GESTION_DE_BAJA'
                ? 'selected'
                : '' ?>
        >
            Gestión de baja
        </option>
    </select>
</div>

<div class="col-md-3">
    <label class="form-label">
        Fecha de creación
    </label>

    <input
        type="date"
        class="form-control"
        name="fecha_creacion"
        value="<?= ad_e(
            $account['fecha_creacion']
        ) ?>"
    >
</div>

<input
    type="hidden"
    name="razon_social_id"
    id="razon_social_id"
    value="<?= ad_e(
        $account['razon_social_id']
    ) ?>"
>

        <div class="col-md-3">
            <label class="form-label">
                Ciudad
            </label>

            <input
                class="form-control"
                name="ciudad"
                id="ciudad_cuenta"
                value="<?= ad_e($account['ciudad']) ?>"
            >
        </div>

        <div class="col-12">
            <label class="form-label">
                Observaciones
            </label>

            <textarea
                class="form-control"
                name="observaciones"
                rows="3"
            ><?= ad_e($account['observaciones']) ?></textarea>
        </div>
    </div>
</div>

<div class="card card-body mb-3">
    <h2 class="h5 mb-1">
        3. Seleccionar licencia
    </h2>

    <p class="text-muted mb-3">
        Las licencias limitadas muestran el total en uso
        y la cantidad disponible. Las licencias sin límite
        definido se muestran como ilimitadas.
    </p>

    <div class="table-responsive">
        <table
            class="table table-sm table-hover align-middle mb-0"
        >
            <thead>
                <tr>
                    <th style="width:80px">
                        Asignar
                    </th>

                    <th>
                        Tipo de licencia
                    </th>

                    <th class="text-end">
                        Valor USD
                    </th>

                    <th class="text-end">
                        En uso
                    </th>

                    <th class="text-end">
                        Disponible
                    </th>
                </tr>
            </thead>

            <tbody>
            <?php foreach (
                $subscriptions as $subscription
            ): ?>
                <?php
                $subscriptionId =
                    (int)$subscription['id'];

                $licenseKey =
                    (string)$subscription['licencia'];

                $checked = in_array(
                    $licenseKey,
                    $selectedLicenseKeys,
                    true
                );

                $isUnlimited =
                    (int)$subscription[
                        'es_ilimitada'
                    ] === 1;

                $inUse = (int)$subscription[
                    'total_en_uso'
                ];

                $available = $isUnlimited
                    ? null
                    : (int)$subscription[
                        'cantidad_disponible'
                    ];

                /*
                 * Una licencia ilimitada nunca se bloquea.
                 * Una licencia ya asignada tampoco se bloquea
                 * cuando se edita la cuenta.
                 */
                $disabled =
                    !$isUnlimited
                    && $available <= 0
                    && !$checked;

                $usdValue =
                    $subscription['valor_usd'];
                ?>

                <tr>
                    <td class="text-center">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            name="suscripciones[]"
                            value="<?= $subscriptionId ?>"
                            <?= $checked
                                ? 'checked'
                                : '' ?>
                            <?= $disabled
                                ? 'disabled'
                                : '' ?>
                        >
                    </td>

                    <td>
                        <strong>
                            <?= ad_e($licenseKey) ?>
                        </strong>

                        <?php if ($disabled): ?>
                            <div class="small text-danger">
                                Sin disponibilidad
                            </div>
                        <?php endif; ?>
                    </td>

                    <td class="text-end">
                        <?php if ($usdValue !== null): ?>
                            USD
                            <?= number_format(
                                (float)$usdValue,
                                2,
                                '.',
                                ','
                            ) ?>
                        <?php else: ?>
                            <span class="text-muted">
                                Sin valor USD
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="text-end">
                        <?= number_format(
                            $inUse,
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td class="text-end">
                        <?php if ($isUnlimited): ?>
                            <span
                                class="badge text-bg-primary"
                            >
                                Ilimitada
                            </span>
                        <?php elseif ($available > 0): ?>
                            <span
                                class="badge text-bg-success"
                            >
                                <?= number_format(
                                    $available,
                                    0,
                                    ',',
                                    '.'
                                ) ?>
                            </span>
                        <?php else: ?>
                            <span
                                class="badge text-bg-danger"
                            >
                                0
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($subscriptions === []): ?>
                <tr>
                    <td
                        colspan="5"
                        class="text-center text-muted p-4"
                    >
                        No existen licencias vigentes.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    <button class="btn btn-primary">
        Guardar cuenta y asignaciones
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    /*
     * Esta pantalla debe mostrar una única fecha de creación.
     * Se conserva el primer campo (el que se envía al guardar) y
     * se retira cualquier copia adicional que pueda llegar a la vista.
     * El selector está limitado a este nombre de campo, por lo que no
     * afecta fechas de otros formularios.
     */
    const fechasCreacion = document.querySelectorAll(
        'input[name="fecha_creacion"]'
    );

    fechasCreacion.forEach(function (fecha, indice) {
        if (indice === 0) {
            return;
        }

        const contenedor = fecha.closest('.col-md-3');

        if (contenedor) {
            contenedor.remove();
        } else {
            fecha.remove();
        }
    });

    const botonConsultar =
        document.getElementById('buscar_funcionario');

    const inputDocumento =
        document.getElementById('numero_documento');

    const selectPais =
        document.getElementById('pais');

    const mensaje =
        document.getElementById('mensaje_funcionario');

    const inputPersonaId =
        document.getElementById('persona_id');

    const camposPersona = {
        nombre_completo:
            document.getElementById('nombre_completo'),

        cargo:
            document.getElementById('cargo'),

        ciudad:
            document.getElementById('persona_ciudad'),

        proceso:
            document.getElementById('proceso'),

        cliente:
            document.getElementById('cliente'),

        proyecto:
            document.getElementById('proyecto')
    };

    const selectRazonSocial =
        document.getElementById('razon_social_id');

    const inputCiudadCuenta =
        document.getElementById('ciudad_cuenta');

    /*
     * Verificar que los elementos principales
     * realmente existan en el formulario.
     */
    if (
        !botonConsultar
        || !inputDocumento
        || !selectPais
        || !mensaje
        || !inputPersonaId
    ) {
        console.error(
            'No se encontraron todos los elementos '
            + 'necesarios para consultar funcionarios.',
            {
                botonConsultar,
                inputDocumento,
                selectPais,
                mensaje,
                inputPersonaId
            }
        );

        return;
    }

    function limpiarFuncionario() {
        inputPersonaId.value = '';

        Object.values(camposPersona).forEach(function (campo) {
            if (campo) {
                campo.value = '';
            }
        });
    }

    function mostrarMensaje(tipo, texto) {
        const clases = {
            success: 'alert alert-success py-2 mb-0',
            danger: 'alert alert-danger py-2 mb-0',
            info: 'alert alert-info py-2 mb-0',
            warning: 'alert alert-warning py-2 mb-0'
        };

        mensaje.className =
            clases[tipo] || clases.info;

        mensaje.textContent = texto;
    }

    function asignarFuncionario(persona) {
        inputPersonaId.value =
            persona.id || '';

        if (camposPersona.nombre_completo) {
            camposPersona.nombre_completo.value =
                persona.nombre_completo || '';
        }

        if (camposPersona.cargo) {
            camposPersona.cargo.value =
                persona.cargo || '';
        }

        if (camposPersona.ciudad) {
            camposPersona.ciudad.value =
                persona.ciudad || '';
        }

        if (camposPersona.proceso) {
            camposPersona.proceso.value =
                persona.proceso || '';
        }

        if (camposPersona.cliente) {
            camposPersona.cliente.value =
                persona.cliente || '';
        }

        if (camposPersona.proyecto) {
            camposPersona.proyecto.value =
                persona.proyecto || '';
        }

        if (
            selectRazonSocial
            && persona.razon_social_id
        ) {
            selectRazonSocial.value =
                String(persona.razon_social_id);
        }

        if (
            inputCiudadCuenta
            && persona.ciudad
        ) {
            inputCiudadCuenta.value =
                persona.ciudad;
        }
    }

    async function consultarFuncionario() {
        const documento =
            inputDocumento.value
                .trim()
                .replace(/\D+/g, '');

        const pais =
            selectPais.value.trim();

        limpiarFuncionario();

        if (!documento) {
            mostrarMensaje(
                'danger',
                'Usuario no encontrado'
            );

            inputDocumento.focus();

            return;
        }

        botonConsultar.disabled = true;
        botonConsultar.textContent = 'Consultando...';

        mostrarMensaje(
            'info',
            'Consultando funcionario...'
        );

        try {
            const endpoint =
                <?= json_encode(
                    ad_url(
                        'api/persona_por_documento.php'
                    ),
                    JSON_UNESCAPED_SLASHES
                ) ?>;

            const url =
                endpoint
                + '?pais='
                + encodeURIComponent(pais)
                + '&documento='
                + encodeURIComponent(documento)
                + '&_='
                + Date.now();

            console.log(
                'Consultando funcionario:',
                url
            );

            const respuesta = await fetch(
                url,
                {
                    method: 'GET',

                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With':
                            'XMLHttpRequest'
                    },

                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            const textoRespuesta =
                await respuesta.text();

            console.log(
                'Respuesta del servidor:',
                textoRespuesta
            );

            let resultado;

            try {
                resultado =
                    JSON.parse(textoRespuesta);
            } catch (errorJson) {
                console.error(
                    'Respuesta no válida:',
                    textoRespuesta
                );

                throw new Error(
                    'El servidor devolvió una '
                    + 'respuesta no válida.'
                );
            }

            if (!respuesta.ok || !resultado.ok) {
                const errorConsulta =
                    new Error(
                        resultado.message
                        || 'Usuario no encontrado'
                    );

                errorConsulta.codigo =
                    resultado.codigo
                    || 'ERROR_CONSULTA';

                throw errorConsulta;
            }

            if (
                !resultado.persona
                || !resultado.persona.id
            ) {
                throw new Error(
                    'El servidor no devolvió '
                    + 'los datos del funcionario.'
                );
            }

            asignarFuncionario(
                resultado.persona
            );

            mostrarMensaje(
                'success',
                'Funcionario encontrado'
            );
        } catch (error) {
            console.error(
                'Error al consultar funcionario:',
                error
            );

            limpiarFuncionario();

            if (
                error.codigo
                === 'USUARIO_NO_ENCONTRADO'
            ) {
                mostrarMensaje(
                    'danger',
                    'Usuario no encontrado'
                );
            } else if (
                error.codigo === 'SESION_VENCIDA'
            ) {
                mostrarMensaje(
                    'danger',
                    'La sesión ha vencido. '
                    + 'Ingrese nuevamente.'
                );
            } else {
                mostrarMensaje(
                    'danger',
                    error.message
                    || 'No fue posible realizar '
                    + 'la consulta'
                );
            }
        } finally {
            botonConsultar.disabled = false;
            botonConsultar.textContent = 'Consultar';
        }
    }

    botonConsultar.addEventListener(
        'click',
        consultarFuncionario
    );

    /*
     * Permitir consultar presionando Enter
     * dentro del campo de documento.
     */
    inputDocumento.addEventListener(
        'keydown',
        function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                consultarFuncionario();
            }
        }
    );

    console.log(
        'Consulta de funcionarios inicializada.'
    );
});
</script>
