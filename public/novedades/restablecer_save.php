<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.editar');
ad_verify_csrf();

$pdo = db();

$novedadId = (int)(
    $_POST['novedad_id'] ?? 0
);

if ($novedadId <= 0) {
    ad_flash(
        'danger',
        'La novedad indicada no es válida.'
    );

    ad_redirect('novedades/index.php');
}

/*
 * Obtener la novedad y la cuenta asociada.
 */
$stmt = $pdo->prepare(
    'SELECT
        n.id,
        n.cuenta_id,
        n.tipo,
        n.fecha_novedad,
        n.datos_origen,

        c.correo,
        c.estado AS cuenta_estado

     FROM ad_novedades n

     INNER JOIN ad_cuentas c
        ON c.id = n.cuenta_id

     WHERE n.id = :id

     LIMIT 1'
);

$stmt->execute([
    ':id' => $novedadId,
]);

$novedad = $stmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$novedad) {
    ad_flash(
        'danger',
        'No se encontró la novedad o la cuenta asociada.'
    );

    ad_redirect('novedades/index.php');
}

$tipo = strtoupper(
    trim(
        (string)$novedad['tipo']
    )
);

$tiposPermitidos = [
    'ELIMINADO_CON_PST',
    'ELIMINADO_CPANEL',
];

if (
    !in_array(
        $tipo,
        $tiposPermitidos,
        true
    )
) {
    ad_flash(
        'danger',
        'La novedad seleccionada no corresponde a una eliminación.'
    );

    ad_redirect('novedades/index.php');
}

$cuentaId = (int)$novedad['cuenta_id'];

$correo = trim(
    (string)$novedad['correo']
);

$estadoCuenta = strtoupper(
    trim(
        (string)$novedad['cuenta_estado']
    )
);

if ($estadoCuenta !== 'ELIMINADA') {
    ad_flash(
        'warning',
        'La cuenta no se encuentra en estado Eliminada.'
    );

    ad_redirect('novedades/index.php');
}

/*
 * Verificar que el mismo correo no haya sido utilizado
 * posteriormente por otra cuenta activa.
 */
$stmt = $pdo->prepare(
    'SELECT id

     FROM ad_cuentas

     WHERE LOWER(TRIM(correo))
        = LOWER(TRIM(:correo))

       AND estado = "ACTIVA"
       AND id <> :cuenta_id

     LIMIT 1'
);

$stmt->execute([
    ':correo' => $correo,
    ':cuenta_id' => $cuentaId,
]);

$cuentaActivaDuplicada =
    (int)$stmt->fetchColumn();

if ($cuentaActivaDuplicada > 0) {
    ad_flash(
        'danger',
        'No es posible restaurar la cuenta porque '
        . 'actualmente existe otra cuenta activa con el correo '
        . $correo
        . '.'
    );

    ad_redirect('novedades/index.php');
}

$pdo->beginTransaction();

try {
    /*
     * Restaurar el estado de la cuenta.
     */
    $stmt = $pdo->prepare(
        'UPDATE ad_cuentas

         SET
            estado = "ACTIVA",
            fecha_eliminacion = NULL,
            observacion_baja = NULL

         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $cuentaId,
    ]);

    /*
     * Recuperar la última asignación del funcionario
     * cerrada por la eliminación.
     */
    $stmt = $pdo->prepare(
        'SELECT id

         FROM ad_asignaciones_cuenta

         WHERE cuenta_id = :cuenta
           AND estado = "CERRADA"
           AND motivo = :motivo

         ORDER BY
            fecha_fin DESC,
            id DESC

         LIMIT 1'
    );

    $stmt->execute([
        ':cuenta' => $cuentaId,
        ':motivo' => $tipo,
    ]);

    $asignacionId =
        (int)$stmt->fetchColumn();

    if ($asignacionId > 0) {
        $stmt = $pdo->prepare(
            'UPDATE ad_asignaciones_cuenta

             SET
                estado = "ACTIVA",
                fecha_fin = NULL,
                motivo = "RESTAURADA"

             WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $asignacionId,
        ]);
    }

    /*
     * Restaurar las licencias cerradas durante
     * la eliminación seleccionada.
     */
    $stmt = $pdo->prepare(
        'UPDATE ad_asignaciones_licencia

         SET
            estado = "ACTIVA",
            fecha_fin = NULL,
            motivo = "RESTAURADA"

         WHERE cuenta_id = :cuenta
           AND estado = "CERRADA"
           AND motivo = :motivo'
    );

    $stmt->execute([
        ':cuenta' => $cuentaId,
        ':motivo' => $tipo,
    ]);

    /*
     * Eliminar únicamente la novedad desde la cual
     * se realizó la restauración.
     *
     * No crea una nueva novedad.
     * No crea ni elimina registros de backup.
     */
    $stmt = $pdo->prepare(
        'DELETE FROM ad_novedades
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $novedadId,
    ]);

    $pdo->commit();

    ad_flash(
        'success',
        'La cuenta fue restaurada correctamente '
        . 'y la novedad de eliminación fue retirada.'
    );

    ad_redirect(
        'cuentas/index.php?estado=ACTIVA&q='
        . urlencode($correo)
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ad_flash(
        'danger',
        'No fue posible restaurar la cuenta: '
        . $e->getMessage()
    );

    ad_redirect('novedades/index.php');
}