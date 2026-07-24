<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Configurações</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Configurações</h1>
      <p style="color:#6b7280">RF-0044 a RF-0047 · alterações aqui não exigem alterar código.</p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <h2 style="margin-top:24px">Parâmetros Globais</h2>
      <table class="ops-table">
        <thead><tr><th>Chave</th><th>Descrição</th><th>Valor</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($settings as $setting): ?>
            <tr>
              <td><code><?= e($setting['key']) ?></code></td>
              <td style="color:#6b7280"><?= e($setting['description']) ?></td>
              <td colspan="2">
                <form method="POST" action="/admin/settings/<?= e($setting['key']) ?>" style="display:flex;gap:8px">
                  <?= csrf_field() ?>
                  <input type="text" name="value" value="<?= e($setting['value']) ?>" style="flex:1;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
                  <button type="submit" class="ops-btn ops-btn-sm">Guardar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:32px">Estados</h2>
      <table class="ops-table">
        <thead><tr><th>Código</th><th>Nome</th><th>Ordem</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($statuses as $status): ?>
            <tr>
              <form method="POST" action="/admin/statuses/<?= (int) $status['id'] ?>">
                <?= csrf_field() ?>
                <td><code><?= e($status['code']) ?></code></td>
                <td><input type="text" name="name" value="<?= e($status['name']) ?>" style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="number" name="sort_order" value="<?= (int) $status['sort_order'] ?>" style="width:70px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="checkbox" name="active" value="1" <?= $status['active'] ? 'checked' : '' ?>></td>
                <td><button type="submit" class="ops-btn ops-btn-sm">Guardar</button></td>
              </form>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:32px">Prioridades</h2>
      <p style="color:#6b7280;margin:4px 0 12px">
        <strong>Próx. Contacto Cliente (h)</strong>: de quantas em quantas horas se volta a contactar o
        cliente <strong>enquanto o SLA está em pausa</strong> (processo em espera). Ao pôr em espera, o
        sistema agenda sozinho o próximo contacto; cada contacto registado empurra o seguinte para mais
        X horas; ao retomar o tratamento o lembrete é removido. Com este campo preenchido o calendário na
        ficha do processo fica inibido — vazio, o operador escolhe a data à mão.
      </p>
      <table class="ops-table">
        <thead><tr><th>Código</th><th>Nome</th><th>Cor</th><th>Ordem</th><th>SLA (min)</th><th title="De quantas em quantas horas contactar o cliente enquanto o SLA está em pausa. Vazio = sem lembrete automático; o operador escolhe a data no calendário do processo.">Próx. Contacto Cliente (h)</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($priorities as $priority): ?>
            <tr>
              <form method="POST" action="/admin/priorities/<?= (int) $priority['id'] ?>">
                <?= csrf_field() ?>
                <td><code><?= e($priority['code']) ?></code></td>
                <td><input type="text" name="name" value="<?= e($priority['name']) ?>" style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="color" name="color" value="<?= e($priority['color'] ?? '#6b7280') ?>"></td>
                <td><input type="number" name="sort_order" value="<?= (int) $priority['sort_order'] ?>" style="width:60px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td><input type="number" name="default_sla_minutes" value="<?= e((string) $priority['default_sla_minutes']) ?>" style="width:80px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td>
                  <input type="number" name="next_contact_auto_hours" min="0" placeholder="manual"
                         value="<?= e((string) ($priority['next_contact_auto_hours'] ?? '')) ?>"
                         title="Preenchido: enquanto o SLA estiver em pausa, o próximo contacto com o cliente é agendado sozinho de X em X horas e o calendário do processo fica inibido. Vazio: sem lembrete automático."
                         style="width:90px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
                </td>
                <td>
                  <button type="submit" name="active" value="<?= $priority['active'] ? '0' : '1' ?>"
                          formaction="/admin/priorities/<?= (int) $priority['id'] ?>/toggle"
                          class="ops-btn ops-btn-sm" style="background:<?= $priority['active'] ? '#dc2626' : '#16a34a' ?>">
                    <?= $priority['active'] ? 'Desativar' : 'Ativar' ?>
                  </button>
                </td>
                <td><button type="submit" class="ops-btn ops-btn-sm">Guardar</button></td>
              </form>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="POST" action="/admin/priorities" class="ops-panel" style="max-width:none;margin-top:12px">
        <?= csrf_field() ?>
        <strong>Nova Prioridade</strong>
        <div style="display:flex;gap:8px;margin-top:8px">
          <input type="text" name="code" placeholder="Código (ex.: P5)" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="text" name="name" placeholder="Nome" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="color" name="color" value="#6b7280">
          <input type="number" name="sort_order" placeholder="Ordem" style="width:80px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="number" name="default_sla_minutes" placeholder="SLA (min)" style="width:100px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="number" name="next_contact_auto_hours" min="0" placeholder="Próx. contacto cliente (h)"
                 title="De quantas em quantas horas contactar o cliente enquanto o SLA está em pausa. Deixe vazio para não haver lembrete automático."
                 style="width:150px;padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <button type="submit" class="ops-btn ops-btn-sm">Criar</button>
        </div>
      </form>

      <h2 style="margin-top:32px">Assuntos</h2>
      <table class="ops-table">
        <thead><tr><th>Código</th><th>Nome</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($subjects as $subject): ?>
            <tr>
              <form method="POST" action="/admin/subjects/<?= (int) $subject['id'] ?>">
                <?= csrf_field() ?>
                <td><code><?= e($subject['code']) ?></code></td>
                <td><input type="text" name="name" value="<?= e($subject['name']) ?>" style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px"></td>
                <td>
                  <button type="submit" name="active" value="<?= $subject['active'] ? '0' : '1' ?>"
                          formaction="/admin/subjects/<?= (int) $subject['id'] ?>/toggle"
                          class="ops-btn ops-btn-sm" style="background:<?= $subject['active'] ? '#dc2626' : '#16a34a' ?>">
                    <?= $subject['active'] ? 'Desativar' : 'Ativar' ?>
                  </button>
                </td>
                <td><button type="submit" class="ops-btn ops-btn-sm">Guardar</button></td>
              </form>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <form method="POST" action="/admin/subjects" class="ops-panel" style="max-width:none;margin-top:12px">
        <?= csrf_field() ?>
        <strong>Novo Assunto</strong>
        <div style="display:flex;gap:8px;margin-top:8px">
          <input type="text" name="code" placeholder="Código" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="text" name="name" placeholder="Nome" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <button type="submit" class="ops-btn ops-btn-sm">Criar</button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>
