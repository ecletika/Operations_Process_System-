<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Cliente · <?= e($customer['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/customers" style="color:#6b7280;text-decoration:none">← Lista de Clientes</a></p>
      <h1>👥 <?= e($customer['name']) ?></h1>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <div class="ops-panel" style="max-width:none">
        <h2 style="margin-top:0">Dados e Contactos</h2>
        <form method="POST" action="/customers/<?= (int) $customer['id'] ?>">
          <?= csrf_field() ?>
          <div style="display:flex;gap:12px;flex-wrap:wrap">
            <div class="ops-form-row" style="flex:2;min-width:200px">
              <label for="name">Nome</label>
              <input type="text" id="name" name="name" value="<?= e($customer['name']) ?>" required>
            </div>
            <div class="ops-form-row" style="flex:1;min-width:160px">
              <label for="phone">Telefone</label>
              <input type="text" id="phone" name="phone" value="<?= e($customer['phone'] ?? '') ?>" required>
            </div>
            <div class="ops-form-row" style="flex:1;min-width:200px">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?= e($customer['email'] ?? '') ?>">
            </div>
            <div class="ops-form-row" style="flex:1;min-width:120px">
              <label for="nif">NIF</label>
              <input type="text" id="nif" name="nif" value="<?= e($customer['nif'] ?? '') ?>">
            </div>
          </div>
          <button type="submit" class="ops-btn ops-btn-sm">Guardar</button>
        </form>
      </div>

      <h2 style="margin-top:32px">🚗 Viaturas (<?= count($vehicles) ?>)</h2>
      <table class="ops-table">
        <thead><tr><th>Matrícula</th><th>Marca</th><th>Modelo</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($vehicles)): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b7280">Sem viaturas.</td></tr>
          <?php endif; ?>
          <?php foreach ($vehicles as $vehicle): ?>
            <tr>
              <td><code><?= e($vehicle['plate']) ?></code></td>
              <td><?= e($vehicle['brand'] ?? '—') ?></td>
              <td><?= e($vehicle['model'] ?? '—') ?></td>
              <td><a href="/vehicles/<?= (int) $vehicle['id'] ?>" class="ops-btn ops-btn-sm">Histórico da Viatura</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:32px">📋 Processos (<?= count($processes) ?>)</h2>
      <table class="ops-table">
        <thead><tr><th>Nº Processo</th><th>Matrícula</th><th>Assunto</th><th>Estado</th><th>Prioridade</th><th>Contactos</th><th>Criado em</th></tr></thead>
        <tbody>
          <?php if (empty($processes)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280">Sem processos.</td></tr>
          <?php endif; ?>
          <?php foreach ($processes as $process): ?>
            <tr>
              <td><a href="/processes/<?= (int) $process['id'] ?>"><?= e($process['process_number']) ?></a></td>
              <td><code><?= e($process['vehicle_plate']) ?></code></td>
              <td><?= e($process['subject_name']) ?></td>
              <td><?= e($process['status_name']) ?></td>
              <td><span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span></td>
              <td><?= (int) $process['contact_count'] ?></td>
              <td style="color:#6b7280"><?= e($process['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h2 style="margin-top:32px">💬 Histórico de Contactos (<?= count($interactions) ?>)</h2>
      <table class="ops-table">
        <thead><tr><th>Quando</th><th>Canal</th><th>Tipo</th><th>Descrição</th><th>Processo</th><th>Operador</th></tr></thead>
        <tbody>
          <?php if (empty($interactions)): ?>
            <tr><td colspan="6" style="text-align:center;color:#6b7280">Sem contactos registados.</td></tr>
          <?php endif; ?>
          <?php foreach ($interactions as $interaction): ?>
            <tr>
              <td style="color:#6b7280;white-space:nowrap"><?= e($interaction['received_at']) ?></td>
              <td><?= e($interaction['channel']) ?></td>
              <td><?= e($interaction['interaction_type']) ?></td>
              <td><?= e(mb_substr((string) ($interaction['description'] ?? ''), 0, 80)) ?></td>
              <td><a href="/processes/<?= (int) $interaction['process_id'] ?>"><?= e($interaction['process_number']) ?></a></td>
              <td><?= e($interaction['first_name'] . ' ' . $interaction['last_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
