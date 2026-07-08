<?php
// Agrupa por "Filial · Departamento" (a lista já vem ordenada assim).
$groups = [];
foreach ($users as $user) {
    $groups[$user['branch_name'] . ' · ' . $user['department_name']][] = $user;
}
$onlineCount = count(array_intersect($onlineIds, array_map(static fn ($u) => (int) $u['id'], $users)));
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Tela Operacional</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
  <!-- Presença muda ao minuto: atualiza sozinha a cada 60s -->
  <meta http-equiv="refresh" content="60">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>🖥️ Tela Operacional</h1>
      <p style="color:#6b7280">
        Presença da equipa em tempo real (atualiza a cada 60s) ·
        <span style="color:#16a34a;font-weight:600"><?= (int) $onlineCount ?> online</span> ·
        <?= count($users) - $onlineCount ?> offline ·
        online = atividade nos últimos <?= (int) $onlineWindowMinutes ?> minutos.
      </p>

      <form method="GET" action="/team" class="ops-panel" style="max-width:none">
        <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
          <div class="ops-form-row" style="min-width:170px">
            <label for="branch">Filial</label>
            <select id="branch" name="branch">
              <option value="">Todas</option>
              <?php foreach ($branches as $branch): ?>
                <option value="<?= e($branch) ?>" <?= $filterBranch === $branch ? 'selected' : '' ?>><?= e($branch) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="min-width:170px">
            <label for="department">Departamento</label>
            <select id="department" name="department">
              <option value="">Todos</option>
              <?php foreach ($departments as $department): ?>
                <option value="<?= e($department) ?>" <?= $filterDepartment === $department ? 'selected' : '' ?>><?= e($department) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="min-width:150px">
            <label for="status">Estado</label>
            <select id="status" name="status">
              <option value="">Todos</option>
              <option value="online" <?= $filterStatus === 'online' ? 'selected' : '' ?>>🟢 Online</option>
              <option value="offline" <?= $filterStatus === 'offline' ? 'selected' : '' ?>>🔴 Offline</option>
            </select>
          </div>
          <div style="padding-bottom:12px;display:flex;gap:8px">
            <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
            <a href="/team" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar</a>
          </div>
        </div>
      </form>

      <?php if ($groups === []): ?>
        <p style="color:#6b7280;margin-top:16px">Nenhum colaborador encontrado com estes filtros.</p>
      <?php endif; ?>

      <?php foreach ($groups as $groupName => $members): ?>
        <h2 style="margin-top:28px">🏢 <?= e($groupName) ?></h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <?php foreach ($members as $member): ?>
            <?php $isOnline = in_array((int) $member['id'], $onlineIds, true); ?>
            <div class="ops-panel" style="max-width:none;flex:0 1 260px;display:flex;align-items:center;gap:12px;margin:0">
              <span title="<?= $isOnline ? 'Online' : 'Offline' ?>"
                    style="width:16px;height:16px;border-radius:50%;flex-shrink:0;
                           background:<?= $isOnline ? '#22c55e' : '#dc2626' ?>;
                           box-shadow:0 0 6px <?= $isOnline ? 'rgba(34,197,94,.7)' : 'rgba(220,38,38,.4)' ?>"></span>
              <div style="min-width:0">
                <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></div>
                <div style="font-size:12px;color:#6b7280"><?= e($member['role_name']) ?> · <code style="font-size:11px"><?= e($member['username']) ?></code></div>
                <div style="font-size:11px;color:<?= $isOnline ? '#16a34a' : '#9ca3af' ?>">
                  <?= $isOnline ? 'Online agora' : ('Último login: ' . e($member['last_login_at'] ?? 'nunca')) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
