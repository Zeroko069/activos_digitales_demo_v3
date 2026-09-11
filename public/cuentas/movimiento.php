<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.editar');

$pdo = db();
$accountId = (int)($_GET['id'] ?? 0);

if ($accountId <= 0) {
    ad_flash('danger', 'La cuenta indicada no es válida.');
    ad_redirect('cuentas/index.php');
}

$stmt = $pdo->prepare(
    'SELECT
        c.id,
        c.correo,
        c.estado,
        c.pais,
        c.ciudad,

        p.id AS persona_id,
        p.numero_documento,
        p.nombre_completo,
        p.cargo,
        p.proceso,
        p.cliente,
        p.proyecto,

        GROUP_CONCAT(
            DISTINCT pl.nombre
            ORDER BY pl.nombre
            SEPARATOR ", "
        ) AS licencias

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

     LEFT JOIN ad_asignaciones_licencia al
       ON al.cuenta_id = c.id
      AND al.estado = "ACTIVA"

     LEFT JOIN ad_suscripciones s
       ON s.id = al.suscripcion_id

     LEFT JOIN ad_planes pl
       ON pl.id = s.plan_id

     WHERE c.id = :id

     GROUP BY
        c.id,
        c.correo,
        c.estado,
        c.pais,
        c.ciudad,
        p.id,
        p.numero_documento,
        p.nombre_completo,
        p.cargo,
        p.proceso,
        p.cliente,
        p.proyecto

     LIMIT 1'
);

$stmt->execute([
    ':id' => $accountId,
]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    ad_flash('danger', 'No se encontró la cuenta.');
    ad_redirect('cuentas/index.php');
}

$canonicalLicenseSql = <<<'SQL'
CASE
    WHEN UPPER(TRIM(p.nombre))
        LIKE '%EXCHANGE ONLINE PLAN 1%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%EXCHANGE ONLINE (PLAN 1)%'
    THEN 'EXCHANGE ONLINE PLAN 1'

    WHEN UPPER(TRIM(p.nombre))
        LIKE '%BUSINESS BASIC%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%EMPRESA BASICO%'
    THEN 'MICROSOFT 365 BUSINESS BASIC'

    WHEN UPPER(TRIM(p.nombre))
        LIKE '%BUSINESS STANDARD%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%EMPRESA ESTANDAR%'
    THEN 'MICROSOFT 365 BUSINESS STANDARD'

    WHEN UPPER(TRIM(p.nombre))
        LIKE '%PROJECT PLAN 3%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%PLANNER AND PROJECT PLAN 3%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%PLANNER Y PROJECT PLAN 3%'
    THEN 'PLANNER AND PROJECT PLAN 3'

    WHEN UPPER(TRIM(p.nombre))
        LIKE '%ONEDRIVE FOR BUSINESS PLAN 2%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%ONEDRIVE PARA LA EMPRESA (PLAN 2)%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%ONEDRIVE PARA LA EMPRESA PLAN 2%'
    THEN 'ONEDRIVE FOR BUSINESS PLAN 2'

    WHEN UPPER(TRIM(p.nombre))
        LIKE '%POWER BI PREMIUM%'
      OR UPPER(TRIM(p.nombre))
        LIKE '%POWER BI PREMIUM POR USUARIO%'
    THEN 'POWER BI PREMIUM PER USER'

    WHEN UPPER(TRIM(p.nombre))
        LIKE '%POWER BI PRO%'
    THEN 'POWER BI PRO'

    ELSE UPPER(TRIM(p.nombre))
END
SQL;

$selectedLicenseKeys = [];

$selectedStmt = $pdo->prepare(
    'SELECT DISTINCT
        ' . $canonicalLicenseSql . ' AS licencia

     FROM ad_asignaciones_licencia al

     INNER JOIN ad_suscripciones s
       ON s.id = al.suscripcion_id

     INNER JOIN ad_planes p
       ON p.id = s.plan_id

     WHERE al.cuenta_id = :cuenta
       AND al.estado = "ACTIVA"'
);

$canonicalLicenseSqlUsage = str_replace(
    'p.nombre',
    'p2.nombre',
    $canonicalLicenseSql
);

$selectedStmt->execute([
    ':cuenta' => $accountId,
]);

