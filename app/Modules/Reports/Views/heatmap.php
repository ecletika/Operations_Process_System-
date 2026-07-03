<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Heatmap de Contactos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/reports" style="color:#6b7280;text-decoration:none">← Relatórios</a></p>
      <h1>🌡️ Heatmap de Contactos</h1>
      <p style="color:#6b7280">Nº de interações por dia da semana × hora. Quanto mais escuro, mais contactos — útil para dimensionar a equipa por turno.</p>

      <form method="GET" action="/reports/heatmap" class="ops-panel" style="max-width:none">
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
          <div class="ops-form-row" style="min-width:150px">
            <label for="from">De</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>">
          </div>
          <div class="ops-form-row" style="min-width:150px">
            <label for="to">Até</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>">
          </div>
          <div style="padding-bottom:12px">
            <button type="submit" class="ops-btn ops-btn-sm">Aplicar período</button>
          </div>
        </div>
      </form>

      <?php $weekdays = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom']; ?>
      <div style="overflow-x:auto;margin-top:16px">
        <table style="border-collapse:collapse;font-size:12px">
          <thead>
            <tr>
              <th style="padding:4px 8px;text-align:left"></th>
              <?php for ($hour = 0; $hour < 24; $hour++): ?>
                <th style="padding:4px 4px;color:#6b7280;font-weight:400"><?= $hour ?>h</th>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($weekdays as $weekday => $label): ?>
              <tr>
                <td style="padding:4px 8px;color:#374151;font-weight:600"><?= e($label) ?></td>
                <?php for ($hour = 0; $hour < 24; $hour++): ?>
                  <?php
                    $value = $grid[$weekday][$hour];
                    $intensity = $max > 0 ? $value / $max : 0;
                    $bg = $value === 0 ? '#f3f4f6' : sprintf('rgba(37, 99, 235, %.2f)', 0.15 + 0.85 * $intensity);
                    $fg = $intensity > 0.55 ? '#fff' : '#374151';
                  ?>
                  <td title="<?= e($label) ?> <?= $hour ?>h: <?= $value ?> contacto(s)"
                      style="width:30px;height:26px;text-align:center;background:<?= $bg ?>;color:<?= $fg ?>;border:1px solid #fff;border-radius:4px">
                    <?= $value > 0 ? $value : '' ?>
                  </td>
                <?php endfor; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($max === 0): ?>
        <p style="color:#6b7280;margin-top:16px">Ainda não há interações registadas neste período.</p>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
