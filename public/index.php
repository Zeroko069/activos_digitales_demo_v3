<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
ad_require_permission('dashboard.ver');
$pdo = db();

$kpis = [
    'cuentas_activas' => (int)$pdo->query("SELECT COUNT(*) FROM ad_cuentas WHERE estado='ACTIVA'")->fetchColumn(),
    'licencias_activas' => (int)$pdo->query("SELECT COUNT(*) FROM ad_asignaciones_licencia WHERE estado='ACTIVA'")->fetchColumn(),
    'facturas_pendientes' => (int)$pdo->query("SELECT COUNT(*) FROM ad_facturas WHERE estado IN ('RECIBIDA','EN_VALIDACION','CON_NOVEDAD','PENDIENTE_APROBACION')")->fetchColumn(),
    'cuentas_retirados' => (int)$pdo->query("SELECT COUNT(DISTINCT c.id) FROM ad_cuentas c JOIN ad_asignaciones_cuenta ac ON ac.cuenta_id=c.id AND ac.estado='ACTIVA' JOIN ad_personas p ON p.id=ac.persona_id WHERE c.estado='ACTIVA' AND p.estado_laboral='RETIRADO'")->fetchColumn(),
];

$invoiceSummary = $pdo->query(
    "SELECT moneda, SUM(total_pagar) total FROM ad_facturas WHERE estado NOT IN ('ANULADA','RECHAZADA') GROUP BY moneda"
)->fetchAll(PDO::FETCH_ASSOC);

$alerts = $pdo->query(
    "SELECT 'FACTURA' tipo, CONCAT(numero_factura, ' vence el ', DATE_FORMAT(fecha_vencimiento,'%Y-%m-%d')) mensaje,
            DATEDIFF(fecha_vencimiento,CURDATE()) dias
     FROM ad_facturas
     WHERE fecha_vencimiento IS NOT NULL AND estado NOT IN ('PAGADA','ANULADA','RECHAZADA')
       AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     UNION ALL
     SELECT 'DOMINIO', CONCAT(dominio, ' vence el ', DATE_FORMAT(fecha_vencimiento,'%Y-%m-%d')),
            DATEDIFF(fecha_vencimiento,CURDATE())
     FROM ad_dominios
     WHERE fecha_vencimiento IS NOT NULL AND estado='ACTIVO'
       AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
     ORDER BY dias ASC LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);

$title = 'Panel principal';
require dirname(__DIR__) . '/views/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><h1 class="h3 mb-0">Panel principal</h1><small class="text-muted">Correos, licencias y facturación</small></div>
  <?php if (ad_can('facturas.crear')): ?><a class="btn btn-primary" href="<?= ad_e(ad_url('facturas/create.php')) ?>">Registrar factura</a><?php endif; ?>
</div>
<div class="row g-3 mb-4">
  <?php foreach ([
      ['Cuentas activas',$kpis['cuentas_activas']],
      ['Licencias asignadas',$kpis['licencias_activas']],
      ['Facturas pendientes',$kpis['facturas_pendientes']],
      ['Cuentas de retirados',$kpis['cuentas_retirados']],
  ] as [$label,$value]): ?>
  <div class="col-sm-6 col-xl-3"><div class="card card-kpi"><div class="card-body"><div class="text-muted"><?= ad_e($label) ?></div><div class="display-6 fw-semibold"><?= number_format((int)$value,0,',','.') ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card card-kpi"><div class="card-header fw-semibold">Facturación acumulada</div><div class="card-body">
      <?php if (!$invoiceSummary): ?><div class="text-muted">Sin facturas registradas.</div><?php endif; ?>
      <?php foreach ($invoiceSummary as $row): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= ad_e($row['moneda']) ?></span><strong><?= ad_e(ad_money($row['total'],$row['moneda'])) ?></strong></div><?php endforeach; ?>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="card card-kpi"><div class="card-header fw-semibold">Alertas</div><div class="card-body p-0">
      <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Tipo</th><th>Detalle</th><th>Días</th></tr></thead><tbody>
      <?php if (!$alerts): ?><tr><td colspan="3" class="text-muted p-3">No hay alertas próximas.</td></tr><?php endif; ?>
      <?php foreach ($alerts as $row): ?><tr><td><?= ad_e($row['tipo']) ?></td><td><?= ad_e($row['mensaje']) ?></td><td><?= (int)$row['dias'] ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>
  </div>
</div>
<?php require dirname(__DIR__) . '/views/footer.php'; ?>