$selectedLicenseKeys = array_map(
    'strval',
    $selectedStmt->fetchAll(PDO::FETCH_COLUMN)
);

$licenseSql = '
    SELECT
        catalogo.licencia,
        MIN(catalogo.id) AS id,
        MIN(catalogo.valor_usd) AS valor_usd,
        GREATEST(
    CAST(MAX(catalogo.cantidad_contratada) AS SIGNED)
    -
    CAST(COUNT(DISTINCT catalogo.cuenta_id) AS SIGNED),
    0
) AS disponible,
        COUNT(DISTINCT catalogo.cuenta_id) AS total_en_uso,
        CASE
            WHEN MAX(catalogo.es_ilimitada) = 1 THEN 1 ELSE 0
        END AS es_ilimitada,
        CASE
    WHEN MAX(catalogo.es_ilimitada) = 1 THEN NULL

    ELSE GREATEST(
        CAST(MAX(catalogo.cantidad_contratada) AS SIGNED)
        -
        CAST(COUNT(DISTINCT catalogo.cuenta_id) AS SIGNED),
        0
    )

END AS cantidad_disponible

    FROM (
        SELECT
            s.id,
            ' . $canonicalLicenseSql . ' AS licencia,
            s.valor_unitario AS valor_usd,
            s.cantidad_contratada AS cantidad_contratada,
            al.cuenta_id AS cuenta_id,
            CASE
                WHEN s.cantidad_contratada IS NULL THEN 1 ELSE 0
            END AS es_ilimitada

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
            ' . $canonicalLicenseSql . '
    ) catalogo

    GROUP BY
        catalogo.licencia

    ORDER BY
        catalogo.licencia
';

$licenses = $pdo
    ->query($licenseSql)
    ->fetchAll(PDO::FETCH_ASSOC);

$title = 'Gestión de cuenta';

require dirname(__DIR__, 2) . '/views/header.php';
?>

<h1 class="h3 mb-1">
    Gestión de cuenta
</h1>

<p class="text-muted">
    Registrar una gestión sobre la cuenta seleccionada
</p>

<form
    method="post"
    action="<?= ad_e(
        ad_url('cuentas/movimiento_save.php')
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
        value="<?= (int)$account['id'] ?>"
    >

    <input
        type="hidden"
        name="persona_nueva_id"
        id="persona_nueva_id"
        value=""
    >

    <div class="card card-body mb-3">
        <h2 class="h5">
            1. Información actual
        </h2>

        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-label">
                    Dirección de correo
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e(
                        (string)($account['correo'] ?? '')
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Estado
                </label>

                <input
                    class="form-control"
                    value="<?= $account['estado'] === 'ACTIVA'
                        ? 'Activa'
                        : 'Gestión de baja' ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    País
                </label>

                <input
                    class="form-control"
                    value="<?= $account['pais'] === 'PE'
                        ? 'Perú'
                        : 'Colombia' ?>"
                    readonly
                >
            </div>

            <div class="col-lg-6">
                <label class="form-label">
                    Funcionario asignado
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e(
                        $account['nombre_completo']
                        ?: 'Sin funcionario asignado'
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Documento
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e(
                        $account['numero_documento']
                    ) ?>"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label class="form-label">
                    Cargo
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e($account['cargo']) ?>"
                    readonly
                >
            </div>

            <div class="col-12">
                <label class="form-label">
                    Licencias actuales
                </label>

                <input
                    class="form-control"
                    value="<?= ad_e(
                        $account['licencias']
                        ?: 'Sin licencias asignadas'
                    ) ?>"
                    readonly
                >
            </div>
        </div>
    </div>

    <div class="card card-body mb-3">
        <h2 class="h5">
            2. Tipo de Gestión
        </h2>

        <div class="row g-3">
            <div class="col-lg-8">
                <label class="form-label">
                    Gestión *
                </label>

                <select
                    class="form-select"
                    name="tipo_gestion"
                    id="tipo_gestion"
                    required
                >
                    <option value="">
                        Seleccione una gestión
                    </option>

                    <option value="ELIMINADO_CON_PST">
                        Eliminado con PST
                    </option>

                    <option value="ELIMINADO_CPANEL">
                        Eliminado de CPANEL
                    </option>

                    <option value="CAMBIO_ASIGNACION">
                        Cambio de asignación
                    </option>

                    <option value="CAMBIO_LICENCIA">
                        Cambio de licencia
                    </option>

                    <option value="OTRO">
                        Otro
                    </option>
                </select>
            </div>

            <div
    class="card card-body mb-3 d-none"
    id="section_email_change"
>
    <h2 class="h5 mb-2">
        Cambio de dirección de correo
    </h2>

    <div class="small text-muted mb-3">
        Active esta opción solamente cuando la gestión requiera
        modificar la dirección de correo de la cuenta.
    </div>

    <div class="form-check form-switch mb-3">
        <input
            class="form-check-input"
            type="checkbox"
            role="switch"
            id="cambiar_correo"
            name="cambiar_correo"
            value="1"
        >

        <label
            class="form-check-label"
            for="cambiar_correo"
        >
            Esta gestión requiere cambiar la dirección de correo
        </label>
    </div>

    <div class="row g-3">

        <div class="col-lg-6">
            <label
                class="form-label"
                for="correo_actual_gestion"
            >
                Dirección de correo actual
            </label>

            <input
                type="email"
                class="form-control"
                id="correo_actual_gestion"
                value="<?= ad_e(
                    (string)(
                        $account['correo']
                        ?? ''
                    )
                ) ?>"
                readonly
            >
        </div>

        <div
            class="col-lg-6 d-none"
            id="container_correo_nuevo"
        >
            <label
                class="form-label"
                for="correo_nuevo"
            >
                Nueva dirección de correo *
            </label>

            <input
                type="email"
                class="form-control"
                id="correo_nuevo"
                name="correo_nuevo"
                value=""
                maxlength="190"
                autocomplete="off"
                placeholder="nombre.apellido@dominio.com"
            >

            <div class="form-text">
                La nueva dirección reemplazará la dirección actual
                de esta cuenta.
            </div>
        </div>

    </div>
