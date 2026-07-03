<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Meu Perfil</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>👤 Meu Perfil</h1>
      <p style="color:#6b7280">Faça a gestão dos seus dados e altere a sua password.</p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>

      <?php $formValues = array_merge($user, $old); ?>

      <h2 style="margin-top:24px">Os meus dados</h2>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>
      <form method="POST" action="/profile" class="ops-panel" style="max-width:520px">
        <?= csrf_field() ?>
        <div class="ops-form-row">
          <label>Utilizador</label>
          <input type="text" value="<?= e($user['username']) ?>" disabled style="background:#f3f4f6;color:#6b7280">
          <p style="color:#9ca3af;font-size:12px;margin:4px 0 0">O nome de utilizador não pode ser alterado.</p>
        </div>
        <div style="display:flex;gap:12px">
          <div class="ops-form-row" style="flex:1">
            <label for="first_name">Nome</label>
            <input type="text" id="first_name" name="first_name" value="<?= e($formValues['first_name'] ?? '') ?>" required>
          </div>
          <div class="ops-form-row" style="flex:1">
            <label for="last_name">Apelido</label>
            <input type="text" id="last_name" name="last_name" value="<?= e($formValues['last_name'] ?? '') ?>" required>
          </div>
        </div>
        <div class="ops-form-row">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($formValues['email'] ?? '') ?>" required>
        </div>
        <div class="ops-form-row">
          <label>Perfil</label>
          <input type="text" value="<?= e($user['role_name']) ?>" disabled style="background:#f3f4f6;color:#6b7280">
        </div>
        <button type="submit" class="ops-btn" style="width:auto">Guardar Dados</button>
      </form>

      <h2 style="margin-top:32px">Alterar Password</h2>
      <?php foreach ($passwordErrors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>
      <form method="POST" action="/profile/password" class="ops-panel" style="max-width:520px">
        <?= csrf_field() ?>
        <div class="ops-form-row">
          <label for="current_password">Password atual</label>
          <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="ops-form-row">
          <label for="new_password">Nova password</label>
          <input type="password" id="new_password" name="new_password" minlength="8" required autocomplete="new-password">
          <p style="color:#9ca3af;font-size:12px;margin:4px 0 0">Mínimo 8 caracteres.</p>
        </div>
        <div class="ops-form-row">
          <label for="confirm_password">Confirmar nova password</label>
          <input type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password">
        </div>
        <button type="submit" class="ops-btn" style="width:auto">Alterar Password</button>
      </form>
    </main>
  </div>
</body>
</html>
