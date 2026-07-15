<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Fila Inteligente™</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Fila Inteligente™</h1>
      <p style="color:#6b7280">Processos ainda sem responsável, ordenados por prioridade.</p>

      <table class="ops-table">
        <thead>
          <tr>
            <th>Nº Processo</th>
            <th>Filial</th>
            <th>Cliente</th>
            <th>Matrícula</th>
            <th>Assunto</th>
            <th>Prioridade</th>
            <th>Falta p/ SLA</th>
            <th>Criado por</th>
            <th>Criado em</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="10" style="text-align:center;color:#6b7280">Fila vazia. Bom trabalho!</td></tr>
          <?php endif; ?>
          <?php foreach ($processes as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td style="color:#6b7280"><?= e($process['branch_name'] ?? '—') ?></td>
              <td><?= e($process['customer_name']) ?></td>
              <td><?= e($process['vehicle_plate']) ?></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= sla_badge($process['created_at'], $process['closed_at'] ?? null, $process['default_sla_minutes'] ?? null) ?></td>
              <td style="color:#6b7280"><?= e(trim(($process['creator_first_name'] ?? '') . ' ' . ($process['creator_last_name'] ?? '')) ?: '—') ?></td>
              <td><?= e($process['created_at']) ?></td>
              <td>
                <form method="POST" action="/processes/<?= (int) $process['id'] ?>/assume">
                  <?= csrf_field() ?>
                  <button type="submit" class="ops-btn ops-btn-sm">Assumir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
