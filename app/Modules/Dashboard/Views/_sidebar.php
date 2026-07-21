<?php
$unreadCount = \App\Core\Session::has('user_id')
    ? (new \App\Modules\Notification\Services\NotificationService())->countUnread((int) \App\Core\Session::get('user_id'))
    : 0;
$permissions = \App\Core\Session::get('permissions', []);

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

/**
 * Item de menu (link) com ícone alinhado e destaque do item ativo.
 * $match: 'exact' compara o caminho todo; 'prefix' destaca também as subpáginas.
 */
$navItem = static function (string $href, string $icon, string $label, string $currentPath, string $match = 'exact', string $badge = ''): string {
    $active = $match === 'prefix'
        ? ($currentPath === $href || str_starts_with($currentPath, $href . '/'))
        : $currentPath === $href;
    $cls = 'ops-nav' . ($active ? ' is-active' : '');

    return '<a href="' . e($href) . '" class="' . $cls . '">'
        . '<span class="ops-nav-ico">' . $icon . '</span>'
        . '<span>' . e($label) . '</span>'
        . $badge
        . '</a>';
};
?>
<style>
/* Regras críticas do layout responsivo, embutidas aqui de propósito:
   as views PHP ficam live logo após o git pull (via shim), enquanto o
   app.css depende de uma cópia para o public_html que já falhou várias
   vezes. Com isto, a barra do telemóvel nunca aparece no desktop mesmo
   que o app.css em produção esteja desatualizado. */
