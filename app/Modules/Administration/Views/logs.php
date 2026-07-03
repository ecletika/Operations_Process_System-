<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Logs</h1>
      <p style="color:#6b7280">RF-0050 · <code>storage/logs/</code> — últimas <?= 300 ?> linhas, mais recente primeiro.</p>

      <?php if (empty($files)): ?>
        <p style="color:#6b7280">Ainda não existem ficheiros de log. São criados automaticamente à medida que ocorrem eventos de segurança (logins falhados, acessos negados) ou quando o job <code>run_intelligence.php</code> corre.</p>
      <?php else: ?>
        <form method="GET" action="/admin/logs" style="margin-bottom:16px">
          <select name="file" onchange="this.form.submit()" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:8px">
            <?php foreach ($files as $file): ?>
              <option value="<?= e($file) ?>" <?= $selected === $file ? 'selected' : '' ?>><?= e($file) ?></option>
            <?php endforeach; ?>
          </select>
        </form>

        <pre style="background:#0f172a;color:#e2e8f0;padding:16px;border-radius:8px;overflow-x:auto;font-size:13px;line-height:1.6"><?php
          foreach ($lines as $line) {
              echo e($line) . "\n";
          }
          if (empty($lines)) {
              echo "(vazio)";
          }
        ?></pre>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
