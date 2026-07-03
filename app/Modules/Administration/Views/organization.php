<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Estrutura Organizacional</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Estrutura Organizacional</h1>
      <p style="color:#6b7280">RF-0032 a RF-0035 · Empresa → Filial → Departamento → Lote.</p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <h2>Empresas</h2>
      <table class="ops-table">
        <thead><tr><th>Código</th><th>Nome</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($companies)): ?>
            <tr><td colspan="3" style="text-align:center;color:#6b7280">Sem empresas.</td></tr>
          <?php endif; ?>
          <?php foreach ($companies as $company): ?>
            <tr>
              <td><code><?= e($company['code']) ?></code></td>
              <td><?= e($company['name']) ?></td>
              <td>
                <form method="POST" action="/admin/organization/companies/<?= (int) $company['id'] ?>/delete"
                      onsubmit="return confirm('Excluir a empresa &quot;<?= e($company['name']) ?>&quot;?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="ops-btn ops-btn-sm" style="background:#dc2626">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="POST" action="/admin/organization/companies" class="ops-panel" style="max-width:none;margin-top:12px">
        <?= csrf_field() ?>
        <div style="display:flex;gap:8px">
          <input type="text" name="code" placeholder="Código" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="text" name="name" placeholder="Nome" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;flex:1">
          <button type="submit" class="ops-btn ops-btn-sm">Criar Empresa</button>
        </div>
      </form>

      <h2 style="margin-top:32px">Filiais</h2>
      <table class="ops-table">
        <thead><tr><th>Empresa</th><th>Código</th><th>Nome</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($branches)): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280">Sem filiais.</td></tr>
          <?php endif; ?>
          <?php foreach ($branches as $branch): ?>
            <tr>
              <td style="color:#6b7280"><?= e($branch['company_name']) ?></td>
              <td><code><?= e($branch['code']) ?></code></td>
              <td><?= e($branch['name']) ?></td>
              <td>
                <form method="POST" action="/admin/organization/branches/<?= (int) $branch['id'] ?>/delete"
                      onsubmit="return confirm('Excluir a filial &quot;<?= e($branch['name']) ?>&quot;?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="ops-btn ops-btn-sm" style="background:#dc2626">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="POST" action="/admin/organization/branches" class="ops-panel" style="max-width:none;margin-top:12px">
        <?= csrf_field() ?>
        <div style="display:flex;gap:8px">
          <select name="company_id" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
            <?php foreach ($companies as $company): ?>
              <option value="<?= (int) $company['id'] ?>"><?= e($company['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="code" placeholder="Código" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="text" name="name" placeholder="Nome" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;flex:1">
          <button type="submit" class="ops-btn ops-btn-sm">Criar Filial</button>
        </div>
      </form>

      <h2 style="margin-top:32px">Departamentos</h2>
      <table class="ops-table">
        <thead><tr><th>Filial</th><th>Código</th><th>Nome</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($departments)): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280">Sem departamentos.</td></tr>
          <?php endif; ?>
          <?php foreach ($departments as $department): ?>
            <tr>
              <td style="color:#6b7280"><?= e($department['branch_name']) ?></td>
              <td><code><?= e($department['code']) ?></code></td>
              <td><?= e($department['name']) ?></td>
              <td>
                <form method="POST" action="/admin/organization/departments/<?= (int) $department['id'] ?>/delete"
                      onsubmit="return confirm('Excluir o departamento &quot;<?= e($department['name']) ?>&quot;?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="ops-btn ops-btn-sm" style="background:#dc2626">Excluir</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="POST" action="/admin/organization/departments" class="ops-panel" style="max-width:none;margin-top:12px">
        <?= csrf_field() ?>
        <div style="display:flex;gap:8px">
          <select name="branch_id" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
            <?php foreach ($branches as $branch): ?>
              <option value="<?= (int) $branch['id'] ?>"><?= e($branch['company_name'] . ' · ' . $branch['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="code" placeholder="Código" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px">
          <input type="text" name="name" placeholder="Nome" required style="padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;flex:1">
          <button type="submit" class="ops-btn ops-btn-sm">Criar Departamento</button>
        </div>
      </form>

      <h2 style="margin-top:32px">Lotes <span style="font-size:13px;color:#6b7280;font-weight:400">(automático — 1 por Departamento, nada para configurar aqui)</span></h2>
      <p style="color:#6b7280;font-size:13px">Cada Departamento tem sempre o seu próprio Lote, criado sozinho assim que o Departamento é criado. É usado apenas internamente (Fila Inteligente™, Dashboard, Relatórios) — não precisam de o gerir.</p>
      <table class="ops-table">
        <thead><tr><th>Departamento</th><th>Código</th></tr></thead>
        <tbody>
          <?php if (empty($batches)): ?>
            <tr><td colspan="2" style="text-align:center;color:#6b7280">Sem lotes ainda — criem o primeiro Departamento acima.</td></tr>
          <?php endif; ?>
          <?php foreach ($batches as $batch): ?>
            <tr>
              <td style="color:#6b7280"><?= e($batch['department_name']) ?></td>
              <td><code><?= e($batch['code']) ?></code></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
