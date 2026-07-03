<?php
$unreadCount = \App\Core\Session::has('user_id')
    ? (new \App\Modules\Notification\Services\NotificationService())->countUnread((int) \App\Core\Session::get('user_id'))
    : 0;
$permissions = \App\Core\Session::get('permissions', []);
?>
<aside class="ops-sidebar">
  <h2 style="line-height:1.25;font-size:20px">Operations<br>Process<br>System</h2>
  <form method="GET" action="/search" style="margin-bottom:14px">
    <input type="text" name="q" placeholder="🔎 Pesquisar..." style="width:100%;padding:8px 10px;border-radius:6px;border:1px solid #374151;background:#1e293b;color:#fff">
  </form>

  <div style="font-size:11px;letter-spacing:1px;color:#64748b;margin:6px 0 2px;text-transform:uppercase">Centro de Operações™</div>
  <a href="/dashboard">📊 Dashboard</a>
  <a href="/processes/create">➕ Novo Processo</a>
  <?php if (in_array('process.assume', $permissions, true)): ?>
    <form method="POST" action="/processes/next" style="margin:0">
      <?= csrf_field() ?>
      <button type="submit" style="all:unset;display:block;width:100%;cursor:pointer;padding:8px 10px;border-radius:6px;color:#cbd5e1;font-size:14px;box-sizing:border-box"
              onmouseover="this.style.background='#1e293b'" onmouseout="this.style.background='transparent'">⏭️ Próximo Processo</button>
    </form>
  <?php endif; ?>
  <a href="/processes/queue">Fila Inteligente™</a>
  <a href="/processes/mine">📥 Minha Caixa de Entrada™</a>
  <?php if (in_array('process.view_all', $permissions, true)): ?>
    <a href="/processes/all">📂 Todos os Processos</a>
  <?php endif; ?>
  <a href="/notifications">🔔 Alertas<?= $unreadCount > 0 ? ' <span class="ops-badge" style="background:#dc2626">' . $unreadCount . '</span>' : '' ?></a>

  <div style="font-size:11px;letter-spacing:1px;color:#64748b;margin:12px 0 2px;text-transform:uppercase">Operação</div>
  <a href="/customers">👥 Clientes</a>
  <a href="/vehicles">🚗 Viaturas</a>
  <a href="/interactions">💬 Interações</a>
  <a href="/timeline">📝 Timeline Global™</a>
  <?php if (in_array('reports.export', $permissions, true)): ?>
    <a href="/reports">📈 Relatórios</a>
    <a href="/intelligence">🧠 Inteligência Operacional™</a>
  <?php endif; ?>

  <?php if (array_intersect(['users.manage', 'companies.manage', 'settings.manage', 'audit.view', 'logs.view'], $permissions) !== []): ?>
    <div style="font-size:11px;letter-spacing:1px;color:#64748b;margin:12px 0 2px;text-transform:uppercase">Administração</div>
  <?php endif; ?>
  <?php if (in_array('users.manage', $permissions, true)): ?>
    <a href="/admin/users">👤 Utilizadores</a>
    <a href="/admin/permissions">🔑 Perfis & Permissões</a>
  <?php endif; ?>
  <?php if (in_array('companies.manage', $permissions, true)): ?>
    <a href="/admin/organization">🏢 Organização</a>
  <?php endif; ?>
  <?php if (in_array('settings.manage', $permissions, true)): ?>
    <a href="/admin">⚙️ Configurações</a>
  <?php endif; ?>
  <?php if (in_array('audit.view', $permissions, true)): ?>
    <a href="/admin/audit">📋 Auditoria</a>
  <?php endif; ?>
  <?php if (in_array('logs.view', $permissions, true)): ?>
    <a href="/admin/logs">🪵 Logs</a>
    <a href="/admin/security">🔒 Acessos & Sessões</a>
  <?php endif; ?>

  <div style="font-size:11px;letter-spacing:1px;color:#64748b;margin:12px 0 2px;text-transform:uppercase">Conta</div>
  <a href="/profile">👤 Meu Perfil</a>
  <a href="/logout">Sair</a>
</aside>