</div>

            <div class="col-lg-4">
                <label class="form-label">
                    Fecha *
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="fecha_gestion"
                    value="<?= date('Y-m-d') ?>"
                    required
                >
            </div>
        </div>
    </div>

<div
    class="card card-body mb-3 d-none"
    id="section_pst"
>
    <h2 class="h5 mb-3">
        Información del PST
    </h2>

    <div class="row g-3">

        <div class="col-lg-3">
            <label class="form-label">
                Servidor *
            </label>

            <input
                type="text"
                class="form-control"
                name="servidor_pst"
                id="servidor_pst"
                placeholder="Ejemplo: Docking"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Disco *
            </label>

            <input
                type="text"
                class="form-control"
                name="disco_pst"
                id="disco_pst"
                placeholder="Ejemplo: Disco D"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Subcarpeta 1
            </label>

            <input
                type="text"
                class="form-control"
                name="subcarpeta_1_pst"
                id="subcarpeta_1_pst"
            >
        </div>

        <div class="col-lg-3">
            <label class="form-label">
                Subcarpeta 2
            </label>

            <input
                type="text"
                class="form-control"
                name="subcarpeta_2_pst"
                id="subcarpeta_2_pst"
            >
        </div>

        <div class="col-lg-6">
            <label class="form-label">
                Nombre del archivo *
            </label>

            <input
                type="text"
                class="form-control"
                name="archivo_pst"
                id="archivo_pst"
                placeholder="Ejemplo: usuario.pst"
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
                id="tamano_pst"
                placeholder="Ejemplo: 4.5 GB"
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
                id="tamano_onedrive"
                placeholder="Ejemplo: 12 GB"
            >
        </div>

    </div>
