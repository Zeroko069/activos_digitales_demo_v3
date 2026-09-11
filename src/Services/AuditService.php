<?php
declare(strict_types=1);

final class AuditService
{
    public static function log(
        PDO $pdo,
        string $entity,
        int $entityId,
        string $action,
        ?array $before = null,
        ?array $after = null
    ): void {
        $stmt = $pdo->prepare(
            'INSERT INTO ad_auditoria
             (entidad, entidad_id, accion, datos_anteriores, datos_nuevos, usuario_id, ip)
             VALUES (:entidad, :entidad_id, :accion, :antes, :despues, :usuario_id, :ip)'
        );
        $stmt->execute([
            ':entidad' => $entity,
            ':entidad_id' => $entityId,
            ':accion' => $action,
            ':antes' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            ':despues' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            ':usuario_id' => ad_current_user_id(),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
