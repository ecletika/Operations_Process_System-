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
          <div class="ops-form-row" style="min-width:150px;margin:0">
            <label for="from">De</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>">
          </div>
          <div class="ops-form-row" style="min-width:150px;margin:0">
            <label for="to">Até</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>">
          </div>
          <?php
            // Excel com exatamente os mesmos filtros aplicados na página
            $excelQuery = http_build_query(array_filter([
                'from' => $from,
                'to' => $to,
                'operators' => $selectedOperators ?? [],
                'priorities' => $selectedPriorities ?? [],
                'group' => ($groupBy ?? '') === 'equipa' ? 'equipa' : '',
            ]));
          ?>
          <div style="display:flex;gap:8px">
            <button type="submit" class="ops-btn ops-btn-sm">Aplicar filtros</button>
            <a href="/reports/view/<?= e($code) ?>" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar</a>
            <a href="/reports/view/<?= e($code) ?>/excel<?= $excelQuery !== '' ? '?' . $excelQuery : '' ?>"
               class="ops-btn ops-btn-sm" style="background:#16a34a;text-decoration:none">⬇️ Baixar Excel</a>
          </div>
        </div>

        <?php if (!empty($showGroupToggle) || !empty($priorityOptions)): ?>
          <div style="display:flex;gap:22px;flex-wrap:wrap;margin:16px 0 0">
            <?php if (!empty($showGroupToggle)): ?>
              <div class="ops-form-row" style="margin:0;min-width:200px">
                <label for="group">Sair por</label>
                <select id="group" name="group">
                  <option value="colaborador" <?= ($groupBy ?? '') !== 'equipa' ? 'selected' : '' ?>>Colaborador</option>
                  <option value="equipa" <?= ($groupBy ?? '') === 'equipa' ? 'selected' : '' ?>>Equipa (Filial · Departamento)</option>
                </select>
              </div>
            <?php endif; ?>
            <?php if (!empty($priorityOptions)): ?>
              <div class="ops-form-row" style="margin:0;min-width:220px">
                <label for="priorities">Prioridade(s) <span style="font-weight:400;color:#9ca3af">(Ctrl/Cmd+clique para várias)</span></label>
                <select id="priorities" name="priorities[]" multiple size="4" style="min-width:220px">
                  <?php foreach ($priorityOptions as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= in_array((int) $pr['id'], $selectedPriorities ?? [], true) ? 'selected' : '' ?>>
                      <?= e($pr['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($operatorOptions)): ?>
          <div class="ops-form-row" style="margin:16px 0 0">
            <label for="operators">Operador(es) <span style="font-weight:400;color:#9ca3af">(Ctrl+clique / Cmd+clique para escolher vários)</span></label>
            <select id="operators" name="operators[]" multiple size="4" style="width:100%;max-width:420px">
              <?php foreach ($operatorOptions as $op): ?>
                <?php if ((int) $op['active'] !== 1) { continue; } ?>
                <option value="<?= (int) $op['id'] ?>" <?= in_array((int) $op['id'], $selectedOperators ?? [], true) ? 'selected' : '' ?>>
                  <?= e($op['first_name'] . ' ' . $op['last_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
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
