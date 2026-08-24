<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Horário & Feriados (SLA)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/admin" style="color:#6b7280;text-decoration:none">← Configurações</a></p>
      <h1>🕘 Horário de Atendimento & Feriados</h1>
      <p style="color:#6b7280">
        Com isto ligado, o relógio do SLA <strong>só conta dentro do horário de atendimento</strong>
        e <strong>salta os feriados</strong>. Ex.: um SLA de 30 min aberto às 17h55 (fecham 18h)
        vence às 09h25 do dia seguinte, não às 18h25.
      </p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <?php $dias = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 0 => 'Domingo']; ?>
      <form method="POST" action="/admin/sla-calendar/hours" class="ops-panel" style="max-width:720px">
        <?= csrf_field() ?>
        <label style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:12px">
          <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
          Contar o SLA apenas em horário de atendimento (com feriados)
        </label>
        <p style="color:#9ca3af;font-size:12px;margin:0 0 12px">Se desligado, o SLA conta 24h/dia, como antes. Desmarque um dia para o considerar fechado.</p>

        <p style="color:#9ca3af;font-size:12px;margin:0 0 12px">O <strong>almoço</strong> é opcional: preenchido, o SLA também não conta nesse intervalo. Deixe vazio para não haver pausa de almoço.</p>
        <table class="ops-table">
          <thead><tr><th>Dia</th><th>Aberto</th><th>Abertura</th><th>Almoço (início)</th><th>Almoço (fim)</th><th>Fecho</th></tr></thead>
          <tbody>
            <?php foreach ($dias as $wd => $label): ?>
              <?php
                $h = $hours[$wd] ?? null;
                $aberto = $h && $h['open_time'] !== null;
                $ls = $aberto && !empty($h['lunch_start']) ? substr((string) $h['lunch_start'], 0, 5) : '';
                $le = $aberto && !empty($h['lunch_end']) ? substr((string) $h['lunch_end'], 0, 5) : '';
              ?>
              <tr>
                <td><strong><?= e($label) ?></strong></td>
                <td><input type="checkbox" name="days[<?= $wd ?>]" value="1" <?= $aberto ? 'checked' : '' ?>></td>
                <td><input type="time" name="open[<?= $wd ?>]" value="<?= e($aberto ? substr((string) $h['open_time'], 0, 5) : '09:00') ?>" style="padding:5px 8px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="time" name="lunch_start[<?= $wd ?>]" value="<?= e($ls) ?>" style="padding:5px 8px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="time" name="lunch_end[<?= $wd ?>]" value="<?= e($le) ?>" style="padding:5px 8px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="time" name="close[<?= $wd ?>]" value="<?= e($aberto ? substr((string) $h['close_time'], 0, 5) : '18:00') ?>" style="padding:5px 8px;border:1px solid #e5e7eb;border-radius:6px"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <button type="submit" class="ops-btn" style="margin-top:12px">Guardar Horário</button>
      </form>

      <h2 style="margin-top:32px">📅 Feriados</h2>
      <p style="color:#6b7280">Os feriados <strong>nacionais</strong> de Portugal já estão incluídos. Adicione aqui os <strong>regionais/municipais</strong> que quiser (ex.: feriado do município). "Repete todos os anos" ignora o ano na comparação.</p>

      <table class="ops-table" style="max-width:720px">
        <thead><tr><th>Data</th><th>Nome</th><th>Âmbito</th><th>Repete</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($holidays)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280">Sem feriados.</td></tr>
          <?php endif; ?>
          <?php foreach ($holidays as $h): ?>
            <tr>
              <td><?= e((int) $h['recurring'] === 1 ? date('d/m', strtotime((string) $h['holiday_date'])) : date('d/m/Y', strtotime((string) $h['holiday_date']))) ?></td>
              <td><?= e($h['name']) ?></td>
              <td><span class="ops-badge" style="background:<?= $h['scope'] === 'NACIONAL' ? '#6b7280' : '#0891b2' ?>"><?= e($h['scope']) ?></span></td>
              <td><?= (int) $h['recurring'] === 1 ? 'Todos os anos' : 'Só ' . date('Y', strtotime((string) $h['holiday_date'])) ?></td>
              <td>
                <form method="POST" action="/admin/sla-calendar/holidays/<?= (int) $h['id'] ?>/delete" onsubmit="return confirm('Remover o feriado <?= e($h['name']) ?>?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="ops-btn ops-btn-sm" style="background:#374151">🗑️</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="POST" action="/admin/sla-calendar/holidays" class="ops-panel" style="max-width:720px;margin-top:12px">
        <?= csrf_field() ?>
        <strong>Novo Feriado (regional)</strong>
        <div style="display:flex;gap:8px;margin-top:8px;align-items:center;flex-wrap:wrap">
          <input type="date" name="holiday_date" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="text" name="name" placeholder="Nome do feriado" required style="flex:1;min-width:200px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#374151">
            <input type="checkbox" name="recurring" value="1" checked> Repete todos os anos
          </label>
          <button type="submit" class="ops-btn ops-btn-sm">Adicionar</button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>
