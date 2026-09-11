<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2)
    . '/bootstrap.php';

ad_require_permission('novedades.ver');

$pdo = db();

$currentUser = ad_current_user();

$currentRole = strtoupper(
    trim(
        (string)($currentUser['rol'] ?? '')
    )
);

$canEditNovelties =
    $currentRole === 'ADMIN'
    || ad_can('novedades.editar');

$q = trim(
    (string)($_GET['q'] ?? '')
);

$tipo = trim(
    (string)($_GET['tipo'] ?? '')
);

if (
    in_array(
        $tipo,
        [
            'ELIMINACION',
            'BAJA_CORREO',
        ],
        true
    )
) {
    $tipo = '';
}

$page = max(
    1,
    (int)($_GET['page'] ?? 1)
);

$perPage = 30;
$offset = ($page - 1) * $perPage;

$tiposDisponibles = [
    'ELIMINADO_CON_PST' =>
        'Eliminado con PST',

    'ELIMINADO_CPANEL' =>
        'Eliminado de CPANEL',

    'CAMBIO_ASIGNACION' =>
        'Cambio de asignación',

    'CAMBIO_NOMBRE_ASIGNACION' =>
        'Cambio de asignación',

    'CAMBIO_LICENCIA' =>
        'Cambio de licencia',

    'RESTABLECIDO' =>
        'Cuenta restablecida',    

    'OTRO' =>
        'Otro',

];

$where = ['1 = 1'];
$params = [];

if ($q !== '') {
    $where[] = '
        (
            c.correo LIKE :q_correo

            OR n.descripcion LIKE :q_descripcion

            OR CAST(
                n.datos_origen AS CHAR
            ) LIKE :q_datos

            OR r.archivo LIKE :q_archivo

            OR r.carpeta LIKE :q_carpeta

            OR r.servidor LIKE :q_servidor
        )
    ';

    $searchValue = '%' . $q . '%';

    $params[':q_correo'] =
        $searchValue;

    $params[':q_descripcion'] =
        $searchValue;

    $params[':q_datos'] =
        $searchValue;

    $params[':q_archivo'] =
        $searchValue;

    $params[':q_carpeta'] =
        $searchValue;

    $params[':q_servidor'] =
        $searchValue;
}

if (
    $tipo !== ''
    && array_key_exists(
        $tipo,
        $tiposDisponibles
    )
) {
    $where[] = 'n.tipo = :tipo';
    $params[':tipo'] = $tipo;
}

$sqlWhere = implode(
    ' AND ',
    $where
);

/*
 * Último respaldo registrado para la cuenta.
 */
/*
 * Un respaldo solo se relaciona visualmente con una
 * novedad de tipo ELIMINADO_CON_PST.
 */
$backupJoin = '
    LEFT JOIN ad_respaldos r
        ON n.tipo = "ELIMINADO_CON_PST"
       AND r.id = (
            SELECT MAX(r2.id)

            FROM ad_respaldos r2

            WHERE r2.cuenta_id = n.cuenta_id
        )
';

/*
 * Conteo total.
 */
$countSql = '
    SELECT COUNT(*)

    FROM ad_novedades n

    LEFT JOIN ad_cuentas c
        ON c.id = n.cuenta_id

    ' . $backupJoin . '

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
 * Consulta de novedades y respaldo asociado.
 */
