<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Viaturas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h1>🚗 Viaturas</h1>
        <a href="/vehicles/create" class="ops-btn">➕ Nova Viatura</a>
      </div>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <div style="display:flex;gap:8px;margin:16px 0 12px;border-bottom:1px solid #e5e7eb">
        <a href="/vehicles?tab=all<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
           style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;color:<?= $tab === 'all' ? '#2563eb' : '#6b7280' ?>;border-bottom:2px solid <?= $tab === 'all' ? '#2563eb' : 'transparent' ?>">Todas</a>
        <a href="/vehicles?tab=recurrent<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
           style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;color:<?= $tab === 'recurrent' ? '#2563eb' : '#6b7280' ?>;border-bottom:2px solid <?= $tab === 'recurrent' ? '#2563eb' : 'transparent' ?>">🔁 Viaturas Recorrentes</a>
      </div>

      <?php if ($tab === 'recurrent'): ?>
        <p style="color:#6b7280;font-size:13px">RN-0060: viaturas com <?= (int) $recurrentThreshold ?>+ processos nos últimos <?= (int) $windowDays ?> dias.</p>
      <?php endif; ?>

      <form method="GET" action="/vehicles" style="margin-bottom:16px;display:flex;gap:8px">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Pesquisar por matrícula (AA-12-BB ou AA12BB), marca, modelo ou cliente..."
               style="flex:1;max-width:460px;padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px">
        <button type="submit" class="ops-btn ops-btn-sm">🔎 Pesquisar</button>
      </form>

      <p style="color:#6b7280"><?= count($vehicles) ?> viatura(s).</p>

      <table class="ops-table">
        <thead>
          <tr><th>Matrícula</th><th>Marca / Modelo</th><th>Cliente</th><th>Processos</th><th>Últimos <?= (int) $windowDays ?>d</th><th>Último processo</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (empty($vehicles)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280">Nenhuma viatura encontrada.</td></tr>
          <?php endif; ?>
          <?php foreach ($vehicles as $vehicle): ?>
            <tr>
              <td>
                <a href="/vehicles/<?= (int) $vehicle['id'] ?>"><code><?= e($vehicle['plate']) ?></code></a>
                <?php if ((int) $vehicle['recent_processes'] >= $recurrentThreshold): ?>
                  <span class="ops-badge" style="background:#f97316" title="Viatura Recorrente (RN-0060)">🔁 Recorrente</span>
                <?php endif; ?>
              </td>
              <td><?= e(trim(($vehicle['brand'] ?? '') . ' ' . ($vehicle['model'] ?? '')) ?: '—') ?></td>
              <td><a href="/customers/<?= (int) $vehicle['customer_id'] ?>"><?= e($vehicle['customer_name']) ?></a></td>
              <td><?= (int) $vehicle['process_count'] ?></td>
              <td><?= (int) $vehicle['recent_processes'] ?></td>
              <td style="color:#6b7280"><?= e($vehicle['last_process_at'] ?? '—') ?></td>
              <td style="display:flex;gap:6px">
                <a href="/vehicles/<?= (int) $vehicle['id'] ?>#editar" class="ops-btn ops-btn-sm">✏️ Editar</a>
                <a href="/vehicles/<?= (int) $vehicle['id'] ?>" class="ops-btn ops-btn-sm" style="background:#6b7280">Histórico</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
