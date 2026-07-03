<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Acessos & Sessões</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>🔒 Acessos & Sessões</h1>
      <p style="color:#6b7280">Quem está online agora e o histórico de tentativas de login (sucesso e falhas).</p>

      <h2 style="margin-top:8px">🟢 Sessões Ativas <span style="font-size:13px;color:#6b7280;font-weight:400">(login nos últimos <?= (int) $sessionTimeout ?> min sem terminar sessão)</span></h2>
      <table class="ops-table">
        <thead><tr><th>Utilizador</th><th>Nome</th><th>IP</th><th>Navegador</th><th>Entrou às</th><th>Ativo há</th></tr></thead>
        <tbody>
          <?php if (empty($activeSessions)): ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280">Ninguém com sessão ativa neste momento.</td></tr>
          <?php endif; ?>
          <?php foreach ($activeSessions as $s): ?>
            <tr>
              <td><code><?= e($s['username']) ?></code></td>
              <td><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
              <td><?= e($s['ip_address']) ?></td>
              <td style="color:#6b7280;font-size:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($s['browser'] ?? '—') ?></td>
              <td><?= e($s['login_at']) ?></td>
              <td><?= (int) $s['minutos_ativo'] ?> min</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:32px">🔑 Tentativas de Login</h2>
      <?php
        $tabs = ['all' => 'Todas', 'success' => '✅ Com sucesso', 'failed' => '❌ Falhadas'];
      ?>
      <div style="display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #e5e7eb">
        <?php foreach ($tabs as $key => $label): ?>
          <a href="/admin/security?filter=<?= urlencode($key) ?>"
             style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;
                    color:<?= $filter === $key ? '#2563eb' : '#6b7280' ?>;
                    border-bottom:2px solid <?= $filter === $key ? '#2563eb' : 'transparent' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>

      <table class="ops-table">
        <thead><tr><th>Data/Hora</th><th>Resultado</th><th>Utilizador</th><th>IP</th><th>Navegador</th><th>Saída</th></tr></thead>
        <tbody>
          <?php if (empty($attempts)): ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280">Sem registos.</td></tr>
          <?php endif; ?>
          <?php foreach ($attempts as $a): ?>
            <tr>
              <td><?= e($a['login_at']) ?></td>
              <td>
                <?php if ((int) $a['success'] === 1): ?>
                  <span class="ops-badge" style="background:#16a34a">Sucesso</span>
                <?php else: ?>
                  <span class="ops-badge" style="background:#dc2626">Falhou</span>
                <?php endif; ?>
              </td>
              <td><?= $a['username'] ? '<code>' . e($a['username']) . '</code>' : '<span style="color:#9ca3af">desconhecido</span>' ?></td>
              <td><?= e($a['ip_address']) ?></td>
              <td style="color:#6b7280;font-size:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($a['browser'] ?? '—') ?></td>
              <td style="color:#6b7280"><?= $a['logout_at'] ? e($a['logout_at']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
