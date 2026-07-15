<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Assuntos por Departamento</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/admin" style="color:#6b7280;text-decoration:none">← Configurações</a></p>
      <h1>🧩 Assuntos por Departamento</h1>
      <p style="color:#6b7280">
        Escolha que Assuntos aparecem no <strong>Novo Processo</strong> para cada Departamento.
        Se um departamento não tiver nenhum assunto marcado, mostra <strong>todos</strong> os assuntos ativos.
      </p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <?php if (empty($departments)): ?>
        <p style="color:#6b7280">Não há departamentos configurados.</p>
      <?php endif; ?>

      <?php foreach ($departments as $dept): ?>
        <?php $chosen = $map[(int) $dept['id']] ?? []; ?>
        <div class="ops-panel" style="max-width:720px">
          <form method="POST" action="/admin/subject-departments/<?= (int) $dept['id'] ?>">
            <?= csrf_field() ?>
            <h3 style="margin:0 0 4px"><?= e($dept['branch_name'] . ' · ' . $dept['name']) ?></h3>
            <p style="color:#9ca3af;font-size:12px;margin:0 0 10px">
              <?= $chosen === [] ? 'Sem restrição — mostra todos os assuntos.' : count($chosen) . ' assunto(s) selecionado(s).' ?>
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:10px 20px">
              <?php foreach ($subjects as $subject): ?>
                <label style="display:flex;align-items:center;gap:6px;min-width:180px;cursor:pointer">
                  <input type="checkbox" name="subjects[]" value="<?= (int) $subject['id'] ?>"
                         <?= in_array((int) $subject['id'], $chosen, true) ? 'checked' : '' ?>>
                  <?= e($subject['name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" class="ops-btn ops-btn-sm" style="margin-top:12px">Guardar</button>
          </form>
        </div>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
