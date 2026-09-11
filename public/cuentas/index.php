<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.ver');

$pdo = db();

/*
 * Licencias disponibles para el filtro.
 * Se normalizan para evitar opciones duplicadas.
 */
$licenseOptionsSql = '
    SELECT DISTINCT

        CASE
            WHEN UPPER(TRIM(p.nombre)) IN (
                "EXCHANGE ONLINE PLAN 1",
                "EXCHANGE ONLINE (PLAN 1)"
            )
            THEN "EXCHANGE ONLINE (PLAN 1)"

            WHEN UPPER(TRIM(p.nombre)) IN (
                "PLANNER AND PROJECT PLAN 3",
                "PLANNER Y PROJECT PLAN 3"
            )
            THEN "PLANNER AND PROJECT PLAN 3"

            ELSE UPPER(TRIM(p.nombre))
        END AS valor,

        CASE
            WHEN UPPER(TRIM(p.nombre)) IN (
                "EXCHANGE ONLINE PLAN 1",
                "EXCHANGE ONLINE (PLAN 1)"
            )
            THEN "EXCHANGE ONLINE (PLAN 1)"

            WHEN UPPER(TRIM(p.nombre)) IN (
                "PLANNER AND PROJECT PLAN 3",
                "PLANNER Y PROJECT PLAN 3"
            )
            THEN "PLANNER AND PROJECT PLAN 3"

            ELSE TRIM(p.nombre)
        END AS etiqueta

    FROM ad_planes p

    INNER JOIN ad_suscripciones s
        ON s.plan_id = p.id

    WHERE s.estado = "VIGENTE"

      AND UPPER(TRIM(p.nombre)) NOT IN (
          "ONEDRIVE FOR BUSINESS PLAN 2",
          "ONEDRIVE PARA LA EMPRESA (PLAN 2)"
      )

    ORDER BY etiqueta
';

$licenseOptions = $pdo
    ->query($licenseOptionsSql)
    ->fetchAll(PDO::FETCH_ASSOC);

$licenseOptions = $pdo
    ->query($licenseOptionsSql)
    ->fetchAll(PDO::FETCH_ASSOC);

$currentUser = ad_current_user();

$currentRole = strtoupper(
    trim(
        (string)($currentUser['rol'] ?? '')
    )
);

$canManageAccounts =
    $currentRole === 'ADMIN'
    || ad_can('cuentas.editar');

$q = trim(
    (string)($_GET['q'] ?? '')
);

/*
 * Estados visibles en la pestaña Cuentas:
 *
 * ACTIVA: cuenta en operación.
 * GESTION_DE_BAJA: baja pendiente de finalizar.
 * TODOS: muestra los dos estados anteriores.
 *
 * ELIMINADA nunca aparece en esta pestaña.
 */
$estado = strtoupper(
    trim(
        (string)($_GET['estado'] ?? 'ACTIVA')
    )
);

$licencia = strtoupper(
    trim(
        (string)($_GET['licencia'] ?? '')
    )
);

if ($licencia === 'SIN_LICENCIA') {
    $licencia = '';
}

$estadosPermitidos = [
    'ACTIVA',
    'GESTION_DE_BAJA',
    'TODOS',
];

if (
    !in_array(
        $estado,
        $estadosPermitidos,
        true
    )
) {
    $estado = 'ACTIVA';
}

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 50;
$offset = ($page - 1) * $perPage;

/*
 * Siempre excluye las cuentas cuya eliminación
 * ya fue finalizada.
 */
$where = [
    'c.estado IN ("ACTIVA", "GESTION_DE_BAJA")',
];

$params = [];

/*
 * Buscar por correo, nombre o documento.
 */
if ($q !== '') {
    $searchConditions = [
        'LOWER(c.correo) LIKE :q_correo',

        'LOWER(
            COALESCE(
                p.nombre_completo,
                ""
            )
        ) LIKE :q_nombre',
    ];

    $searchText = strtolower($q);

    $params[':q_correo'] =
        '%' . $searchText . '%';

    $params[':q_nombre'] =
        '%' . $searchText . '%';

    $documentoBuscado = preg_replace(
        '/\D+/',
        '',
        $q
    ) ?? '';

    if ($documentoBuscado !== '') {
        $searchConditions[] = '
            REPLACE(
                REPLACE(
                    REPLACE(
                        COALESCE(
                            p.numero_documento,
                            ""
                        ),
                        ".",
                        ""
                    ),
                    "-",
                    ""
                ),
                " ",
                ""
            ) LIKE :q_documento
        ';

        $params[':q_documento'] =
            '%' . $documentoBuscado . '%';
    }

    $where[] =
        '('
        . implode(
            ' OR ',
            $searchConditions
        )
        . ')';
}

