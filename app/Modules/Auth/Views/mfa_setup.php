<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Configurar duas etapas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body class="ops-auth-page">
  <div class="ops-card" style="max-width:440px">
    <div class="ops-brand">
      <h1>🔐 Ativar duas etapas</h1>
      <span>Proteja a sua conta com uma app autenticadora (Google Authenticator, Microsoft Authenticator, Authy…).</span>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="ops-errors"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <ol style="font-size:13px;color:#374151;padding-left:18px;line-height:1.7">
      <li>Abra a app autenticadora e toque em <strong>＋ / Adicionar</strong>.</li>
      <li>Leia o QR code abaixo (ou introduza a chave à mão).</li>
      <li>Escreva o código de 6 dígitos que a app mostra.</li>
    </ol>

    <div style="text-align:center;margin:12px 0">
      <div id="qrcode" style="display:inline-block;padding:10px;background:#fff;border:1px solid #e5e7eb;border-radius:8px"></div>
      <div style="margin-top:10px;font-size:12px;color:#6b7280">Chave manual:</div>
      <code style="font-size:14px;letter-spacing:2px;word-break:break-all"><?= e($secret) ?></code>
    </div>

    <form method="POST" action="/mfa/setup">
      <?= csrf_field() ?>
      <div class="ops-field">
        <label for="code">Código de confirmação</label>
        <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code"
               pattern="[0-9]*" maxlength="6" placeholder="000000" autofocus required
               style="letter-spacing:8px;text-align:center;font-size:22px">
      </div>
      <button type="submit" class="ops-btn">Ativar e entrar</button>
    </form>

    <p style="margin-top:16px;text-align:center;font-size:13px"><a href="/logout" style="color:#6b7280">Cancelar e sair</a></p>
  </div>

  <!-- QR gerado no browser (a chave nunca sai para terceiros). Se a app não
       carregar, use a chave manual acima. -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script>
    (function () {
      var uri = <?= json_encode($uri, JSON_UNESCAPED_SLASHES) ?>;
      if (window.QRCode) {
        new QRCode(document.getElementById('qrcode'), { text: uri, width: 180, height: 180 });
      } else {
        document.getElementById('qrcode').innerHTML = '<span style="font-size:12px;color:#6b7280">Use a chave manual abaixo.</span>';
      }
    })();
  </script>
</body>
</html>
