<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

ad_require_permission('respaldos.editar');
ad_verify_csrf();

$pdo = db();

$backupId = (int)($_POST['id'] ?? 0);

if ($backupId <= 0) {
    ad_flash(
        'danger',
        'El backup indicado no es válido.'
    );

    ad_redirect('respaldos/index.php');
}

$stmt = $pdo->prepare(
    'SELECT *
     FROM ad_respaldos
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $backupId,
]);

$before = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$before) {
    ad_flash(
        'danger',
        'No se encontró el backup.'
    );

    ad_redirect('respaldos/index.php');
}

$data = [
    'servidor' => trim(
        (string)($_POST['servidor'] ?? '')
    ),

    'carpeta' => trim(
        (string)($_POST['carpeta'] ?? '')
    ),

    'subcarpeta_1' => trim(
        (string)($_POST['subcarpeta_1'] ?? '')
    ),

    'subcarpeta_2' => trim(
        (string)($_POST['subcarpeta_2'] ?? '')
    ),

    'archivo' => trim(
        (string)($_POST['archivo'] ?? '')
    ),

    'fecha_carga' => trim(
        (string)($_POST['fecha_carga'] ?? '')
    ),

    'tamano_pst' => trim(
        (string)($_POST['tamano_pst'] ?? '')
    ),

    'tamano_onedrive' => trim(
        (string)($_POST['tamano_onedrive'] ?? '')
    ),

];

$dateObject = DateTimeImmutable::createFromFormat(
    'Y-m-d',
    $data['fecha_carga']
);

if (
    !$dateObject
    || $dateObject->format('Y-m-d')
        !== $data['fecha_carga']
) {
    ad_flash(
        'danger',
        'La fecha indicada no es válida.'
    );

    ad_redirect(
        'respaldos/form.php?id=' . $backupId
    );
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'UPDATE ad_respaldos
         SET
            servidor = :servidor,
            carpeta = :carpeta,
            subcarpeta_1 = :subcarpeta_1,
            subcarpeta_2 = :subcarpeta_2,
            archivo = :archivo,
            fecha_carga = :fecha,
            tamano_pst = :tamano_pst,
            tamano_onedrive = :tamano_onedrive,
         WHERE id = :id'
    );

    $stmt->execute([
        ':servidor' => $data['servidor'] ?: null,
        ':carpeta' => $data['carpeta'] ?: null,
        ':subcarpeta_1' =>
            $data['subcarpeta_1'] ?: null,
        ':subcarpeta_2' =>
            $data['subcarpeta_2'] ?: null,
        ':subcarpeta_3' =>
            $data['subcarpeta_3'] ?: null,
        ':archivo' => $data['archivo'] ?: null,
        ':repositorio' =>
            $data['repositorio_cloud'] ?: null,
        ':fecha' => $data['fecha_carga'],
        ':tamano_pst' =>
            $data['tamano_pst'] ?: null,
        ':tamano_onedrive' =>
            $data['tamano_onedrive'] ?: null,
        ':estado' =>
            $data['estado'] ?: 'REGISTRADO',
        ':id' => $backupId,
    ]);

    try {
        AuditService::log(
            $pdo,
            'ad_respaldos',
            $backupId,
            'UPDATE',
            $before,
            $data
        );
    } catch (Throwable $auditError) {
        error_log(
            'Error de auditoría al editar backup: '
            . $auditError->getMessage()
        );
    }

    $pdo->commit();

    ad_flash(
        'success',
        'El backup fue actualizado correctamente.'
    );

    ad_redirect('respaldos/index.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    ad_flash(
        'danger',
        'No fue posible actualizar el backup: '
        . $e->getMessage()
    );

    ad_redirect(
        'respaldos/form.php?id=' . $backupId
    );
}