$sql = '
    SELECT
        n.id,
        n.cuenta_id,
        n.tipo,
        n.estado,
        n.fecha_novedad,
        n.descripcion,
        n.datos_origen,
        n.legacy_sheet,
        n.legacy_row,
        n.created_at,

        c.estado AS cuenta_estado,
        c.correo AS correo_actual,
        COALESCE(
    NULLIF(
        (
            SELECT GROUP_CONCAT(
                DISTINCT
                CASE
                    WHEN UPPER(TRIM(p3.nombre)) IN (
                        "EXCHANGE ONLINE PLAN 1",
                        "EXCHANGE ONLINE (PLAN 1)"
                    )
                    THEN "EXCHANGE ONLINE (PLAN 1)"

                    WHEN UPPER(TRIM(p3.nombre)) IN (
                        "PLANNER AND PROJECT PLAN 3",
                        "PLANNER Y PROJECT PLAN 3"
                    )
                    THEN "PLANNER AND PROJECT PLAN 3"

                    ELSE TRIM(p3.nombre)
                END
                ORDER BY p3.nombre
                SEPARATOR ", "
            )

            FROM ad_asignaciones_licencia al3

            INNER JOIN ad_suscripciones s3
                ON s3.id = al3.suscripcion_id

            INNER JOIN ad_planes p3
                ON p3.id = s3.plan_id

            WHERE al3.cuenta_id = n.cuenta_id
              AND al3.estado = "ACTIVA"
        ),
        ""
    ),

    NULLIF(
        (
            SELECT GROUP_CONCAT(
                DISTINCT
                CASE
                    WHEN UPPER(TRIM(p4.nombre)) IN (
                        "EXCHANGE ONLINE PLAN 1",
                        "EXCHANGE ONLINE (PLAN 1)"
                    )
                    THEN "EXCHANGE ONLINE (PLAN 1)"

                    WHEN UPPER(TRIM(p4.nombre)) IN (
                        "PLANNER AND PROJECT PLAN 3",
                        "PLANNER Y PROJECT PLAN 3"
                    )
                    THEN "PLANNER AND PROJECT PLAN 3"

                    ELSE TRIM(p4.nombre)
                END
                ORDER BY p4.nombre
                SEPARATOR ", "
            )

            FROM ad_asignaciones_licencia al4

            INNER JOIN ad_suscripciones s4
                ON s4.id = al4.suscripcion_id

            INNER JOIN ad_planes p4
                ON p4.id = s4.plan_id

            WHERE al4.cuenta_id = n.cuenta_id
              AND al4.estado = "CERRADA"
              AND al4.fecha_fin IS NOT NULL
              AND DATE(al4.fecha_fin)
                  = DATE(n.fecha_novedad)
        ),
        ""
    ),

    ""
) AS licencias_cuenta,

        r.servidor AS backup_servidor,
        r.carpeta AS backup_carpeta,
        r.subcarpeta_1 AS backup_subcarpeta_1,
        r.subcarpeta_2 AS backup_subcarpeta_2,
        r.subcarpeta_3 AS backup_subcarpeta_3,
        r.archivo AS backup_archivo,
        r.repositorio_cloud AS backup_repositorio,
        r.fecha_carga AS backup_fecha,
        r.tamano_pst AS backup_tamano_pst,
        r.tamano_onedrive AS backup_tamano_onedrive

    FROM ad_novedades n

    LEFT JOIN ad_cuentas c
        ON c.id = n.cuenta_id

    ' . $backupJoin . '

    WHERE ' . $sqlWhere . '

    ORDER BY
        COALESCE(
            n.fecha_novedad,
            DATE(n.created_at)
        ) DESC,

        n.id DESC

    LIMIT :limit
    OFFSET :offset
';

$stmt = $pdo->prepare($sql);

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

/*
 * Retorna el primer dato no vacío encontrado
 * dentro de las claves indicadas.
 */
$firstValue = static function (
    array $data,
    array $keys
): string {
    foreach ($keys as $key) {
        if (
            array_key_exists($key, $data)
            && trim((string)$data[$key]) !== ''
        ) {
            return trim(
                (string)$data[$key]
            );
        }
    }

    return '';
};

$title = 'Novedades';

require dirname(__DIR__, 2)
    . '/views/header.php';
?>

<style>
    .custom-restore-modal {
    border: 0;
    border-radius: 16px;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.18);
    overflow: hidden;
}

.custom-restore-modal .modal-header,
.custom-restore-modal .modal-body,
.custom-restore-modal .modal-footer {
    background: #ffffff;
}

.restore-modal-icon {
    width: 56px;
    height: 56px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e8f7ee;
    color: #198754;
    font-size: 1.6rem;
    font-weight: 700;
}

.custom-restore-modal .modal-title {
    color: #1d2939;
}

.custom-restore-modal .btn-success {
    min-width: 130px;
}

.custom-restore-modal .btn-outline-secondary {
    min-width: 110px;
}

    .novelty-list {
        overflow: hidden;
    }

    .novelty-item {
        position: relative;
        border-bottom: 1px solid #dee2e6;
    }

    .novelty-item:last-child {
        border-bottom: 0;
    }

    .novelty-item summary {
        padding: 17px;
        padding-right: 165px;
        cursor: pointer;
        list-style: none;
    }

@media (max-width: 700px) {
    .novelty-item summary {
        padding-right: 17px;
    }

    .novelty-restore-form {
        position: static;
        display: flex;
        justify-content: flex-end;
        margin: 0 17px 12px;
    }
}

    .novelty-restore-form {
    position: absolute;
    top: 13px;
    right: 16px;
    z-index: 5;
    margin: 0;
}

