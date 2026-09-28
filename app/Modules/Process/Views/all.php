<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Todos os Processos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= e(asset('/css/app.css')) ?>">
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


/* ==========================================================================
   Filtros — segmentos e etiquetas de filtro ativo
   ==========================================================================
   Os filtros com poucas opções (Situação, Prioridade) ficam à vista e
   escolhem-se num clique, em vez de obrigarem a Ctrl+clique dentro de uma
   caixa com barra de deslocamento. São checkboxes e radios por baixo, por
   isso continuam a funcionar sem JavaScript.
   -------------------------------------------------------------------- */

/* Os seletores levam .ops-form-row à frente de propósito: dentro de um
   .ops-form-row, as regras genéricas do formulário (label a display:block,
   input a width:100%) têm a mesma especificidade que .ops-seg e ganhavam
   consoante a ordem do ficheiro — o que empilhava os segmentos em coluna e
   deixava os círculos à vista. Aqui a especificidade decide, não a ordem. */

.ops-seg,
.ops-form-row .ops-seg {
  display: inline-flex;
  align-items: center;
  background: #f3f4f6;
  border-radius: 9px;
  padding: 3px;
  gap: 3px;
  flex-wrap: wrap;
  width: auto;
  max-width: 100%;
}

.ops-seg input,
.ops-form-row .ops-seg input {
  /* Fora do ecrã mas focável: o teclado continua a chegar ao controlo. */
  position: absolute;
  opacity: 0;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: 0;
  border: none;
  pointer-events: none;
  appearance: none;
}

.ops-seg label,
.ops-form-row .ops-seg label {
  display: inline-flex;
  align-items: center;
  margin: 0;
  font-size: 13px;
  font-weight: 500;
  color: #4b5563;
  padding: 6px 13px;
  border-radius: 7px;
  cursor: pointer;
  white-space: nowrap;
  user-select: none;
  transition: background .12s ease-out, color .12s ease-out;
}

.ops-form-row .ops-seg label:hover { color: #1f2937; background: rgba(255, 255, 255, .6); }

.ops-seg input:checked + label,
.ops-form-row .ops-seg input:checked + label {
  background: var(--ops-white);
  color: var(--ops-primary-dark);
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .12);
}

.ops-form-row .ops-seg input:focus-visible + label {
  outline: 2px solid var(--ops-primary);
  outline-offset: 2px;
}

/* A prioridade traz a sua própria cor quando está escolhida — é a mesma
   que a tabela usa nas etiquetas, por isso lê-se sem aprender nada novo. */
