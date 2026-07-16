<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Minha Caixa de Entrada™</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>📥 Minha Caixa de Entrada™</h1>
      <p style="color:#6b7280">Os processos que assumiu e os que criou — para tratar e para acompanhar.</p>

      <?php $archived = !empty($archived); ?>
      <div style="display:flex;gap:6px;margin:12px 0 16px;border-bottom:1px solid #e5e7eb">
        <a href="/processes/mine"
           style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;color:<?= !$archived ? '#2563eb' : '#6b7280' ?>;border-bottom:2px solid <?= !$archived ? '#2563eb' : 'transparent' ?>">📨 Em curso</a>
        <a href="/processes/mine?view=archived"
           style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;color:<?= $archived ? '#2563eb' : '#6b7280' ?>;border-bottom:2px solid <?= $archived ? '#2563eb' : 'transparent' ?>">🗄️ Caixa Arquivada</a>
      </div>
      <?php if ($archived): ?>
        <p style="color:#6b7280;font-size:13px">Processos já finalizados (Resolvidos/Encerrados) que assumiu ou criou.</p>
      <?php endif; ?>

      <?php
        // Lista de temas presentes nas duas tabelas, para o filtro por Assunto.
        $subjectNames = [];
        foreach (array_merge($processes, $createdProcesses) as $p) {
            if (!empty($p['subject_name'])) { $subjectNames[$p['subject_name']] = true; }
        }
        ksort($subjectNames);
      ?>
      <div class="ops-panel" style="max-width:none;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="ops-form-row" style="margin:0;min-width:200px">
          <label for="filter_subject">Filtrar por Assunto</label>
          <select id="filter_subject">
            <option value="">Todos os assuntos</option>
            <?php foreach (array_keys($subjectNames) as $name): ?>
              <option value="<?= e(mb_strtolower($name)) ?>"><?= e($name) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ops-form-row" style="margin:0;min-width:200px">
          <label for="filter_plate">Filtrar por Matrícula</label>
          <input type="text" id="filter_plate" placeholder="Ex.: AA00AA (com ou sem traços)">
        </div>
        <button type="button" id="filter_clear" class="ops-btn ops-btn-sm" style="background:#6b7280">Limpar</button>
      </div>

      <h2 style="margin-top:20px">🗂️ Meus Processos <span style="font-size:13px;color:#6b7280;font-weight:400">(assumidos por mim — sou o responsável)</span></h2>
      <table class="ops-table">
        <thead>
          <tr>
            <th>Nº Processo</th>
            <th>Cliente</th>
            <th>Matrícula</th>
            <th>Assunto</th>
            <th>Estado</th>
            <th>Prioridade</th>
            <th>Falta p/ SLA</th>
            <th>Último Contacto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="8" style="text-align:center;color:#6b7280"><?= $archived ? 'Ainda não tem processos finalizados.' : 'Ainda não tem processos atribuídos.' ?></td></tr>
          <?php endif; ?>
          <?php foreach ($processes as $process): ?>
            <tr class="proc-row" data-subject="<?= e(mb_strtolower($process['subject_name'] ?? '')) ?>" data-plate="<?= e(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $process['vehicle_plate'] ?? ''))) ?>">
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= sla_badge($process['created_at'] ?? null, $process['closed_at'] ?? null, $process['default_sla_minutes'] ?? null) ?></td>
              <td><?= dt($process['last_contact_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:32px">📤 Processos Criados <span style="font-size:13px;color:#6b7280;font-weight:400">(criados por mim — posso acompanhar e interagir, mesmo com outro responsável)</span></h2>
      <table class="ops-table">
        <thead>
          <tr>
            <th>Nº Processo</th>
            <th>Cliente</th>
            <th>Matrícula</th>
            <th>Assunto</th>
            <th>Estado</th>
            <th>Prioridade</th>
            <th>Falta p/ SLA</th>
            <th>Responsável</th>
            <th>Último Contacto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($createdProcesses)): ?>
            <tr><td colspan="9" style="text-align:center;color:#6b7280"><?= $archived ? 'Ainda não tem processos criados finalizados.' : 'Ainda não criou processos.' ?></td></tr>
          <?php endif; ?>
          <?php foreach ($createdProcesses as $process): ?>
            <tr class="proc-row" data-subject="<?= e(mb_strtolower($process['subject_name'] ?? '')) ?>" data-plate="<?= e(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $process['vehicle_plate'] ?? ''))) ?>">
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= sla_badge($process['created_at'] ?? null, $process['closed_at'] ?? null, $process['default_sla_minutes'] ?? null) ?></td>
              <td>
                <?php if ($process['assigned_first_name']): ?>
                  <span style="display:flex;align-items:center">
                    <?= online_dot($process['assigned_last_activity'] ?? null) ?>
                    <?= (int) $process['assigned_to'] === (int) $userId
                        ? '<strong>Eu</strong>'
                        : e($process['assigned_first_name'] . ' ' . $process['assigned_last_name']) ?>
                  </span>
                <?php else: ?>
                  <span style="color:#9ca3af">— na fila</span>
                <?php endif; ?>
              </td>
              <td><?= dt($process['last_contact_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="color:#9ca3af;font-size:12px;margin-top:8px">💡 Ao abrir um processo criado por si, pode adicionar observações e anexos normalmente, mesmo que outro operador o tenha assumido.</p>

      <script>
        // Filtros de Meus Processos (por Assunto e por Matrícula). Filtragem
        // no lado do cliente: instantânea e disponível para todos os utilizadores.
        (function () {
          var subject = document.getElementById('filter_subject');
          var plate = document.getElementById('filter_plate');
          var clear = document.getElementById('filter_clear');
          var rows = document.querySelectorAll('tr.proc-row');

          function apply() {
            var s = (subject.value || '').trim();
            var p = (plate.value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            rows.forEach(function (row) {
              var okS = s === '' || row.getAttribute('data-subject') === s;
              var okP = p === '' || (row.getAttribute('data-plate') || '').indexOf(p) !== -1;
              row.style.display = (okS && okP) ? '' : 'none';
            });
          }

          subject.addEventListener('change', apply);
          plate.addEventListener('input', apply);
          clear.addEventListener('click', function () {
            subject.value = '';
            plate.value = '';
            apply();
          });
        })();
      </script>
    </main>
  </div>
</body>
</html>
