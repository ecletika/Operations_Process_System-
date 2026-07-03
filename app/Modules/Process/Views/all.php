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

      <form method="GET" action="/processes/all" class="ops-panel" style="max-width:none">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="status_id">Estado</label>
            <select id="status_id" name="status_id">
              <option value="">Todos</option>
              <?php foreach ($statuses as $status): ?>
                <option value="<?= (int) $status['id'] ?>" <?= (string) $filters['status_id'] === (string) $status['id'] ? 'selected' : '' ?>><?= e($status['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="batch_id">Filial / Departamento</label>
            <select id="batch_id" name="batch_id">
              <option value="">Todos</option>
              <?php foreach ($batches as $batch): ?>
                <option value="<?= (int) $batch['id'] ?>" <?= (string) $filters['batch_id'] === (string) $batch['id'] ? 'selected' : '' ?>><?= e(($batch['branch_name'] ?? '') !== '' ? $batch['branch_name'] . ' · ' . $batch['department_name'] : $batch['department_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="priority_id">Prioridade</label>
            <select id="priority_id" name="priority_id">
              <option value="">Todas</option>
              <?php foreach ($priorities as $priority): ?>
                <option value="<?= (int) $priority['id'] ?>" <?= (string) $filters['priority_id'] === (string) $priority['id'] ? 'selected' : '' ?>><?= e($priority['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="subject_id">Assunto</label>
            <select id="subject_id" name="subject_id">
              <option value="">Todos</option>
              <?php foreach ($subjects as $subject): ?>
                <option value="<?= (int) $subject['id'] ?>" <?= (string) $filters['subject_id'] === (string) $subject['id'] ? 'selected' : '' ?>><?= e($subject['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="assigned_to">Responsável</label>
            <select id="assigned_to" name="assigned_to">
              <option value="">Todos</option>
              <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>" <?= (string) $filters['assigned_to'] === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['first_name'] . ' ' . $user['last_name']) ?></option>
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
        <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
        <a href="/processes/all?tab=<?= e($tab) ?>" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar filtros</a>
      </form>

      <p style="color:#6b7280;margin-top:12px"><?= count($processes) ?> processo(s) encontrado(s).</p>

      <table class="ops-table">
        <thead>
          <tr>
            <th>Nº Processo</th><th>Filial / Departamento</th><th>Cliente</th><th>Matrícula</th><th>Assunto</th>
            <th>Estado</th><th>Prioridade</th><th>Responsável</th><th>Criado por</th><th>Contactos</th><th>Criado em</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="11" style="text-align:center;color:#6b7280">Nenhum processo encontrado com estes filtros.</td></tr>
          <?php endif; ?>
          <?php foreach ($processes as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td style="color:#6b7280"><?= e(trim(($process['branch_name'] ?? '') . ' · ' . ($process['department_name'] ?? ''), ' ·') ?: '—') ?></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= $process['assigned_first_name'] ? e($process['assigned_first_name'] . ' ' . $process['assigned_last_name']) : '—' ?></td>
              <td><?= $process['creator_first_name'] ? e($process['creator_first_name'] . ' ' . $process['creator_last_name']) : '—' ?></td>
              <td><?= (int) $process['contact_count'] ?></td>
              <td><?= e($process['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
