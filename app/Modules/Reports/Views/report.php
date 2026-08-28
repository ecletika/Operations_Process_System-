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
    /* Dropdown pesquisável (progressive enhancement de um <select multiple>) */
    .ss-wrap{position:relative}
    .ss-control{display:flex;flex-wrap:wrap;gap:4px;align-items:center;min-height:40px;border:1px solid #e5e7eb;border-radius:8px;padding:5px 7px;background:#fff;cursor:text}
    .ss-control:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
    .ss-chip{display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;padding:1px 6px;font-size:12px;white-space:nowrap}
    .ss-chip button{border:none;background:none;color:#1d4ed8;cursor:pointer;font-size:14px;line-height:1;padding:0}
    .ss-input{border:none;outline:none;flex:1;min-width:90px;font-size:13px;padding:2px;background:transparent}
    .ss-panel{position:absolute;z-index:50;left:0;right:0;top:calc(100% + 2px);max-height:240px;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.14);display:none}
    .ss-panel.open{display:block}
    .ss-opt{padding:8px 10px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px}
    .ss-opt:hover,.ss-opt.active{background:#f1f5f9}
    .ss-opt.sel{color:#1d4ed8;font-weight:600}
    .ss-opt .tick{width:14px;text-align:center;color:#1d4ed8}
    .ss-empty{padding:9px 10px;color:#9ca3af;font-size:12px}
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
                  <label for="operators">Operador(es) <span class="hint">— escreva para procurar</span></label>
                  <select class="rf-control ss-enhance" id="operators" name="operators[]" multiple size="4" data-placeholder="Procurar operador…">
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

          // Pesquisa pelo nº do processo dentro do modal. Fica aqui (e não no
          // fragmento) porque o HTML é injetado por innerHTML, e o browser não
          // executa <script> inserido dessa maneira.
          function ligarPesquisa() {
            var input = body.querySelector('#sla-proc-search');
            if (!input) { return; }

            var linhas = Array.prototype.slice.call(body.querySelectorAll('.sla-proc-row'));
            var vazio = body.querySelector('#sla-proc-empty');
            var contador = body.querySelector('#sla-proc-count');

            function filtrar() {
              var q = input.value.trim().toLowerCase();
              var visiveis = 0;

              linhas.forEach(function (linha) {
                var mostra = q === '' || (linha.getAttribute('data-numero') || '').indexOf(q) !== -1;
                linha.style.display = mostra ? '' : 'none';
                if (mostra) { visiveis++; }
              });

              if (vazio) { vazio.style.display = (visiveis === 0 ? '' : 'none'); }
              if (contador) { contador.textContent = q === '' ? '' : visiveis + ' de ' + linhas.length; }
            }

            input.addEventListener('input', filtrar);
            input.focus();
          }
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
                .then(function (html) { body.innerHTML = html; ligarPesquisa(); })
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
  <script>
  // Dropdown pesquisável: transforma <select multiple class="ss-enhance"> numa
  // caixa com pesquisa e chips. Progressive enhancement — se o JS falhar, o
  // <select> nativo continua a funcionar e a enviar operators[] como antes.
  (function () {
    function enhance(select) {
      var placeholder = select.getAttribute('data-placeholder') || 'Procurar…';
      var options = Array.prototype.map.call(select.options, function (o) {
        return { value: o.value, label: o.text, selected: o.selected };
      });
      var wrap = document.createElement('div'); wrap.className = 'ss-wrap';
      var control = document.createElement('div'); control.className = 'ss-control';
      var input = document.createElement('input'); input.className = 'ss-input'; input.type = 'text';
      input.setAttribute('autocomplete', 'off');
      var panel = document.createElement('div'); panel.className = 'ss-panel';
      control.appendChild(input); wrap.appendChild(control); wrap.appendChild(panel);
      select.style.display = 'none';
      select.parentNode.insertBefore(wrap, select);
      var activeIdx = -1;
      function selectedCount() { return options.filter(function (o) { return o.selected; }).length; }
      function syncNative() {
        Array.prototype.forEach.call(select.options, function (o) {
          var m = options.find(function (x) { return x.value === o.value; });
          o.selected = !!(m && m.selected);
        });
      }
      function renderChips() {
        Array.prototype.slice.call(control.querySelectorAll('.ss-chip')).forEach(function (c) { c.remove(); });
        options.filter(function (o) { return o.selected; }).forEach(function (o) {
          var chip = document.createElement('span'); chip.className = 'ss-chip';
          chip.textContent = o.label;
          var x = document.createElement('button'); x.type = 'button'; x.textContent = '×';
          x.addEventListener('click', function (e) { e.stopPropagation(); o.selected = false; syncNative(); renderChips(); renderPanel(); });
          chip.appendChild(x); control.insertBefore(chip, input);
        });
        input.placeholder = selectedCount() ? '' : placeholder;
      }
      function renderPanel() {
        var q = input.value.trim().toLowerCase();
        var matches = options.filter(function (o) { return o.label.toLowerCase().indexOf(q) !== -1; });
        panel.innerHTML = '';
        if (matches.length === 0) {
          var empty = document.createElement('div'); empty.className = 'ss-empty'; empty.textContent = 'Sem resultados';
          panel.appendChild(empty); return;
        }
        matches.forEach(function (o, i) {
          var row = document.createElement('div');
          row.className = 'ss-opt' + (o.selected ? ' sel' : '') + (i === activeIdx ? ' active' : '');
          row.innerHTML = '<span class="tick">' + (o.selected ? '✓' : '') + '</span>' + escapeHtml(o.label);
          row.addEventListener('mousedown', function (e) {
            e.preventDefault(); o.selected = !o.selected; syncNative(); renderChips(); renderPanel(); input.focus();
          });
          panel.appendChild(row);
        });
      }
      function escapeHtml(s) { return s.replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }
      function open() { panel.classList.add('open'); renderPanel(); }
      function close() { panel.classList.remove('open'); activeIdx = -1; }
      control.addEventListener('click', function () { input.focus(); open(); });
      input.addEventListener('focus', open);
      input.addEventListener('input', function () { activeIdx = -1; open(); });
      input.addEventListener('keydown', function (e) {
        var rows = panel.querySelectorAll('.ss-opt');
        if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(rows.length - 1, activeIdx + 1); renderPanel(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(0, activeIdx - 1); renderPanel(); }
        else if (e.key === 'Enter') { e.preventDefault(); if (rows[activeIdx]) rows[activeIdx].dispatchEvent(new MouseEvent('mousedown')); }
        else if (e.key === 'Escape') { close(); }
        else if (e.key === 'Backspace' && input.value === '') {
          var sel = options.filter(function (o) { return o.selected; }); if (sel.length) { sel[sel.length - 1].selected = false; syncNative(); renderChips(); renderPanel(); }
        }
      });
      document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
      renderChips();
    }
    document.querySelectorAll('select.ss-enhance[multiple]').forEach(enhance);
  })();
  </script>
</body>
</html>
