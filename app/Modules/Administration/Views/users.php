<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Utilizadores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Utilizadores</h1>
      <p style="color:#6b7280">RF-0028 a RF-0031 · nenhum utilizador é eliminado, apenas desativado.</p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <table class="ops-table">
        <thead><tr><th>Nome</th><th>Utilizador</th><th>Email</th><th>Perfil</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
          <?php $currentGroup = null; ?>
          <?php foreach ($users as $user): ?>
            <?php $group = $user['branch_name'] . ' · ' . $user['department_name']; ?>
            <?php if ($group !== $currentGroup): $currentGroup = $group; ?>
              <tr>
                <td colspan="6" style="background:#f3f4f6;font-weight:600;color:#374151">
                  🏢 <?= e($user['branch_name']) ?> <span style="color:#9ca3af">·</span> <?= e($user['department_name']) ?>
                  <span style="font-weight:400;font-size:12px;color:#6b7280">(<?= e($user['company_name']) ?>)</span>
                </td>
              </tr>
            <?php endif; ?>
            <tr>
              <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
              <td><code><?= e($user['username']) ?></code></td>
              <td><?= e($user['email']) ?></td>
              <td><?= e($user['role_name']) ?></td>
              <td><?= $user['active'] ? '✅' : '❌' ?></td>
              <td style="display:flex;gap:6px">
                <a href="/admin/users/<?= (int) $user['id'] ?>/edit" class="ops-btn ops-btn-sm">Editar</a>
                <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/toggle">
                  <?= csrf_field() ?>
                  <button type="submit" name="active" value="<?= $user['active'] ? '0' : '1' ?>"
                          class="ops-btn ops-btn-sm" style="background:<?= $user['active'] ? '#dc2626' : '#16a34a' ?>">
                    <?= $user['active'] ? 'Desativar' : 'Reativar' ?>
                  </button>
                </form>
                <?php if ((int) ($user['mfa_enabled'] ?? 0) === 1): ?>
                  <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/reset-mfa"
                        onsubmit="return confirm('Repor o MFA de <?= e($user['username']) ?>? Ele terá de configurar de novo.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="ops-btn ops-btn-sm" style="background:#b45309" title="Repor MFA (perdeu o telemóvel)">🔐 Repor MFA</button>
                  </form>
                <?php endif; ?>
                <?php if (in_array('records.delete', \App\Core\Session::get('permissions', []), true) && (int) $user['id'] !== (int) \App\Core\Session::get('user_id')): ?>
                  <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/delete"
                        onsubmit="return confirm('Excluir o utilizador <?= e($user['username']) ?>? Fica recuperável na Lixeira.');">
                    <?= csrf_field() ?>
                    <button type="submit" class="ops-btn ops-btn-sm" style="background:#374151">🗑️ Excluir</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php $formValues = array_merge($editingUser ?? [], $old); ?>
      <h2 style="margin-top:32px"><?= $editingUser ? 'Editar Utilizador' : 'Novo Utilizador' ?></h2>
      <form method="POST" action="<?= $editingUser ? '/admin/users/' . (int) $editingUser['id'] : '/admin/users' ?>" class="ops-panel" style="max-width:none">
        <?= csrf_field() ?>

        <?php if (!$editingUser): ?>
          <div class="ops-form-row">
            <label for="username">Utilizador</label>
            <input type="text" id="username" name="username" value="<?= e($formValues['username'] ?? '') ?>" required>
          </div>
          <div class="ops-form-row">
            <label for="password">Password inicial</label>
            <input type="password" id="password" name="password" minlength="8" required>
          </div>
        <?php endif; ?>

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

        <div style="display:flex;gap:12px">
          <div class="ops-form-row" style="flex:1">
            <label for="role_id">Perfil</label>
            <select id="role_id" name="role_id" required>
              <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= (int) ($formValues['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1">
            <label for="company_id">Empresa</label>
            <select id="company_id" name="company_id" required>
              <?php foreach ($companies as $company): ?>
                <option value="<?= (int) $company['id'] ?>" <?= (int) ($formValues['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="display:flex;gap:12px">
          <div class="ops-form-row" style="flex:1">
            <label for="branch_id">Filial</label>
            <select id="branch_id" name="branch_id" required>
              <?php foreach ($allBranches as $branch): ?>
                <option value="<?= (int) $branch['id'] ?>" <?= (int) ($formValues['branch_id'] ?? 0) === (int) $branch['id'] ? 'selected' : '' ?>>
                  <?= e($branch['company_name'] . ' · ' . $branch['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1">
            <label for="department_id">Departamento</label>
            <select id="department_id" name="department_id" required>
              <?php foreach ($allDepartments as $department): ?>
                <option value="<?= (int) $department['id'] ?>" data-branch-id="<?= (int) $department['branch_id'] ?>"
                        <?= (int) ($formValues['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>>
                  <?= e($department['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="ops-form-row">
          <label for="view_all_batches">Visibilidade da Fila Inteligente™</label>
          <select id="view_all_batches" name="view_all_batches">
            <option value="0" <?= empty($formValues['view_all_batches']) ? 'selected' : '' ?>>Apenas o próprio lote (departamento)</option>
            <option value="1" <?= !empty($formValues['view_all_batches']) ? 'selected' : '' ?>>Todos os lotes (todas as filiais)</option>
          </select>
          <p style="color:#6b7280;font-size:12px;margin:4px 0 0">O Lote deste utilizador é automático: segue sempre o Departamento escolhido acima. Aqui só decidem se, na Fila, ele vê apenas os processos do seu lote ou de todos.</p>
        </div>

        <button type="submit" class="ops-btn"><?= $editingUser ? 'Guardar Alterações' : 'Criar Utilizador' ?></button>
        <?php if ($editingUser): ?>
          <a href="/admin/users" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Cancelar</a>
        <?php endif; ?>
      </form>
    </main>
  </div>
  <script>
    // Departamento em cascata: só mostra os departamentos da Filial escolhida.
    (function () {
      var branchSelect = document.getElementById('branch_id');
      var departmentSelect = document.getElementById('department_id');
      if (!branchSelect || !departmentSelect) return;

      var allOptions = Array.prototype.slice.call(departmentSelect.options);

      function refresh(keepSelection) {
        var branchId = branchSelect.value;
        var selected = keepSelection ? departmentSelect.value : '';
        departmentSelect.innerHTML = '';
        allOptions.forEach(function (opt) {
          if (opt.getAttribute('data-branch-id') === branchId) {
            departmentSelect.appendChild(opt);
          }
        });
        if (selected) {
          departmentSelect.value = selected;
        }
        if (!departmentSelect.value && departmentSelect.options.length > 0) {
          departmentSelect.selectedIndex = 0;
        }
      }

      branchSelect.addEventListener('change', function () { refresh(false); });
      refresh(true);
    })();
  </script>
</body>
</html>
