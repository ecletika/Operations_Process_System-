<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Centro de Operações™</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Centro de Operações™</h1>
      <p style="color:#6b7280">Olá, <?= e($userName) ?> · Perfil: <?= e($roleName) ?></p>

      <?php if ($roleCode === 'ROLE_OPERATOR'): ?>
        <div class="ops-kpis">
          <div class="ops-kpi"><div class="value">🔴 <?= (int) $widgets['critical_count'] ?></div><div class="label">Processos Críticos</div></div>
          <div class="ops-kpi"><div class="value">🟠 <?= (int) $widgets['sla_near_count'] ?></div><div class="label">SLA Próximo</div></div>
          <div class="ops-kpi"><div class="value">🟢 <?= (int) $widgets['my_processes_count'] ?></div><div class="label">Meus Processos</div></div>
          <div class="ops-kpi"><div class="value">🔵 <?= (int) $widgets['waiting_count'] ?></div><div class="label">Em Espera</div></div>
        </div>

        <h2 style="margin-top:32px">Próximo Processo a Trabalhar</h2>
        <?php if ($widgets['next_to_work']): ?>
          <?php $next = $widgets['next_to_work']; ?>
          <div class="ops-panel">
            <strong><?= e($next['process_number']) ?></strong><br>
            Cliente: <?= e($next['customer_name']) ?><br>
            Matrícula: <?= e($next['vehicle_plate']) ?><br>
            Prioridade: <span class="ops-badge" style="background:<?= e($next['priority_color']) ?>"><?= e($next['priority_name']) ?></span><br>
            <div style="margin-top:12px">
              <a href="/processes/<?= (int) $next['id'] ?>" class="ops-btn ops-btn-sm" style="display:inline-block;text-decoration:none;text-align:center">Ver Processo</a>
            </div>
          </div>
        <?php else: ?>
          <p style="color:#6b7280">Fila vazia. Sem processos à espera.</p>
        <?php endif; ?>

        <h2 style="margin-top:32px">Minha Caixa de Entrada™</h2>
        <ul class="ops-timeline">
          <?php foreach ($widgets['inbox'] as $item): ?>
            <li style="border-left-color: <?= e($item['color']) ?>">
              <div class="time"><?= dt($item['created_at']) ?> · <?= e($item['process_number']) ?></div>
              <div class="title"><a href="/processes/<?= (int) $item['process_id'] ?>"><?= e($item['title']) ?></a></div>
            </li>
          <?php endforeach; ?>
          <?php if (empty($widgets['inbox'])): ?>
            <li style="border-left-color:#9ca3af;color:#6b7280">Sem atividade recente.</li>
          <?php endif; ?>
        </ul>

      <?php else: ?>
        <div class="ops-kpis">
          <div class="ops-kpi"><div class="value"><?= (int) $widgets['created_today'] ?></div><div class="label">Processos Criados Hoje</div></div>
          <div class="ops-kpi"><div class="value"><?= (int) $widgets['in_queue'] ?></div><div class="label">Em Fila</div></div>
          <div class="ops-kpi"><div class="value"><?= (int) $widgets['assigned_open'] ?></div><div class="label">Em Tratamento</div></div>
          <div class="ops-kpi"><div class="value"><?= (int) $widgets['closed_today'] ?></div><div class="label">Concluídos Hoje</div></div>
          <div class="ops-kpi"><div class="value"><?= $widgets['sla_percentage'] !== null ? $widgets['sla_percentage'] . '%' : '—' ?></div><div class="label">SLA Cumprido Hoje</div></div>
          <div class="ops-kpi"><div class="value"><?= $widgets['avg_resolution_minutes'] !== null ? $widgets['avg_resolution_minutes'] . ' min' : '—' ?></div><div class="label">Tempo Médio</div></div>
          <div class="ops-kpi"><div class="value">🔴 <?= (int) $widgets['critical_count'] ?></div><div class="label">Processos Críticos</div></div>
          <div class="ops-kpi"><div class="value"><?= (int) $widgets['operators_online'] ?></div><div class="label">Operadores Online</div></div>
        </div>

        <h2 style="margin-top:32px">🏆 Ranking (últimos 30 dias)</h2>
        <table class="ops-table">
          <thead><tr><th>Operador</th><th>Processos Concluídos</th><th>Tempo Médio</th></tr></thead>
          <tbody>
            <?php if (empty($widgets['ranking'])): ?>
              <tr><td colspan="3" style="text-align:center;color:#6b7280">Sem dados no período.</td></tr>
            <?php endif; ?>
            <?php foreach ($widgets['ranking'] as $row): ?>
              <tr>
                <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= (int) $row['closed_total'] ?></td>
                <td><?= $row['avg_minutes'] !== null ? (int) round((float) $row['avg_minutes']) . ' min' : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
