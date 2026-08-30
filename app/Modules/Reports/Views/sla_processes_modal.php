<?php
/**
 * Fragmento (sem layout) injetado no modal do Relatório SLA: lista os
 * processos concluídos que compõem uma célula (operador/equipa × prioridade),
 * com início, fim, tempo total, tempo em pausa e quem pausou, e no fim a média
 * e a % dentro do SLA.
 *
 * O tempo é contado com a mesma regra do resto do sistema (só horário de
 * atendimento, quando ligado), por isso bate certo com a ficha do processo.
 *
 * @var array<int,array<string,mixed>> $rows
 * @var string $label  nome do operador ou da equipa
 * @var string $priorityName
 * @var int $count @var int $within @var int $avg @var int $mediana @var ?int $pct
 */
?>
<div style="font-size:14px;color:#1f2937">
  <div style="margin-bottom:10px">
    <div style="font-size:18px;font-weight:700"><?= e($label) ?></div>
    <div style="color:#6b7280">Prioridade <strong><?= e($priorityName) ?></strong> · processos concluídos no período</div>
  </div>

  <?php if (!empty($rows)): ?>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
      <div style="position:relative;flex:1;max-width:320px">
        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af">🔍</span>
        <input type="text" id="sla-proc-search" placeholder="Procurar pelo nº do processo…"
               autocomplete="off"
               style="width:100%;padding:8px 10px 8px 32px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px">
      </div>
      <span id="sla-proc-count" style="color:#6b7280;font-size:13px"></span>
    </div>
  <?php endif; ?>

  <div style="max-height:52vh;overflow:auto;border:1px solid #e5e7eb;border-radius:8px">
    <table class="ops-table" style="margin:0">
      <thead>
        <tr>
          <th>Nº Processo</th><th>Cliente</th><th>Matrícula</th>
          <th>Início</th><th>Fim</th><th>Tempo total</th>
          <th>Em pausa</th><th>Pausado por</th><th>SLA</th>
        </tr>
      </thead>
      <tbody id="sla-proc-body">
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" style="text-align:center;color:#6b7280">Sem processos concluídos neste período.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <?php $pausa = (int) ($r['pausa_min'] ?? 0); ?>
          <tr class="sla-proc-row" data-numero="<?= e(mb_strtolower((string) $r['process_number'])) ?>">
            <td><a href="/processes/<?= (int) $r['id'] ?>" target="_blank"><?= e((string) $r['process_number']) ?></a></td>
            <td><?= e((string) $r['customer_name']) ?></td>
            <td style="white-space:nowrap"><?= e((string) $r['vehicle_plate']) ?></td>
            <td style="white-space:nowrap"><?= dt((string) $r['created_at']) ?></td>
            <td style="white-space:nowrap"><?= dt((string) $r['closed_at']) ?></td>
            <td style="white-space:nowrap"><?= e(sla_human((int) $r['tempo_total_min'])) ?></td>
            <td style="white-space:nowrap">
              <?php if ($pausa > 0): ?>
                <span title="Tempo em que o processo esteve em espera. O relógio do SLA esteve parado."
                      style="color:#2563eb;font-weight:600">⏸ <?= e(sla_human($pausa)) ?></span>
              <?php else: ?>
                <span style="color:#9ca3af">—</span>
              <?php endif; ?>
            </td>
            <td><?= ($r['pausa_por'] ?? '') !== '' ? e((string) $r['pausa_por']) : '<span style="color:#9ca3af">—</span>' ?></td>
            <td style="white-space:nowrap">
              <?php if ((int) $r['dentro_sla'] === 1): ?>
                <span style="color:#16a34a;font-weight:700">🟢 No prazo</span>
              <?php else: ?>
                <span style="color:#dc2626;font-weight:700">🔴 Fora</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <tr id="sla-proc-empty" style="display:none">
          <td colspan="9" style="text-align:center;color:#6b7280">Nenhum processo com esse número.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:14px">
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px">
      <div style="font-size:22px;font-weight:800"><?= (int) $count ?></div>
      <div style="color:#6b7280;font-size:12px">Processos concluídos</div>
    </div>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px"
         title="Metade dos processos demorou menos do que isto. É o caso típico.">
      <div style="font-size:22px;font-weight:800"><?= e(sla_human((int) $mediana)) ?></div>
      <div style="color:#6b7280;font-size:12px">Tempo mediano (caso típico)</div>
    </div>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px"
         title="Sobe com um só processo demorado. Compare com a mediana ao lado.">
      <div style="font-size:22px;font-weight:800"><?= e(sla_human((int) $avg)) ?></div>
      <div style="color:#6b7280;font-size:12px">Tempo médio de finalização</div>
    </div>
    <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px">
      <div style="font-size:22px;font-weight:800;color:<?= $pct === null ? '#6b7280' : ($pct >= 90 ? '#16a34a' : ($pct >= 70 ? '#b45309' : '#dc2626')) ?>">
        <?= $pct === null ? '—' : (int) $pct . '%' ?>
      </div>
      <div style="color:#6b7280;font-size:12px"><?= (int) $within ?>/<?= (int) $count ?> dentro do SLA</div>
    </div>
  </div>
</div>
