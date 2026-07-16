<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Todos os Processos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Todos os Processos</h1>
      <p style="color:#6b7280">Visão completa para Administração/Supervisão, com filtros combináveis.</p>

      <?php
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
        $queryWithoutTab = $filters;
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
            <select id="assigned_to" name="assigned_to[]" multiple size="4">
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
          <a href="/processes/all.xls?<?= http_build_query($filters) ?>" class="ops-btn ops-btn-sm" style="background:#16a34a;text-decoration:none">⬇️ Baixar Excel</a>
        </div>
      </form>

      <p style="color:#6b7280;margin-top:12px"><?= count($processes) ?> processo(s) encontrado(s).</p>

      <?php $canDelete = in_array('process.delete', \App\Core\Session::get('permissions', []), true); ?>
      <table class="ops-table">
        <thead>
          <tr>
            <th>Nº Processo</th><th>Filial / Departamento</th><th>Cliente</th><th>Matrícula</th><th>Assunto</th>
            <th>Estado</th><th>Prioridade</th><th>Falta p/ SLA</th><th>Responsável</th><th>Criado por</th><th>Contactos</th><th>Criado em</th>
            <th>Reatribuir</th>
            <?php if ($canDelete): ?><th>Excluir</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="14" style="text-align:center;color:#6b7280">Nenhum processo encontrado com estes filtros.</td></tr>
          <?php endif; ?>
          <?php $activeUsers = array_values(array_filter($users, fn ($u) => (int) $u['active'] === 1)); ?>
          <?php $backUrl = '/processes/all?' . http_build_query($filters); ?>
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
              <td><?= $process['creator_first_name'] ? e($process['creator_first_name'] . ' ' . $process['creator_last_name']) : '—' ?></td>
              <td><?= (int) $process['contact_count'] ?></td>
              <td><?= dt($process['created_at']) ?></td>
              <td>
                <?php if (!in_array($process['status_code'], ['SOLVED', 'CLOSED'], true)): ?>
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
    </main>
  </div>
</body>
</html>
