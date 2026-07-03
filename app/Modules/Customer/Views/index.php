<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Clientes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h1>👥 Clientes</h1>
        <a href="/customers/create" class="ops-btn">➕ Novo Cliente</a>
      </div>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <div style="display:flex;gap:8px;margin:16px 0 12px;border-bottom:1px solid #e5e7eb">
        <a href="/customers?tab=all<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
           style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;color:<?= $tab === 'all' ? '#2563eb' : '#6b7280' ?>;border-bottom:2px solid <?= $tab === 'all' ? '#2563eb' : 'transparent' ?>">Todos</a>
        <a href="/customers?tab=frequent<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
           style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;color:<?= $tab === 'frequent' ? '#2563eb' : '#6b7280' ?>;border-bottom:2px solid <?= $tab === 'frequent' ? '#2563eb' : 'transparent' ?>">⭐ Clientes Frequentes</a>
      </div>

      <?php if ($tab === 'frequent'): ?>
        <p style="color:#6b7280;font-size:13px">RN-0059: clientes com <?= (int) $frequentThreshold ?>+ processos nos últimos <?= (int) $windowDays ?> dias.</p>
      <?php endif; ?>

      <form method="GET" action="/customers" style="margin-bottom:16px;display:flex;gap:8px">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Pesquisar por nome, telefone ou email..."
               style="flex:1;max-width:420px;padding:8px 12px;border:1px solid #e5e7eb;border-radius:6px">
        <button type="submit" class="ops-btn ops-btn-sm">Pesquisar</button>
      </form>

      <p style="color:#6b7280"><?= count($customers) ?> cliente(s).</p>

      <table class="ops-table">
        <thead>
          <tr><th>Nome</th><th>Telefone</th><th>Email</th><th>Viaturas</th><th>Processos</th><th>Últimos <?= (int) $windowDays ?>d</th><th>Último processo</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr><td colspan="8" style="text-align:center;color:#6b7280">Nenhum cliente encontrado.</td></tr>
          <?php endif; ?>
          <?php foreach ($customers as $customer): ?>
            <tr>
              <td>
                <a href="/customers/<?= (int) $customer['id'] ?>"><?= e($customer['name']) ?></a>
                <?php if ((int) $customer['recent_processes'] >= $frequentThreshold): ?>
                  <span class="ops-badge" style="background:#f59e0b" title="Cliente Frequente (RN-0059)">⭐ Frequente</span>
                <?php endif; ?>
              </td>
              <td><?= e($customer['phone'] ?? '—') ?></td>
              <td><?= e($customer['email'] ?? '—') ?></td>
              <td><?= (int) $customer['vehicle_count'] ?></td>
              <td><?= (int) $customer['process_count'] ?></td>
              <td><?= (int) $customer['recent_processes'] ?></td>
              <td style="color:#6b7280"><?= e($customer['last_process_at'] ?? '—') ?></td>
              <td><a href="/customers/<?= (int) $customer['id'] ?>" class="ops-btn ops-btn-sm">Histórico</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
