<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('respaldos.ver');

$pdo = db();

$currentUser = ad_current_user();

$currentRole = strtoupper(
    trim(
        (string)($currentUser['rol'] ?? '')
    )
);

$canEditBackups =
    $currentRole === 'ADMIN'
    || ad_can('respaldos.editar');

$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = ['1 = 1'];
$params = [];

if ($q !== '') {
    /*
     * Cada condición utiliza un parámetro diferente.
     * Esto evita SQLSTATE[HY093].
     */
    $where[] = '
        (
            LOWER(COALESCE(c.correo, "")) LIKE :q_correo
            OR LOWER(COALESCE(r.servidor, "")) LIKE :q_servidor
            OR LOWER(COALESCE(r.carpeta, "")) LIKE :q_carpeta
            OR LOWER(COALESCE(r.subcarpeta_1, "")) LIKE :q_sub1
            OR LOWER(COALESCE(r.subcarpeta_2, "")) LIKE :q_sub2
            OR LOWER(COALESCE(r.subcarpeta_3, "")) LIKE :q_sub3
            OR LOWER(COALESCE(r.archivo, "")) LIKE :q_archivo
            OR LOWER(COALESCE(r.repositorio_cloud, "")) LIKE :q_cloud
        )
    ';

    $search = '%' . strtolower($q) . '%';

    $params = [
        ':q_correo' => $search,
        ':q_servidor' => $search,
        ':q_carpeta' => $search,
        ':q_sub1' => $search,
        ':q_sub2' => $search,
        ':q_sub3' => $search,
        ':q_archivo' => $search,
        ':q_cloud' => $search,
    ];
}

$sqlWhere = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM ad_respaldos r
     LEFT JOIN ad_cuentas c
       ON c.id = r.cuenta_id
     WHERE ' . $sqlWhere
);

$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();

$totalPages = max(
    1,
    (int)ceil($total / $perPage)
);

$stmt = $pdo->prepare(
    'SELECT
        r.*,
        c.correo,

        COALESCE(
            NULLIF(
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT
                        CASE
                            WHEN UPPER(TRIM(p1.nombre)) IN (
                                "EXCHANGE ONLINE PLAN 1",
                                "EXCHANGE ONLINE (PLAN 1)"
                            )
                            THEN "EXCHANGE ONLINE (PLAN 1)"

                            WHEN UPPER(TRIM(p1.nombre)) IN (
                                "PLANNER AND PROJECT PLAN 3",
                                "PLANNER Y PROJECT PLAN 3"
                            )
                            THEN "PLANNER AND PROJECT PLAN 3"

                            ELSE TRIM(p1.nombre)
                        END
                        ORDER BY p1.nombre
                        SEPARATOR ", "
                    )

                    FROM ad_asignaciones_licencia al1

                    INNER JOIN ad_suscripciones s1
                        ON s1.id = al1.suscripcion_id

                    INNER JOIN ad_planes p1
                        ON p1.id = s1.plan_id

                    WHERE al1.cuenta_id = r.cuenta_id
                      AND al1.estado = "ACTIVA"
                ),
                ""
            ),

            NULLIF(
                (
                    SELECT GROUP_CONCAT(
                        DISTINCT
                        CASE
                            WHEN UPPER(TRIM(p2.nombre)) IN (
                                "EXCHANGE ONLINE PLAN 1",
                                "EXCHANGE ONLINE (PLAN 1)"
                            )
                            THEN "EXCHANGE ONLINE (PLAN 1)"

                            WHEN UPPER(TRIM(p2.nombre)) IN (
                                "PLANNER AND PROJECT PLAN 3",
                                "PLANNER Y PROJECT PLAN 3"
                            )
                            THEN "PLANNER AND PROJECT PLAN 3"

                            ELSE TRIM(p2.nombre)
                        END
                        ORDER BY p2.nombre
                        SEPARATOR ", "
                    )

                    FROM ad_asignaciones_licencia al2

                    INNER JOIN ad_suscripciones s2
                        ON s2.id = al2.suscripcion_id

                    INNER JOIN ad_planes p2
                        ON p2.id = s2.plan_id

                    WHERE al2.cuenta_id = r.cuenta_id
                      AND al2.estado = "CERRADA"
                      AND al2.fecha_fin IS NOT NULL
                      AND DATE(al2.fecha_fin)
                          = DATE(r.fecha_carga)
                ),
                ""
            ),

            "Sin licencia registrada"
        ) AS licencias_backup
    FROM ad_respaldos r
    LEFT JOIN ad_cuentas c
        ON c.id = r.cuenta_id
    WHERE ' . $sqlWhere . '
    ORDER BY r.fecha_carga DESC, r.id DESC
    LIMIT :limit OFFSET :offset'
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

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = 'Backups';

