<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · <?= e($process['process_number']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1><?= e($process['process_number']) ?>
        <span class="ops-badge" style="background:<?= e($process['priority_color']) ?>"><?= e($process['priority_name']) ?></span>
        <?php if ($isFrequentCustomer): ?><span class="ops-badge" style="background:#7c3aed" title="RN-0059">Cliente Frequente</span><?php endif; ?>
        <?php if ($isRecurrentVehicle): ?><span class="ops-badge" style="background:#b45309" title="RN-0060">Viatura Recorrente</span><?php endif; ?>
      </h1>
      <p style="color:#6b7280">
        Estado: <strong><?= e($process['status_name']) ?></strong>
        · Cliente: <?= e($process['customer_name']) ?> (<?= e($process['customer_phone']) ?>)
        · Matrícula: <?= e($process['vehicle_plate']) ?>
        · Assunto: <?= e($process['subject_name']) ?>
        <br>
        Criado por: <strong><?= $process['creator_first_name'] ? e($process['creator_first_name'] . ' ' . $process['creator_last_name']) : '—' ?></strong>
        em <?= e($process['created_at']) ?>
        <?php if ($process['assigned_first_name']): ?>
          · Responsável: <?= online_dot($process['assigned_last_activity'] ?? null) ?><strong><?= e($process['assigned_first_name'] . ' ' . $process['assigned_last_name']) ?></strong>
        <?php endif; ?>
      </p>

      <?php if ($success): ?><div class="ops-alert ops-alert-success"><?= e($success) ?></div><?php endif; ?>
      <?php foreach ($errors as $error): ?><div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"><?= e($error) ?></div><?php endforeach; ?>

      <div style="display:flex;gap:10px;margin-bottom:20px">
        <?php if ($process['status_code'] === 'QUEUE'): ?>
          <form method="POST" action="/processes/<?= (int) $process['id'] ?>/assume">
            <?= csrf_field() ?>
            <button type="submit" class="ops-btn ops-btn-sm">Assumir Processo</button>
          </form>
        <?php endif; ?>

        <?php if (!in_array($process['status_code'], ['SOLVED', 'CLOSED'], true)): ?>
          <form method="POST" action="/processes/<?= (int) $process['id'] ?>/close">
            <?= csrf_field() ?>
            <button type="submit" class="ops-btn ops-btn-sm" style="background:#16a34a">Concluir Processo</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($process['status_code'], ['SOLVED', 'CLOSED'], true)): ?>
          <form method="POST" action="/processes/<?= (int) $process['id'] ?>/reopen">
            <?= csrf_field() ?>
            <button type="submit" class="ops-btn ops-btn-sm" style="background:#dc2626">Reabrir Processo</button>
          </form>
        <?php endif; ?>

        <?php if (!in_array($process['status_code'], ['QUEUE', 'SOLVED', 'CLOSED'], true) && in_array('process.assume', \App\Core\Session::get('permissions', []), true)): ?>
          <form method="POST" action="/processes/<?= (int) $process['id'] ?>/release"
                onsubmit="return confirm('Devolver este processo à Fila Inteligente™ para outro operador o assumir?');">
            <?= csrf_field() ?>
            <button type="submit" class="ops-btn ops-btn-sm" style="background:#f59e0b">↩️ Voltar para a Fila</button>
          </form>
        <?php endif; ?>

        <?php if (in_array($process['status_code'], ['SOLVED', 'CLOSED'], true) && in_array('process.close', \App\Core\Session::get('permissions', []), true)): ?>
          <form method="POST" action="/processes/<?= (int) $process['id'] ?>/archive">
            <?= csrf_field() ?>
            <?php if ((int) ($process['archived'] ?? 0) === 1): ?>
              <input type="hidden" name="archive" value="0">
              <button type="submit" class="ops-btn ops-btn-sm" style="background:#6b7280">📤 Desarquivar</button>
            <?php else: ?>
              <input type="hidden" name="archive" value="1">
              <button type="submit" class="ops-btn ops-btn-sm" style="background:#0891b2">📦 Arquivar</button>
            <?php endif; ?>
          </form>
        <?php endif; ?>

        <a href="/processes/<?= (int) $process['id'] ?>/replay" class="ops-btn ops-btn-sm" style="background:#7c3aed;text-decoration:none;display:inline-flex;align-items:center">🎬 Reproduzir Processo</a>

        <?php if (in_array('process.delete', \App\Core\Session::get('permissions', []), true)): ?>
          <form method="POST" action="/processes/<?= (int) $process['id'] ?>/delete"
                onsubmit="return confirm('Excluir definitivamente o processo <?= e($process['process_number']) ?> das listagens? Esta ação só pode ser desfeita por um Administrador diretamente na base de dados.');">
            <?= csrf_field() ?>
            <button type="submit" class="ops-btn ops-btn-sm" style="background:#374151">🗑️ Excluir Processo</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="ops-kpis">
        <div class="ops-kpi"><div class="value"><?= (int) ($dna['total_minutes'] / 60) ?>h<?= $dna['total_minutes'] % 60 ?>m</div><div class="label">Tempo Total</div></div>
        <div class="ops-kpi"><div class="value"><?= (int) $dna['contact_count'] ?></div><div class="label">Contactos</div></div>
        <div class="ops-kpi"><div class="value"><?= (int) $dna['events_total'] ?></div><div class="label">Eventos</div></div>
        <div class="ops-kpi"><div class="value"><?= (int) $dna['reopen_count'] ?></div><div class="label">Reaberturas</div></div>
        <div class="ops-kpi">
          <div class="value"><?= $dna['sla_met'] === null ? '—' : ($dna['sla_met'] ? '🟢' : '🔴') ?></div>
          <div class="label">SLA <?= $dna['sla_minutes'] !== null ? "({$dna['sla_minutes']} min)" : '' ?></div>
        </div>
      </div>

      <h2 style="margin-top:32px">Timeline Viva™</h2>
      <ul class="ops-timeline">
        <?php foreach ($timeline as $item): ?>
          <li style="border-left-color: <?= e($item['color']) ?>">
            <div class="time"><?= e($item['created_at']) ?></div>
            <div class="title"><?= e($item['title']) ?></div>
            <?php if ($item['description']): ?><div><?= e($item['description']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>

      <h2 style="margin-top:32px">Interações</h2>
      <table class="ops-table">
        <thead><tr><th>Data</th><th>Operador</th><th>Canal</th><th>Descrição</th></tr></thead>
        <tbody>
          <?php foreach ($interactions as $interaction): ?>
            <tr>
              <td><?= e($interaction['received_at']) ?></td>
              <td><?= e($interaction['first_name'] . ' ' . $interaction['last_name']) ?></td>
              <td><?= e($interaction['channel']) ?></td>
              <td><?= e($interaction['description']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <h2 style="margin-top:32px">Observações</h2>
      <form method="POST" action="/processes/<?= (int) $process['id'] ?>/notes" class="ops-panel" style="max-width:none">
        <?= csrf_field() ?>
        <div class="ops-form-row">
          <textarea name="note" rows="3" placeholder="Escreva uma observação interna..." required></textarea>
        </div>
        <button type="submit" class="ops-btn ops-btn-sm">Adicionar Observação</button>
      </form>

      <?php foreach ($notes as $note): ?>
        <?php
          $ageMinutes = (time() - strtotime((string) $note['created_at'])) / 60;
          $canEdit = (int) $note['author_id'] === $currentUserId && $ageMinutes <= 10;
        ?>
        <div class="ops-panel" style="max-width:none;margin-top:10px">
          <div style="color:#6b7280;font-size:12px">
            <?= e($note['first_name'] . ' ' . $note['last_name']) ?> · <?= e($note['created_at']) ?>
            · versão <?= (int) $note['version'] ?>
            <?= $canEdit ? '· editável durante mais ' . max(0, 10 - (int) $ageMinutes) . ' min' : '' ?>
          </div>
          <?php if ($canEdit): ?>
            <form method="POST" action="/notes/<?= (int) $note['id'] ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="process_id" value="<?= (int) $process['id'] ?>">
              <div class="ops-form-row" style="margin-top:8px">
                <textarea name="note" rows="2" required><?= e($note['note']) ?></textarea>
              </div>
              <button type="submit" class="ops-btn ops-btn-sm">Guardar edição</button>
            </form>
          <?php else: ?>
            <div style="margin-top:8px"><?= nl2br(e($note['note'])) ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <h2 style="margin-top:32px">Anexos</h2>
      <form method="POST" action="/processes/<?= (int) $process['id'] ?>/attachments" enctype="multipart/form-data" style="margin-bottom:12px">
        <?= csrf_field() ?>
        <input type="file" name="file" required>
        <button type="submit" class="ops-btn ops-btn-sm">Enviar Anexo</button>
        <span style="color:#6b7280;font-size:12px">PDF, JPG, PNG, DOCX, XLSX, ZIP · máx. 20MB</span>
      </form>

      <table class="ops-table">
        <thead><tr><th>Ficheiro</th><th>Enviado por</th><th>Data</th><th>Tamanho</th><th></th></tr></thead>
        <tbody>
          <?php if (empty($attachments)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b7280">Sem anexos.</td></tr>
          <?php endif; ?>
          <?php foreach ($attachments as $attachment): ?>
            <tr>
              <td><?= e($attachment['original_name']) ?></td>
              <td><?= e($attachment['first_name'] . ' ' . $attachment['last_name']) ?></td>
              <td><?= e($attachment['created_at']) ?></td>
              <td><?= number_format((int) $attachment['file_size'] / 1024, 1) ?> KB</td>
              <td><a href="/attachments/<?= (int) $attachment['id'] ?>" target="_blank">Abrir</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </main>
  </div>
</body>
</html>
