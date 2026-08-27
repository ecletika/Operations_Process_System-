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

      <?php if ($editingUser): ?>
        <div style="display:flex;gap:10px;align-items:center;margin:12px 0;padding:10px 12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px">
          <a href="/admin/users" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">← Voltar à lista</a>
          <span style="color:#1e40af">
            A editar <strong><?= e($editingUser['first_name'] . ' ' . $editingUser['last_name']) ?></strong>
            (<code><?= e($editingUser['username']) ?></code>)
          </span>
        </div>
      <?php else: ?>
        <div style="display:flex;gap:8px;align-items:center;margin:12px 0">
          <input type="text" id="user_search" placeholder="🔎 Procurar por nome, utilizador, email ou perfil..."
                 style="flex:1;max-width:480px;padding:9px 12px;border:1px solid #e5e7eb;border-radius:8px">
          <button type="button" id="user_search_clear" class="ops-btn ops-btn-sm" style="background:#6b7280">Limpar</button>
          <span id="user_search_count" style="color:#6b7280;font-size:13px"></span>
        </div>
      <?php endif; ?>

      <table class="ops-table">
        <thead><tr><th>Nome</th><th>Utilizador</th><th>Email</th><th>Perfil</th><th>Ativo</th><th></th></tr></thead>
        <tbody>
          <?php $currentGroup = null; ?>
          <?php foreach ($users as $user): ?>
            <?php // A editar: a lista inteira só distrai — fica apenas a linha dele. ?>
            <?php if ($editingUser && (int) $user['id'] !== (int) $editingUser['id']) { continue; } ?>
            <?php $group = $user['branch_name'] . ' · ' . $user['department_name']; ?>
            <?php if ($group !== $currentGroup): $currentGroup = $group; ?>
              <tr class="user-group">
                <td colspan="6" style="background:#f3f4f6;font-weight:600;color:#374151">
                  🏢 <?= e($user['branch_name']) ?> <span style="color:#9ca3af">·</span> <?= e($user['department_name']) ?>
                  <span style="font-weight:400;font-size:12px;color:#6b7280">(<?= e($user['company_name']) ?>)</span>
                </td>
              </tr>
            <?php endif; ?>
            <tr class="user-row" data-search="<?= e(mb_strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['username'] . ' ' . $user['email'] . ' ' . $user['role_name'])) ?>">
              <td><?= e($user['first_name'] . ' ' . $user['last_name']) ?></td>
              <td><code><?= e($user['username']) ?></code></td>
              <td><?= e($user['email']) ?></td>
              <td><?= e($user['role_name']) ?></td>
              <td><?= $user['active'] ? '✅' : '❌' ?></td>
              <td style="display:flex;gap:6px">
                <?php if (!$editingUser): ?>
                  <a href="/admin/users/<?= (int) $user['id'] ?>/edit" class="ops-btn ops-btn-sm">Editar</a>
                <?php endif; ?>
                <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/toggle">
                  <?= csrf_field() ?>
                  <button type="submit" name="active" value="<?= $user['active'] ? '0' : '1' ?>"
                          class="ops-btn ops-btn-sm" style="background:<?= $user['active'] ? '#dc2626' : '#16a34a' ?>">
                    <?= $user['active'] ? 'Desativar' : 'Reativar' ?>
                  </button>
                </form>
                <details style="position:relative">
                  <summary class="ops-btn ops-btn-sm" style="background:#0ea5e9;list-style:none;cursor:pointer" title="Repor password">🔑 Password</summary>
                  <form method="POST" action="/admin/users/<?= (int) $user['id'] ?>/reset-password"
                        onsubmit="return confirm('Repor a password de <?= e($user['username']) ?>?');"
                        style="position:absolute;z-index:20;top:110%;left:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:10px;box-shadow:0 6px 20px rgba(0,0,0,.15);display:flex;flex-direction:column;gap:6px;width:220px">
                    <?= csrf_field() ?>
                    <label style="font-size:12px;color:#374151">Nova password (mín. 8)</label>
                    <input type="text" name="password" minlength="8" required autocomplete="new-password"
                           placeholder="Nova password" style="padding:6px 8px;border:1px solid #e5e7eb;border-radius:6px">
                    <button type="submit" class="ops-btn ops-btn-sm" style="background:#0ea5e9">Repor password</button>
                  </form>
                </details>
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

        <?php $scope = (string) ($formValues['view_scope'] ?? 'OWN'); ?>
        <div class="ops-form-row">
          <label for="view_scope">Departamentos que pode trabalhar</label>
          <select id="view_scope" name="view_scope">
            <option value="OWN" <?= $scope === 'OWN' ? 'selected' : '' ?>>Apenas o seu departamento</option>
            <option value="BRANCH" <?= $scope === 'BRANCH' ? 'selected' : '' ?>>Toda a Filial (todos os departamentos da filial dele)</option>
            <option value="CUSTOM" <?= $scope === 'CUSTOM' ? 'selected' : '' ?>>Departamentos escolhidos ↓</option>
          </select>
          <p style="color:#6b7280;font-size:12px;margin:4px 0 0">
            Além do seu próprio departamento, ele passa a ver estes departamentos na
            <strong>Fila Inteligente™</strong> (e pode assumir de lá) e também em
            "Todos os Processos", se o Perfil lhe der esse menu.
          </p>
          <p id="view_scope_hint" style="font-size:12px;margin:6px 0 0;padding:8px 10px;border-radius:6px;display:none"></p>
        </div>

        <div class="ops-form-row" id="view_departments_box" style="<?= $scope === 'CUSTOM' ? '' : 'display:none' ?>">
          <label>Departamentos autorizados</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px 18px;border:1px solid #e5e7eb;border-radius:8px;padding:10px">
            <?php foreach ($allDepartments as $department): ?>
              <label style="display:flex;align-items:center;gap:6px;min-width:220px;cursor:pointer;font-weight:400">
                <input type="checkbox" name="view_departments[]" value="<?= (int) $department['id'] ?>"
                       <?= in_array((int) $department['id'], $viewDepartmentIds ?? [], true) ? 'checked' : '' ?>>
                <?= e($department['branch_name'] . ' · ' . $department['name']) ?>
              </label>
            <?php endforeach; ?>
          </div>
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

    // Visibilidade: mostra a lista de departamentos quando é "escolhidos" e
    // avisa se o Perfil selecionado não usa esta definição — é o Perfil (e
    // não este campo) que faz de alguém Supervisor de Departamento.
    (function () {
      var scope = document.getElementById('view_scope');
      var box = document.getElementById('view_departments_box');
      var role = document.getElementById('role_id');
      var hint = document.getElementById('view_scope_hint');
      if (!scope || !box) { return; }

      var roleScopes = <?= json_encode($roleViewScopes ?? [], JSON_UNESCAPED_UNICODE) ?>;

      function refresh() {
        box.style.display = scope.value === 'CUSTOM' ? '' : 'none';

        if (!role || !hint) { return; }
        var tipo = roleScopes[role.value] || 'none';

        if (tipo === 'all') {
          hint.style.cssText += ';display:block;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af';
          hint.textContent = 'ℹ️ Este perfil já vê TODOS os processos de todas as filiais — para VER, esta definição não acrescenta nada. Continua a valer para a Fila Inteligente™ e o Próximo Processo, que dão sempre trabalho dos departamentos indicados aqui.';
        } else if (tipo === 'none') {
          hint.style.cssText += ';display:block;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534';
          hint.textContent = '✓ Este perfil não tem o menu "Todos os Processos", mas a definição vale na mesma: estes departamentos entram na Fila Inteligente™ dele e pode assumir de lá.';
        } else {
          hint.style.cssText += ';display:block;background:#f0fdf4;border:1px solid #bbf7d0;color:#166534';
          hint.textContent = '✓ Este perfil vê "Todos os Processos" conforme o âmbito escolhido aqui, e a Fila Inteligente™ mostra-lhe (para assumir) os processos destes departamentos.';
        }
      }

      scope.addEventListener('change', refresh);
      if (role) { role.addEventListener('change', refresh); }
      refresh();
    })();
  </script>
  <script>
    // Pesquisa de utilizadores: filtra as linhas e esconde os cabeçalhos de
    // departamento que fiquem sem nenhum utilizador visível.
    (function () {
      var input = document.getElementById('user_search');
      var clear = document.getElementById('user_search_clear');
      var count = document.getElementById('user_search_count');
      if (!input) { return; }

      var rows = Array.prototype.slice.call(document.querySelectorAll('tr.user-row'));

      function apply() {
        var q = input.value.trim().toLowerCase();
        var visible = 0;
        rows.forEach(function (row) {
          var match = q === '' || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
          row.style.display = match ? '' : 'none';
          if (match) { visible++; }
        });

        // Esconde cabeçalhos de grupo sem utilizadores visíveis a seguir.
        document.querySelectorAll('tr.user-group').forEach(function (header) {
          var anyVisible = false;
          var node = header.nextElementSibling;
          while (node && !node.classList.contains('user-group')) {
            if (node.classList.contains('user-row') && node.style.display !== 'none') { anyVisible = true; break; }
            node = node.nextElementSibling;
          }
          header.style.display = anyVisible ? '' : 'none';
        });

        count.textContent = q === '' ? '' : (visible + ' utilizador(es)');
      }

      input.addEventListener('input', apply);
      clear.addEventListener('click', function () { input.value = ''; apply(); input.focus(); });
    })();
  </script>
</body>
</html>
