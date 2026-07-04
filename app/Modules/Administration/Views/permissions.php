<?php
/**
 * 🔑 Matriz de Permissões (ACL). Agrupa as permissões por área (prefixo do
 * código, ex.: "process.*") para leitura mais fácil.
 */
$groups = [];
foreach ($permissions as $perm) {
    $area = strtok((string) $perm['code'], '.');
    $groups[$area][] = $perm;
}

$areaLabels = [
    'dashboard' => '📊 Dashboard',
    'process' => '📋 Processos',
    'records' => '🗑️ Exclusão de Registos',
    'users' => '👤 Utilizadores',
    'companies' => '🏢 Empresas',
    'branches' => '🏢 Filiais',
    'batches' => '📦 Lotes',
    'subjects' => '🏷️ Assuntos',
    'audit' => '📋 Auditoria',
    'logs' => '🪵 Logs',
    'settings' => '⚙️ Configurações',
    'reports' => '📈 Relatórios',
];
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Perfis & Permissões</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>🔑 Perfis & Permissões</h1>
      <p style="color:#6b7280">Matriz de controlo de acessos (ACL). Marque o que cada Perfil pode fazer. O <strong>Administrador</strong> mantém sempre acesso total.</p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <form method="POST" action="/admin/permissions">
        <?= csrf_field() ?>
        <table class="ops-table" style="min-width:640px">
          <thead>
            <tr>
              <th style="text-align:left">Permissão</th>
              <?php foreach ($roles as $role): ?>
                <th style="text-align:center"><?= e($role['name']) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groups as $area => $perms): ?>
              <tr style="background:#f3f4f6">
                <td colspan="<?= count($roles) + 1 ?>" style="font-weight:600;color:#374151">
                  <?= e($areaLabels[$area] ?? ucfirst($area)) ?>
                </td>
              </tr>
              <?php foreach ($perms as $perm): ?>
                <?php $permId = (int) $perm['id']; ?>
                <tr>
                  <td>
                    <div><?= e($perm['description']) ?></div>
                    <code style="font-size:11px;color:#9ca3af"><?= e($perm['code']) ?></code>
                  </td>
                  <?php foreach ($roles as $role): ?>
                    <?php
                      $roleId = (int) $role['id'];
                      $isAdmin = $role['code'] === 'ROLE_ADMIN';
                      $checked = $isAdmin || in_array($permId, $matrix[$roleId] ?? [], true);
                    ?>
                    <td style="text-align:center">
                      <input type="checkbox"
                             name="perms[<?= $roleId ?>][]"
                             value="<?= $permId ?>"
                             <?= $checked ? 'checked' : '' ?>
                             <?= $isAdmin ? 'disabled title="O Administrador tem sempre acesso total"' : '' ?>>
                    </td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div style="margin-top:16px">
          <button type="submit" class="ops-btn" style="width:auto">Guardar Permissões</button>
        </div>
      </form>
    </main>
  </div>
</body>
</html>