.ops-topbar { display: none; }
.ops-backdrop { display: none; }
@media (max-width: 860px) {
  .ops-shell { display: block; }
  .ops-topbar {
    display: flex; align-items: center; gap: 12px;
    position: fixed; top: 0; left: 0; right: 0; height: 54px;
    background: #0f172a; z-index: 40; padding: 0 12px;
  }
  .ops-burger { background: none; border: none; color: #fff; font-size: 26px; line-height: 1; cursor: pointer; padding: 4px 8px; }
  .ops-topbar-logo { height: 32px; background: #fff; border-radius: 6px; padding: 3px 7px; box-sizing: border-box; }
  .ops-sidebar {
    position: fixed; top: 0; left: 0; height: 100vh; z-index: 50;
    transform: translateX(-100%); transition: transform .25s ease; overflow-y: auto;
  }
  body.ops-nav-open .ops-sidebar { transform: translateX(0); }
  .ops-backdrop {
    display: block; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 45; opacity: 0; pointer-events: none; transition: opacity .25s ease;
  }
  body.ops-nav-open .ops-backdrop { opacity: 1; pointer-events: auto; }
  .ops-main { padding: 70px 14px 24px; }
  .ops-table { display: block; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
  .ops-panel { max-width: none !important; }
}
</style>
<div class="ops-topbar">
  <button class="ops-burger" type="button" aria-label="Abrir menu" onclick="document.body.classList.toggle('ops-nav-open')">☰</button>
  <img src="/img/irmaos-leite-logo.png" alt="Irmãos Leite" class="ops-topbar-logo" style="max-height:36px;width:auto">
</div>
<div class="ops-backdrop" onclick="document.body.classList.remove('ops-nav-open')"></div>

<aside class="ops-sidebar">
  <div class="ops-brand">
    <img src="/img/irmaos-leite-logo.png" alt="Irmãos Leite" style="max-width:100%;height:auto">
  </div>
  <span class="ops-brand-name">Operations Process System</span>

  <form method="GET" action="/search" style="margin-bottom:10px">
    <input type="text" name="q" placeholder="🔎 Pesquisar..." style="width:100%;box-sizing:border-box;padding:8px 10px;border-radius:8px;border:1px solid #334155;background:#1e293b;color:#fff">
  </form>

  <div class="ops-nav-section">Centro de Operações™</div>
  <?= $navItem('/dashboard', '📊', 'Dashboard', $currentPath) ?>
  <?= $navItem('/team', '🖥️', 'Tela Operacional', $currentPath) ?>
  <?= $navItem('/processes/create', '➕', 'Novo Processo', $currentPath) ?>
  <?php if (in_array('process.assume', $permissions, true)): ?>
    <form method="POST" action="/processes/next" style="margin:0">
      <?= csrf_field() ?>
      <button type="submit" class="ops-nav">
        <span class="ops-nav-ico">⏭️</span><span>Próximo Processo</span>
      </button>
    </form>
  <?php endif; ?>
  <?= $navItem('/processes/queue', '🚦', 'Fila Inteligente™', $currentPath) ?>
  <?= $navItem('/processes/mine', '📥', 'Minha Caixa de Entrada™', $currentPath) ?>
  <?php if (array_intersect(['process.view_all', 'process.view_branch'], $permissions) !== []): ?>
    <?= $navItem('/processes/all', '📂', 'Todos os Processos', $currentPath) ?>
  <?php endif; ?>
  <?php $alertBadge = $unreadCount > 0 ? '<span class="ops-nav-badge ops-badge" style="background:#dc2626">' . $unreadCount . '</span>' : ''; ?>
  <?= $navItem('/notifications', '🔔', 'Alertas', $currentPath, 'exact', $alertBadge) ?>

  <div class="ops-nav-section">Operação</div>
  <?= $navItem('/customers', '👥', 'Clientes', $currentPath, 'prefix') ?>
  <?= $navItem('/vehicles', '🚗', 'Viaturas', $currentPath, 'prefix') ?>
  <?= $navItem('/interactions', '💬', 'Interações', $currentPath) ?>
  <?= $navItem('/timeline', '📝', 'Timeline Global™', $currentPath) ?>
  <?php if (in_array('reports.export', $permissions, true)): ?>
    <?= $navItem('/reports', '📈', 'Relatórios', $currentPath, 'prefix') ?>
    <?= $navItem('/intelligence', '🧠', 'Inteligência Operacional™', $currentPath) ?>
  <?php endif; ?>

  <?php if (array_intersect(['users.manage', 'companies.manage', 'settings.manage', 'subjects.manage', 'sla_reasons.manage', 'sla_calendar.manage', 'audit.view', 'logs.view'], $permissions) !== []): ?>
    <div class="ops-nav-section">Administração</div>
  <?php endif; ?>
  <?php if (in_array('users.manage', $permissions, true)): ?>
    <?= $navItem('/admin/users', '👤', 'Utilizadores', $currentPath, 'prefix') ?>
    <?= $navItem('/admin/permissions', '🔑', 'Perfis & Permissões', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('companies.manage', $permissions, true)): ?>
    <?= $navItem('/admin/organization', '🏢', 'Organização', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('settings.manage', $permissions, true)): ?>
    <?= $navItem('/admin', '⚙️', 'Configurações', $currentPath, 'exact') ?>
  <?php endif; ?>
  <?php if (in_array('subjects.manage', $permissions, true)): ?>
    <?= $navItem('/admin/subject-departments', '🧩', 'Assuntos por Depto.', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('sla_reasons.manage', $permissions, true)): ?>
    <?= $navItem('/admin/sla-reasons', '⏸', 'Motivos de Pausa SLA', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('sla_calendar.manage', $permissions, true)): ?>
    <?= $navItem('/admin/sla-calendar', '🕘', 'Horário & Feriados SLA', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('audit.view', $permissions, true)): ?>
    <?= $navItem('/admin/audit', '📋', 'Auditoria', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('logs.view', $permissions, true)): ?>
    <?= $navItem('/admin/logs', '🪵', 'Logs', $currentPath) ?>
    <?= $navItem('/admin/security', '🔒', 'Acessos & Sessões', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('records.delete', $permissions, true)): ?>
    <?= $navItem('/admin/trash', '🗑️', 'Lixeira', $currentPath) ?>
  <?php endif; ?>
  <?php if (in_array('settings.manage', $permissions, true)): ?>
    <?= $navItem('/admin/tools', '🔧', 'Ferramentas', $currentPath) ?>
    <?= $navItem('/admin/import', '📥', 'Importação', $currentPath) ?>
  <?php endif; ?>

  <div class="ops-nav-section">Conta</div>
  <?= $navItem('/profile', '🪪', 'Meu Perfil', $currentPath) ?>
  <?= $navItem('/logout', '🚪', 'Terminar Sessão', $currentPath) ?>
</aside>

<!-- #6: Pop-up (toast) de novas notificações — ex.: nova lead na fila. -->
<div id="ops-toasts" style="position:fixed;top:16px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;max-width:340px"></div>
<script>
  (function () {
    var STORAGE_KEY = 'ops_last_notif_id';
    var container = document.getElementById('ops-toasts');
    if (!container) { return; }

    function showToast(item) {
      var el = document.createElement('div');
      el.style.cssText = 'background:#0f172a;color:#fff;border-left:4px solid #22c55e;border-radius:8px;padding:12px 14px;box-shadow:0 6px 20px rgba(0,0,0,.25);cursor:pointer;font-size:14px';
      el.innerHTML = '<strong style="display:block;margin-bottom:2px">' + item.title.replace(/</g,'&lt;') + '</strong><span style="opacity:.85">' + item.message.replace(/</g,'&lt;') + '</span>';
      el.addEventListener('click', function () { window.location.href = item.link || '/processes/queue'; });
      container.appendChild(el);
      setTimeout(function () { el.style.transition = 'opacity .4s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 400); }, 8000);
    }

    function poll() {
      fetch('/notifications/poll', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data || !data.items) { return; }
          var lastSeen = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
          var maxId = lastSeen;
          var isFirstRun = localStorage.getItem(STORAGE_KEY) === null;
          data.items.forEach(function (item) {
            if (item.id > maxId) { maxId = item.id; }
            // Na primeira execução não faz pop-up do histórico; só marca o ponto.
            if (!isFirstRun && item.id > lastSeen) { showToast(item); }
          });
          localStorage.setItem(STORAGE_KEY, String(maxId));
        })
        .catch(function () { /* silencioso */ });
    }

    poll();
    setInterval(poll, 30000);
  })();
</script>
