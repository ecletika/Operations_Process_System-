<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Novo Cliente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/customers" style="color:#6b7280;text-decoration:none">← Lista de Clientes</a></p>
      <h1>➕ Novo Cliente</h1>
      <p style="color:#6b7280">Também pode criar clientes automaticamente ao abrir um Novo Processo — este formulário serve para registar um cliente antes de existir qualquer processo.</p>

      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <form method="POST" action="/customers" class="ops-panel">
        <?= csrf_field() ?>
        <div class="ops-form-row">
          <label for="name">Nome</label>
          <input type="text" id="name" name="name" value="<?= e($old['name'] ?? '') ?>" required autofocus>
        </div>
        <div class="ops-form-row">
          <label for="phone">Telefone</label>
          <input type="text" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" required>
        </div>
        <div class="ops-form-row">
          <label for="email">Email (opcional)</label>
          <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>">
        </div>
        <button type="submit" class="ops-btn">Criar Cliente</button>
      </form>
    </main>
  </div>
</body>
</html>
