<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Motivos de Pausa do SLA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/admin" style="color:#6b7280;text-decoration:none">← Configurações</a></p>
      <h1>⏸ Motivos de Pausa do SLA</h1>
      <p style="color:#6b7280">
        Os motivos que o operador pode escolher no processo em <strong>"Pôr em espera"</strong>.
        Enquanto o processo está num destes motivos, o <strong>relógio do SLA fica parado</strong> —
        o tempo à espera não é contado contra o operador.
      </p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <table class="ops-table">
        <thead><tr><th>Código</th><th>Nome</th><th>Em uso</th><th>Ativo</th><th></th><th></th></tr></thead>
        <tbody>
          <?php if (empty($reasons)): ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280">Ainda não há motivos de pausa.</td></tr>
          <?php endif; ?>
          <?php foreach ($reasons as $reason): ?>
            <tr>
              <form method="POST" action="/admin/sla-reasons/<?= (int) $reason['id'] ?>">
                <?= csrf_field() ?>
                <td><code style="color:#6b7280"><?= e($reason['code']) ?></code></td>
                <td><input type="text" name="name" value="<?= e($reason['name']) ?>" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td style="color:#6b7280"><?= $reason['in_use'] ? 'Sim' : '—' ?></td>
                <td>
                  <button type="submit" name="active" value="<?= $reason['active'] ? '0' : '1' ?>"
                          class="ops-btn ops-btn-sm" style="background:<?= $reason['active'] ? '#dc2626' : '#16a34a' ?>">
                    <?= $reason['active'] ? 'Desativar' : 'Ativar' ?>
                  </button>
                </td>
                <td><button type="submit" class="ops-btn ops-btn-sm">Guardar</button></td>
              </form>
              <td>
                <?php if (!$reason['in_use']): ?>
                  <form method="POST" action="/admin/sla-reasons/<?= (int) $reason['id'] ?>/delete"
                        onsubmit="return confirm('Excluir o motivo <?= e($reason['name']) ?>?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="ops-btn ops-btn-sm" style="background:#374151">🗑️</button>
                  </form>
                <?php else: ?>
                  <span style="color:#9ca3af;font-size:12px" title="Há processos neste motivo; desative-o em vez de o excluir">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="POST" action="/admin/sla-reasons" class="ops-panel" style="max-width:none;margin-top:12px">
        <?= csrf_field() ?>
        <strong>Novo Motivo de Pausa</strong>
        <div style="display:flex;gap:8px;margin-top:8px;align-items:center">
          <input type="text" name="name" placeholder="Ex.: Aguarda Seguradora" required
                 style="flex:1;max-width:320px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <button type="submit" class="ops-btn ops-btn-sm">Criar</button>
        </div>
        <p style="color:#9ca3af;font-size:12px;margin:8px 0 0">
          O código é gerado automaticamente a partir do nome, para não colidir com os
          estados do fluxo principal (Fila, Assumido, Em Tratamento, Resolvido…).
        </p>
      </form>
    </main>
  </div>
</body>
</html>
