<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('cuentas.editar');
ad_verify_csrf();

$pdo = db();

$cuentaId = (int)(
    $_POST['cuenta_id'] ?? 0
);

$fechaBaja = trim(
    (string)($_POST['fecha_baja'] ?? '')
);

$observacion = trim(
    (string)($_POST['observacion_baja'] ?? '')
);

if ($cuentaId <= 0) {
    ad_flash(
        'danger',
        'La cuenta indicada no es válida.'
    );

    ad_redirect('cuentas/index.php');
}

/*
 * Validar la fecha.
 */
$fechaObjeto = DateTimeImmutable::createFromFormat(
    'Y-m-d',
    $fechaBaja
);

if (
    !$fechaObjeto
    || $fechaObjeto->format('Y-m-d') !== $fechaBaja
) {
    ad_flash(
        'danger',
        'La fecha de baja no es válida.'
    );

    ad_redirect(
        'cuentas/baja.php?id=' . $cuentaId
    );
}

if ($observacion === '') {
    ad_flash(
        'danger',
        'Debe registrar una observación.'
    );

    ad_redirect(
        'cuentas/baja.php?id=' . $cuentaId
    );
}

/*
 * Verificar que la cuenta exista.
 */
$stmt = $pdo->prepare(
    'SELECT id, correo, estado

     FROM ad_cuentas

     WHERE id = :id

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

try {
    /*
     * Solo cambia el estado, la fecha y la observación.
     *
     * No crea novedad.
     * No crea backup.
     * No modifica asignaciones.
     * No modifica licencias.
     * No registra auditoría por ahora.
     */
    $stmt = $pdo->prepare(
        'UPDATE ad_cuentas

         SET
            estado = "GESTION_DE_BAJA",
            fecha_eliminacion = :fecha,
            observacion_baja = :observacion

         WHERE id = :id'
    );

    $stmt->execute([
        ':fecha' => $fechaBaja,
        ':observacion' => $observacion,
        ':id' => $cuentaId,
    ]);

    ad_flash(
        'success',
        'La cuenta pasó correctamente a gestión de baja.'
    );

    ad_redirect(
        'cuentas/index.php?estado=GESTION_DE_BAJA'
    );
} catch (Throwable $e) {
    ad_flash(
        'danger',
        'No fue posible gestionar la baja: '
        . $e->getMessage()
    );

    ad_redirect(
        'cuentas/baja.php?id=' . $cuentaId
    );
}