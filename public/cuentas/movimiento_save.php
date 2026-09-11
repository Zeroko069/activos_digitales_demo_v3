<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.editar');
ad_verify_csrf();

$pdo = db();

$accountId = (int)($_POST['cuenta_id'] ?? 0);

$type = strtoupper(
    trim((string)($_POST['tipo_gestion'] ?? ''))
);

$correoNuevoInput = strtolower(
    trim(
        (string)(
            $_POST['correo_nuevo']
            ?? ''
        )
    )
);

$correoAnterior = '';
$correoNuevo = '';
$cambiarCorreo = false;

$date = trim(
    (string)($_POST['fecha_gestion'] ?? '')
);

$observation = trim(
    (string)($_POST['observaciones'] ?? '')
);

$descriptionCambioAsignacion = '';
$descriptionCambioLicencia = '';

$labels = [
    'ELIMINADO_CON_PST' =>
        'Eliminado con PST',

    'ELIMINADO_CPANEL' =>
        'Eliminado de CPANEL',

    'CAMBIO_ASIGNACION' =>
        'Cambio de asignación',

    'CAMBIO_LICENCIA' =>
        'Cambio de licencia',

    'OTRO' =>
        'Otro',
];

if (
    $accountId <= 0
    || !isset($labels[$type])
) {
    ad_flash(
        'danger',
        'La gestión indicada no es válida.'
    );

    ad_redirect('cuentas/index.php');
}

if ($type === 'OTRO' && $observation === '') {
    ad_flash(
        'danger',
        'Debe indicar la observación de la gestión.'
    );

    ad_redirect(
        'cuentas/movimiento.php?id=' . $accountId
    );
}

$correoActualCuenta = '';

$personaAnteriorData = [];
$personaNuevaData = [];

$dateObject = DateTimeImmutable::createFromFormat(
    'Y-m-d',
    $date
);

if (
    !$dateObject
    || $dateObject->format('Y-m-d') !== $date
) {
    ad_flash(
        'danger',
        'La fecha indicada no es válida.'
    );

    ad_redirect(
        'cuentas/movimiento.php?id=' . $accountId
    );
}

$stmt = $pdo->prepare(
    'SELECT
        c.*,

        p.id AS persona_id,
        p.numero_documento,
        p.nombre_completo,
        p.cargo,
        p.ciudad AS persona_ciudad

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
    ':id' => $accountId,
]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    ad_flash('danger', 'No se encontró la cuenta.');
    ad_redirect('cuentas/index.php');
}

$correoActualCuenta = strtolower(
    trim(
        (string)(
            $account['correo']
            ?? ''
        )
    )
);

$correoAnterior = $correoActualCuenta;
$correoNuevo = $correoActualCuenta;

/*
 * Información que posteriormente se guardará
 * dentro de datos_origen de la novedad.
 */
$cuentaData = [
    'id' => $accountId,
    'correo_anterior' => $correoAnterior,
    'correo_nuevo' => $correoNuevo,
    'cambio_correo' => false,
];

/*
 * Licencias anteriores para el historial.
 */
$stmt = $pdo->prepare(
    'SELECT
        al.suscripcion_id,
        p.nombre,
        s.valor_unitario,
        s.moneda

     FROM ad_asignaciones_licencia al

     INNER JOIN ad_suscripciones s
       ON s.id = al.suscripcion_id

     INNER JOIN ad_planes p
       ON p.id = s.plan_id

     WHERE al.cuenta_id = :cuenta
       AND al.estado = "ACTIVA"'
);

$stmt->execute([
    ':cuenta' => $accountId,
]);

$previousLicenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdo->beginTransaction();