</div>

    <div
        class="card card-body mb-3 d-none"
        id="section_assignment"
    >
        <h2 class="h5">
            Nueva asignación
        </h2>

        <div class="small text-muted mb-3">
            Seleccione el nuevo funcionario. Si no existe en la matriz,
            puede guardar la asignación como pendiente indicando su documento.
        </div>

        <div class="row g-3">
            <div class="col-lg-3">
                <label class="form-label">
                    País
                </label>

                <select
                    class="form-select"
                    id="pais_nuevo"
                    name="pais_nuevo"
                >
                    <option value="CO"
                        <?= strtoupper(
                            (string)($account['pais'] ?? 'CO')
                        ) === 'CO'
                            ? 'selected'
                            : '' ?>
                    >
                        Colombia
                    </option>

                    <option value="PE"
                        <?= strtoupper(
                            (string)($account['pais'] ?? '')
                        ) === 'PE'
                            ? 'selected'
                            : '' ?>
                    >
                        Perú
                    </option>
                </select>
            </div>

            <div class="col-lg-6">
                <label class="form-label" for="documento_nuevo">
                    Documento del nuevo funcionario
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="documento_nuevo"
                    name="documento_nuevo"
                    autocomplete="off"
                    placeholder="Ingrese el número de documento"
                >
            </div>

            <div class="col-lg-3 d-flex align-items-end">
                <button
                    type="button"
                    class="btn btn-dark w-100"
                    id="consultar_persona"
                >
                    Consultar
                </button>
            </div>

            <div class="col-12">
                <div
                    class="alert alert-secondary py-2 mb-0"
                    id="resultado_persona"
                >
                    Consulte el nuevo funcionario.
                </div>
            </div>

            <div class="col-lg-6">
                <label
                    class="form-label"
                    for="nombre_nuevo"
                >
                    Nombre
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="nombre_nuevo"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label
                    class="form-label"
                    for="cargo_nuevo"
                >
                    Cargo
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="cargo_nuevo"
                    readonly
                >
            </div>

            <div class="col-lg-3">
                <label
                    class="form-label"
                    for="ciudad_nueva"
                >
                    Ciudad
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="ciudad_nueva"
                    readonly
                >
            </div>

            <div class="col-12">
                <div class="form-check mt-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="guardar_asignacion_pendiente"
                        name="guardar_asignacion_pendiente"
                        value="1"
                    >

                    <label
                        class="form-check-label"
                        for="guardar_asignacion_pendiente"
                    >
                        Guardar asignación pendiente si el funcionario no existe en la matriz.
                    </label>
                </div>

                <div class="form-text mt-2">
                    Si el funcionario no aparece, guarde la asignación y luego podrá completar
                    los datos cuando la base de personal se actualice.
                </div>
            </div>
        </div>
    </div>

    <div
        class="card card-body mb-3"
        id="section_observation"
    >
        <label class="form-label">
            Observaciones
        </label>

        <textarea
            class="form-control"
            name="observaciones"
            id="observaciones"
            rows="4"
        ></textarea>
    </div>

    <button class="btn btn-primary">
        Guardar gestión
    </button>

    <a
        class="btn btn-outline-secondary"
        href="<?= ad_e(ad_url('cuentas/index.php')) ?>"
    >
        Cancelar
    </a>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /*
     * Elementos relacionados con el tipo de gestión.
     */
    const typeSelect = document.getElementById(
        'tipo_gestion'
    );

    const pstSection = document.getElementById(
        'section_pst'
    );

    const assignmentSection = document.getElementById(
        'section_assignment'
    );

    const licenseSection = document.getElementById(
        'section_license'
    );

    const observationSection = document.getElementById(
        'section_observation'
    );

    const observationInput = document.getElementById(
        'observaciones'
    );

    const serverInput = document.getElementById(
        'servidor_pst'
    );

    const diskInput = document.getElementById(
        'disco_pst'
    );

    const fileInput = document.getElementById(
        'archivo_pst'
    );

    /*
     * Mostrar u ocultar las secciones según
     * el tipo de gestión.
     */
    function updateManagementSections() {
        if (!typeSelect) {
            return;
        }

        const managementType = typeSelect.value;

        const isPst =
            managementType === 'ELIMINADO_CON_PST';

        const isAssignment =
            managementType === 'CAMBIO_ASIGNACION';

        const isLicense =
            managementType === 'CAMBIO_LICENCIA';

        const isCpanel =
            managementType === 'ELIMINADO_CPANEL';

        if (pstSection) {
            pstSection.classList.toggle(
                'd-none',
                !isPst
            );
        }

        if (assignmentSection) {
            assignmentSection.classList.toggle(
                'd-none',
                !isAssignment
            );
        }

        if (licenseSection) {
            licenseSection.classList.toggle(
                'd-none',
                !isLicense
            );
        }

        if (observationSection) {
            observationSection.classList.toggle(
                'd-none',
                isCpanel
            );
        }

        if (serverInput) {
            serverInput.required = isPst;
        }

        if (diskInput) {
            diskInput.required = isPst;
        }

        if (fileInput) {
            fileInput.required = isPst;
        }

        if (observationInput) {
            observationInput.required =
                managementType === 'OTRO';
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener(
            'change',
            updateManagementSections
        );

        updateManagementSections();
    }

    /*
     * Elementos de consulta del nuevo funcionario.
     */
    const consultButton = document.getElementById(
        'consultar_persona'
    );

    const countryInput = document.getElementById(
        'pais_nuevo'
    );

    const documentInput = document.getElementById(
        'documento_nuevo'
    );

    const personIdInput = document.getElementById(
        'persona_nueva_id'
    );

    const nameInput = document.getElementById(
        'nombre_nuevo'
    );

    const positionInput = document.getElementById(
        'cargo_nuevo'
    );

    const cityInput = document.getElementById(
        'ciudad_nueva'
    );

    const resultBox = document.getElementById(
        'resultado_persona'
    );

    function clearPersonResult() {
        if (personIdInput) {
            personIdInput.value = '';
        }

        if (nameInput) {
            nameInput.value = '';
        }

        if (positionInput) {
            positionInput.value = '';
        }

        if (cityInput) {
            cityInput.value = '';
        }
    }

    function showPersonResult(type, message) {
        if (!resultBox) {
            return;
        }

        const classes = {
            secondary:
                'alert alert-secondary py-2 mb-0',

            info:
                'alert alert-info py-2 mb-0',

            success:
                'alert alert-success py-2 mb-0',

            danger:
                'alert alert-danger py-2 mb-0'
        };

        resultBox.className =
            classes[type]
            || classes.secondary;

        resultBox.textContent = message;
    }

    if (documentInput) {
        documentInput.addEventListener(
            'input',
            function () {
                clearPersonResult();

                showPersonResult(
                    'secondary',
                    'Presione Consultar para validar el funcionario.'
                );
            }
        );
    }

    if (countryInput) {
        countryInput.addEventListener(
            'change',
            function () {
                clearPersonResult();

                showPersonResult(
                    'secondary',
                    'Presione Consultar para validar el funcionario.'
                );
            }
        );
    }

    if (consultButton) {
        consultButton.addEventListener(
            'click',
            async function () {
                const documentNumber =
                    documentInput
                        ? documentInput.value.trim()
                        : '';

                const country =
                    countryInput
                        ? countryInput.value
                        : 'CO';

                clearPersonResult();

                if (documentNumber === '') {
                    showPersonResult(
                        'danger',
                        'Debe ingresar el número de documento.'
                    );

                    if (documentInput) {
                        documentInput.focus();
                    }

                    return;
                }

                consultButton.disabled = true;
                consultButton.textContent =
                    'Consultando...';

                showPersonResult(
                    'info',
                    'Consultando funcionario...'
                );

                try {
                    const endpoint =
                        <?= json_encode(
                            ad_url(
                                'api/persona_por_documento.php'
                            ),
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ) ?>;

                    const url = new URL(
                        endpoint,
                        window.location.origin
                    );

                    url.searchParams.set(
                        'pais',
                        country
                    );

                    url.searchParams.set(
                        'documento',
                        documentNumber
                    );

                    const response = await fetch(
                        url.toString(),
                        {
                            method: 'GET',

                            headers: {
                                Accept:
                                    'application/json'
                            },

                            credentials:
                                'same-origin',

                            cache:
                                'no-store'
                        }
                    );

                    /*
                     * Se recibe primero como texto para
                     * detectar respuestas PHP o HTML inválidas.
                     */
                    const rawResponse =
                        await response.text();

                    let data;

                    try {
                        data = JSON.parse(
                            rawResponse
                        );
                    } catch (jsonError) {
                        console.error(
                            'Respuesta no válida de la API:',
                            rawResponse
                        );

                        throw new Error(
                            'La consulta devolvió una respuesta no válida.'
                        );
                    }

                    const person =
                        data.persona
                        || data.data
                        || null;

                    const requestWasSuccessful =
                        data.ok === true
                        || data.success === true;

                    if (
                        !response.ok
                        || !requestWasSuccessful
                        || !person
                    ) {
                        throw new Error(
                            data.message
                            || data.error
                            || 'Usuario no encontrado.'
                        );
                    }

                    const personId =
                        person.id
                        || person.persona_id
                        || '';

                    const fullName =
                        person.nombre_completo
                        || person.nombre
                        || '';

                    const position =
                        person.cargo
                        || person.puesto
                        || '';

                    const city =
                        person.ciudad
                        || person.ubicacion
                        || '';

                    if (!personId) {
                        throw new Error(
                            'El funcionario fue encontrado, '
                            + 'pero no tiene un identificador válido.'
                        );
                    }

                    if (personIdInput) {
                        personIdInput.value =
                            personId;
                    }

                    if (nameInput) {
                        nameInput.value =
                            fullName;
                    }

                    if (positionInput) {
                        positionInput.value =
                            position;
                    }

                    if (cityInput) {
                        cityInput.value =
                            city;
                    }

                    showPersonResult(
                        'success',
                        'Funcionario encontrado correctamente.'
                    );
                } catch (error) {
                    console.error(error);

                    clearPersonResult();

                    showPersonResult(
                        'danger',
                        error.message
                        || 'Usuario no encontrado.'
                    );
                } finally {
                    consultButton.disabled = false;
                    consultButton.textContent =
                        'Consultar';
                }
            }
        );
    }

    /*
     * Evitar guardar un cambio de asignación
     * sin haber consultado correctamente la persona.
     */
    const managementForm =
        typeSelect
            ? typeSelect.closest('form')
            : null;

    const pendingCheckbox = document.getElementById(
        'guardar_asignacion_pendiente'
    );

    if (managementForm) {
        managementForm.addEventListener(
            'submit',
            function (event) {
                if (
                    typeSelect.value
                        === 'CAMBIO_ASIGNACION'
                ) {
                    const documentNumber =
                        documentInput
                            ? documentInput.value.trim()
                            : '';

                    const allowPending =
                        pendingCheckbox
                        && pendingCheckbox.checked
                        && documentNumber !== '';

                    if (
                        (!personIdInput
                        || personIdInput.value === '')
                        && !allowPending
                    ) {
                        event.preventDefault();

                        showPersonResult(
                            'danger',
                            'Debe consultar y seleccionar '
                            + 'el nuevo funcionario antes de guardar, '
                            + 'o marque guardar asignación pendiente.'
                        );

                        if (documentInput) {
                            documentInput.focus();
                        }

                        return;
                    }

                    if (
                        (!personIdInput
                        || personIdInput.value === '')
                        && allowPending
                    ) {
                        showPersonResult(
                            'info',
                            'El funcionario no fue encontrado. '
                            + 'Se guardará la asignación como pendiente.'
                        );
                    }
                }
            }
        );
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const managementSelect = document.querySelector(
        '[name="tipo_gestion"]'
    );

    const emailSection = document.getElementById(
        'section_email_change'
    );

    const changeEmailCheckbox = document.getElementById(
        'cambiar_correo'
    );

    const newEmailContainer = document.getElementById(
        'container_correo_nuevo'
    );

    const newEmailInput = document.getElementById(
        'correo_nuevo'
    );

    if (
        !managementSelect
        || !emailSection
        || !changeEmailCheckbox
        || !newEmailContainer
        || !newEmailInput
    ) {
        return;
    }

    function updateEmailField() {
        const shouldChangeEmail =
            changeEmailCheckbox.checked;

        newEmailContainer.classList.toggle(
            'd-none',
            !shouldChangeEmail
        );

        newEmailInput.required =
            shouldChangeEmail;

        if (!shouldChangeEmail) {
            newEmailInput.value = '';
            newEmailInput.setCustomValidity('');
        }
    }

    function updateEmailSection() {
        const managementType =
            managementSelect.value;

        const permitsEmailChange = [
            'CAMBIO_ASIGNACION',
            'CAMBIO_LICENCIA'
        ].includes(managementType);

        emailSection.classList.toggle(
            'd-none',
            !permitsEmailChange
        );

        if (!permitsEmailChange) {
            changeEmailCheckbox.checked = false;
            newEmailInput.value = '';
        }

        updateEmailField();
    }

    managementSelect.addEventListener(
        'change',
        updateEmailSection
    );

    changeEmailCheckbox.addEventListener(
        'change',
        updateEmailField
    );

    updateEmailSection();
});
</script>

<?php
require dirname(__DIR__, 2) . '/views/footer.php';
?>