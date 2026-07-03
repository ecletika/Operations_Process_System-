<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Iniciar sessão</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body class="ops-auth-page">
  <div class="ops-card">
    <div class="ops-brand">
      <img src="/img/irmaos-leite-logo.png" alt="Irmãos Leite" style="max-width:260px;width:100%;height:auto;margin:0 auto 16px;display:block">
      <h1>Operations Process System</h1>
      <span>Transformando contactos em processos, e processos em conhecimento.</span>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="ops-errors">
        <?php foreach ($errors as $error): ?>
          <div><?= e($error) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="/login">
      <?= csrf_field() ?>
      <div class="ops-field">
        <label for="username">Utilizador</label>
        <input type="text" id="username" name="username" value="<?= e($old['username'] ?? '') ?>" autofocus required>
      </div>
      <div class="ops-field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="ops-btn">Entrar</button>
    </form>
  </div>
</body>
</html>