try {
    $newPerson = null;
    $newLicenses = [];
    $pstData = null;

    if ($type === 'ELIMINADO_CPANEL') {
        /*
         * No se solicita observación al usuario.
         */
        $observation = 'Eliminado de CPANEL';
    }

    if ($type === 'ELIMINADO_CON_PST') {
        if ($observation === '') {
            $observation = 'Eliminado con PST';
        }
    }

    /*
     * ELIMINACIONES
     */
    if (
        in_array(
            $type,
            [
                'ELIMINADO_CON_PST',
                'ELIMINADO_CPANEL',
            ],
            true
        )
    ) {
        $stmt = $pdo->prepare(
            'UPDATE ad_cuentas
             SET
                estado = "ELIMINADA",
                fecha_eliminacion = :fecha
             WHERE id = :id'
        );

        $stmt->execute([
            ':fecha' => $date,
            ':id' => $accountId,
        ]);

        $stmt = $pdo->prepare(
            'UPDATE ad_asignaciones_cuenta
             SET
                estado = "CERRADA",
                fecha_fin = :fecha,
                motivo = :motivo
             WHERE cuenta_id = :cuenta
               AND estado = "ACTIVA"'
        );

        $stmt->execute([
            ':fecha' => $date,
            ':motivo' => $type,
            ':cuenta' => $accountId,
        ]);

        $stmt = $pdo->prepare(
            'UPDATE ad_asignaciones_licencia
             SET
                estado = "CERRADA",
                fecha_fin = :fecha,
                motivo = :motivo
             WHERE cuenta_id = :cuenta
               AND estado = "ACTIVA"'
        );

        $stmt->execute([
            ':fecha' => $date,
            ':motivo' => $type,
            ':cuenta' => $accountId,
        ]);
    }

    /*
     * ELIMINADO CON PST:
     * Es el único tipo que crea un registro en Backups.
     */
    if ($type === 'ELIMINADO_CON_PST') {
    $server = trim(
        (string)($_POST['servidor_pst'] ?? '')
    );

    $disk = trim(
        (string)($_POST['disco_pst'] ?? '')
    );

    $subfolder1 = trim(
        (string)($_POST['subcarpeta_1_pst'] ?? '')
    );

    $subfolder2 = trim(
        (string)($_POST['subcarpeta_2_pst'] ?? '')
    );

    $file = trim(
        (string)($_POST['archivo_pst'] ?? '')
    );

    $pstSize = trim(
        (string)($_POST['tamano_pst'] ?? '')
    );

    $oneDriveSize = trim(
        (string)($_POST['tamano_onedrive'] ?? '')
    );

    if (
        $server === ''
        || $disk === ''
        || $file === ''
    ) {
        throw new RuntimeException(
            'Debe indicar el servidor, el disco y el nombre del archivo.'
        );
    }

    $stmt = $pdo->prepare(
        'INSERT INTO ad_respaldos
         (
            cuenta_id,
            servidor,
            carpeta,
            subcarpeta_1,
            subcarpeta_2,
            archivo,
            repositorio_cloud,
            fecha_carga,
            tamano_pst,
            tamano_onedrive,
            estado
         )
         VALUES
         (
            :cuenta,
            :servidor,
            :disco,
            :subcarpeta_1,
            :subcarpeta_2,
            :archivo,
            "PST",
            :fecha,
            :tamano_pst,
            :tamano_onedrive,
            "REGISTRADO"
         )'
    );

    $stmt->execute([
        ':cuenta' => $accountId,
        ':servidor' => $server,
        ':disco' => $disk,

        ':subcarpeta_1' =>
            $subfolder1 !== ''
                ? $subfolder1
                : null,

        ':subcarpeta_2' =>
            $subfolder2 !== ''
                ? $subfolder2
                : null,

        ':archivo' => $file,
        ':fecha' => $date,

        ':tamano_pst' =>
            $pstSize !== ''
                ? $pstSize
                : null,

        ':tamano_onedrive' =>
            $oneDriveSize !== ''
                ? $oneDriveSize
                : null,
    ]);

    /*
     * Datos utilizados por la novedad.
     * Se mantienen también ubicacion y tamano
     * para conservar compatibilidad visual.
     */
    $locationParts = array_values(
        array_filter([
            $server,
            $disk,
            $subfolder1,
            $subfolder2,
        ])
    );

    $pstData = [
    'servidor' => $server,
    'disco' => $disk,
    'subcarpeta_1' => $subfolder1,
    'subcarpeta_2' => $subfolder2,
    'archivo' => $file,
    'tamano_pst' => $pstSize,
    'tamano_onedrive' => $oneDriveSize,

    /*
     * Compatibilidad con la vista de Novedades.
     */
    'ubicacion' => implode(
        ' / ',
        $locationParts
    ),

    'tamano' => $pstSize,
];
}

    /*
     * CAMBIO DE ASIGNACIÓN
     *
     * El correo no cambia.
     */
if ($type === 'CAMBIO_ASIGNACION') {
    $personaNuevaId = (int)(
        $_POST['persona_nueva_id']
        ?? 0
    );

    $guardarAsignacionPendiente =
        (string)(
            $_POST['guardar_asignacion_pendiente']
            ?? ''
        ) === '1';

    $documentoNuevoFuncionario = trim(
        (string)(
            $_POST['documento_nuevo']
            ?? ''
        )
    );

    $paisNuevoFuncionario = strtoupper(
        trim(
            (string)(
                $_POST['pais_nuevo']
                ?? 'CO'
            )
        )
    );

    if (
        $personaNuevaId <= 0
        && !$guardarAsignacionPendiente
    ) {
        throw new RuntimeException(
            'Debe consultar y seleccionar el nuevo funcionario.'
        );
    }

    if (
        $guardarAsignacionPendiente
        && $documentoNuevoFuncionario === ''
    ) {
        throw new RuntimeException(
            'Debe ingresar el documento del nuevo funcionario para guardar la asignación pendiente.'
        );
    }

    $correoAnterior = strtolower(
        trim(
            (string)(
                $account['correo']
                ?? ''
            )
        )
    );

    $cambiarCorreo =
        (string)(
            $_POST['cambiar_correo']
            ?? ''
        ) === '1';

    $correoNuevo = $correoAnterior;

    if ($cambiarCorreo) {
        $correoNuevo = $correoNuevoInput;

        if ($correoNuevo === '') {
            throw new RuntimeException(
                'Debe indicar la dirección de correo '
                . 'para la nueva asignación.'
            );
        }

        if (
            filter_var(
                $correoNuevo,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new RuntimeException(
                'La nueva dirección de correo '
                . 'no tiene un formato válido.'
            );
        }

        if (
            strcasecmp(
                $correoAnterior,
                $correoNuevo
            ) !== 0
        ) {
            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    correo,
                    estado

                 FROM ad_cuentas

                 WHERE id <> :cuenta_id

                   AND LOWER(
                        TRIM(correo)
                       ) = LOWER(
                            TRIM(:correo)
                       )

                   AND estado IN (
                       "ACTIVA",
                       "GESTION_DE_BAJA"
                   )

                 LIMIT 1'
            );

            $stmt->execute([
                ':cuenta_id' => $accountId,
                ':correo' => $correoNuevo,
            ]);

            $cuentaDuplicada = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if ($cuentaDuplicada) {
                throw new RuntimeException(
                    'La dirección '
                    . $correoNuevo
                    . ' ya está siendo utilizada '
                    . 'por otra cuenta.'
                );
            }
        }
    }

    $stmt = $pdo->prepare(
        'SELECT
            ac.id AS asignacion_id,
            ac.persona_id,

            p.numero_documento,
            p.nombre_completo,
            p.cargo,
            p.ciudad,
            p.pais

         FROM ad_asignaciones_cuenta ac

         LEFT JOIN ad_personas p
            ON p.id = ac.persona_id

         WHERE ac.cuenta_id = :cuenta
           AND ac.estado = "ACTIVA"

         ORDER BY ac.id DESC

         LIMIT 1'
    );

    $stmt->execute([
        ':cuenta' => $accountId,
    ]);

    $asignacionAnterior = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    $personaNueva = null;
    $assignmentState = 'PENDIENTE';
    $assignmentPersonaId = null;
    $assignmentObservations = null;

    if ($personaNuevaId > 0) {
        $stmt = $pdo->prepare(
            'SELECT
                id,
                numero_documento,
                nombre_completo,
                cargo,
                ciudad,
                pais

             FROM ad_personas

             WHERE id = :id

             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $personaNuevaId,
        ]);

        $personaNueva = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$personaNueva) {
            throw new RuntimeException(
                'No se encontró el nuevo funcionario.'
            );
        }

        $assignmentState = 'ACTIVA';
        $assignmentPersonaId = $personaNuevaId;
    } else {
        $assignmentState = 'PENDIENTE';
        $assignmentPersonaId = null;
        $assignmentObservations =
            'Asignación pendiente para documento: '
            . $documentoNuevoFuncionario
            . ', pais: '
            . $paisNuevoFuncionario;
    }

    $stmt = $pdo->prepare(
        'UPDATE ad_asignaciones_cuenta

         SET
            estado = "CERRADA",
            fecha_fin = :fecha,
            motivo = "CAMBIO_ASIGNACION"

         WHERE cuenta_id = :cuenta
           AND estado = "ACTIVA"'
    );

    $stmt->execute([
        ':fecha' => $date,
        ':cuenta' => $accountId,
    ]);

    $stmt = $pdo->prepare(
        'INSERT INTO ad_asignaciones_cuenta
         (
            cuenta_id,
            persona_id,
            fecha_inicio,
            estado,
            motivo,
            observaciones,
            created_by
         )
         VALUES
         (
            :cuenta,
            :persona,
            :fecha,
            :estado,
            :motivo,
            :observaciones,
            :usuario
         )'
    );

    $stmt->execute([
        ':cuenta' => $accountId,
        ':persona' => $assignmentPersonaId,
        ':fecha' => $date,
        ':estado' => $assignmentState,
        ':motivo' => $assignmentState === 'PENDIENTE'
            ? 'CAMBIO_ASIGNACION_PENDIENTE'
            : 'CAMBIO_ASIGNACION',
        ':observaciones' => $assignmentObservations,
        ':usuario' => ad_current_user_id(),
    ]);

    if ($cambiarCorreo) {
        $stmt = $pdo->prepare(
            'UPDATE ad_cuentas

             SET correo = :correo

             WHERE id = :id'
        );

        $stmt->execute([
            ':correo' => $correoNuevo,
            ':id' => $accountId,
        ]);
    }

    $cuentaData = [
        'id' => $accountId,
        'correo_anterior' => $correoAnterior,
        'correo_nuevo' => $correoNuevo,
        'cambio_correo' => $cambiarCorreo,
    ];

    $personaAnteriorData = [
        'id' => (int)(
            $asignacionAnterior['persona_id']
            ?? 0
        ),

        'nombre' => trim(
            (string)(
                $asignacionAnterior['nombre_completo']
                ?? ''
            )
        ),

        'documento' => trim(
            (string)(
                $asignacionAnterior['numero_documento']
                ?? ''
            )
        ),

        'cargo' => trim(
            (string)(
                $asignacionAnterior['cargo']
                ?? ''
            )
        ),

        'ciudad' => trim(
            (string)(
                $asignacionAnterior['ciudad']
                ?? ''
            )
        ),

        'pais' => trim(
            (string)(
                $asignacionAnterior['pais']
                ?? ''
            )
        ),
    ];

    $personaNuevaData = [
        'id' => $personaNueva
            ? (int)$personaNueva['id']
            : 0,

        'nombre' => trim(
            (string)(
                $personaNueva['nombre_completo']
                ?? ''
            )
        ),

        'documento' => $personaNueva
            ? trim(
                (string)(
                    $personaNueva['numero_documento']
                    ?? ''
                )
            )
            : $documentoNuevoFuncionario,

        'cargo' => trim(
            (string)(
                $personaNueva['cargo']
                ?? ''
            )
        ),

        'ciudad' => trim(
            (string)(
                $personaNueva['ciudad']
                ?? ''
            )
        ),

        'pais' => $personaNueva
            ? trim(
                (string)(
                    $personaNueva['pais']
                    ?? ''
                )
            )
            : $paisNuevoFuncionario,

        'pendiente' => $personaNueva === null,
    ];

    $descriptionCambioAsignacion = 'Cambio de asignación';

    if ($cambiarCorreo) {
        $descriptionCambioAsignacion .=
            '. Cambio de dirección de correo: '
            . $correoAnterior
            . ' → '
            . $correoNuevo;
    } else {
        $descriptionCambioAsignacion .=
            ' sin cambio de dirección de correo';
    }

    if ($observation !== '') {
        $descriptionCambioAsignacion .=
            '. Observaciones: '
            . $observation;
    }
}

    /*
     * CAMBIO DE LICENCIA
     */
    if ($type === 'CAMBIO_LICENCIA') {
        $subscriptionIds = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'intval',
                        is_array(
                            $_POST['suscripciones'] ?? null
                        )
                            ? $_POST['suscripciones']
                            : []
                    )
                )
            )
        );

        $cambiarCorreo =
            (string)(
                $_POST['cambiar_correo']
                ?? ''
            ) === '1';

        $correoAnterior = strtolower(
            trim(
                (string)(
                    $account['correo']
                    ?? ''
                )
            )
        );

        $correoNuevo = $correoAnterior;

        if ($cambiarCorreo) {
            $correoNuevo = $correoNuevoInput;

            if ($correoNuevo === '') {
                throw new RuntimeException(
                    'Debe ingresar la nueva dirección de correo.'
                );
            }

            if (
                filter_var(
                    $correoNuevo,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    'La nueva dirección de correo '
                    . 'no tiene un formato válido.'
                );
            }

            if (
                strcasecmp(
                    $correoAnterior,
                    $correoNuevo
                ) !== 0
            ) {
                $stmt = $pdo->prepare(
                    'SELECT
                        id

                     FROM ad_cuentas

                     WHERE id <> :cuenta_id
                       AND LOWER(TRIM(correo)) = LOWER(TRIM(:correo))
                       AND estado IN ("ACTIVA", "GESTION_DE_BAJA")

                     LIMIT 1'
                );

                $stmt->execute([
                    ':cuenta_id' => $accountId,
                    ':correo' => $correoNuevo,
                ]);

                $cuentaDuplicada = $stmt->fetchColumn();

                if ($cuentaDuplicada !== false && (int)$cuentaDuplicada > 0) {
                    throw new RuntimeException(
                        'La dirección '
                        . $correoNuevo
                        . ' ya está siendo utilizada '
                        . 'por otra cuenta.'
                    );
                }
            }

            $stmt = $pdo->prepare(
                'UPDATE ad_cuentas
                 SET correo = :correo
                 WHERE id = :id'
            );

            $stmt->execute([
                ':correo' => $correoNuevo,
                ':id' => $accountId,
            ]);
        }

        if ($subscriptionIds === []) {
            throw new RuntimeException(
                'Debe seleccionar al menos una licencia.'
            );
        }

        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($subscriptionIds),
                '?'
            )
        );

        $stmt = $pdo->prepare(
            'SELECT
                s.id,
                s.valor_unitario,
                s.moneda,
                p.nombre

             FROM ad_suscripciones s

             INNER JOIN ad_planes p
               ON p.id = s.plan_id

             WHERE s.id IN (' . $placeholders . ')
               AND s.estado = "VIGENTE"'
        );

        $stmt->execute($subscriptionIds);

        $newLicenses = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (
            count($newLicenses)
            !== count($subscriptionIds)
        ) {
            throw new RuntimeException(
                'Una de las licencias no está vigente.'
            );
        }

        $stmt = $pdo->prepare(
            'UPDATE ad_asignaciones_licencia
             SET
                estado = "CERRADA",
                fecha_fin = :fecha,
                motivo = "CAMBIO_LICENCIA"
             WHERE cuenta_id = :cuenta
               AND estado = "ACTIVA"'
        );

        $stmt->execute([
            ':fecha' => $date,
            ':cuenta' => $accountId,
        ]);

        $insert = $pdo->prepare(
            'INSERT INTO ad_asignaciones_licencia
             (
                cuenta_id,
                suscripcion_id,
                fecha_inicio,
                valor_unitario_asignado,
                moneda,
                estado,
                motivo,
                created_by
             )
             VALUES
             (
                :cuenta,
                :suscripcion,
                :fecha,
                :valor,
                :moneda,
                "ACTIVA",
                "CAMBIO_LICENCIA",
                :usuario
             )'
        );

        foreach ($newLicenses as $license) {
            $insert->execute([
                ':cuenta' => $accountId,
                ':suscripcion' => $license['id'],
                ':fecha' => $date,
                ':valor' => $license['valor_unitario'],
                ':moneda' => $license['moneda'],
                ':usuario' => ad_current_user_id(),
            ]);
        }

        $descriptionCambioLicencia = '';

        if ($observation === '') {
            $oldNames = array_column(
                $previousLicenses,
                'nombre'
            );

            $newNames = array_column(
                $newLicenses,
                'nombre'
            );

            $observation =
                'Cambio de licencia: '
                . (
                    $oldNames
                        ? implode(', ', $oldNames)
                        : 'Sin licencia'
                )
                . ' → '
                . implode(', ', $newNames);
        }

        $descriptionCambioLicencia =
            'Cambio de licencia: ' . $observation;
    }

    /*
     * OTRO
     *
     * No modifica cuenta, asignación, licencia ni backup.
     */
    if ($type === 'OTRO') {
        // La observación ya fue validada como obligatoria.
    }

    /*
     * Todas las gestiones generan novedad.
     */
    $description = $labels[$type] . ': ' . $observation;

    if ($type === 'CAMBIO_ASIGNACION' && $descriptionCambioAsignacion !== '') {
        $description = $descriptionCambioAsignacion;
    }

    if ($type === 'CAMBIO_LICENCIA' && $descriptionCambioLicencia !== '') {
        $description = $descriptionCambioLicencia;
    }

    $datosOrigen = [
        'origen' => 'APLICACION',
        'cuenta' => [
            'id' => $accountId,
            'correo_anterior' => $correoAnterior ?? $correoActualCuenta,
            'correo_nuevo' => $correoNuevo ?? $correoActualCuenta,
            'cambio_correo' => $cambiarCorreo,
        ],
        'persona_anterior' => $personaAnteriorData,
        'persona_nueva' => $personaNuevaData,
        'pst' => $pstData ?? [],
        'observaciones' => $observation,
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO ad_novedades
         (
            cuenta_id,
            tipo,
            estado,
            fecha_novedad,
            descripcion,
            datos_origen,
            created_by
         )
         VALUES
         (
            :cuenta,
            :tipo,
            "CERRADA",
            :fecha,
            :descripcion,
            :datos,
            :usuario
         )'
    );

    $stmt->execute([
        ':cuenta' => $accountId,
        ':tipo' => $type,
        ':fecha' => $date,
        ':descripcion' => $description,
        ':datos' => json_encode(
            $datosOrigen,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ),
        ':usuario' => ad_current_user_id(),
    ]);

    /*
     * Auditoría general.
     */
    try {
        AuditService::log(
            $pdo,
            'ad_cuentas',
            $accountId,
            'UPDATE',
            $account,
            null
        );
    } catch (Throwable $auditError) {
        error_log(
            'Error de auditoría: '
            . $auditError->getMessage()
        );
    }

    $pdo->commit();

    ad_flash(
        'success',
        'La gestión fue registrada correctamente.'
    );

    ad_redirect('novedades/index.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ad_flash(
        'danger',
        'No fue posible registrar la gestión: '
        . $e->getMessage()
    );

    ad_redirect(
        'cuentas/movimiento.php?id=' . $accountId
    );
}