@media (max-width: 700px) {
    .novelty-item summary {
        padding-right: 17px;
    }

    .novelty-restore-form {
        position: static;
        display: flex;
        justify-content: flex-end;
        margin: 0 17px 12px;
    }
}

    .novelty-item summary::-webkit-details-marker {
        display: none;
    }

    .novelty-item summary:hover {
        background: #f8f9fa;
    }

    .novelty-item[open] summary {
        border-bottom: 1px solid #e4e7eb;
        background: #fbfcfe;
    }

    .novelty-summary-grid {
        display: grid;
        grid-template-columns:
            minmax(120px, .7fr)
            minmax(180px, 1fr)
            minmax(210px, 1.4fr)
            minmax(210px, 1.4fr);
        gap: 18px;
        align-items: center;
        margin-top: 10px;
    }

    .novelty-details {
        padding: 0 18px 18px 40px;
        background: #fafafa;
    }

    .novelty-detail-box {
        height: 100%;
        padding: 13px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        overflow-wrap: anywhere;
    }

    @media (max-width: 900px) {
        .novelty-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 600px) {
        .novelty-summary-grid {
            grid-template-columns: 1fr;
        }

        .novelty-details {
            padding-left: 18px;
        }
    }
</style>

<div class="w-100">

    <div class="mb-3">
        <h1 class="h3 mb-1">
            Novedades
        </h1>

        <div class="text-muted">
            <?= number_format(
                $total,
                0,
                ',',
                '.'
            ) ?>
            novedades registradas
        </div>
    </div>

<form
    method="get"
    class="card card-body mb-3 module-filter-card"
    id="noveltyFiltersForm"
>
    <div class="row g-3 align-items-end">

        <div class="col-lg-8">
            <label
                class="form-label"
                for="noveltySearch"
            >
                Buscar
            </label>

            <input
                type="search"
                class="form-control"
                id="noveltySearch"
                name="q"
                value="<?= ad_e($q) ?>"
                placeholder="Correo, funcionario, archivo, carpeta u observación"
                autocomplete="off"
            >
        </div>

        <div class="col-lg-4">
            <label
                class="form-label"
                for="noveltyTypeFilter"
            >
                Tipo de novedad
            </label>

            <select
                class="form-select"
                id="noveltyTypeFilter"
                name="tipo"
            >
                <option value="">
                    Todos los tipos
                </option>

                <?php foreach ($tiposDisponibles as $value => $label): ?>
                    <option
                        value="<?= ad_e($value) ?>"
                        <?= $tipo === $value ? 'selected' : '' ?>
                    >
                        <?= ad_e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

    </div>
</form>

    <div class="card card-kpi novelty-list">

        <?php if (!$rows): ?>
            <div class="text-center text-muted p-4">
                No se encontraron novedades.
            </div>
        <?php endif; ?>

        <?php foreach ($rows as $row): ?>
            <?php
            $datos = json_decode(
                (string)$row['datos_origen'],
                true
            );

            $datos = is_array($datos)
                ? $datos
                : [];

            $tipoNovedad = strtoupper(
                trim(
                    (string)($row['tipo'] ?? '')
                )
            );

            $cuentaDatos = is_array(
                $datos['cuenta'] ?? null
            )
                ? $datos['cuenta']
                : [];

            $personaAnterior = is_array(
                $datos['persona_anterior'] ?? null
            )
                ? $datos['persona_anterior']
                : [];

            $personaNueva = is_array(
                $datos['persona_nueva'] ?? null
            )
                ? $datos['persona_nueva']
                : [];

            $pst = is_array(
                $datos['pst'] ?? null
            )
                ? $datos['pst']
                : [];

            $esAplicacion =
                ($datos['origen'] ?? '')
                === 'APLICACION';

/*
 * Correos registrados durante la gestión.
 */
$correoAnterior = $firstValue(
    $cuentaDatos,
    [
        'correo_anterior',
    ]
);

$correoNuevo = $firstValue(
    $cuentaDatos,
    [
        'correo_nuevo',
    ]
);

if ($correoAnterior === '') {
    $correoAnterior = trim(
        (string)(
            $row['correo_actual']
            ?? ''
        )
    );
}

if ($correoNuevo === '') {
    $correoNuevo = $correoAnterior;
}

