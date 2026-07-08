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
            <th>Último Contacto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280">Ainda não tem processos atribuídos.</td></tr>
          <?php endif; ?>
          <?php foreach ($processes as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= e($process['last_contact_at']) ?></td>
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
            <th>Responsável</th>
            <th>Último Contacto</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($createdProcesses)): ?>
            <tr><td colspan="8" style="text-align:center;color:#6b7280">Ainda não criou processos.</td></tr>
          <?php endif; ?>
          <?php foreach ($createdProcesses as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
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
              <td><?= e($process['last_contact_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <p style="color:#9ca3af;font-size:12px;margin-top:8px">💡 Ao abrir um processo criado por si, pode adicionar observações e anexos normalmente, mesmo que outro operador o tenha assumido.</p>
    </main>
  </div>
</body>
</html>
