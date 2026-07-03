<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Timeline Global™</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>📝 Timeline Global™</h1>
      <p style="color:#6b7280">Os últimos acontecimentos de toda a operação, em tempo real. A Timeline de cada processo e o <strong>Events Replay™</strong> estão no detalhe do processo; a <strong>Auditoria Visual</strong> em <a href="/admin/audit">Auditoria</a>.</p>

      <?php if (empty($entries)): ?>
        <p style="color:#6b7280">Ainda não há acontecimentos registados.</p>
      <?php endif; ?>

      <div style="max-width:860px">
        <?php foreach ($entries as $entry): ?>
          <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #f3f4f6">
            <div style="width:34px;height:34px;border-radius:50%;background:<?= e($entry['color'] ?: '#2563eb') ?>;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px">
              <?= e($entry['icon'] ?: '•') ?>
            </div>
            <div style="flex:1">
              <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap">
                <strong><?= e($entry['title']) ?></strong>
                <span style="color:#9ca3af;font-size:13px;white-space:nowrap"><?= e($entry['created_at']) ?></span>
              </div>
              <?php if (!empty($entry['description'])): ?>
                <div style="color:#4b5563;font-size:14px;margin-top:2px"><?= e($entry['description']) ?></div>
              <?php endif; ?>
              <div style="color:#6b7280;font-size:13px;margin-top:4px">
                <a href="/processes/<?= (int) $entry['process_id'] ?>"><?= e($entry['process_number']) ?></a>
                · <?= e($entry['customer_name']) ?>
                <?php if (!empty($entry['first_name'])): ?>
                  · por <?= e($entry['first_name'] . ' ' . $entry['last_name']) ?>
                <?php endif; ?>
                · <a href="/processes/<?= (int) $entry['process_id'] ?>/replay" style="color:#7c3aed">▶ Replay</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</body>
</html>
