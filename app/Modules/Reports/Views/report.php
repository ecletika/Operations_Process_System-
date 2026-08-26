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
    /* Modal do drill-down do SLA */
    .sla-modal-overlay{display:none;position:fixed;inset:0;z-index:60;background:rgba(15,23,42,.55);padding:24px}
    .sla-modal-overlay.open{display:flex;align-items:flex-start;justify-content:center}
    .sla-modal{background:#fff;border-radius:14px;max-width:900px;width:100%;margin-top:4vh;padding:20px 22px;box-shadow:0 30px 60px rgba(0,0,0,.35);position:relative;max-height:88vh;overflow:auto}
    .sla-modal-close{position:absolute;top:10px;right:14px;border:none;background:none;font-size:26px;line-height:1;color:#6b7280;cursor:pointer}
    .sla-modal-close:hover{color:#111827}
    .sla-drill{cursor:pointer}
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
        <?php
          $columns = array_keys($rows[0]);
          // Colunas terminadas em "id" (id, operator_id, priority_id, batch_id)
          // são metadados internos — não se mostram, servem só ao botão de drill.
          $hidden = static fn (string $c): bool => $c === 'id' || str_ends_with($c, '_id');
        ?>
        <table class="ops-table">
          <thead>
            <tr>
              <?php foreach ($columns as $column): ?>
                <?php if ($hidden($column)) continue; ?>
                <th><?= e(ucfirst(str_replace('_', ' ', $column))) ?></th>
              <?php endforeach; ?>
              <?php if ($code === 'sla'): ?><th></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($columns as $column): ?>
                  <?php if ($hidden($column)) continue; ?>
                  <td>
                    <?php if ($column === 'processo' && isset($row['id'])): ?>
                      <a href="/processes/<?= (int) $row['id'] ?>"><?= e((string) $row[$column]) ?></a>
                    <?php else: ?>
                      <?= e((string) ($row[$column] ?? '—')) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <?php if ($code === 'sla'): ?>
                  <td>
                    <button type="button" class="ops-btn ops-btn-sm sla-drill" style="background:#0891b2;white-space:nowrap"
                      data-operator="<?= (int) ($row['operator_id'] ?? 0) ?>"
                      data-batch="<?= (int) ($row['batch_id'] ?? 0) ?>"
                      data-priority-id="<?= (int) ($row['priority_id'] ?? 0) ?>"
                      data-priority="<?= e((string) ($row['prioridade'] ?? '')) ?>"
                      data-label="<?= e((string) ($row['colaborador'] ?? ($row['equipa'] ?? ''))) ?>">🔍 Ver processos</button>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($code === 'sla'): ?>
        <div id="sla-modal" class="sla-modal-overlay">
          <div class="sla-modal">
            <button type="button" class="sla-modal-close" title="Fechar">×</button>
            <div class="sla-modal-body" id="sla-modal-body"></div>
          </div>
        </div>
        <script>
        (function () {
          var from = <?= json_encode((string) $from) ?>, to = <?= json_encode((string) $to) ?>;
          var overlay = document.getElementById('sla-modal');
          var body = document.getElementById('sla-modal-body');
          function open() { overlay.classList.add('open'); }
          function close() { overlay.classList.remove('open'); body.innerHTML = ''; }
          document.querySelectorAll('.sla-drill').forEach(function (btn) {
            btn.addEventListener('click', function () {
              var p = new URLSearchParams();
              if (btn.dataset.operator && btn.dataset.operator !== '0') p.set('operator_id', btn.dataset.operator);
              if (btn.dataset.batch && btn.dataset.batch !== '0') p.set('batch_id', btn.dataset.batch);
              p.set('priority_id', btn.dataset.priorityId);
              p.set('priority', btn.dataset.priority || '');
              p.set('label', btn.dataset.label || '');
              if (from) p.set('from', from);
              if (to) p.set('to', to);
              body.innerHTML = '<div style="padding:24px;color:#6b7280">A carregar…</div>';
              open();
              fetch('/reports/sla/processes?' + p.toString(), { headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.text(); })
                .then(function (html) { body.innerHTML = html; })
                .catch(function () { body.innerHTML = '<div style="padding:24px;color:#dc2626">Erro ao carregar os processos.</div>'; });
            });
          });
          overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
          overlay.querySelector('.sla-modal-close').addEventListener('click', close);
          document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        })();
        </script>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
