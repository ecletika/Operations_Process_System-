<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Importação</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/admin" style="color:#6b7280;text-decoration:none">← Configurações</a></p>
      <h1>📥 Importação de Clientes</h1>
      <p style="color:#6b7280">Suba um ficheiro .xlsx ou .csv para adicionar Clientes em massa à base de dados.</p>

      <?php if (!empty($errors)): ?>
        <div class="ops-panel" style="border-left:4px solid #dc2626;max-width:none">
          <?php foreach ($errors as $error): ?>
            <p style="color:#dc2626;margin:4px 0"><?= e($error) ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($result)): ?>
        <?php $stats = $result['stats']; ?>
        <div class="ops-panel" style="border-left:4px solid #16a34a;max-width:none">
          <h3 style="margin-top:0">Resultado da importação de <?= e($result['type']) ?></h3>
          <p>
            ✅ <strong><?= (int) $stats['created'] ?></strong> criado(s) &nbsp;·&nbsp;
            🔁 <strong><?= (int) $stats['duplicates'] ?></strong> já existente(s) (ignorado) &nbsp;·&nbsp;
            ⏭️ <strong><?= (int) $stats['skipped'] ?></strong> ignorado(s) (sem telefone/email)
          </p>
          <?php if (!empty($stats['errors'])): ?>
            <details style="margin-top:8px">
              <summary style="cursor:pointer;color:#6b7280">Ver avisos (<?= count($stats['errors']) ?>)</summary>
              <ul style="color:#6b7280">
                <?php foreach ($stats['errors'] as $errorLine): ?>
                  <li><?= e($errorLine) ?></li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="ops-panel" style="max-width:640px">
        <h3 style="margin-top:0">Clientes</h3>
        <p style="color:#6b7280">
          Colunas esperadas (em qualquer ordem, cabeçalho na 1ª linha):
          <strong>Nome</strong>, <strong>Telefone</strong>, <strong>Email</strong>, <strong>NIF</strong>.
          Clientes já existentes (mesmo telefone ou email) são ignorados automaticamente, sem duplicar.
        </p>
        <form method="POST" action="/admin/import/customers" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="ops-form-row">
            <label for="file">Ficheiro (.xlsx ou .csv)</label>
            <input type="file" id="file" name="file" accept=".xlsx,.csv" required>
          </div>
          <button type="submit" class="ops-btn">Importar Clientes</button>
        </form>
        <p style="color:#9ca3af;font-size:0.85em;margin-top:12px">
          Se o envio do .xlsx falhar por falta de suporte no servidor, exporte a folha como
          <strong>CSV UTF-8</strong> (Excel → Ficheiro → Guardar Como → CSV UTF-8) e envie o .csv.
        </p>
      </div>

      <div class="ops-panel" style="max-width:640px;opacity:0.7">
        <h3 style="margin-top:0">Viaturas</h3>
        <p style="color:#6b7280">
          Importação de Viaturas (Matrícula, Marca, Modelo) ainda não disponível — cada viatura
          precisa de estar associada a um Cliente. Assim que definirmos como ligar as viaturas
          aos clientes da folha, ativamos esta opção aqui.
        </p>
      </div>
    </main>
  </div>
</body>
</html>