$cambioCorreoGestion =
    strcasecmp(
        $correoAnterior,
        $correoNuevo
    ) !== 0;

$esGestionConCorreo = in_array(
    $tipoNovedad,
    [
        'CAMBIO_ASIGNACION',
        'CAMBIO_LICENCIA',
    ],
    true
);

/*
 * La tarjeta principal muestra la dirección resultante.
 */
$correo =
    $correoNuevo !== ''
        ? $correoNuevo
        : $correoAnterior;

if ($correoNuevo === '') {
    $correoNuevo = $firstValue(
        $datos,
        [
            'CORREO_NUEVO',
            'CORREO NUEVO',
        ]
    );
}

if (
    $correoAnterior === ''
    && $correoNuevo === ''
) {
    $correoAnterior = trim(
        (string)(
            $row['correo_actual']
            ?? ''
        )
    );
}

/*
 * Cuando el registro histórico no trae correo nuevo,
 * se entiende que la dirección no cambió.
 */
if ($correoNuevo === '') {
    $correoNuevo = $correoAnterior;
}

if ($correoAnterior === '') {
    $correoAnterior = $correoNuevo;
}

$cambioCorreoAsignacion =
    strcasecmp(
        $correoAnterior,
        $correoNuevo
    ) !== 0;

/*
 * En el resumen se muestra primero la dirección nueva.
 */
$correo =
    $correoNuevo !== ''
        ? $correoNuevo
        : $correoAnterior;
            $nombre = $firstValue(
                $personaAnterior,
                ['nombre']
            );

            if ($nombre === '') {
                $nombre = $firstValue(
                    $datos,
                    [
                        'APELLIDO NOMBRE',
                        'APELLIDOS Y NOMBRES',
                        'APELLIDOS_Y_NOMBRES',
                        'NOMBRE',
                    ]
                );
            }

            $documento = $firstValue(
                $personaAnterior,
                ['documento']
            );

            if ($documento === '') {
                $documento = $firstValue(
                    $datos,
                    [
                        'NUM_DOCUMENTO',
                        'DOCUMENTO',
                        'CEDULA',
                    ]
                );
            }

            $archivo = $firstValue(
                $pst,
                ['archivo']
            );

            if ($archivo === '') {
                $archivo = $firstValue(
                    $datos,
                    [
                        'ARCHIVO',
                        'NOMBRE_ARCHIVO',
                        'ARCHIVO PST',
                    ]
                );
            }

            if ($archivo === '') {
                $archivo = trim(
                    (string)(
                        $row['backup_archivo']
                        ?? ''
                    )
                );
            }

            $ubicacion = $firstValue(
                $pst,
                ['ubicacion']
            );

            if ($ubicacion === '') {
                $pathParts = array_values(
                    array_unique(
                        array_filter([
                            $firstValue(
                                $datos,
                                [
                                    'SERVIDOR',
                                    'SERVER',
                                ]
                            ),

                            $firstValue(
                                $datos,
                                [
                                    'CARPETA',
                                    'UBICACION',
                                    'UBICACIÓN',
                                    'RUTA',
                                ]
                            ),

                            $firstValue(
                                $datos,
                                [
                                    'SUBCARPETA 1',
                                    'SUBCARPETA_1',
                                ]
                            ),

                            $firstValue(
                                $datos,
                                [
                                    'SUBCARPETA 2',
                                    'SUBCARPETA_2',
                                ]
                            ),

                            $firstValue(
                                $datos,
                                [
                                    'SUBCARPETA 3',
                                    'SUBCARPETA_3',
                                ]
                            ),

                            trim((string)($row['backup_servidor'] ?? '')),
                            trim((string)($row['backup_carpeta'] ?? '')),
                            trim((string)($row['backup_subcarpeta_1'] ?? '')),
                            trim((string)($row['backup_subcarpeta_2'] ?? '')),
                            trim((string)($row['backup_subcarpeta_3'] ?? '')),
                        ])
                    )
                );

                $ubicacion = implode(
                    ' / ',
                    $pathParts
                );
            }

            $observaciones = $firstValue(
                $datos,
                [
                    'observaciones',
                    'OBSERVACION',
                    'OBSERVACIÓN',
                    'OBSERVACIONES',
                    'OBSERVACIÓN (Solicitante)',
                ]
            );

            if ($observaciones === '') {
                $observaciones = trim(
                    (string)($row['descripcion'] ?? '')
                );
            }

            $tamano = $firstValue(
                $pst,
                ['tamano']
            );

            if ($tamano === '') {
                $tamano = $firstValue(
                    $datos,
                    [
                        'TamPST',
                        'TAMANO_PST',
                        'TAMAÑO PST',
                    ]
                );
            }

            if ($tamano === '') {
                $tamano = trim(
                    (string)($row['backup_tamano_pst'] ?? '')
                );
            }

            $fechaBase = $row['fecha_novedad'] ?: $row['created_at'];

            $fecha = 'Sin fecha';

            if ($fechaBase) {
                $timestamp = strtotime((string)$fechaBase);

                if ($timestamp !== false) {
                    $fecha = date('d/m/Y', $timestamp);
                }
            }

            if (!empty($row['created_at'])) {
                $timestamp = strtotime((string)$row['created_at']);

                if ($timestamp !== false) {
                    $hora = date('H:i:s', $timestamp);
                }
            }

            $tipoNovedad = strtoupper(
    trim(
        (string)($row['tipo'] ?? '')
    )
);