/*
 * Aplicar el estado seleccionado.
 * Cuando selecciona TODOS, mantiene únicamente
 * ACTIVA y GESTION_DE_BAJA.
 */
if ($estado !== 'TODOS') {
    $where[] = 'c.estado = :estado';

    $params[':estado'] = $estado;
}
if ($licencia !== '') {
    $where[] = '
        EXISTS
        (
            SELECT 1

            FROM ad_asignaciones_licencia alf

            INNER JOIN ad_suscripciones sf
                ON sf.id = alf.suscripcion_id

            INNER JOIN ad_planes pf
                ON pf.id = sf.plan_id

            WHERE alf.cuenta_id = c.id
              AND alf.estado = "ACTIVA"

              AND
              (
                  CASE
                      WHEN UPPER(TRIM(pf.nombre)) IN (
                          "EXCHANGE ONLINE PLAN 1",
                          "EXCHANGE ONLINE (PLAN 1)"
                      )
                      THEN "EXCHANGE ONLINE (PLAN 1)"

                      WHEN UPPER(TRIM(pf.nombre)) IN (
                          "PLANNER AND PROJECT PLAN 3",
                          "PLANNER Y PROJECT PLAN 3"
                      )
                      THEN "PLANNER AND PROJECT PLAN 3"

                      ELSE UPPER(TRIM(pf.nombre))
                  END
              ) = :licencia
        )
    ';

    $params[':licencia'] = $licencia;
}

$sqlWhere = implode(
    ' AND ',
    $where
);

/*
 * Obtener únicamente la asignación activa más reciente
 * de cada cuenta.
 */
$joinPersona = '
    LEFT JOIN ad_asignaciones_cuenta ac
        ON ac.id = (
            SELECT MAX(ac2.id)

            FROM ad_asignaciones_cuenta ac2

            WHERE ac2.cuenta_id = c.id
              AND ac2.estado = "ACTIVA"
        )

    LEFT JOIN ad_personas p
        ON p.id = ac.persona_id
';

/*
 * Conteo para la paginación.
 */
$countSql = '
    SELECT COUNT(DISTINCT c.id)

    FROM ad_cuentas c

    ' . $joinPersona . '

    WHERE ' . $sqlWhere;

$countStmt = $pdo->prepare(
    $countSql
);

foreach ($params as $key => $value) {
    $countStmt->bindValue(
        $key,
        $value,
        PDO::PARAM_STR
    );
}

$countStmt->execute();

$total = (int)$countStmt->fetchColumn();

$totalPages = max(
    1,
    (int)ceil(
        $total / $perPage
    )
);

/*
 * Consulta principal.
 *
 * La información se agrupa visualmente para evitar
 * una tabla demasiado ancha:
 *
 * Cuenta: correo + ubicación.
 * Funcionario: nombre + documento + cargo.
 */
$sql = <<<SQL
    SELECT
        c.id,
        c.correo,
        c.estado,
        c.pais,
        c.ciudad,
        c.fecha_creacion,

        p.numero_documento,
        p.nombre_completo,
        p.cargo,
        p.proceso,
        p.cliente,
        p.proyecto,

        COALESCE(
            lic.licencias,
            "Sin licencia"
        ) AS licencias

    FROM ad_cuentas c

    {$joinPersona}

    LEFT JOIN (
        SELECT
            al.cuenta_id,

            GROUP_CONCAT(
                DISTINCT CASE
                    WHEN UPPER(TRIM(pl.nombre)) IN (
                        'EXCHANGE ONLINE PLAN 1',
                        'EXCHANGE ONLINE (PLAN 1)'
                    )
                    THEN 'EXCHANGE ONLINE (PLAN 1)'

                    WHEN UPPER(TRIM(pl.nombre)) IN (
                        'PLANNER AND PROJECT PLAN 3',
                        'PLANNER Y PROJECT PLAN 3'
                    )
                    THEN 'PLANNER AND PROJECT PLAN 3'

                    ELSE TRIM(pl.nombre)
                END
                SEPARATOR ', '
            ) AS licencias

        FROM ad_asignaciones_licencia al

        INNER JOIN ad_suscripciones s
            ON s.id = al.suscripcion_id

        INNER JOIN ad_planes pl
            ON pl.id = s.plan_id

        WHERE al.estado = "ACTIVA"

        GROUP BY al.cuenta_id
    ) lic
        ON lic.cuenta_id = c.id

    WHERE {$sqlWhere}

    ORDER BY c.correo ASC

    LIMIT :limit
    OFFSET :offset
