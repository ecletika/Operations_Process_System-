<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Relatórios</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>📊 Relatórios</h1>
      <p style="color:#6b7280">Relatórios operacionais e exportação (RF-0041 a RF-0043).</p>

      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px;margin:16px 0">
        <?php foreach ($reports as $reportCode => [$title, $description]): ?>
          <a href="/reports/view/<?= e($reportCode) ?>" style="text-decoration:none;color:inherit;border:1px solid #e5e7eb;border-radius:10px;padding:16px;background:#fff;display:block">
            <div style="font-weight:700;margin-bottom:4px"><?= e($title) ?></div>
            <div style="color:#6b7280;font-size:13px"><?= e($description) ?></div>
          </a>
        <?php endforeach; ?>
        <a href="/reports/imobilizados" style="text-decoration:none;color:inherit;border:1px solid #e5e7eb;border-radius:10px;padding:16px;background:#fff;display:block">
          <div style="font-weight:700;margin-bottom:4px">🅿️ Imobilizados — Cumprimento de Prazos</div>
          <div style="color:#6b7280;font-size:13px">Linha do tempo dos contactos de cada Imobilizado, verde/vermelho conforme o prazo de 16h úteis.</div>
        </a>
        <a href="/reports/heatmap" style="text-decoration:none;color:inherit;border:1px solid #e5e7eb;border-radius:10px;padding:16px;background:#fff;display:block">
          <div style="font-weight:700;margin-bottom:4px">🌡️ Heatmap de Contactos</div>
          <div style="color:#6b7280;font-size:13px">Interações por dia da semana × hora, para dimensionar a equipa.</div>
        </a>
      </div>

      <h2 style="margin-top:24px">📄 Relatório de Processos — Exportar</h2>
      <form method="GET" action="/reports/processes.csv" class="ops-panel">
        <div class="ops-form-row">
          <label for="from">De</label>
          <input type="date" id="from" name="from" value="<?= e($from) ?>">
        </div>
        <div class="ops-form-row">
          <label for="to">Até</label>
          <input type="date" id="to" name="to" value="<?= e($to) ?>">
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" formaction="/reports/processes.csv" class="ops-btn ops-btn-sm">Exportar CSV</button>
          <button type="submit" formaction="/reports/processes.xls" class="ops-btn ops-btn-sm" style="background:#16a34a">Exportar Excel</button>
          <button type="submit" formaction="/reports/processes.pdf" class="ops-btn ops-btn-sm" style="background:#dc2626">Exportar PDF</button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>
