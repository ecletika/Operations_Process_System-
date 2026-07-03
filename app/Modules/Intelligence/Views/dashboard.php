<?php
/**
 * 📈 Inteligência Operacional™ - Dashboard Executivo.
 * Formata minutos em algo legível (ex.: "2h 15m", "3d 4h").
 */
$fmtMin = static function (?int $minutes): string {
    if ($minutes === null) {
        return '—';
    }
    if ($minutes < 60) {
        return $minutes . 'm';
    }
    if ($minutes < 1440) {
        return intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
    }
    $days = intdiv($minutes, 1440);
    $hours = intdiv($minutes % 1440, 60);
    return $days . 'd ' . $hours . 'h';
};

$trendMax = 1;
foreach ($trend as $day) {
    $trendMax = max($trendMax, $day['created'], $day['resolved']);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Inteligência Operacional™</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>📈 Inteligência Operacional™</h1>
      <p style="color:#6b7280">Dashboard Executivo · KPIs, tendências, gargalos e padrões da operação.</p>

      <form method="GET" action="/intelligence" style="display:flex;gap:8px;align-items:flex-end;margin-bottom:20px">
        <div class="ops-form-row" style="margin:0"><label for="from">De</label>
          <input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="ops-form-row" style="margin:0"><label for="to">Até</label>
          <input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
        <a href="/intelligence" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar</a>
      </form>

      <!-- KPIs executivos -->
      <div class="ops-kpis">
        <div class="ops-kpi"><div class="value">📋 <?= (int) $kpis['total'] ?></div><div class="label">Processos no período</div></div>
        <div class="ops-kpi"><div class="value">🟠 <?= (int) $kpis['abertos'] ?></div><div class="label">Em Aberto</div></div>
        <div class="ops-kpi"><div class="value">✅ <?= (int) $kpis['concluidos'] ?></div><div class="label">Concluídos</div></div>
        <div class="ops-kpi"><div class="value">🔴 <?= (int) $kpis['criticos_abertos'] ?></div><div class="label">Críticos em Aberto</div></div>
        <div class="ops-kpi"><div class="value">⏱️ <?= e($fmtMin($kpis['tempo_medio_min'])) ?></div><div class="label">Tempo Médio de Resolução</div></div>
        <div class="ops-kpi"><div class="value"><?= $kpis['sla_pct'] !== null ? '🎯 ' . (int) $kpis['sla_pct'] . '%' : '🎯 —' ?></div><div class="label">Cumprimento de SLA</div></div>
        <div class="ops-kpi"><div class="value">🔁 <?= (int) $kpis['reabertos'] ?></div><div class="label">Reaberturas</div></div>
      </div>

      <!-- Tendências: criados vs concluídos (14 dias) -->
      <h2 style="margin-top:32px">📊 Tendência (últimos 14 dias)</h2>
      <div class="ops-panel" style="max-width:none">
        <div style="display:flex;align-items:flex-end;gap:6px;height:160px;padding-top:8px">
          <?php foreach ($trend as $day => $d): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;height:100%;justify-content:flex-end">
              <div style="display:flex;align-items:flex-end;gap:2px;height:130px">
                <div title="Criados: <?= (int) $d['created'] ?>" style="width:9px;background:#2563eb;border-radius:2px 2px 0 0;height:<?= (int) round(($d['created'] / $trendMax) * 130) ?>px"></div>
                <div title="Concluídos: <?= (int) $d['resolved'] ?>" style="width:9px;background:#16a34a;border-radius:2px 2px 0 0;height:<?= (int) round(($d['resolved'] / $trendMax) * 130) ?>px"></div>
              </div>
              <div style="font-size:9px;color:#9ca3af;white-space:nowrap;transform:rotate(-45deg);transform-origin:center;margin-top:6px"><?= e(date('d/m', strtotime($day))) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:14px;font-size:12px;color:#6b7280">
          <span style="display:inline-block;width:10px;height:10px;background:#2563eb;border-radius:2px"></span> Criados
          &nbsp;&nbsp;
          <span style="display:inline-block;width:10px;height:10px;background:#16a34a;border-radius:2px"></span> Concluídos
        </div>
      </div>

      <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:32px">
        <!-- Gargalos Operacionais -->
        <div style="flex:1;min-width:340px">
          <h2>🚧 Gargalos Operacionais</h2>
          <p style="color:#6b7280;font-size:13px;margin-top:-4px">Processos parados por estado e há quanto tempo (média).</p>
          <table class="ops-table">
            <thead><tr><th>Estado</th><th>Nº</th><th>Parado (média)</th></tr></thead>
            <tbody>
              <?php if (empty($bottlenecks)): ?>
                <tr><td colspan="3" style="text-align:center;color:#6b7280">Sem processos em aberto. 🎉</td></tr>
              <?php endif; ?>
              <?php foreach ($bottlenecks as $b): ?>
                <tr>
                  <td><?= e($b['estado']) ?></td>
                  <td><?= (int) $b['total'] ?></td>
                  <td><?= e($fmtMin($b['parado_medio_min'] !== null ? (int) round((float) $b['parado_medio_min']) : null)) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Estatística por Assunto -->
        <div style="flex:1;min-width:340px">
          <h2>📁 Por Assunto</h2>
          <p style="color:#6b7280;font-size:13px;margin-top:-4px">Volume de processos por tipo de assunto.</p>
          <table class="ops-table">
            <thead><tr><th>Assunto</th><th>Total</th></tr></thead>
            <tbody>
              <?php if (empty($bySubject)): ?>
                <tr><td colspan="2" style="text-align:center;color:#6b7280">Sem dados no período.</td></tr>
              <?php endif; ?>
              <?php foreach ($bySubject as $s): ?>
                <tr><td><?= e($s['assunto']) ?></td><td><?= (int) $s['total'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Processos Críticos em Aberto -->
      <h2 style="margin-top:32px">🔴 Processos Críticos em Aberto</h2>
      <table class="ops-table">
        <thead><tr><th>Nº Processo</th><th>Cliente</th><th>Matrícula</th><th>Assunto</th><th>Estado</th><th>Responsável</th><th>Aberto há</th></tr></thead>
        <tbody>
          <?php if (empty($critical)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280">Nenhum processo crítico em aberto. 👍</td></tr>
          <?php endif; ?>
          <?php foreach ($critical as $p): ?>
            <tr>
              <td><a href="/processes/<?= (int) $p['id'] ?>"><?= e($p['process_number']) ?></a></td>
              <td><?= e($p['customer_name']) ?></td>
              <td><?= e($p['vehicle_plate']) ?></td>
              <td><?= e($p['subject_name']) ?></td>
              <td><?= e($p['status_name']) ?></td>
              <td style="color:#6b7280"><?= $p['assigned_first_name'] ? e($p['assigned_first_name'] . ' ' . $p['assigned_last_name']) : '— sem responsável' ?></td>
              <td><?= (int) $p['horas_aberto'] ?>h</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:32px">
        <!-- Clientes Frequentes (RN-0059) -->
        <div style="flex:1;min-width:340px">
          <h2>👥 Clientes Frequentes</h2>
          <p style="color:#6b7280;font-size:13px;margin-top:-4px">RN-0059 · acima do limiar na janela configurada.</p>
          <table class="ops-table">
            <thead><tr><th>Cliente</th><th>Contacto</th><th>Processos</th></tr></thead>
            <tbody>
              <?php if (empty($frequentCustomers)): ?>
                <tr><td colspan="3" style="text-align:center;color:#6b7280">Nenhum cliente frequente no período.</td></tr>
              <?php endif; ?>
              <?php foreach ($frequentCustomers as $c): ?>
                <tr>
                  <td><a href="/customers/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a></td>
                  <td style="color:#6b7280"><?= e($c['phone'] ?: $c['email'] ?: '—') ?></td>
                  <td><span class="ops-badge" style="background:#f97316"><?= (int) $c['total'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Viaturas Recorrentes (RN-0060) -->
        <div style="flex:1;min-width:340px">
          <h2>🚗 Viaturas Recorrentes</h2>
          <p style="color:#6b7280;font-size:13px;margin-top:-4px">RN-0060 · acima do limiar na janela configurada.</p>
          <table class="ops-table">
            <thead><tr><th>Matrícula</th><th>Processos</th></tr></thead>
            <tbody>
              <?php if (empty($recurrentVehicles)): ?>
                <tr><td colspan="2" style="text-align:center;color:#6b7280">Nenhuma viatura recorrente no período.</td></tr>
              <?php endif; ?>
              <?php foreach ($recurrentVehicles as $v): ?>
                <tr>
                  <td><a href="/vehicles/<?= (int) $v['id'] ?>"><?= e($v['plate']) ?></a></td>
                  <td><span class="ops-badge" style="background:#f97316"><?= (int) $v['total'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
