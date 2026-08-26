<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Todos os Processos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
  <style>
    /* Dropdown pesquisável (progressive enhancement de um <select multiple>) */
    .ss-wrap{position:relative}
    .ss-control{display:flex;flex-wrap:wrap;gap:4px;align-items:center;min-height:38px;border:1px solid #e5e7eb;border-radius:6px;padding:4px 6px;background:#fff;cursor:text}
    .ss-control:focus-within{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
    .ss-chip{display:inline-flex;align-items:center;gap:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;padding:1px 6px;font-size:12px;white-space:nowrap}
    .ss-chip button{border:none;background:none;color:#1d4ed8;cursor:pointer;font-size:14px;line-height:1;padding:0}
    .ss-input{border:none;outline:none;flex:1;min-width:90px;font-size:13px;padding:2px;background:transparent}
    .ss-panel{position:absolute;z-index:40;left:0;right:0;top:calc(100% + 2px);max-height:240px;overflow:auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.14);display:none}
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
      <h1>Todos os Processos</h1>
      <p style="color:#6b7280">Visão completa para Administração/Supervisão, com filtros combináveis.</p>

      <?php
        // O âmbito de visibilidade é interno (vem da ficha do utilizador),
        // não é um filtro do ecrã — fora dos links/URLs.
        $urlFilters = $filters;
        unset($urlFilters['scope_department_ids']);

        $tabs = [
          'in_progress' => 'Abertos',
          'em_tratamento' => 'Em Tratamento',
          'em_espera' => 'Em Espera',
          'resolvidos' => 'Resolvidos',
          'encerrados' => 'Encerrados',
          'reabertos' => 'Reabertos',
          'arquivados' => '📦 Arquivados',
          'no_interaction' => 'Sem Interação',
          'all' => 'Todos',
        ];
        $queryWithoutTab = $urlFilters;
        unset($queryWithoutTab['tab']);
      ?>
      <div style="display:flex;gap:6px;margin-bottom:16px;border-bottom:1px solid #e5e7eb;flex-wrap:wrap">
        <?php foreach ($tabs as $key => $label): ?>
          <a href="/processes/all?<?= http_build_query(array_merge($queryWithoutTab, ['tab' => $key])) ?>"
             style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;
                    color:<?= $tab === $key ? '#2563eb' : '#6b7280' ?>;
                    border-bottom:2px solid <?= $tab === $key ? '#2563eb' : 'transparent' ?>">
            <?= e($label) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php
        // Filtros com multi-seleção: cada um destes aceita vários valores
        // (Ctrl/Cmd+clique). $filters[...] vem sempre como array de ids.
        $sel = static fn (int $id, array $chosen): string => in_array($id, $chosen, true) ? 'selected' : '';
      ?>
      <form method="GET" action="/processes/all" class="ops-panel" style="max-width:none">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">

        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px">
          <input type="text" name="q" value="<?= e($filters['q'] ?? '') ?>"
                 placeholder="🔎 Procurar por matrícula, cliente ou nº de processo..."
                 style="flex:1;max-width:460px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px">
          <button type="submit" class="ops-btn ops-btn-sm">Pesquisar</button>
          <?php if (($filters['q'] ?? '') !== ''): ?>
            <a href="/processes/all?tab=<?= e($tab) ?>" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar pesquisa</a>
          <?php endif; ?>
        </div>

        <p style="color:#9ca3af;font-size:12px;margin:0 0 8px">Pode escolher vários valores em cada filtro (Ctrl+clique / Cmd+clique).</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="status_id">Estado</label>
            <select id="status_id" name="status_id[]" multiple size="4">
              <?php foreach ($statuses as $status): ?>
                <option value="<?= (int) $status['id'] ?>" <?= $sel((int) $status['id'], $filters['status_id']) ?>><?= e($status['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="batch_id">Filial / Departamento</label>
            <select id="batch_id" name="batch_id[]" multiple size="4">
              <?php foreach ($batches as $batch): ?>
                <option value="<?= (int) $batch['id'] ?>" <?= $sel((int) $batch['id'], $filters['batch_id']) ?>><?= e(($batch['branch_name'] ?? '') !== '' ? $batch['branch_name'] . ' · ' . $batch['department_name'] : $batch['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="priority_id">Prioridade</label>
            <select id="priority_id" name="priority_id[]" multiple size="4">
              <?php foreach ($priorities as $priority): ?>
                <option value="<?= (int) $priority['id'] ?>" <?= $sel((int) $priority['id'], $filters['priority_id']) ?>><?= e($priority['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="subject_id">Assunto</label>
            <select id="subject_id" name="subject_id[]" multiple size="4">
              <?php foreach ($subjects as $subject): ?>
                <option value="<?= (int) $subject['id'] ?>" <?= $sel((int) $subject['id'], $filters['subject_id']) ?>><?= e($subject['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="assigned_to">Responsável</label>
            <select id="assigned_to" name="assigned_to[]" multiple size="4" class="ss-enhance" data-placeholder="Procurar responsável…">
              <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>" <?= $sel((int) $user['id'], $filters['assigned_to']) ?>><?= e($user['first_name'] . ' ' . $user['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="min-width:150px">
            <label for="date_from">De</label>
            <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
          </div>
          <div class="ops-form-row" style="min-width:150px">
            <label for="date_to">Até</label>
            <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
          </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:8px">
          <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
          <a href="/processes/all?tab=<?= e($tab) ?>" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar filtros</a>
          <a href="/processes/all.xls?<?= http_build_query($urlFilters) ?>" class="ops-btn ops-btn-sm" style="background:#16a34a;text-decoration:none">⬇️ Baixar Excel</a>
        </div>
      </form>

      <p style="color:#6b7280;margin-top:12px"><?= count($processes) ?> processo(s) encontrado(s).</p>

      <?php $canDelete = in_array('process.delete', \App\Core\Session::get('permissions', []), true); ?>
      <style>
        /* Tabela compacta: são muitas colunas, por isso aperta-se o espaçamento
           e o tipo de letra só neste ecrã (não afeta as outras listagens). */
        .ops-table-compact { font-size: 13px; }
        .ops-table-compact th, .ops-table-compact td { padding: 6px 8px; }
        .ops-table-compact th { font-size: 11px; text-transform: uppercase; letter-spacing: .02em; }
        /* max-width evita que a tabela estique a página: em ecrãs estreitos o
           scroll fica dentro da tabela, não no site todo. */
        .ops-table-wrap { overflow-x: auto; max-width: 100%; }
      </style>
      <div class="ops-table-wrap">
      <table class="ops-table ops-table-compact">
        <thead>
          <tr>
            <th>Nº Processo</th><th>Filial / Departamento</th><th>Cliente</th><th>Matrícula</th><th>Assunto</th>
            <th>Estado</th><th>Prioridade</th><th>Falta p/ SLA</th><th>Responsável</th>
            <th>Criado em</th><th>Último Contacto</th><th>Próximo Contacto</th>
            <th>Reatribuir</th>
            <?php if ($canDelete): ?><th>Excluir</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="14" style="text-align:center;color:#6b7280">Nenhum processo encontrado com estes filtros.</td></tr>
          <?php endif; ?>
          <?php $activeUsers = array_values(array_filter($users, fn ($u) => (int) $u['active'] === 1)); ?>
          <?php $backUrl = '/processes/all?' . http_build_query($urlFilters); ?>
          <?php foreach ($processes as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td style="color:#6b7280"><?= e(trim(($process['branch_name'] ?? '') . ' · ' . ($process['department_name'] ?? ''), ' ·') ?: '—') ?></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= sla_badge($process) ?></td>
              <td>
                <?php if ($process['assigned_first_name']): ?>
                  <span style="display:flex;align-items:center"><?= online_dot($process['assigned_last_activity'] ?? null) ?><?= e($process['assigned_first_name'] . ' ' . $process['assigned_last_name']) ?></span>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap">
                <?= dt($process['created_at']) ?>
                <?php if ($process['creator_first_name']): ?>
                  <div style="color:#9ca3af;font-size:11px" title="Criado por">por <?= e($process['creator_first_name'] . ' ' . $process['creator_last_name']) ?></div>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap"><?= $process['last_contact_at'] ? dt($process['last_contact_at']) : '<span style="color:#9ca3af">—</span>' ?></td>
              <td><?= next_contact_badge($process['next_contact_at'] ?? null) ?></td>
              <td>
                <?php
                  // Só se reatribui o que é do próprio departamento. O
                  // Supervisor de Departamento vê a Filial toda, mas nos
                  // processos de outros departamentos só consulta.
                  $podeAgir = $actionableBatchIds === null
                    || in_array((int) ($process['batch_id'] ?? 0), $actionableBatchIds, true);
                ?>
                <?php if (!$podeAgir): ?>
                  <span style="color:#9ca3af;font-size:12px" title="Processo de outro departamento — só consulta">🔒 Outro depto.</span>
                <?php elseif (!in_array($process['status_code'], ['SOLVED', 'CLOSED'], true)): ?>
                  <form method="POST" action="/processes/<?= (int) $process['id'] ?>/reassign" style="display:flex;gap:4px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                    <select name="user_id" required style="padding:4px 6px;border:1px solid #e5e7eb;border-radius:6px;font-size:12px;max-width:130px">
                      <option value="">Operador…</option>
                      <?php foreach ($activeUsers as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['first_name'] . ' ' . $u['last_name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="ops-btn ops-btn-sm" style="padding:4px 8px">➜</button>
                  </form>
                <?php else: ?>
                  <span style="color:#9ca3af">—</span>
                <?php endif; ?>
              </td>
              <?php if ($canDelete): ?>
                <td>
                  <form method="POST" action="/processes/<?= (int) $process['id'] ?>/delete"
                        onsubmit="return confirm('Excluir definitivamente o processo <?= e($process['process_number']) ?> das listagens? Esta ação só pode ser desfeita por um Administrador diretamente na base de dados.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="ops-btn ops-btn-sm" style="padding:4px 8px;background:#dc2626">🗑️</button>
                  </form>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </main>
  </div>
  <script>
  // Transforma qualquer <select multiple class="ss-enhance"> num dropdown
  // pesquisável. O <select> original fica escondido e sincronizado, por isso
  // o formulário continua a enviar assigned_to[] tal como antes (se o JS
  // falhar, o select nativo continua a funcionar).
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
        // remove chips antigos (tudo menos o input)
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
