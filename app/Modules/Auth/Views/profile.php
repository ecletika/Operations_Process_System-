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

      <h2 style="margin-top:32px">🔐 Autenticação de Dois Fatores (MFA)</h2>
      <?php foreach ($mfaErrors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>
      <div class="ops-panel" style="max-width:520px">
        <?php if ((int) ($user['mfa_enabled'] ?? 0) === 1): ?>
          <p style="color:#16a34a;font-weight:600;margin-top:0">✔ MFA ativo nesta conta.</p>
          <p style="color:#6b7280;font-size:13px">A cada login (uma vez por dia por dispositivo) será pedido o código da app autenticadora.</p>
          <form method="POST" action="/profile/mfa/disable" onsubmit="return confirm('Desativar a autenticação de dois fatores?');">
            <?= csrf_field() ?>
            <button type="submit" class="ops-btn ops-btn-sm" style="background:#dc2626">Desativar MFA</button>
          </form>
        <?php elseif ($mfaSetup !== null): ?>
          <p style="margin-top:0;font-size:13px;color:#374151">Leia o QR code na sua app autenticadora (Google/Microsoft Authenticator, Authy…) ou introduza a chave manual, e confirme com o código de 6 dígitos.</p>
          <div style="text-align:center;margin:12px 0">
            <div id="qrcode" style="display:inline-block;padding:10px;background:#fff;border:1px solid #e5e7eb;border-radius:8px"></div>
            <div style="margin-top:8px;font-size:12px;color:#6b7280">Chave manual:</div>
            <code style="letter-spacing:2px;word-break:break-all"><?= e($mfaSetup['secret']) ?></code>
          </div>
          <form method="POST" action="/profile/mfa/enable" style="display:flex;gap:8px;align-items:flex-end">
            <?= csrf_field() ?>
            <div class="ops-form-row" style="margin:0">
              <label for="mfa_code">Código</label>
              <input type="text" id="mfa_code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" required style="letter-spacing:6px;text-align:center">
            </div>
            <button type="submit" class="ops-btn ops-btn-sm" style="background:#16a34a">Ativar</button>
          </form>
          <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
          <script>
            (function () {
              var uri = <?= json_encode($mfaSetup['uri'], JSON_UNESCAPED_SLASHES) ?>;
              if (window.QRCode) { new QRCode(document.getElementById('qrcode'), { text: uri, width: 170, height: 170 }); }
              else { document.getElementById('qrcode').innerHTML = '<span style="font-size:12px;color:#6b7280">Use a chave manual.</span>'; }
            })();
          </script>
        <?php else: ?>
          <p style="color:#6b7280;margin-top:0;font-size:13px">Acrescente uma camada extra de segurança: além da password, será pedido um código gerado pela sua app autenticadora.</p>
          <a href="/profile?mfa=setup" class="ops-btn ops-btn-sm" style="text-decoration:none;display:inline-block">🔐 Ativar MFA</a>
        <?php endif; ?>
      </div>
    </main>
  </div>
</body>
</html>
