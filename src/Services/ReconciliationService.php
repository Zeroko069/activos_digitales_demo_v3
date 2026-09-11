<?php
declare(strict_types=1);

final class ReconciliationService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function reconcileInvoice(int $invoiceId): array
    {
        $this->pdo->beginTransaction();
        try {
            $details = $this->fetchDetails($invoiceId);
            if ($details === []) {
                throw new RuntimeException('La factura no tiene detalles para conciliar.');
            }

            $lineResults = $this->reconcileTariffLines($details);
            $planResults = $this->reconcilePlanQuantities($invoiceId, $details);

            $hasIssue = false;
            foreach (array_merge($lineResults, $planResults) as $row) {
                if (($row['estado'] ?? 'POR_JUSTIFICAR') !== 'OK') {
                    $hasIssue = true;
                    break;
                }
            }
            $invoiceStatus = $hasIssue ? 'CON_NOVEDAD' : 'EN_VALIDACION';
            $stmt = $this->pdo->prepare('UPDATE ad_facturas SET estado=:estado WHERE id=:id');
            $stmt->execute([':estado' => $invoiceStatus, ':id' => $invoiceId]);

            $auditData = ['por_linea' => $lineResults, 'por_plan' => $planResults];
            AuditService::log($this->pdo, 'ad_facturas', $invoiceId, 'CONCILIAR', null, $auditData);
            $this->pdo->commit();
            return $auditData;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function reconcileTariffLines(array $details): array
    {
        $results = [];
        foreach ($details as $detail) {
            $subscriptionId = $detail['suscripcion_id'] ? (int)$detail['suscripcion_id'] : null;
            $contracted = $subscriptionId ? $this->subscriptionQuantity($subscriptionId) : null;
            $contractRate = $subscriptionId ? $this->subscriptionRate($subscriptionId) : null;
            $rate = (float)$detail['valor_unitario'];
            $rateDifference = $contractRate === null ? null : $rate - $contractRate;

            if ($contractRate === null) {
                $status = 'POR_JUSTIFICAR';
            } elseif (abs((float)$rateDifference) < 0.0001) {
                $status = 'OK';
            } else {
                $status = 'CON_NOVEDAD';
            }

            $sql = 'INSERT INTO ad_conciliaciones
                (factura_detalle_id, cantidad_facturada, cantidad_contratada, cantidad_asignada,
                 diferencia_facturado_asignado, tarifa_contratada, tarifa_facturada,
                 diferencia_tarifa, estado, calculado_at)
                VALUES
                (:detalle, :facturada, :contratada, NULL, NULL,
                 :tarifa_contratada, :tarifa_facturada, :dif_tarifa, :estado, NOW())
                ON DUPLICATE KEY UPDATE
                 cantidad_facturada=VALUES(cantidad_facturada),
                 cantidad_contratada=VALUES(cantidad_contratada),
                 cantidad_asignada=NULL,
                 diferencia_facturado_asignado=NULL,
                 tarifa_contratada=VALUES(tarifa_contratada),
                 tarifa_facturada=VALUES(tarifa_facturada),
                 diferencia_tarifa=VALUES(diferencia_tarifa),
                 estado=IF(ad_conciliaciones.estado="APROBADA_EXCEPCION", ad_conciliaciones.estado, VALUES(estado)),
                 calculado_at=NOW()';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':detalle' => (int)$detail['id'],
                ':facturada' => (float)$detail['cantidad'],
                ':contratada' => $contracted,
                ':tarifa_contratada' => $contractRate,
                ':tarifa_facturada' => $rate,
                ':dif_tarifa' => $rateDifference,
                ':estado' => $status,
            ]);

            $results[] = [
                'detalle_id' => (int)$detail['id'],
                'plan' => $detail['plan_nombre'] ?? $detail['descripcion'],
                'cantidad_facturada' => (float)$detail['cantidad'],
                'cantidad_contratada_linea' => $contracted,
                'tarifa_contratada' => $contractRate,
                'tarifa_facturada' => $rate,
                'diferencia_tarifa' => $rateDifference,
                'estado' => $status,
            ];
        }
        return $results;
    }

    private function reconcilePlanQuantities(int $invoiceId, array $details): array
    {
        $groups = [];
        foreach ($details as $detail) {
            if (empty($detail['plan_id'])) {
                continue;
            }
            $planId = (int)$detail['plan_id'];
            if (!isset($groups[$planId])) {
                $groups[$planId] = [
                    'plan_id' => $planId,
                    'plan' => $detail['plan_nombre'] ?? $detail['descripcion'],
                    'facturada' => 0.0,
                    'subscription_ids' => [],
                ];
            }
            $groups[$planId]['facturada'] += (float)$detail['cantidad'];
            if (!empty($detail['suscripcion_id'])) {
                $groups[$planId]['subscription_ids'][(int)$detail['suscripcion_id']] = true;
            }
        }

        $results = [];
        foreach ($groups as $group) {
            $planId = $group['plan_id'];
            $assigned = $this->assignedQuantity($planId);
            $subscriptionIds = array_keys($group['subscription_ids']);
            $contracted = $subscriptionIds !== []
                ? $this->contractedQuantityForSubscriptions($subscriptionIds)
                : $this->contractedQuantityForPlan($planId);
            $factured = (float)$group['facturada'];
            $difference = $factured - $assigned;
            $status = abs($difference) < 0.0001 ? 'OK' : 'POR_JUSTIFICAR';

            $stmt = $this->pdo->prepare(
                'INSERT INTO ad_conciliaciones_plan
                 (factura_id, plan_id, cantidad_facturada, cantidad_contratada,
                  cantidad_asignada, diferencia_facturado_asignado, estado, calculado_at)
                 VALUES (:factura, :plan, :facturada, :contratada, :asignada, :diferencia, :estado, NOW())
                 ON DUPLICATE KEY UPDATE
                  cantidad_facturada=VALUES(cantidad_facturada),
                  cantidad_contratada=VALUES(cantidad_contratada),
                  cantidad_asignada=VALUES(cantidad_asignada),
                  diferencia_facturado_asignado=VALUES(diferencia_facturado_asignado),
                  estado=IF(ad_conciliaciones_plan.estado="APROBADA_EXCEPCION", ad_conciliaciones_plan.estado, VALUES(estado)),
                  calculado_at=NOW()'
            );
            $stmt->execute([
                ':factura' => $invoiceId,
                ':plan' => $planId,
                ':facturada' => $factured,
                ':contratada' => $contracted,
                ':asignada' => $assigned,
                ':diferencia' => $difference,
                ':estado' => $status,
            ]);

            $results[] = [
                'plan_id' => $planId,
                'plan' => $group['plan'],
                'facturada' => $factured,
                'contratada' => $contracted,
                'asignada' => $assigned,
                'diferencia' => $difference,
                'estado' => $status,
            ];
        }
        return $results;
    }

    private function fetchDetails(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, p.nombre AS plan_nombre
             FROM ad_factura_detalles d
             LEFT JOIN ad_planes p ON p.id=d.plan_id
             WHERE d.factura_id=:factura_id
             ORDER BY d.id'
        );
        $stmt->execute([':factura_id' => $invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function assignedQuantity(int $planId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM ad_asignaciones_licencia al
             INNER JOIN ad_suscripciones s ON s.id=al.suscripcion_id
             WHERE s.plan_id=:plan_id AND al.estado="ACTIVA"'
        );
        $stmt->execute([':plan_id' => $planId]);
        return (float)$stmt->fetchColumn();
    }

    private function contractedQuantityForSubscriptions(array $subscriptionIds): ?float
    {
        if ($subscriptionIds === []) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($subscriptionIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT SUM(cantidad_contratada) FROM ad_suscripciones
             WHERE id IN ($placeholders) AND cantidad_contratada IS NOT NULL"
        );
        $stmt->execute($subscriptionIds);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (float)$value;
    }

    private function contractedQuantityForPlan(int $planId): ?float
    {
        $stmt = $this->pdo->prepare(
            'SELECT SUM(cantidad_contratada)
             FROM ad_suscripciones
             WHERE plan_id=:plan_id AND estado="VIGENTE"
               AND origen<>"MIGRACION_EXCEL" AND cantidad_contratada IS NOT NULL'
        );
        $stmt->execute([':plan_id' => $planId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (float)$value;
    }

    private function subscriptionQuantity(int $subscriptionId): ?float
    {
        $stmt = $this->pdo->prepare('SELECT cantidad_contratada FROM ad_suscripciones WHERE id=:id');
        $stmt->execute([':id' => $subscriptionId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (float)$value;
    }

    private function subscriptionRate(int $subscriptionId): ?float
    {
        $stmt = $this->pdo->prepare('SELECT valor_unitario FROM ad_suscripciones WHERE id=:id');
        $stmt->execute([':id' => $subscriptionId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (float)$value;
    }
}