$correoAnterior = strtolower(
    trim(
        (string)(
            $account['correo']
            ?? ''
        )
    )
);

$correoNuevo = $correoAnterior;

$gestionPermiteCambioCorreo = in_array(
    $type,
    [
        'CAMBIO_ASIGNACION',
        'CAMBIO_LICENCIA',
    ],
    true
);

/*
 * El cambio de correo solamente puede realizarse
 * en cambio de asignación o cambio de licencia.
 */
if (
    $cambiarCorreo
    && !$gestionPermiteCambioCorreo
) {
    throw new RuntimeException(
        'El tipo de gestión seleccionado '
        . 'no permite cambiar la dirección de correo.'
    );
}

if ($cambiarCorreo) {
    if ($correoNuevoInput === '') {
        throw new RuntimeException(
            'Debe ingresar la nueva dirección de correo.'
        );
    }

    if (
        filter_var(
            $correoNuevoInput,
            FILTER_VALIDATE_EMAIL
        ) === false
    ) {
        throw new RuntimeException(
            'La nueva dirección de correo '
            . 'no tiene un formato válido.'
        );
    }

    $correoNuevo = $correoNuevoInput;

    if (
        strcasecmp(
            $correoAnterior,
            $correoNuevo
        ) === 0
    ) {
        throw new RuntimeException(
            'La nueva dirección de correo '
            . 'es igual a la dirección actual.'
        );
    }

    /*
     * Comprobar que otra cuenta activa no esté
     * utilizando la misma dirección.
     */
    $stmt = $pdo->prepare(
        'SELECT
            id,
            correo,
            estado

         FROM ad_cuentas

         WHERE id <> :cuenta_id

           AND LOWER(
                TRIM(correo)
               ) = LOWER(
                    TRIM(:correo)
               )

           AND estado IN (
               "ACTIVA",
               "GESTION_DE_BAJA"
           )

         LIMIT 1'
    );

    $stmt->execute([
        ':cuenta_id' => $accountId,
        ':correo' => $correoNuevo,
    ]);

    $cuentaConMismoCorreo = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($cuentaConMismoCorreo) {
        throw new RuntimeException(
            'La dirección '
            . $correoNuevo
            . ' ya está asociada a otra cuenta.'
        );
    }
}

if ($cambiarCorreo) {
    $stmt = $pdo->prepare(
        'UPDATE ad_cuentas

         SET correo = :correo

         WHERE id = :cuenta_id'
    );

    $stmt->execute([
        ':correo' => $correoNuevo,
        ':cuenta_id' => $accountId,
    ]);
}