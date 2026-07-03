<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Pesquisa<?= $query !== '' ? ' · ' . e($query) : '' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>🔎 Pesquisa</h1>
      <form method="GET" action="/search" style="max-width:520px;display:flex;gap:8px;margin-bottom:16px">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Nº Processo, cliente, matrícula, telefone, assunto ou responsável..."
               style="flex:1;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px" autofocus>
        <button type="submit" class="ops-btn">Pesquisar</button>
      </form>

      <?php if ($query === ''): ?>
        <p style="color:#6b7280">Escreva algo para pesquisar. Matrículas e números de processo são encontrados com ou sem hífens/espaços.</p>
      <?php elseif (empty($results)): ?>
        <p style="color:#6b7280">Sem resultados para "<?= e($query) ?>".</p>
      <?php else: ?>
        <p style="color:#6b7280"><?= count($results) ?> resultado(s) para "<?= e($query) ?>"</p>
        <table class="ops-table">
          <thead><tr><th>Nº Processo</th><th>Cliente</th><th>Matrícula</th><th>Assunto</th><th>Estado</th><th>Responsável</th><th>Criado em</th></tr></thead>
          <tbody>
            <?php foreach ($results as $process): ?>
              <tr>
                <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
                <td><?= e($process['customer_name']) ?></td>
                <td><?= e($process['vehicle_plate']) ?></td>
                <td><?= e($process['subject_name']) ?></td>
                <td><?= e($process['status_name']) ?></td>
                <td><?= e(trim(($process['assigned_first_name'] ?? '') . ' ' . ($process['assigned_last_name'] ?? '')) ?: '—') ?></td>
                <td><?= e($process['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
