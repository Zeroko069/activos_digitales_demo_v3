<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('novedades.editar');
ad_verify_csrf();

$pdo = db();

$noveltyId = (int)($_POST['id'] ?? 0);

$date = trim(
    (string)($_POST['fecha_novedad'] ?? '')
);

$observations = trim(
    (string)($_POST['observaciones'] ?? '')
);

if ($noveltyId <= 0) {
    ad_flash(
        'danger',
        'La novedad indicada no es válida.'
    );

    ad_redirect('novedades/index.php');
}

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
        'novedades/form.php?id=' . $noveltyId
    );
}

$stmt = $pdo->prepare(
    'SELECT *
     FROM ad_novedades
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $noveltyId,
]);

$before = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$before) {
    ad_flash(
        'danger',
        'No se encontró la novedad.'
    );

    ad_redirect('novedades/index.php');
}

$originData = json_decode(
    (string)($before['datos_origen'] ?? ''),
    true
);

$originData = is_array($originData)
    ? $originData
    : [];

$originData['observaciones'] = $observations;

$originData['edicion_manual'] = [
    'fecha' => date('Y-m-d H:i:s'),
    'usuario_id' => ad_current_user_id(),
];

$typeLabel = ucwords(
    strtolower(
        str_replace(
            '_',
            ' ',
            (string)$before['tipo']
        )
    )
);

$description = $typeLabel;

if ($observations !== '') {
    $description .= ': ' . $observations;
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'UPDATE ad_novedades
         SET
            fecha_novedad = :fecha,
            descripcion = :descripcion,
            datos_origen = :datos
         WHERE id = :id'
    );

    $stmt->execute([
        ':fecha' => $date,
        ':descripcion' => $description,
        ':datos' => json_encode(
            $originData,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
        ),
        ':id' => $noveltyId,
    ]);

    try {
        AuditService::log(
            $pdo,
            'ad_novedades',
            $noveltyId,
            'UPDATE',
            $before,
            [
                'fecha_novedad' => $date,
                'descripcion' => $description,
                'datos_origen' => $originData,
            ]
        );
    } catch (Throwable $auditError) {
        error_log(
            'Error de auditoría al editar novedad: '
            . $auditError->getMessage()
        );
    }

    $pdo->commit();

    ad_flash(
        'success',
        'La novedad fue actualizada correctamente.'
    );

    ad_redirect('novedades/index.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ad_flash(
        'danger',
        'No fue posible actualizar la novedad: '
        . $e->getMessage()
    );

    ad_redirect(
        'novedades/form.php?id=' . $noveltyId
    );
}