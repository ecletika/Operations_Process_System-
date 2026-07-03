<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Meus Processos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Meus Processos</h1>

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
            <tr><td colspan="6" style="text-align:center;color:#6b7280">Ainda não tem processos atribuídos.</td></tr>
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
    </main>
  </div>
</body>
</html>
