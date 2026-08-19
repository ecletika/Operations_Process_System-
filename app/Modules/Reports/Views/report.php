<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · <?= e($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
  <style>
    .rf{display:flex;flex-direction:column;gap:16px}
    .rf-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px 16px;align-items:end}
    .rf-grid.multi{grid-template-columns:repeat(auto-fill,minmax(260px,1fr));align-items:start}
    .rf-field{display:flex;flex-direction:column;gap:6px;min-width:0}
    .rf-field label{font-size:12px;font-weight:600;color:#374151;line-height:1.3}
    .rf-field label .hint{font-weight:400;color:#9ca3af;font-size:11px}
    .rf-control{width:100%;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;color:#1f2937;background:#fff}
    input.rf-control,select.rf-control:not([multiple]){height:40px;padding:8px 11px}
    select.rf-control[multiple]{height:118px;padding:6px}
    .rf-control:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.16)}
    .rf-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding-top:2px;border-top:1px solid #f1f3f5;margin-top:2px;padding-top:16px}
    .rf-actions .ops-btn{cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
    .rf-actions .spacer{flex:1}
  </style>
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/reports" style="color:#6b7280;text-decoration:none">← Relatórios</a></p>
      <h1><?= e($title) ?></h1>
      <p style="color:#6b7280"><?= e($description) ?></p>

      <?php
        // Excel com exatamente os mesmos filtros aplicados na página
        $excelQuery = http_build_query(array_filter([
            'from' => $from,
            'to' => $to,
            'operators' => $selectedOperators ?? [],
            'priorities' => $selectedPriorities ?? [],
            'group' => ($groupBy ?? '') === 'equipa' ? 'equipa' : '',
        ]));
        $hasMulti = !empty($priorityOptions) || !empty($operatorOptions);
      ?>
      <form method="GET" action="/reports/view/<?= e($code) ?>" class="ops-panel" style="max-width:none">
        <div class="rf">

          <!-- Período + dimensão simples: todos com 40px de altura, alinhados -->
          <div class="rf-grid">
            <div class="rf-field">
              <label for="from">De</label>
              <input class="rf-control" type="date" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="rf-field">
              <label for="to">Até</label>
              <input class="rf-control" type="date" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <?php if (!empty($showGroupToggle)): ?>
              <div class="rf-field">
                <label for="group">Sair por</label>
                <select class="rf-control" id="group" name="group">
                  <option value="colaborador" <?= ($groupBy ?? '') !== 'equipa' ? 'selected' : '' ?>>Colaborador</option>
                  <option value="equipa" <?= ($groupBy ?? '') === 'equipa' ? 'selected' : '' ?>>Equipa (Filial · Departamento)</option>
                </select>
              </div>
            <?php endif; ?>
          </div>

          <!-- Multi-seleções: mesma altura, alinhadas entre si -->
          <?php if ($hasMulti): ?>
            <div class="rf-grid multi">
              <?php if (!empty($priorityOptions)): ?>
                <div class="rf-field">
                  <label for="priorities">Prioridade(s) <span class="hint">— Ctrl/Cmd+clique para várias</span></label>
                  <select class="rf-control" id="priorities" name="priorities[]" multiple size="4">
                    <?php foreach ($priorityOptions as $pr): ?>
                      <option value="<?= (int) $pr['id'] ?>" <?= in_array((int) $pr['id'], $selectedPriorities ?? [], true) ? 'selected' : '' ?>>
                        <?= e($pr['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
              <?php if (!empty($operatorOptions)): ?>
                <div class="rf-field">
                  <label for="operators">Operador(es) <span class="hint">— Ctrl/Cmd+clique para vários</span></label>
                  <select class="rf-control" id="operators" name="operators[]" multiple size="4">
                    <?php foreach ($operatorOptions as $op): ?>
                      <?php if ((int) $op['active'] !== 1) { continue; } ?>
                      <option value="<?= (int) $op['id'] ?>" <?= in_array((int) $op['id'], $selectedOperators ?? [], true) ? 'selected' : '' ?>>
                        <?= e($op['first_name'] . ' ' . $op['last_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Ações -->
          <div class="rf-actions">
            <button type="submit" class="ops-btn ops-btn-sm">Aplicar filtros</button>
            <a href="/reports/view/<?= e($code) ?>" class="ops-btn ops-btn-sm" style="background:#6b7280">Limpar</a>
            <span class="spacer"></span>
            <a href="/reports/view/<?= e($code) ?>/excel<?= $excelQuery !== '' ? '?' . $excelQuery : '' ?>"
               class="ops-btn ops-btn-sm" style="background:#16a34a">⬇️ Baixar Excel</a>
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