SQL;

$stmt = $pdo->prepare(
    $sql
);

foreach ($params as $key => $value) {
    $stmt->bindValue(
        $key,
        $value,
        PDO::PARAM_STR
    );
}

$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$rows = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

$title = 'Cuentas';

require dirname(__DIR__, 2)
    . '/views/header.php';
?>

<style>
    .account-list {
        overflow: hidden;
    }

    .account-item {
        border-bottom: 1px solid #dee2e6;
    }

    .account-item:last-child {
        border-bottom: 0;
    }

    .account-item summary {
        padding: 17px;
        cursor: pointer;
        list-style: none;
    }

    .account-item summary::-webkit-details-marker {
        display: none;
    }

    .account-item summary:hover {
        background: #f8f9fa;
    }

    .account-item[open] summary {
        border-bottom: 1px solid #e4e7eb;
        background: #fbfcfe;
    }

    .account-summary-grid {
        display: grid;
        grid-template-columns:
            minmax(220px, 1.3fr)
            minmax(180px, 1.1fr)
            minmax(120px, .8fr)
            minmax(120px, .7fr)
            minmax(120px, .7fr);
        gap: 18px;
        margin-top: 10px;
        align-items: center;
    }

    .account-detail-box {
        height: 100%;
        padding: 13px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        overflow-wrap: anywhere;
    }

    .account-details {
        padding: 0 18px 18px 40px;
        background: #fafafa;
    }

    .account-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 7px;
    }

    @media (max-width: 1000px) {
        .account-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 400px) {
        .account-summary-grid {
            grid-template-columns: 1fr;
        }

        .account-details {
            padding-left: 18px;
        }
    }
</style>

<div class="w-100">

    <div
        class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"
    >
        <div>
            <h1 class="h3 mb-1">
                Cuentas
            </h1>

            <div class="text-muted">
                <?= number_format(
                    $total,
                    0,
                    ',',
                    '.'
                ) ?>
                cuentas encontradas
            </div>
        </div>

        <?php if ($canManageAccounts): ?>
            <a
                class="btn btn-primary"
                href="<?= ad_e(
                    ad_url(
                        'cuentas/form.php'
                    )
                ) ?>"
            >
                Nueva cuenta
            </a>
        <?php endif; ?>
    </div>

<form
    method="get"
    class="card card-body mb-3 module-filter-card"
    id="accountFiltersForm"
>
    <div class="row g-3 align-items-end">

        <div class="col-lg-5">
            <label
                class="form-label"
                for="accountSearch"
            >
                Buscar cuenta
            </label>

            <input
                type="search"
                class="form-control module-filter-control"
                id="accountSearch"
                name="q"
                value="<?= ad_e($q) ?>"
                placeholder="Correo, nombre o documento"
                autocomplete="off"
            >
        </div>

        <div class="col-lg-3">
            <label
                class="form-label"
                for="accountStatusFilter"
            >
                Estado
            </label>

            <select
                class="form-select module-filter-control js-account-filter"
                id="accountStatusFilter"
                name="estado"
            >
                <option
                    value="ACTIVA"
                    <?= $estado === 'ACTIVA'
                        ? 'selected'
                        : '' ?>
                >
                    Cuentas activas
                </option>

                <option
                    value="GESTION_DE_BAJA"
                    <?= $estado === 'GESTION_DE_BAJA'
                        ? 'selected'
                        : '' ?>
                >
                    Gestión de baja
                </option>

                <option
                    value="TODOS"
                    <?= $estado === 'TODOS'
                        ? 'selected'
                        : '' ?>
                >
                    Todos los estados
                </option>
            </select>
        </div>

        <div class="col-lg-4">
            <label
                class="form-label"
                for="accountLicenseFilter"
            >
                Licencia
            </label>

            <select
                class="form-select module-filter-control js-account-filter"
                id="accountLicenseFilter"
                name="licencia"
            >
                <option value="">
                    Todas las licencias
                </option>

                <?php foreach (
                    $licenseOptions as $licenseOption
                ): ?>
                    <option
                        value="<?= ad_e(
                            $licenseOption['valor']
                        ) ?>"
                        <?= $licencia
                            === $licenseOption['valor']
                            ? 'selected'
                            : '' ?>
                    >
                        <?= ad_e(
                            $licenseOption['etiqueta']
                        ) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
