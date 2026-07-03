<?php
$fmtBytes = static function (int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 1) . ' MB';
};
$yn = static fn (bool $v): string => $v ? '<span style="color:#16a34a">✔ Sim</span>' : '<span style="color:#dc2626">✘ Não</span>';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Ferramentas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>🔧 Ferramentas</h1>
      <p style="color:#6b7280">Sistema &amp; Monitorização — estado do servidor, base de dados e armazenamento (só leitura).</p>

      <div style="display:flex;gap:20px;flex-wrap:wrap">
        <!-- Sistema -->
        <div style="flex:1;min-width:320px">
          <h2>🖥️ Sistema</h2>
          <table class="ops-table">
            <tbody>
              <tr><td style="color:#6b7280">PHP</td><td><?= e($php['version']) ?></td></tr>
              <tr><td style="color:#6b7280">Sistema Operativo</td><td><?= e($php['os']) ?></td></tr>
              <tr><td style="color:#6b7280">Servidor Web</td><td><?= e($php['server']) ?></td></tr>
              <tr><td style="color:#6b7280">Fuso horário</td><td><?= e($php['timezone']) ?></td></tr>
              <tr><td style="color:#6b7280">Limite de memória</td><td><?= e($php['memory_limit']) ?></td></tr>
              <tr><td style="color:#6b7280">Upload máx.</td><td><?= e($php['upload_max']) ?> / POST <?= e($php['post_max']) ?></td></tr>
              <tr><td style="color:#6b7280">Modo Debug</td>
                <td><?= $php['debug'] ? '<span style="color:#dc2626">Ativo ⚠️ (desligar em produção)</span>' : '<span style="color:#16a34a">Desligado ✔</span>' ?></td></tr>
            </tbody>
          </table>

          <h2 style="margin-top:24px">🧩 Extensões PHP</h2>
          <table class="ops-table">
            <tbody>
              <?php foreach ($extensions as $name => $loaded): ?>
                <tr><td style="color:#6b7280"><code><?= e($name) ?></code></td><td><?= $yn($loaded) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Base de dados + Armazenamento -->
        <div style="flex:1;min-width:320px">
          <h2>🗄️ Base de Dados</h2>
          <table class="ops-table">
            <tbody>
              <?php if ($db['ok']): ?>
                <tr><td style="color:#6b7280">Ligação</td><td><span style="color:#16a34a">✔ OK</span></td></tr>
                <tr><td style="color:#6b7280">MySQL</td><td><?= e($db['version']) ?></td></tr>
                <tr><td style="color:#6b7280">Base de dados</td><td><code><?= e($db['name']) ?></code></td></tr>
                <tr><td style="color:#6b7280">Tabelas / Vistas</td><td><?= (int) $db['tables'] ?></td></tr>
              <?php else: ?>
                <tr><td style="color:#6b7280">Ligação</td><td><span style="color:#dc2626">✘ Falhou</span></td></tr>
                <tr><td style="color:#6b7280">Erro</td><td style="color:#dc2626"><?= e($db['error']) ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>

          <h2 style="margin-top:24px">💾 Armazenamento</h2>
          <table class="ops-table">
            <tbody>
              <tr><td style="color:#6b7280">Anexos (uploads)</td><td><?= e($fmtBytes($storage['uploads_size'])) ?> · escrita <?= $yn($storage['uploads_writable']) ?></td></tr>
              <tr><td style="color:#6b7280">Logs</td><td><?= e($fmtBytes($storage['logs_size'])) ?> · <?= (int) $storage['log_files'] ?> ficheiro(s) · escrita <?= $yn($storage['logs_writable']) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <h2 style="margin-top:32px">🗃️ Migrations &amp; Seeders</h2>
      <p style="color:#6b7280;font-size:13px;margin-top:-4px">Ficheiros de instalação da base de dados incluídos no projeto.</p>
      <table class="ops-table" style="max-width:520px">
        <tbody>
          <?php foreach ($migrations as $file): ?>
            <tr><td><code><?= e($file) ?></code></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="ops-alert ops-alert-info" style="margin-top:24px">
        ℹ️ <strong>Backup e Importação</strong> devem ser feitos pelas ferramentas do alojamento
        (no cPanel: <em>Backup Wizard</em> e <em>phpMyAdmin → Exportar/Importar</em>), que fazem cópias
        completas e seguras da base de dados e dos ficheiros.
      </div>
    </main>
  </div>
</body>
</html>