.ops-form-row .ops-seg input:checked + label[data-cor="critica"] { color: #b02a20; }
.ops-form-row .ops-seg input:checked + label[data-cor="alta"]    { color: #9a5b06; }

@media (prefers-reduced-motion: reduce) {
  .ops-seg label,
  .ops-form-row .ops-seg label { transition: none; }
}

/* Etiquetas do que está filtrado neste momento ---------------------------- */

.ops-filtros-ativos {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  margin-top: 12px;
}

.ops-filtro-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #eff6ff;
  color: var(--ops-primary-dark);
  border: 1px solid #bfdbfe;
  border-radius: 7px;
  padding: 3px 4px 3px 9px;
  font-size: 12.5px;
  font-weight: 500;
  line-height: 1.5;
}

.ops-filtro-chip .tipo { color: #60a5fa; font-weight: 400; }

.ops-filtro-chip a {
  display: flex;
  align-items: center;
  color: #60a5fa;
  text-decoration: none;
  padding: 1px 3px;
  border-radius: 4px;
  font-size: 14px;
  line-height: 1;
}

.ops-filtro-chip a:hover { color: var(--ops-primary-dark); background: #dbeafe; }
.ops-filtro-chip a:focus-visible { outline: 2px solid var(--ops-primary); outline-offset: 1px; }

.ops-filtros-vazio { color: #9ca3af; font-size: 12.5px; }
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

        <?php
          // Situação: os três grupos do dia a dia. São radios — escolhe-se um
          // — e o filtro "Estado" continua ao lado para quem precisa de um
          // estado concreto, como "Aguarda Peças".
          $situacoes = [
            '' => 'Todos',
            'aberto' => 'Em aberto',
            'aguardar' => 'A aguardar',
            'concluidos' => 'Concluídos',
          ];
          $situacaoAtual = (string) ($filters['situacao'] ?? '');

          // A cor da prioridade escolhida acompanha a da tabela.
          $corPrioridade = static function (string $nome): string {
            $n = mb_strtolower($nome);
            return str_contains($n, 'crít') ? 'critica' : (str_contains($n, 'alta') ? 'alta' : '');
          };
        ?>

        <div style="display:flex;gap:22px;flex-wrap:wrap;align-items:flex-start;margin-bottom:14px">
          <div class="ops-form-row" style="margin:0">
            <label style="margin-bottom:6px">Situação</label>
            <div class="ops-seg">
              <?php foreach ($situacoes as $valor => $rotulo): ?>
                <input type="radio" name="situacao" id="sit_<?= e($valor !== '' ? $valor : 'todos') ?>"
                       value="<?= e($valor) ?>" <?= $situacaoAtual === $valor ? 'checked' : '' ?>>
                <label for="sit_<?= e($valor !== '' ? $valor : 'todos') ?>"><?= e($rotulo) ?></label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="ops-form-row" style="margin:0">
            <label style="margin-bottom:6px">Prioridade</label>
            <div class="ops-seg">
              <?php foreach ($priorities as $priority): ?>
                <?php $pid = (int) $priority['id']; ?>
                <input type="checkbox" name="priority_id[]" id="prio_<?= $pid ?>" value="<?= $pid ?>"
                       <?= in_array($pid, $filters['priority_id'], true) ? 'checked' : '' ?>>
                <label for="prio_<?= $pid ?>" data-cor="<?= e($corPrioridade((string) $priority['name'])) ?>"><?= e($priority['name']) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="ops-form-row" style="flex:1;min-width:190px">
            <label for="batch_id">Filial / Departamento</label>
            <select id="batch_id" name="batch_id[]" multiple size="4" class="ss-enhance" data-placeholder="Procurar departamento…">
              <?php foreach ($batches as $batch): ?>
                <option value="<?= (int) $batch['id'] ?>" <?= $sel((int) $batch['id'], $filters['batch_id']) ?>><?= e(($batch['branch_name'] ?? '') !== '' ? $batch['branch_name'] . ' · ' . $batch['department_name'] : $batch['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:190px">
            <label for="subject_id">Assunto</label>
            <select id="subject_id" name="subject_id[]" multiple size="4" class="ss-enhance" data-placeholder="Procurar assunto…">
              <?php foreach ($subjects as $subject): ?>
                <option value="<?= (int) $subject['id'] ?>" <?= $sel((int) $subject['id'], $filters['subject_id']) ?>><?= e($subject['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:190px">
            <label for="assigned_to">Responsável</label>
            <select id="assigned_to" name="assigned_to[]" multiple size="4" class="ss-enhance" data-placeholder="Procurar responsável…">
              <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>" <?= $sel((int) $user['id'], $filters['assigned_to']) ?>><?= e($user['first_name'] . ' ' . $user['last_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:190px">
            <label for="status_id">Estado <span style="font-weight:400;color:#9ca3af;font-size:11px">(detalhe)</span></label>
            <select id="status_id" name="status_id[]" multiple size="4" class="ss-enhance" data-placeholder="Procurar estado…">
              <?php foreach ($statuses as $status): ?>
                <option value="<?= (int) $status['id'] ?>" <?= $sel((int) $status['id'], $filters['status_id']) ?>><?= e($status['name']) ?></option>
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
        <?php
          // Etiquetas do que está filtrado agora. Cada uma tem um link que
          // remove só aquele valor — antes, tirar um departamento obrigava a
          // Ctrl+clique no item certo, dentro de uma caixa com scroll.
          //
          // Os links são construídos aqui e não em JavaScript: assim o
          // botão do meio do rato abre noutro separador, e continuam a
          // funcionar se o JS falhar.
          $rotulos = [];
          foreach ($batches as $b) {
              $rotulos['batch_id'][(int) $b['id']] = ($b['branch_name'] ?? '') !== ''
                  ? $b['branch_name'] . ' · ' . $b['department_name']
                  : $b['department_name'];
          }
          foreach ($subjects as $s) { $rotulos['subject_id'][(int) $s['id']] = $s['name']; }
          foreach ($statuses as $s) { $rotulos['status_id'][(int) $s['id']] = $s['name']; }
          foreach ($priorities as $p) { $rotulos['priority_id'][(int) $p['id']] = $p['name']; }
          foreach ($users as $u) { $rotulos['assigned_to'][(int) $u['id']] = $u['first_name'] . ' ' . $u['last_name']; }

          $nomeFiltro = [
            'batch_id' => 'Departamento', 'subject_id' => 'Assunto', 'status_id' => 'Estado',
            'priority_id' => 'Prioridade', 'assigned_to' => 'Responsável',
          ];

          /**
           * URL igual à atual mas sem um valor (ou sem campos inteiros).
           *
           * @param string[] $campos campos a limpar por completo
           */
          $urlSem = static function (array $campos, ?string $campoValor = null, ?int $valor = null) use ($urlFilters): string {
              $q = $urlFilters;
              foreach ($campos as $campo) {
                  unset($q[$campo]);
              }
              if ($campoValor !== null && $valor !== null) {
                  $q[$campoValor] = array_values(array_filter(
                      (array) ($q[$campoValor] ?? []),
                      static fn ($v): bool => (int) $v !== $valor
                  ));
              }

              return '/processes/all?' . http_build_query(array_filter($q, static fn ($v): bool => $v !== '' && $v !== []));
          };

          /** Data do filtro como o utilizador a escreveu — sem passar por fusos. */
          $diaCurto = static function (string $iso): string {
              $p = explode('-', $iso);

              return count($p) === 3 ? $p[2] . '/' . $p[1] . '/' . $p[0] : $iso;
          };

          $ativos = [];
          if ($situacaoAtual !== '') {
              $ativos[] = ['tipo' => 'Situação', 'valor' => $situacoes[$situacaoAtual], 'url' => $urlSem(['situacao'])];
          }
          foreach ($nomeFiltro as $campo => $nome) {
              foreach ((array) ($filters[$campo] ?? []) as $id) {
                  $id = (int) $id;
                  if (isset($rotulos[$campo][$id])) {
                      $ativos[] = ['tipo' => $nome, 'valor' => $rotulos[$campo][$id], 'url' => $urlSem([], $campo, $id)];
                  }
              }
          }
          if (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '') {
              $de = ($filters['date_from'] ?? '') !== '' ? $diaCurto((string) $filters['date_from']) : '…';
              $ate = ($filters['date_to'] ?? '') !== '' ? $diaCurto((string) $filters['date_to']) : '…';
              $ativos[] = ['tipo' => 'Período', 'valor' => $de . ' a ' . $ate, 'url' => $urlSem(['date_from', 'date_to'])];
          }
        ?>

        <div class="ops-filtros-ativos">
          <?php if ($ativos === []): ?>
            <span class="ops-filtros-vazio">Sem filtros — a mostrar todos os processos deste separador.</span>
          <?php else: ?>
            <?php foreach ($ativos as $a): ?>
              <span class="ops-filtro-chip">
                <span class="tipo"><?= e($a['tipo']) ?></span><?= e((string) $a['valor']) ?>
                <a href="<?= e($a['url']) ?>" title="Remover este filtro" aria-label="Remover filtro <?= e($a['tipo']) ?>: <?= e((string) $a['valor']) ?>">×</a>
              </span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div style="display:flex;gap:8px;margin-top:14px">
          <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
          <?php if ($ativos !== []): ?>
            <a href="/processes/all?tab=<?= e($tab) ?>" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar filtros</a>
          <?php endif; ?>
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

  // Situação e Prioridade aplicam-se logo ao clicar, sem passar pelo botão
  // Filtrar: são as escolhas de todos os dias e não vale a pena cobrar dois
  // cliques por elas. Sem JavaScript, o botão Filtrar continua a funcionar.
  (function () {
    var form = document.querySelector('form[action="/processes/all"]');
    if (!form || typeof form.requestSubmit !== 'function') { return; }

    form.querySelectorAll('.ops-seg input').forEach(function (campo) {
      campo.addEventListener('change', function () { form.requestSubmit(); });
    });
  })();
  </script>
</body>
</html>
