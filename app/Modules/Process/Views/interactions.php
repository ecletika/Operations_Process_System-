<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Interações</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>💬 Interações</h1>
      <p style="color:#6b7280">Histórico de Contactos de toda a operação. Para registar uma <strong>Nova Interação</strong>, abram o processo respetivo — cada interação pertence sempre a um processo (RF-0016).</p>

      <?php
        $tabs = [
          '' => 'Todas',
          'PHONE' => '📞 Contactos Telefónicos',
          'WHATSAPP' => '🟢 WhatsApp',
          'EMAIL' => '✉️ Emails',
          'IN_PERSON' => '🧑 Presencial',
        ];
      ?>
      <div style="display:flex;gap:8px;margin:16px 0 12px;border-bottom:1px solid #e5e7eb">
        <?php foreach ($tabs as $channelKey => $label): ?>
          <a href="/interactions?channel=<?= urlencode($channelKey) ?>"
             style="padding:10px 16px;text-decoration:none;font-weight:600;font-size:14px;
                    color:<?= $filters['channel'] === $channelKey ? '#2563eb' : '#6b7280' ?>;
                    border-bottom:2px solid <?= $filters['channel'] === $channelKey ? '#2563eb' : 'transparent' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>

      <form method="GET" action="/interactions" class="ops-panel" style="max-width:none">
        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <div class="ops-form-row" style="flex:1;min-width:160px">
            <label for="channel">Canal</label>
            <select id="channel" name="channel">
              <option value="">Todos</option>
              <?php foreach ($channels as $channel): ?>
                <option value="<?= e($channel) ?>" <?= $filters['channel'] === $channel ? 'selected' : '' ?>><?= e($channel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="flex:1;min-width:180px">
            <label for="operator_id">Operador</label>
            <select id="operator_id" name="operator_id">
              <option value="">Todos</option>
              <?php foreach ($operators as $operator): ?>
                <option value="<?= (int) $operator['id'] ?>" <?= (string) $filters['operator_id'] === (string) $operator['id'] ? 'selected' : '' ?>>
                  <?= e($operator['first_name'] . ' ' . $operator['last_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="ops-form-row" style="min-width:150px">
            <label for="date_from">De</label>
            <input type="date" id="date_from" name="date_from" value="<?= e($filters['date_from']) ?>">
          </div>
          <div class="ops-form-row" style="min-width:150px">
            <label for="date_to">Até</label>
            <input type="date" id="date_to" name="date_to" value="<?= e($filters['date_to']) ?>">
          </div>
        </div>
        <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
        <a href="/interactions" class="ops-btn ops-btn-sm" style="background:#6b7280;text-decoration:none">Limpar filtros</a>
      </form>

      <p style="color:#6b7280;margin-top:12px"><?= count($interactions) ?> interação(ões).</p>

      <table class="ops-table">
        <thead><tr><th>Quando</th><th>Canal</th><th>Tipo</th><th>Cliente</th><th>Processo</th><th>Operador</th><th>Descrição</th></tr></thead>
        <tbody>
          <?php if (empty($interactions)): ?>
            <tr><td colspan="7" style="text-align:center;color:#6b7280">Nenhuma interação encontrada com estes filtros.</td></tr>
          <?php endif; ?>
          <?php foreach ($interactions as $interaction): ?>
            <tr>
              <td style="color:#6b7280;white-space:nowrap"><?= dt($interaction['received_at']) ?></td>
              <td><?= e($interaction['channel']) ?></td>
              <td><?= e($interaction['interaction_type']) ?></td>
              <td><?= e($interaction['customer_name']) ?></td>
              <td><a href="/processes/<?= (int) $interaction['process_id'] ?>"><?= e($interaction['process_number']) ?></a></td>
              <td><?= e($interaction['first_name'] . ' ' . $interaction['last_name']) ?></td>
              <td><?= e(mb_substr((string) ($interaction['description'] ?? ''), 0, 70)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
