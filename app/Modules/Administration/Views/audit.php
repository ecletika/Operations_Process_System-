<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Auditoria</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Auditoria</h1>
      <p style="color:#6b7280">RF-0027 · RN-0037 a RN-0039 — registo imutável de todas as ações relevantes do sistema. Mostra os últimos 100 registos.</p>

      <form method="GET" action="/admin/audit" style="margin-bottom:16px">
        <select name="table_name" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px">
          <option value="">Todas as tabelas</option>
          <?php foreach ($tables as $table): ?>
            <option value="<?= e($table) ?>" <?= $selectedTable === $table ? 'selected' : '' ?>><?= e($table) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <table class="ops-table">
        <thead><tr><th>Data</th><th>Ação</th><th>Tabela</th><th>Registo</th><th>Utilizador</th><th>IP</th><th>Alterações</th></tr></thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280">Sem registos.</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?= dt($log['created_at']) ?></td>
              <td><span class="ops-badge" style="background:#374151"><?= e($log['action']) ?></span></td>
              <td><code><?= e($log['table_name']) ?></code></td>
              <td>#<?= (int) $log['record_id'] ?></td>
              <td><?= $log['user_id'] ? e(trim($log['first_name'] . ' ' . $log['last_name'])) : '—' ?></td>
              <td style="color:#6b7280;font-size:12px"><?= e($log['ip_address'] ?? '—') ?></td>
              <td style="max-width:320px">
                <?php if ($log['old_values'] || $log['new_values']): ?>
                  <details>
                    <summary style="cursor:pointer;color:#2563eb">ver</summary>
                    <?php if ($log['old_values']): ?><div style="color:#dc2626;font-size:12px">- <?= e((string) $log['old_values']) ?></div><?php endif; ?>
                    <?php if ($log['new_values']): ?><div style="color:#16a34a;font-size:12px">+ <?= e((string) $log['new_values']) ?></div><?php endif; ?>
                  </details>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