$esCambioLicencia =
    $tipoNovedad === 'CAMBIO_LICENCIA';

$licenciasCuenta = trim(
    (string)(
        $row['licencias_cuenta']
        ?? ''
    )
);

$extractLicenseName = static function ($value): string {
    if (
        is_string($value)
        || is_numeric($value)
    ) {
        return trim((string)$value);
    }

    if (!is_array($value)) {
        return '';
    }

    foreach (
        [
            'nombre',
            'licencia',
            'plan',
            'nombre_plan',
            'tipo_licencia',
        ]
        as $key
    ) {
        if (
            isset($value[$key])
            && trim((string)$value[$key]) !== ''
        ) {
            return trim(
                (string)$value[$key]
            );
        }
    }

    $names = [];

    foreach ($value as $item) {
        if (
            is_string($item)
            || is_numeric($item)
        ) {
            $name = trim((string)$item);

            if ($name !== '') {
                $names[] = $name;
            }

            continue;
        }

        if (is_array($item)) {
            foreach (
                [
                    'nombre',
                    'licencia',
                    'plan',
                    'nombre_plan',
                    'tipo_licencia',
                ]
                as $key
            ) {
                if (
                    isset($item[$key])
                    && trim((string)$item[$key]) !== ''
                ) {
                    $names[] = trim(
                        (string)$item[$key]
                    );

                    break;
                }
            }
        }
    }

    return implode(
        ', ',
        array_values(
            array_unique($names)
        )
    );
};

$datosLicencia = is_array(
    $datos['licencia'] ?? null
)
    ? $datos['licencia']
    : [];

$licenciaAnterior = $extractLicenseName(
    $datos['licencia_anterior']
    ?? $datosLicencia['anterior']
    ?? $datos['plan_anterior']
    ?? null
);

$licenciaNueva = $extractLicenseName(
    $datos['licencia_nueva']
    ?? $datosLicencia['nueva']
    ?? $datos['nueva_licencia']
    ?? $datos['plan_nuevo']
    ?? null
);

if (
    $licenciaNueva === ''
    && $esCambioLicencia
) {
    $licenciaNueva = $licenciasCuenta;
}

$tipoEtiqueta =
    $tiposDisponibles[$tipoNovedad]
    ?? str_replace(
        '_',
        ' ',
        $tipoNovedad
    );

$muestraRespaldo =
    $tipoNovedad === 'ELIMINADO_CON_PST';

$esCambioAsignacion = in_array(
    $tipoNovedad,
    [
        'CAMBIO_ASIGNACION',
        'CAMBIO_NOMBRE_ASIGNACION',
    ],
    true
);

$esNovedadDeEliminacion = in_array(
    $tipoNovedad,
    [
        'ELIMINADO_CON_PST',
        'ELIMINADO_CPANEL',
    ],
    true
);

$puedeRestaurarCuenta =
    $canEditNovelties
    && $esNovedadDeEliminacion
    && (int)($row['cuenta_id'] ?? 0) > 0
    && strtoupper(
        trim(
            (string)($row['cuenta_estado'] ?? '')
        )
    ) === 'ELIMINADA';

/*
 * Persona anterior.
 */
$nombreAnterior = $nombre;
$documentoAnterior = $documento;

$cargoAnterior = $firstValue(
    $personaAnterior,
    [
        'cargo',
        'CARGO',
    ]
);

/*
 * Persona nueva de un cambio de asignación.
 */