</form>

    <div class="card card-kpi account-list">
        <?php if (!$rows): ?>
            <div class="text-center text-muted p-4">
                No se encontraron cuentas.
            </div>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $esActiva = $row['estado'] === 'ACTIVA';

            $ubicacion = trim(
                implode(
                    ' · ',
                    array_filter([
                        $row['pais'] ?? '',
                        $row['ciudad'] ?? '',
                    ])
                )
            );

            $fechaCreacion = 'Sin fecha registrada';

            if (!empty($row['fecha_creacion'])) {
                $timestampCreacion = strtotime(
                    (string)$row['fecha_creacion']
                );

                if ($timestampCreacion !== false) {
                    $fechaCreacion = date(
                        'd/m/Y',
                        $timestampCreacion
                    );
                }
            }
            ?>

            <details class="account-item">
                <summary>
                    <strong>
                        Ver información de la cuenta
                    </strong>

                    <div class="account-summary-grid">
                        <div class="summary-box bg-soft-blue">
                            <div class="backup-label">
                                Cuenta
                            </div>

                            <div class="backup-value">
                                <strong>
                                    <?= ad_e($row['correo']) ?>
                                </strong>
                            </div>
                        </div>

                        <div class="summary-box bg-soft-green">
                            <div class="backup-label">
                                Funcionario
                            </div>

                            <div class="backup-value">
                                <?= ad_e(
                                    $row['nombre_completo']
                                    ?: 'Sin asignar'
                                ) ?>
                            </div>
                        </div>

                        <div class="summary-box bg-soft-yellow">
                            <div class="backup-label">
                                Licencias
                            </div>

                            <div class="backup-value">
                                <?= ad_e(
                                    $row['licencias']
                                    ?: 'Sin licencia'
                                ) ?>
                            </div>
                        </div>

                        <div class="summary-box bg-soft-purple">
                            <div class="backup-label">
                                Estado
                            </div>

                            <div class="backup-value">
                                <span class="badge text-bg-<?= $esActiva ? 'success' : 'warning' ?>">
                                    <?= $esActiva ? 'Activa' : 'Gestión de baja' ?>
                                </span>
                            </div>
                        </div>

                        <div class="summary-box bg-soft-cyan">
                            <div class="backup-label">
                                Fecha creación
                            </div>

                            <div class="backup-value">
                                <?= ad_e($fechaCreacion) ?>
                            </div>
                        </div>
                    </div>
                </summary>

                <div class="account-details">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <strong>
                                Detalles de la cuenta
                            </strong>

                            <div class="small text-muted">
                                Ubicación, datos del funcionario y gestión
                            </div>
                        </div>

                        <?php if ($canManageAccounts): ?>
                            <div class="account-actions">
                                <a
                                    class="btn btn-sm btn-outline-danger"
                                    href="<?= ad_e(
                                        ad_url(
                                            'cuentas/baja.php?id='
                                            . (int)$row['id']
                                        )
                                    ) ?>"
                                >
                                    <?= $row['estado'] === 'ACTIVA'
                                        ? 'Gestionar baja'
                                        : 'Actualizar baja' ?>
                                </a>

                                <a
                                    class="btn btn-sm btn-outline-warning"
                                    href="<?= ad_e(
                                        ad_url(
                                            'cuentas/movimiento.php?id='
                                            . (int)$row['id']
                                        )
                                    ) ?>"
                                >
                                    Gestionar
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="account-detail-box bg-soft-blue">
                                <div class="backup-label mb-1">
                                    Ubicación
                                </div>

                                <div class="backup-value">
                                    <?= ad_e($ubicacion ?: 'Sin ubicación registrada') ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="account-detail-box bg-soft-green">
                                <div class="backup-label mb-1">
                                    Documento
                                </div>

                                <div class="backup-value">
                                    <?= ad_e(
                                        $row['numero_documento']
                                        ?: 'Sin documento'
                                    ) ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="account-detail-box bg-soft-purple">
                                <div class="backup-label mb-1">
                                    Cargo / proceso
                                </div>

                                <div class="backup-value">
                                    <?= ad_e(
                                        trim(
                                            (string)($row['cargo'] ?? '')
                                        )
                                        ?: 'Sin cargo registrado'
                                    ) ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="account-detail-box bg-soft-cyan">
                                <div class="backup-label mb-1">
                                    Cliente / proyecto
                                </div>

                                <div class="backup-value">
                                    <?= ad_e(
                                        trim(
                                            (string)($row['cliente'] ?? '')
                                        )
                                        ?: 'Sin cliente proyectado'
                                    ) ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="account-detail-box bg-soft-pink">
                                <div class="backup-label mb-1">
                                    Proceso / proyecto
                                </div>

                                <div class="backup-value">
                                    <?= ad_e(
                                        trim(
                                            (string)($row['proceso'] ?? '')
                                        )
                                        ?: 'Sin proceso registrado'
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul
                class="pagination pagination-sm flex-wrap"
            >
                <li
                    class="page-item <?= $page <= 1
                        ? 'disabled'
                        : '' ?>"
                >
                    <a
                        class="page-link"
                        href="?<?= ad_e(
                            http_build_query([
                                'q' => $q,
                                'licencia' => $licencia,
                                'page' => $pageNumber,
                            ])
                        ) ?>"
                    >
                        Anterior
                    </a>
                </li>

                <?php
                $startPage = max(
                    1,
                    $page - 2
                );

                $endPage = min(
                    $totalPages,
                    $page + 2
                );
                ?>

                <?php for (
                    $pageNumber = $startPage;
                    $pageNumber <= $endPage;
                    $pageNumber++
                ): ?>
                    <li
                        class="page-item <?= $pageNumber
                            === $page
                            ? 'active'
                            : '' ?>"
                    >
                        <a
                            class="page-link"
                            href="?<?= ad_e(
                                http_build_query([
                                    'q' => $q,
                                    'licencia' => $licencia,
                                    'page' => $pageNumber,
                                ])
                            ) ?>"
                        >
                            <?= $pageNumber ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li
                    class="page-item <?= $page
                        >= $totalPages
                        ? 'disabled'
                        : '' ?>"
                >
                    <a
                        class="page-link"
                        href="?<?= ad_e(
                            http_build_query([
                                'q' => $q,
                                'licencia' => $licencia,
                                'page' => min(
                                    $totalPages,
                                    $page + 1
                                ),
                            ])
                        ) ?>"
                    >
                        Siguiente
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.getElementById('accountFiltersForm');
    const searchInput = document.getElementById('accountSearch');
    const automaticFilters = document.querySelectorAll('.js-account-filter');

    if (!filterForm) {
        return;
    }

    const selectionKey = 'filterSelection:' + (searchInput ? searchInput.id : '');

    const restoreSelection = function () {
        if (!searchInput) {
            return;
        }

        const raw = sessionStorage.getItem(selectionKey);

        if (!raw) {
            return;
        }

        try {
            const saved = JSON.parse(raw);

            if (
                saved
                && saved.value === searchInput.value
                && typeof saved.start === 'number'
                && typeof saved.end === 'number'
            ) {
                searchInput.focus();
                window.setTimeout(function () {
                    searchInput.setSelectionRange(saved.start, saved.end);
                }, 0);
            }
        } catch (error) {
            // ignore invalid session data
        } finally {
            sessionStorage.removeItem(selectionKey);
        }
    };

    const saveSelection = function () {
        if (!searchInput) {
            return;
        }

        try {
            sessionStorage.setItem(selectionKey, JSON.stringify({
                start: searchInput.selectionStart,
                end: searchInput.selectionEnd,
                value: searchInput.value,
            }));
        } catch (error) {
            // ignore storage issues
        }
    };

    /*
     * Estado y licencia:
     * filtrar inmediatamente.
     */
    automaticFilters.forEach(function (filter) {
        filter.addEventListener('change', function () {
            filterForm.requestSubmit();
        });
    });

    restoreSelection();

    /*
     * Búsqueda de texto:
     * esperar brevemente para evitar recargar
     * en cada tecla.
     */
    let searchTimer = null;

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            saveSelection();

            searchTimer = window.setTimeout(function () {
                filterForm.requestSubmit();
            }, 350);
        });

        /*
         * Al presionar Enter, buscar inmediatamente.
         */
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();

                window.clearTimeout(searchTimer);
                saveSelection();
                filterForm.requestSubmit();
            }
        });
    }
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById(
        'accountFiltersForm'
    );

    const search = document.getElementById(
        'accountSearch'
    );

    const selects = document.querySelectorAll(
        '.js-account-filter'
    );

    if (!form) {
        return;
    }

    selects.forEach(function (select) {
        select.addEventListener(
            'change',
            function () {
                form.requestSubmit();
            }
        );
    });

    let timer = null;

    if (search) {
        search.addEventListener(
            'input',
            function () {
                window.clearTimeout(timer);

                timer = window.setTimeout(
                    function () {
                        form.requestSubmit();
                    },
                    500
                );
            }
        );

        search.addEventListener(
            'keydown',
            function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    window.clearTimeout(timer);
                    form.requestSubmit();
                }
            }
        );
    }
});
</script>

<?php
require dirname(__DIR__, 2)
    . '/views/footer.php';
?>