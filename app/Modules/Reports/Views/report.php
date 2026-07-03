<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · <?= e($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/reports" style="color:#6b7280;text-decoration:none">← Relatórios</a></p>
      <h1><?= e($title) ?></h1>
      <p style="color:#6b7280"><?= e($description) ?></p>

      <form method="GET" action="/reports/view/<?= e($code) ?>" class="ops-panel" style="max-width:none">
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

      <p style="color:#6b7280;margin-top:12px"><?= count($rows) ?> linha(s).</p>

      <?php if (empty($rows)): ?>
        <p style="color:#6b7280">Sem dados para este período.</p>
      <?php else: ?>
        <?php $columns = array_keys($rows[0]); ?>
        <table class="ops-table">
          <thead>
            <tr>
              <?php foreach ($columns as $column): ?>
                <?php if ($column === 'id') continue; ?>
                <th><?= e(ucfirst(str_replace('_', ' ', $column))) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($columns as $column): ?>
                  <?php if ($column === 'id') continue; ?>
                  <td>
                    <?php if ($column === 'processo' && isset($row['id'])): ?>
                      <a href="/processes/<?= (int) $row['id'] ?>"><?= e((string) $row[$column]) ?></a>
                    <?php else: ?>
                      <?= e((string) ($row[$column] ?? '—')) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