$nombreNuevo = $firstValue(
    $personaNueva,
    [
        'nombre',
        'nombre_completo',
    ]
);

$documentoNuevo = $firstValue(
    $personaNueva,
    [
        'documento',
        'numero_documento',
    ]
);

$cargoNuevo = $firstValue(
    $personaNueva,
    [
        'cargo',
        'CARGO',
    ]
);

/*
 * En el resumen de un cambio de asignación se muestra
 * el nuevo funcionario.
 */
$nombreResumen =
    $esCambioAsignacion
    && $nombreNuevo !== ''
        ? $nombreNuevo
        : $nombreAnterior;

$documentoResumen =
    $esCambioAsignacion
    && $documentoNuevo !== ''
        ? $documentoNuevo
        : $documentoAnterior;

/*
 * Tamaño de OneDrive.
 */
$tamanoOneDrive = $firstValue(
    $pst,
    [
        'tamano_onedrive',
        'tamanoOneDrive',
    ]
);

if ($tamanoOneDrive === '') {
    $tamanoOneDrive = trim(
        (string)(
            $row['backup_tamano_onedrive']
            ?? ''
        )
    );
}

/*
 * Tamaño de OneDrive almacenado en la novedad
 * o en el backup asociado.
 */
$tamanoOneDrive = $firstValue(
    $pst,
    ['tamano_onedrive']
);

if ($tamanoOneDrive === '') {
    $tamanoOneDrive = trim(
        (string)(
            $row['backup_tamano_onedrive']
            ?? ''
        )
    );
}
?>

<details class="novelty-item">

    <summary>
        <strong>
            Ver detalle de la novedad
        </strong>

        <div class="backup-summary">

            <div class="summary-box bg-soft-blue">
                <div class="backup-label">
                    Fecha
                </div>

                <div class="backup-value">
                    <?= ad_e($fecha) ?>
                </div>
            </div>

            <div class="summary-box bg-soft-green">
                <div class="backup-label">
                    Tipo
                </div>

                <div class="backup-value">
                    <?= ad_e($tipoEtiqueta) ?>
                </div>
            </div>

            <div class="summary-box bg-soft-yellow">
                <div class="backup-label">
                    Correo
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $correo
                            ?: 'Sin correo'
                        ) ?>
                    </strong>
                </div>
            </div>

            <div class="summary-box bg-soft-purple">
                <div class="backup-label">
                    Funcionario
                </div>

                <div class="backup-value">
                    <?= ad_e(
                        $nombreResumen
                        ?: 'Sin asignar'
                    ) ?>

                    <?php if (
                        $documentoResumen !== ''
                    ): ?>
                        <div class="small text-muted">
                            <?= ad_e($documentoResumen) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </summary>

    <div class="novelty-details">

    <div class="mb-3">
        <strong>
            Detalles de la novedad
        </strong>

        <div class="small text-muted">
            Información relevante y observaciones asociadas
        </div>
    </div>

    <?php if ($muestraRespaldo): ?>

        <div class="row g-3">

            <div class="col-lg-6">
                <div class="novelty-detail-box bg-soft-cyan">
                    <div class="backup-label mb-1">
                        Ubicación del respaldo
                    </div>

                    <div class="backup-value">
                        <?= ad_e(
                            $ubicacion
                            ?: 'No registrada'
                        ) ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="novelty-detail-box bg-soft-blue">
                    <div class="backup-label mb-1">
                        Archivo
                    </div>

                    <div class="backup-value">
                        <?= ad_e(
                            $archivo
                            ?: 'No registrado'
                        ) ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="novelty-detail-box bg-soft-yellow">
                    <div class="backup-label mb-1">
                        Tamaño PST
                    </div>

                    <div class="backup-value">
                        <?= ad_e(
                            $tamano
                            ?: 'No registrado'
                        ) ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="novelty-detail-box bg-soft-green">
                    <div class="backup-label mb-1">
                        Tamaño OneDrive
                    </div>

                    <div class="backup-value">
                        <?= ad_e(
                            $tamanoOneDrive
                            ?: 'No registrado'
                        ) ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="novelty-detail-box bg-soft-pink">
                    <div class="backup-label mb-1">
                        Observaciones
                    </div>

                    <div class="backup-value">
                        <?= nl2br(
                            ad_e(
                                $observaciones
                                ?: 'Sin observaciones'
                            )
                        ) ?>
                    </div>
                </div>
            </div>

        </div>