require dirname(__DIR__, 2) . '/views/header.php';
?>

<style>
    .backup-list {
        overflow: hidden;
    }

    .backup-item {
        border-bottom: 1px solid #dee2e6;
    }

    .backup-item:last-child {
        border-bottom: 0;
    }

    .backup-item summary {
        padding: 17px;
        cursor: pointer;
    }

    .backup-item summary:hover {
        background: #f8f9fa;
    }

    .backup-summary {
        display: grid;
        grid-template-columns:
            minmax(220px, 1.3fr)
            minmax(180px, 1fr)
            minmax(140px, .8fr)
            minmax(110px, .6fr);
        gap: 18px;
        margin-top: 10px;
        align-items: center;
    }

    .backup-label {
        color: #6c757d;
        font-size: .75rem;
        text-transform: uppercase;
    }

    .backup-value {
        overflow-wrap: anywhere;
    }

    .backup-details {
        padding: 0 18px 18px 40px;
        background: #fafafa;
    }

    .backup-box {
        height: 100%;
        padding: 13px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        overflow-wrap: anywhere;
    }

    @media (max-width: 800px) {
        .backup-summary {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 520px) {
        .backup-summary {
            grid-template-columns: 1fr;
        }

        .backup-details {
            padding-left: 18px;
        }
    }
</style>

<div class="w-100">

    <div class="mb-3">
        <h1 class="h3 mb-1">
            Backups de correo
        </h1>

        <div class="text-muted">
            <?= number_format($total, 0, ',', '.') ?>
            respaldos registrados
        </div>
    </div>

<form
    method="get"
    class="card card-body mb-3 module-filter-card"
    id="backupFiltersForm"
>
    <div class="row g-3 align-items-end">

        <div class="col-12">
            <label
                class="form-label"
                for="backupSearch"
            >
                Buscar backup
            </label>

            <input
                type="search"
                class="form-control module-filter-control"
                id="backupSearch"
                name="q"
                value="<?= ad_e($q) ?>"
                placeholder="Correo, servidor, disco, subcarpeta o nombre del archivo"
                autocomplete="off"
            >
        </div>

    </div>
</form>

    <div class="card card-kpi backup-list">

        <?php if (!$rows): ?>
            <div class="text-center text-muted p-4">
                No se encontraron backups.
            </div>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
    <?php
    $path = array_values(
        array_filter([
            trim((string)($row['servidor'] ?? '')),
            trim((string)($row['carpeta'] ?? '')),
            trim((string)($row['subcarpeta_1'] ?? '')),
            trim((string)($row['subcarpeta_2'] ?? '')),
        ])
    );

    $location = implode(
        ' / ',
        $path
    );

    $date = 'Sin fecha';

    if (!empty($row['fecha_carga'])) {
        $timestamp = strtotime(
            (string)$row['fecha_carga']
        );

        if ($timestamp !== false) {
            $date = date(
                'd/m/Y',
                $timestamp
            );
        }
    }

    $pstSize = trim(
        (string)($row['tamano_pst'] ?? '')
    );

    $oneDriveSize = trim(
        (string)($row['tamano_onedrive'] ?? '')
    );
    ?>

    <details class="backup-item">

        <summary>
            <strong>
                Ver información del respaldo
            </strong>

            <div class="backup-summary">

    <div class="summary-box bg-soft-blue">
        <div class="backup-label">
            Cuenta
        </div>

        <div class="backup-value">
            <strong>
                <?= ad_e(
                    $row['correo']
                    ?: 'Sin cuenta vinculada'
                ) ?>
            </strong>
        </div>
    </div>

    <div class="summary-box bg-soft-green">
        <div class="backup-label">
            Archivo
        </div>

        <div class="backup-value">
            <?= ad_e(
                $row['archivo']
                ?: 'Sin nombre'
            ) ?>
        </div>
    </div>

<div class="summary-box bg-soft-yellow">
    <div class="backup-label">
        Licencia
    </div>

    <div class="backup-value">
        <?= ad_e(
            $row['licencias_backup']
            ?? 'Sin licencia registrada'
        ) ?>
    </div>
</div>

    <div class="summary-box bg-soft-purple">
        <div class="backup-label">
            Fecha
        </div>

        <div class="backup-value">
            <?= ad_e($date) ?>
        </div>
    </div>

</div>
        </summary>

        <details>
    <summary>
        Información principal
    </summary>
        <!-- Todo el detalle debe permanecer dentro de <details> -->
        <div class="backup-details">
             Ubicación
        Tamaño PST
        Tamaño OneDrive
        Botón Editar
    </div>
</details>

            <div
                class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"
            >
                <div>
                    <strong>
                        Detalles del respaldo
                    </strong>

                    <div class="small text-muted">
                        Ubicación y tamaños almacenados
                    </div>
                </div>

                <?php if ($canEditBackups): ?>
                    <a
                        class="btn btn-sm btn-outline-primary"
                        href="<?= ad_e(
                            ad_url(
                                'respaldos/form.php?id='
                                . (int)$row['id']
                            )
                        ) ?>"
                    >
                        Editar backup
                    </a>
                <?php endif; ?>
            </div>

            <div class="row g-3">

    <div class="col-lg-6">
        <div class="backup-box bg-soft-cyan">
            <div class="backup-label mb-1">
                Ubicación
            </div>

            <div class="backup-value">
                <?= ad_e(
                    $location
                    ?: 'No registrada'
                ) ?>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="backup-box bg-soft-pink">
            <div class="backup-label mb-1">
                Tamaño PST
            </div>

            <div class="backup-value">
                <?= ad_e(
                    $pstSize !== ''
                        ? $pstSize
                        : 'No registrado'
                ) ?>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="backup-box bg-soft-green">
            <div class="backup-label mb-1">
                Tamaño OneDrive
            </div>

            <div class="backup-value">
                <?= ad_e(
                    $oneDriveSize !== ''
                        ? $oneDriveSize
                        : 'No registrado'
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
            <ul class="pagination pagination-sm">
                <?php for (
                    $pageNumber = max(1, $page - 2);
                    $pageNumber <= min(
                        $totalPages,
                        $page + 2
                    );
                    $pageNumber++
                ): ?>
                    <li
                        class="page-item <?= $pageNumber === $page
                            ? 'active'
                            : '' ?>"
                    >
                        <a
                            class="page-link"
                            href="?<?= ad_e(
                                http_build_query([
                                    'q' => $q,
                                    'page' => $pageNumber,
                                ])
                            ) ?>"
                        >
                            <?= $pageNumber ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('backupFiltersForm');
    const search = document.getElementById('backupSearch');

    if (!form || !search) {
        return;
    }

    const selectionKey = 'filterSelection:' + search.id;

    const restoreSelection = function () {
        const raw = sessionStorage.getItem(selectionKey);

        if (!raw) {
            return;
        }

        try {
            const saved = JSON.parse(raw);

            if (
                saved
                && saved.value === search.value
                && typeof saved.start === 'number'
                && typeof saved.end === 'number'
            ) {
                search.focus();
                window.setTimeout(function () {
                    search.setSelectionRange(saved.start, saved.end);
                }, 0);
            }
        } catch (error) {
            // ignore invalid session data
        } finally {
            sessionStorage.removeItem(selectionKey);
        }
    };

    const saveSelection = function () {
        try {
            sessionStorage.setItem(selectionKey, JSON.stringify({
                start: search.selectionStart,
                end: search.selectionEnd,
                value: search.value,
            }));
        } catch (error) {
            // ignore storage issues
        }
    };

    restoreSelection();

    let timer = null;

    search.addEventListener('input', function () {
        window.clearTimeout(timer);
        saveSelection();

        timer = window.setTimeout(function () {
            form.requestSubmit();
        }, 350);
    });

    search.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(timer);
            saveSelection();
            form.requestSubmit();
        }
    });
});
</script>

<?php
require dirname(__DIR__, 2) . '/views/footer.php';
?>