<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Verificação em duas etapas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body class="ops-auth-page">
  <div class="ops-card">
    <div class="ops-brand">
      <img src="/img/irmaos-leite-logo.png" alt="Irmãos Leite" style="max-width:220px;width:100%;height:auto;margin:0 auto 16px;display:block">
      <h1>Verificação em duas etapas</h1>
      <span>Introduza o código de 6 dígitos da sua app autenticadora.</span>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="ops-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="POST" action="/mfa/challenge">
      <?= csrf_field() ?>
      <div class="ops-field">
        <label for="code">Código</label>
        <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]*" maxlength="6" placeholder="000000" autofocus required
               style="letter-spacing:8px;text-align:center;font-size:22px">
      </div>
      <button type="submit" class="ops-btn">Confirmar</button>
    </form>

    <p style="margin-top:16px;text-align:center;font-size:13px"><a href="/logout" style="color:#6b7280">Cancelar e sair</a></p>
  </div>
</body>
</html>