<?php elseif ($esCambioAsignacion): ?>

    <div class="row g-3">

        <div class="col-lg-6">
            <div
                class="novelty-detail-box bg-soft-blue"
            >
                <div class="backup-label mb-1">
                    Correo anterior
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $correoAnterior
                            ?: 'No registrado'
                        ) ?>
                    </strong>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div
                class="novelty-detail-box bg-soft-green"
            >
                <div class="backup-label mb-1">
                    Correo de la nueva asignación
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $correoNuevo
                            ?: 'No registrado'
                        ) ?>
                    </strong>

                    <?php if (
                        !$cambioCorreoAsignacion
                    ): ?>
                        <div class="small text-muted mt-1">
                            La dirección de correo no cambió.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div
                class="novelty-detail-box bg-soft-pink"
            >
                <div class="backup-label mb-1">
                    Funcionario anterior
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $nombreAnterior
                            ?: 'Sin asignar'
                        ) ?>
                    </strong>

                    <div class="small text-muted mt-1">
                        Documento:
                        <?= ad_e(
                            $documentoAnterior
                            ?: 'No registrado'
                        ) ?>
                    </div>

                    <div class="small text-muted">
                        Cargo:
                        <?= ad_e(
                            $cargoAnterior
                            ?: 'No registrado'
                        ) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div
                class="novelty-detail-box bg-soft-purple"
            >
                <div class="backup-label mb-1">
                    Nuevo funcionario
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $nombreNuevo
                            ?: 'No registrado'
                        ) ?>
                    </strong>

                    <div class="small text-muted mt-1">
                        Documento:
                        <?= ad_e(
                            $documentoNuevo
                            ?: 'No registrado'
                        ) ?>
                    </div>

                    <div class="small text-muted">
                        Cargo:
                        <?= ad_e(
                            $cargoNuevo
                            ?: 'No registrado'
                        ) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div
                class="novelty-detail-box bg-soft-cyan"
            >
                <div class="backup-label mb-1">
                    Observaciones
                </div>

                <div class="backup-value">
                    <?= nl2br(
                        ad_e(
                            $observaciones
                            ?: 'Sin observaciones'
                        )
                    ) ?>
                </div>
            </div>
        </div>

    </div>

        <div class="row g-3">

            <div class="col-lg-6">
                <div class="novelty-detail-box bg-soft-pink">
                    <div class="backup-label mb-1">
                        Licencia anterior
                    </div>

                    <div class="backup-value">
                        <strong>
                            <?= ad_e(
                                $licenciaAnterior
                                ?: 'No registrada'
                            ) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="novelty-detail-box bg-soft-green">
                    <div class="backup-label mb-1">
                        Nueva licencia
                    </div>

                    <div class="backup-value">
                        <strong>
                            <?= ad_e(
                                $licenciaNueva
                                ?: 'No registrada'
                            ) ?>
                        </strong>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="novelty-detail-box bg-soft-cyan">
                    <div class="backup-label mb-1">
                        Observaciones
                    </div>

                    <div class="backup-value">
                        <?= nl2br(
                            ad_e(
                                $observaciones
                                ?: 'Sin observaciones'
                            )
                        ) ?>
                    </div>
                </div>
            </div>

        </div>

    <?php else: ?>

        <div class="row g-3">

            <div class="col-lg-4">
                <div class="novelty-detail-box bg-soft-blue">
                    <div class="backup-label mb-1">
                        Tipo de gestión
                    </div>

                    <div class="backup-value">
                        <?= ad_e($tipoEtiqueta) ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="novelty-detail-box bg-soft-cyan">
                    <div class="backup-label mb-1">
                        Observaciones
                    </div>

                    <div class="backup-value">
                        <?= nl2br(
                            ad_e(
                                $observaciones
                                ?: 'Sin observaciones'
                            )
                        ) ?>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

    <?php if (
        !$esCambioLicencia
        && $licenciasCuenta !== ''
    ): ?>
        <div class="row g-3 mt-1">

            <div class="col-12">
                <div class="novelty-detail-box bg-soft-yellow">
                    <div class="backup-label mb-1">
                        Licencias asociadas
                    </div>

                    <div class="backup-value">
                        <?= ad_e($licenciasCuenta) ?>
                    </div>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <?php if ($esGestionConCorreo): ?>

    <div class="row g-3 mt-1">

        <div class="col-lg-6">
            <div
                class="novelty-detail-box bg-soft-blue"
            >
                <div class="backup-label mb-1">
                    Dirección de correo anterior
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $correoAnterior
                            ?: 'No registrada'
                        ) ?>
                    </strong>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div
                class="novelty-detail-box bg-soft-green"
            >
                <div class="backup-label mb-1">
                    Dirección de correo resultante
                </div>

                <div class="backup-value">
                    <strong>
                        <?= ad_e(
                            $correoNuevo
                            ?: 'No registrada'
                        ) ?>
                    </strong>

                    <?php if (
                        !$cambioCorreoGestion
                    ): ?>
                        <div class="small text-muted mt-1">
                            La dirección de correo no fue modificada
                            durante esta gestión.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

