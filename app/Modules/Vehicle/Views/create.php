<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Nova Viatura</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/vehicles" style="color:#6b7280;text-decoration:none">← Lista de Viaturas</a></p>
      <h1>➕ Nova Viatura</h1>
      <p style="color:#6b7280">Também pode criar viaturas automaticamente ao abrir um Novo Processo. Cada viatura pertence sempre a um cliente (identificado pelo telefone).</p>

      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <form method="POST" action="/vehicles" class="ops-panel">
        <?= csrf_field() ?>
        <div class="ops-form-row">
          <label for="plate">Matrícula</label>
          <input type="text" id="plate" name="plate" value="<?= e($old['plate'] ?? '') ?>" placeholder="AA-12-BB" required autofocus>
        </div>
        <div class="ops-form-row">
          <label for="customer_phone">Telefone do Cliente (dono da viatura)</label>
          <input type="text" id="customer_phone" name="customer_phone" value="<?= e($old['customer_phone'] ?? '') ?>" required>
        </div>
        <div class="ops-form-row">
          <label for="brand">Marca (opcional)</label>
          <input type="text" id="brand" name="brand" value="<?= e($old['brand'] ?? '') ?>">
        </div>
        <div class="ops-form-row">
          <label for="model">Modelo (opcional)</label>
          <input type="text" id="model" name="model" value="<?= e($old['model'] ?? '') ?>">
        </div>
        <button type="submit" class="ops-btn">Criar Viatura</button>
      </form>
    </main>
  </div>
</body>
</html>
