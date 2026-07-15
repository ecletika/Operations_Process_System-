<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Novo Processo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <h1>Novo Processo</h1>
      <p style="color:#6b7280">Indique a matrícula e o assunto. Se já existir um processo aberto para o mesmo caso, o sistema adiciona automaticamente uma interação em vez de duplicar.</p>

      <?php if (!empty($errors)): ?>
        <div class="ops-alert" style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626">
          <?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($reopenCandidate): ?>
        <div class="ops-panel" style="border-color:#f59e0b;background:#fffbeb">
          <strong>⚠️ Processo <?= e($reopenCandidate['process_number']) ?> foi encerrado recentemente para esta matrícula/assunto.</strong>
          <p style="color:#6b7280">Deseja reabrir o processo existente ou criar um processo novo?</p>
          <form method="POST" action="/processes" style="display:flex;gap:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="plate" value="<?= e($old['plate'] ?? '') ?>">
            <input type="hidden" name="customer_name" value="<?= e($old['customer_name'] ?? '') ?>">
            <input type="hidden" name="contact_channel" value="<?= e($old['contact_channel'] ?? 'PHONE') ?>">
            <input type="hidden" name="contact_value" value="<?= e($old['contact_value'] ?? '') ?>">
            <input type="hidden" name="subject_id" value="<?= e($old['subject_id'] ?? '') ?>">
            <input type="hidden" name="priority_id" value="<?= e($old['priority_id'] ?? '') ?>">
            <input type="hidden" name="description" value="<?= e($old['description'] ?? '') ?>">
            <button type="submit" name="reopen_if_eligible" value="1" class="ops-btn ops-btn-sm">Reabrir processo existente</button>
            <button type="submit" name="reopen_if_eligible" value="0" class="ops-btn ops-btn-sm" style="background:#6b7280">Criar processo novo</button>
          </form>
        </div>
      <?php else: ?>
        <div class="ops-panel">
          <form method="POST" action="/processes">
            <?= csrf_field() ?>
            <div class="ops-form-row">
              <label for="plate">Matrícula</label>
              <input type="text" id="plate" name="plate" value="<?= e($old['plate'] ?? '') ?>" placeholder="AA-00-AA" required>
            </div>
            <div class="ops-form-row">
              <label for="customer_name">Nome do Cliente</label>
              <input type="text" id="customer_name" name="customer_name" value="<?= e($old['customer_name'] ?? '') ?>" required>
              <small id="plate_hint" style="display:none;color:#16a34a;font-size:12px"></small>
            </div>
            <script>
              // RF-0037: ao sair do campo Matrícula, procura a viatura e, se já
              // existir, preenche automaticamente o nome do cliente.
              (function () {
                var plate = document.getElementById('plate');
                var name = document.getElementById('customer_name');
                var hint = document.getElementById('plate_hint');
                if (!plate || !name) { return; }

                plate.addEventListener('blur', function () {
                  var value = plate.value.trim();
                  if (value === '') { return; }
                  fetch('/processes/vehicle-lookup?plate=' + encodeURIComponent(value), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                      if (!data.found) {
                        hint.style.display = 'none';
                        return;
                      }
                      // Só preenche se o utilizador ainda não escreveu um nome.
                      if (name.value.trim() === '') { name.value = data.customer_name; }
                      var veic = [data.brand, data.model].filter(Boolean).join(' ');
                      hint.textContent = '✓ Viatura reconhecida: ' + data.customer_name + (veic ? ' — ' + veic : '');
                      hint.style.display = 'block';
                    })
                    .catch(function () { /* silencioso: o preenchimento é apenas uma ajuda */ });
                });
              })();
            </script>
            <?php if (!empty($canChooseBatch) && !empty($batches)): ?>
            <div class="ops-form-row">
              <label for="batch_id">Filial / Departamento (onde o processo entra na fila)</label>
              <select id="batch_id" name="batch_id" required>
                <?php $selBatch = (string) ($old['batch_id'] ?? $sessionBatchId); ?>
                <?php foreach ($batches as $batch): ?>
                  <option value="<?= (int) $batch['id'] ?>" <?= $selBatch === (string) $batch['id'] ? 'selected' : '' ?>>
                    <?= e(($batch['branch_name'] ?? '') !== '' ? $batch['branch_name'] . ' · ' . $batch['department_name'] : $batch['department_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php
              $contactChannel = $old['contact_channel'] ?? 'PHONE';
              $contactValue = $old['contact_value'] ?? '';
              $channelMeta = [
                'PHONE' => ['label' => 'Telefone', 'placeholder' => '+351 912 345 678', 'type' => 'text', 'icon' => '📞'],
                'WHATSAPP' => ['label' => 'WhatsApp', 'placeholder' => '+351 912 345 678', 'type' => 'text', 'icon' => '🟢'],
                'EMAIL' => ['label' => 'Email', 'placeholder' => 'cliente@exemplo.pt', 'type' => 'email', 'icon' => '✉️'],
                'IN_PERSON' => ['label' => 'Telefone ou Email', 'placeholder' => 'Telefone ou email do cliente', 'type' => 'text', 'icon' => '🧑'],
              ];
            ?>
            <div class="ops-form-row">
              <label for="contact_channel_select">Tipo de Interação</label>
              <select id="contact_channel_select" name="contact_channel">
                <option value="PHONE" <?= $contactChannel === 'PHONE' ? 'selected' : '' ?>>📞 Telefone</option>
                <option value="WHATSAPP" <?= $contactChannel === 'WHATSAPP' ? 'selected' : '' ?>>🟢 WhatsApp</option>
                <option value="EMAIL" <?= $contactChannel === 'EMAIL' ? 'selected' : '' ?>>✉️ Email</option>
                <option value="IN_PERSON" <?= $contactChannel === 'IN_PERSON' ? 'selected' : '' ?>>🧑 Presencial</option>
              </select>
            </div>
            <div class="ops-form-row">
              <label for="contact_value_input" id="contact_value_label"><?= e($channelMeta[$contactChannel]['icon']) ?> <?= e($channelMeta[$contactChannel]['label']) ?></label>
              <input type="<?= e($channelMeta[$contactChannel]['type']) ?>" id="contact_value_input" name="contact_value"
                     value="<?= e($contactValue) ?>" placeholder="<?= e($channelMeta[$contactChannel]['placeholder']) ?>" required>
            </div>
            <script>
              (function () {
                var meta = <?= json_encode($channelMeta, JSON_UNESCAPED_UNICODE) ?>;
                var select = document.getElementById('contact_channel_select');
                var input = document.getElementById('contact_value_input');
                var label = document.getElementById('contact_value_label');

                select.addEventListener('change', function () {
                  var info = meta[select.value];
                  input.type = info.type;
                  input.placeholder = info.placeholder;
                  input.value = '';
                  label.textContent = info.icon + ' ' + info.label;
                });
              })();
            </script>
            <div class="ops-form-row">
              <label for="subject_id">Assunto</label>
              <select id="subject_id" name="subject_id" required>
                <option value="">Selecione</option>
                <?php foreach ($subjects as $subject): ?>
                  <option value="<?= (int) $subject['id'] ?>" <?= (string) ($old['subject_id'] ?? '') === (string) $subject['id'] ? 'selected' : '' ?>>
                    <?= e($subject['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ops-form-row">
              <label for="priority_id">Prioridade</label>
              <select id="priority_id" name="priority_id" required>
                <option value="">Selecione</option>
                <?php foreach ($priorities as $priority): ?>
                  <option value="<?= (int) $priority['id'] ?>" <?= (string) ($old['priority_id'] ?? '') === (string) $priority['id'] ? 'selected' : '' ?>>
                    <?= e($priority['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="ops-form-row">
              <label for="description">Descrição do contacto</label>
              <textarea id="description" name="description" rows="4" required><?= e($old['description'] ?? '') ?></textarea>
            </div>
            <?php if (!empty($canChooseBatch) && !empty($batches)): ?>
            <script>
              // #5: a lista de Assuntos muda conforme a Filial/Departamento.
              (function () {
                var subjectsByDept = <?= json_encode($subjectsByDept ?? [], JSON_UNESCAPED_UNICODE) ?>;
                var batchDept = <?= json_encode(array_column($batches, 'department_id', 'id'), JSON_UNESCAPED_UNICODE) ?>;
                var batchSelect = document.getElementById('batch_id');
                var subjectSelect = document.getElementById('subject_id');
                if (!batchSelect || !subjectSelect) { return; }

                batchSelect.addEventListener('change', function () {
                  var deptId = batchDept[batchSelect.value];
                  var list = subjectsByDept[deptId];
                  if (!list) { return; } // sem config específica: mantém a lista atual
                  var previous = subjectSelect.value;
                  subjectSelect.innerHTML = '<option value="">Selecione</option>';
                  list.forEach(function (s) {
                    var opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name;
                    if (String(s.id) === previous) { opt.selected = true; }
                    subjectSelect.appendChild(opt);
                  });
                });
              })();
            </script>
            <?php endif; ?>
            <button type="submit" class="ops-btn">Criar Processo</button>
          </form>
        </div>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