<?php endif; ?>

    <?php if ($puedeRestaurarCuenta): ?>
        <div class="mt-3 d-flex justify-content-end">
            <form
                method="post"
                action="<?= ad_e(ad_url('novedades/restablecer_save.php')) ?>"
                class="js-restore-form"
                data-correo="<?= ad_e($correo ?: 'esta cuenta') ?>"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= ad_e(ad_csrf_token()) ?>"
                >

                <input
                    type="hidden"
                    name="novedad_id"
                    value="<?= (int)($row['id'] ?? 0) ?>"
                >

                <button
                    type="button"
                    class="btn btn-success btn-sm js-open-restore-modal"
                >
                    Restaurar cuenta
                </button>
            </form>
        </div>
    <?php endif; ?>

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
                                'tipo' => $tipo,
                                'page' => max(
                                    1,
                                    $page - 1
                                ),
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
                                    'tipo' => $tipo,
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
                                'tipo' => $tipo,
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

<div
    class="modal fade"
    id="restoreAccountModal"
    tabindex="-1"
    aria-labelledby="restoreAccountModalLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-restore-modal">

            <div class="modal-header border-0 pb-0">
                <div>
                    <h5
                        class="modal-title fw-bold"
                        id="restoreAccountModalLabel"
                    >
                        Restaurar cuenta
                    </h5>

                    <div class="text-muted small">
                        Confirmación de acción
                    </div>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Cerrar"
                ></button>
            </div>

            <div class="modal-body pt-2">
                <div class="restore-modal-icon mb-3">
                    ↺
                </div>

                <p class="mb-2 fw-semibold">
                    ¿Desea restaurar esta cuenta?
                </p>

                <p
                    class="text-muted mb-0"
                    id="restoreModalMessage"
                >
                    La cuenta volverá al estado Activa y esta novedad será eliminada.
                </p>
            </div>

            <div class="modal-footer border-0 pt-0">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    class="btn btn-success"
                    id="confirmRestoreBtn"
                >
                    Sí, restaurar
                </button>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('restoreAccountModal');
    const messageElement = document.getElementById('restoreModalMessage');
    const confirmButton = document.getElementById('confirmRestoreBtn');

    if (!modalElement || !confirmButton) {
        return;
    }

    const restoreModal = new bootstrap.Modal(modalElement);
    let activeForm = null;

    document.querySelectorAll('.js-open-restore-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            activeForm = button.closest('.js-restore-form');

            const correo = activeForm
                ? (activeForm.getAttribute('data-correo') || 'esta cuenta')
                : 'esta cuenta';

            messageElement.textContent =
                'La cuenta "' + correo + '" volverá al estado Activa y esta novedad será eliminada.';

            restoreModal.show();
        });
    });

    confirmButton.addEventListener('click', function () {
        if (activeForm) {
            activeForm.submit();
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('noveltyFiltersForm');
    const search = document.getElementById('noveltySearch');
    const typeFilter = document.getElementById('noveltyTypeFilter');

    if (!form) {
        return;
    }

    const selectionKey = 'filterSelection:' + (search ? search.id : '');

    const restoreSelection = function () {
        if (!search) {
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
        if (!search) {
            return;
        }

        try {
            sessionStorage.setItem(selectionKey, JSON.stringify({
                start: search.selectionStart,
                end: search.selectionEnd,
                value: search.value,
            }));
        } catch (error) {
            // ignore storage errors
        }
    };

    if (typeFilter) {
        typeFilter.addEventListener('change', function () {
            form.requestSubmit();
        });
    }

    restoreSelection();

    let timer = null;

    if (search) {
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
    }
});
</script>

<?php
require dirname(__DIR__, 2)
    . '/views/footer.php';
?>