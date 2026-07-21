<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Notificações</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <h1>Notificações</h1>
        <form method="POST" action="/notifications/read-all">
          <?= csrf_field() ?>
          <input type="hidden" name="redirect_to" value="/notifications">
          <button type="submit" class="ops-btn ops-btn-sm">Marcar todas como lidas</button>
        </form>
      </div>

      <ul class="ops-timeline">
        <?php if (empty($notifications)): ?>
          <li style="border-left-color:#9ca3af;color:#6b7280">Sem notificações.</li>
        <?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
          <li style="border-left-color: <?= $notification['severity'] === 'CRITICAL' ? '#dc2626' : ($notification['severity'] === 'WARNING' ? '#f59e0b' : '#2563eb') ?>; opacity: <?= $notification['read_at'] ? '0.55' : '1' ?>">
            <div class="time"><?= dt($notification['created_at']) ?></div>
            <div class="title">
              <?php if (!empty($notification['link'])): ?>
                <a href="<?= e($notification['link']) ?>"><?= e($notification['title']) ?></a>
              <?php else: ?>
                <?= e($notification['title']) ?>
              <?php endif; ?>
            </div>
            <div><?= e($notification['message']) ?></div>
            <?php if (!$notification['read_at']): ?>
              <form method="POST" action="/notifications/<?= (int) $notification['id'] ?>/read" style="margin-top:6px">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect_to" value="/notifications">
                <button type="submit" class="ops-btn ops-btn-sm" style="background:#6b7280">Marcar como lida</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </main>
  </div>
</body>
</html>
