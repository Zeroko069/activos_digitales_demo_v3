<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ad_require_permission('cuentas.editar');
ad_verify_csrf();
$pdo = db();
/*
 * Variables disponibles durante todo el proceso,
 * incluyendo el bloque catch.
 */
$cuentaId = (int)(
    $_POST['id']
    ?? $_POST['cuenta_id']
    ?? 0
);

$correo = mb_strtolower(
    trim(
        (string)(
            $_POST['correo']
            ?? ''
        )
    ),
    'UTF-8'
);
$id = (int)($_POST['id'] ?? 0);
$email = ad_email_normalize((string)($_POST['correo'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ad_flash('danger','El correo no es válido.');
    ad_redirect('cuentas/form.php'.($id?'?id='.$id:''));
}
$domain = substr(strrchr($email,'@') ?: '',1);
$pdo->beginTransaction();
/*
 * Verificar si el correo ya está registrado antes
 * de intentar ejecutar el INSERT.
 */
/*
 * Solamente las cuentas activas reservan
 * la dirección de correo.
 *
 * Una cuenta eliminada o en gestión de baja
 * permite reutilizar la misma dirección.
 */
$duplicateStmt = $pdo->prepare(
    'SELECT id
     FROM ad_cuentas
     WHERE LOWER(TRIM(correo)) = :correo
       AND estado = "ACTIVA"
       AND id <> :cuenta_id
     LIMIT 1'
);

$duplicateStmt->execute([
    ':correo' => $correo,
    ':cuenta_id' => $cuentaId,
]);

$duplicateId = (int)$duplicateStmt->fetchColumn();

if (
    $duplicateId > 0
) {
    $_SESSION['cuenta_duplicada_correo'] = $correo;

    $redirectUrl = 'cuentas/form.php';

    if ($cuentaId > 0) {
        $redirectUrl .= '?id=' . $cuentaId;
    }

    ad_redirect($redirectUrl);
}
try {
    $stmt = $pdo->prepare('INSERT INTO ad_dominios (dominio) VALUES (:dominio) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
    $stmt->execute([':dominio'=>$domain]);
    $domainId = (int)$pdo->lastInsertId();
    $estado = strtoupper(
    trim((string)($_POST['estado'] ?? 'ACTIVA'))
);

$estadosPermitidos = [
    'ACTIVA',
    'GESTION_DE_BAJA',
];

if (!in_array($estado, $estadosPermitidos, true)) {
    throw new RuntimeException(
        'El estado seleccionado no es válido.'
    );
}
    $data = [
        ':correo'=>$email, ':correo_normalizado'=>$email, ':dominio'=>$domainId,
        ':tipo'=>$_POST['tipo'] ?? 'PERSONAL',
        ':estado'=>$estado,
        ':razon'=>($_POST['razon_social_id'] ?? '')!==''?(int)$_POST['razon_social_id']:null,
        ':pais'=>$_POST['pais'] ?? 'CO', ':ciudad'=>trim((string)($_POST['ciudad'] ?? '')) ?: null,
        ':responsable'=>null,
        ':observaciones'=>trim((string)($_POST['observaciones'] ?? '')) ?: null,
        ':fecha'=>($_POST['fecha_creacion'] ?? '') ?: null,
    ];
    if ($id > 0) {
        $beforeStmt=$pdo->prepare('SELECT * FROM ad_cuentas WHERE id=:id');$beforeStmt->execute([':id'=>$id]);$before=$beforeStmt->fetch(PDO::FETCH_ASSOC)?:null;
        $sql='UPDATE ad_cuentas SET correo=:correo,correo_normalizado=:correo_normalizado,dominio_id=:dominio,tipo=:tipo,estado=:estado,razon_social_id=:razon,pais=:pais,ciudad=:ciudad,responsable_funcional=:responsable,observaciones=:observaciones,fecha_creacion=:fecha WHERE id=:id';
        $data[':id']=$id;$stmt=$pdo->prepare($sql);$stmt->execute($data);AuditService::log($pdo,'ad_cuentas',$id,'ACTUALIZAR',$before,$data);
    } else {
        $sql='INSERT INTO ad_cuentas (correo,correo_normalizado,dominio_id,tipo,estado,razon_social_id,pais,ciudad,responsable_funcional,observaciones,fecha_creacion) VALUES (:correo,:correo_normalizado,:dominio,:tipo,:estado,:razon,:pais,:ciudad,:responsable,:observaciones,:fecha)';
        $stmt=$pdo->prepare($sql);$stmt->execute($data);$id=(int)$pdo->lastInsertId();AuditService::log($pdo,'ad_cuentas',$id,'CREAR',null,$data);
    }
    $personId = (int)($_POST['persona_id'] ?? 0);

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

$assignmentDate =
    ($_POST['fecha_creacion'] ?? '')
    ?: date('Y-m-d');

/*
 * Validar que no se estén asignando dos
 * suscripciones del mismo tipo de licencia.
 */
if ($subscriptionIds !== []) {
    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($subscriptionIds),
            '?'
        )
    );

    $stmt = $pdo->prepare(
        '
        SELECT
            s.id,
            s.plan_id,
            p.nombre AS plan,
            s.valor_unitario,
            s.moneda,
            s.estado
        FROM ad_suscripciones s
        INNER JOIN ad_planes p
            ON p.id = s.plan_id
        WHERE s.id IN (' . $placeholders . ')
          AND s.estado = "VIGENTE"
        ORDER BY s.id
        '
    );

    $stmt->execute($subscriptionIds);

    $validSubscriptions =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

    $plansFound = [];
    $validatedIds = [];

    foreach (
        $validSubscriptions as $subscription
    ) {
        $planId = (int)$subscription['plan_id'];

        if (isset($plansFound[$planId])) {
            throw new RuntimeException(
                'No se pueden asignar dos licencias '
                . 'del mismo tipo: '
                . $subscription['plan']
            );
        }

        $plansFound[$planId] = true;

        $validatedIds[] =
            (int)$subscription['id'];
    }

    if (
        count($validatedIds)
        !== count($subscriptionIds)
    ) {
        throw new RuntimeException(
            'Una de las licencias seleccionadas '
            . 'no existe o no está vigente.'
        );
    }

    $subscriptionIds = $validatedIds;
}

/*
 * Cerrar la asignación anterior del funcionario.
 */
if ($personId > 0) {
    $stmt = $pdo->prepare(
        'UPDATE ad_asignaciones_cuenta
         SET
            estado="CERRADA",
            fecha_fin=CURDATE(),
            motivo="CAMBIO_DESDE_FORMULARIO"
         WHERE cuenta_id=:cuenta
           AND estado="ACTIVA"
           AND (
                persona_id IS NULL
                OR persona_id<>:persona
           )'
    );

    $stmt->execute([
        ':cuenta' => $id,
        ':persona' => $personId,
    ]);

    $stmt = $pdo->prepare(
        'SELECT id
         FROM ad_asignaciones_cuenta
         WHERE cuenta_id=:cuenta
           AND persona_id=:persona
           AND estado="ACTIVA"
         LIMIT 1'
    );

    $stmt->execute([
        ':cuenta' => $id,
        ':persona' => $personId,
    ]);

    if (!$stmt->fetchColumn()) {
        $stmt = $pdo->prepare(
            'INSERT INTO ad_asignaciones_cuenta
             (
                cuenta_id,
                persona_id,
                fecha_inicio,
                estado,
                motivo,
                created_by
             )
             VALUES
             (
                :cuenta,
                :persona,
                :fecha,
                "ACTIVA",
                "ASIGNACION_MANUAL",
                :usuario
             )'
        );

        $stmt->execute([
            ':cuenta' => $id,
            ':persona' => $personId,
            ':fecha' => $assignmentDate,
            ':usuario' => ad_current_user_id(),
        ]);
    }
}

/*
 * Cerrar las licencias que se desmarcaron.
 */
if ($subscriptionIds === []) {
    $stmt = $pdo->prepare(
        'UPDATE ad_asignaciones_licencia
         SET
            estado="CERRADA",
            fecha_fin=CURDATE(),
            motivo="RETIRO_DESDE_FORMULARIO"
         WHERE cuenta_id=:cuenta
           AND estado="ACTIVA"'
    );

    $stmt->execute([
        ':cuenta' => $id
    ]);
} else {
    $placeholders = [];
    $parameters = [
        ':cuenta' => $id
    ];

    foreach (
        $subscriptionIds as $index => $subscriptionId
    ) {
        $key = ':sus' . $index;

        $placeholders[] = $key;
        $parameters[$key] = $subscriptionId;
    }

    $stmt = $pdo->prepare(
        'UPDATE ad_asignaciones_licencia
         SET
            estado="CERRADA",
            fecha_fin=CURDATE(),
            motivo="CAMBIO_DESDE_FORMULARIO"
         WHERE cuenta_id=:cuenta
           AND estado="ACTIVA"
           AND suscripcion_id NOT IN ('
            . implode(',', $placeholders)
            . ')'
    );

    $stmt->execute($parameters);
}

/*
 * Crear o actualizar las licencias seleccionadas.
 */
foreach ($subscriptionIds as $subscriptionId) {
    $stmt = $pdo->prepare(
        'SELECT
            id,
            valor_unitario,
            moneda,
            pais
         FROM ad_suscripciones
         WHERE id=:id
           AND estado="VIGENTE"
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $subscriptionId
    ]);

    $subscription =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new RuntimeException(
            'Una licencia seleccionada no está vigente.'
        );
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM ad_asignaciones_licencia
         WHERE cuenta_id=:cuenta
           AND suscripcion_id=:suscripcion
           AND estado="ACTIVA"
         LIMIT 1'
    );

    $stmt->execute([
        ':cuenta' => $id,
        ':suscripcion' => $subscriptionId,
    ]);

    $licenseAssignmentId =
        $stmt->fetchColumn();

    if ($licenseAssignmentId) {
        $stmt = $pdo->prepare(
            'UPDATE ad_asignaciones_licencia
             SET
                valor_unitario_asignado=:valor,
                moneda=:moneda
             WHERE id=:id'
        );

        $stmt->execute([
            ':valor' =>
                $subscription['valor_unitario'],
            ':moneda' =>
                $subscription['moneda'],
            ':id' =>
                (int)$licenseAssignmentId,
        ]);
    } else {
        $stmt = $pdo->prepare(
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
                "ASIGNACION_MANUAL",
                :usuario
             )'
        );

        $stmt->execute([
            ':cuenta' => $id,
            ':suscripcion' => $subscriptionId,
            ':fecha' => $assignmentDate,
            ':valor' =>
                $subscription['valor_unitario'],
            ':moneda' =>
                $subscription['moneda'],
            ':usuario' =>
                ad_current_user_id(),
        ]);
    }
}
    $pdo->commit();ad_flash('success','Cuenta guardada correctamente.');ad_redirect('cuentas/index.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    /*
     * Protección adicional en caso de que dos usuarios
     * intenten registrar el mismo correo simultáneamente.
     */
    if (
        $e instanceof PDOException
        && $e->getCode() === '23000'
        && (
            stripos(
                $e->getMessage(),
                'Duplicate entry'
            ) !== false
            || stripos(
                $e->getMessage(),
                'uq_ad_cuenta_correo_activo'
            ) !== false
        )
    ) {
        $_SESSION['cuenta_duplicada_correo'] =
            $correo !== ''
                ? $correo
                : 'El correo indicado';

        $redirectUrl = 'cuentas/form.php';

        if ($cuentaId > 0) {
            $redirectUrl .= '?id=' . $cuentaId;
        }

        ad_redirect($redirectUrl);
    }

    ad_flash(
        'danger',
        'No fue posible guardar la cuenta: '
        . $e->getMessage()
    );

    $redirectUrl = 'cuentas/form.php';

    if ($cuentaId > 0) {
        $redirectUrl .= '?id=' . $cuentaId;
    }

    ad_redirect($redirectUrl);
}