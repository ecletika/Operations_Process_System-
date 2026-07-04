<?php
$entityIcons = ['process' => '📋', 'customer' => '👥', 'vehicle' => '🚗'];
$totalDeleted = 0;
foreach ($groups as $g) {
    $totalDeleted += count($g['rows']);
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Lixeira</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>🗑️ Lixeira</h1>
      <p style="color:#6b7280">Registos excluídos, recuperáveis individualmente ou todos de uma vez. Nada é apagado fisicamente (RN-0048).</p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <?php if ($totalDeleted === 0): ?>
        <div class="ops-panel" style="max-width:none;text-align:center;color:#6b7280">
          A Lixeira está vazia. 🎉
        </div>
      <?php else: ?>
        <form method="POST" action="/admin/trash/restore-all" style="margin-bottom:20px"
              onsubmit="return confirm('Restaurar TODOS os <?= (int) $totalDeleted ?> registos da Lixeira?');">
          <?= csrf_field() ?>
          <button type="submit" class="ops-btn" style="width:auto;background:#16a34a">♻️ Restaurar Tudo (<?= (int) $totalDeleted ?>)</button>
        </form>

        <?php foreach ($groups as $entity => $group): ?>
          <?php if (empty($group['rows'])) { continue; } ?>
          <h2 style="margin-top:24px"><?= $entityIcons[$entity] ?? '📄' ?> <?= e($group['name']) ?> (<?= count($group['rows']) ?>)</h2>
          <table class="ops-table">
            <thead><tr><th>Registo</th><th>Excluído em</th><th>Excluído por</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($group['rows'] as $row): ?>
                <tr>
                  <td><code><?= e((string) $row['label']) ?></code></td>
                  <td style="color:#6b7280"><?= e((string) $row['deleted_at']) ?></td>
                  <td style="color:#6b7280"><?= e(trim((string) ($row['deleted_by_name'] ?? '')) ?: '—') ?></td>
                  <td>
                    <form method="POST" action="/admin/trash/<?= e($entity) ?>/<?= (int) $row['id'] ?>/restore">
                      <?= csrf_field() ?>
                      <button type="submit" class="ops-btn ops-btn-sm" style="background:#16a34a">♻️ Restaurar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endforeach; ?>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
