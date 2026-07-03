<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Viatura · <?= e($vehicle['plate']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/vehicles" style="color:#6b7280;text-decoration:none">← Lista de Viaturas</a></p>
      <h1>🚗 <code><?= e($vehicle['plate']) ?></code></h1>
      <?php if ($customer): ?>
        <p style="color:#6b7280">Cliente: <a href="/customers/<?= (int) $customer['id'] ?>"><?= e($customer['name']) ?></a> · <?= e($customer['phone'] ?? '') ?></p>
      <?php endif; ?>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <div class="ops-panel" style="max-width:none">
        <h2 style="margin-top:0">Dados da Viatura</h2>
        <form method="POST" action="/vehicles/<?= (int) $vehicle['id'] ?>">
          <?= csrf_field() ?>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div class="ops-form-row" style="flex:1;min-width:140px">
              <label for="brand">Marca</label>
              <input type="text" id="brand" name="brand" value="<?= e($vehicle['brand'] ?? '') ?>">
            </div>
            <div class="ops-form-row" style="flex:1;min-width:140px">
              <label for="model">Modelo</label>
              <input type="text" id="model" name="model" value="<?= e($vehicle['model'] ?? '') ?>">
            </div>
            <div class="ops-form-row" style="min-width:100px">
              <label for="year">Ano</label>
              <input type="number" id="year" name="year" value="<?= e((string) ($vehicle['year'] ?? '')) ?>" min="1950" max="2100">
            </div>
          </div>
          <button type="submit" class="ops-btn ops-btn-sm">Guardar</button>
        </form>
      </div>

      <h2 style="margin-top:32px">📋 Histórico da Viatura (<?= count($processes) ?> processo(s))</h2>
      <table class="ops-table">
        <thead><tr><th>Nº Processo</th><th>Assunto</th><th>Estado</th><th>Prioridade</th><th>Responsável</th><th>Reaberturas</th><th>Criado em</th><th>Encerrado em</th></tr></thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="8" style="text-align:center;color:#6b7280">Sem processos para esta matrícula.</td></tr>
          <?php endif; ?>
          <?php foreach ($processes as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= $process['assigned_first_name'] ? e($process['assigned_first_name'] . ' ' . $process['assigned_last_name']) : '—' ?></td>
              <td><?= (int) $process['reopen_count'] ?></td>
              <td style="color:#6b7280"><?= e($process['created_at']) ?></td>
              <td style="color:#6b7280"><?= e($process['closed_at'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
