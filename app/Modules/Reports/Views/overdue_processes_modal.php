<?php
/**
 * Fragmento injetado no modal do "Carga contra capacidade": os processos que
 * FALHARAM o prazo naquele dia da semana e hora.
 *
 * Cada linha traz o tempo repartido por fila, trabalho, pausa e encerrado, e
 * a parcela que mais pesou. Saber que oito processos falharam às 16h não diz
 * o que fazer; ver que em seis deles o tempo foi quase todo espera do cliente
 * já diz — e é essa a pergunta a seguir a "quantos".
 *
 * @var array<int,array<string,mixed>> $processos  ordenados por excesso DESC
 * @var array{fila:int,trabalho:int,pausa:int,encerrado:int} $totais
 * @var string $label @var string $hora @var int $count @var int $excedeuMediana
 */

$paleta = ['fila' => '#2a78d6', 'trabalho' => '#eb6834', 'pausa' => '#1baf7a', 'encerrado' => '#eda100'];
$nomes = ['fila' => 'Na fila', 'trabalho' => 'A trabalhar', 'pausa' => 'Em pausa', 'encerrado' => 'Encerrado'];
$somaTotal = array_sum($totais);
?>
<div style="font-size:14px;color:#1f2937">

  <div style="margin-bottom:12px">
    <div style="font-size:18px;font-weight:700"><?= e($label) ?></div>
    <div style="color:#6b7280">
      Processos que <strong>falharam o prazo</strong>, entrados às <strong><?= e($hora) ?>h</strong>
    </div>
  </div>

  <?php if ($count === 0): ?>
    <p style="color:#6b7280">Nenhum processo falhou o prazo nesta hora e neste período.</p>
  <?php else: ?>

    <!-- Onde se perdeu o tempo, no conjunto -->
    <?php if ($somaTotal > 0): ?>
      <figure style="margin:0 0 14px">
        <figcaption style="font-weight:600;font-size:13.5px;color:#374151;margin-bottom:6px">
          Onde se perdeu o tempo destes <?= (int) $count ?> processos
        </figcaption>
        <div style="display:flex;height:26px;border-radius:4px;overflow:hidden;background:#f3f4f6;gap:2px">
          <?php foreach ($totais as $chave => $minutos): ?>
            <?php $percent = (int) round($minutos / $somaTotal * 100); ?>
            <?php if ($percent <= 0) continue; ?>
            <div style="width:<?= $percent ?>%;background:<?= $paleta[$chave] ?>;display:flex;align-items:center;justify-content:center"
                 title="<?= e($nomes[$chave]) ?>: <?= e(sla_human($minutos)) ?> no total (<?= $percent ?>%)">
              <?php if ($percent >= 12): ?>
                <span style="font-size:11px;font-weight:600;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.35)"><?= $percent ?>%</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:8px">
          <?php foreach ($nomes as $chave => $nome): ?>
            <span style="display:inline-flex;align-items:center;gap:6px;font-size:12.5px;color:#374151">
              <i style="width:11px;height:11px;border-radius:3px;background:<?= $paleta[$chave] ?>;display:inline-block"></i>
              <?= e($nome) ?> · <?= e(sla_human((int) $totais[$chave])) ?>
            </span>
          <?php endforeach; ?>
        </div>
        <p style="margin:8px 0 0;font-size:12.5px;color:#6b7280;max-width:80ch">
          A parcela maior é por onde começar. Se for <strong>fila</strong>, faltou quem pegasse nos processos a esta hora.
          Se for <strong>pausa</strong>, o atraso veio de fora — cliente, peças ou oficina — e o operador não o controlava.
        </p>
      </figure>
    <?php endif; ?>

    <!-- Resumo -->
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px">
      <div style="background:#fbeae8;border:1px solid #a5251c;border-radius:10px;padding:10px 14px">
        <div style="font-size:20px;font-weight:800;color:#a5251c"><?= (int) $count ?></div>
        <div style="color:#6b7280;font-size:12px">Fora do prazo</div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px">
        <div style="font-size:20px;font-weight:800"><?= e(sla_human($excedeuMediana)) ?></div>
        <div style="color:#6b7280;font-size:12px">Excesso típico (mediana)</div>
      </div>
    </div>

    <!-- Pesquisa -->
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
      <div style="position:relative;flex:1;max-width:320px">
        <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af">🔍</span>
        <input type="text" id="sla-proc-search" placeholder="Procurar pelo nº do processo…" autocomplete="off"
               style="width:100%;padding:8px 10px 8px 32px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px">
      </div>
      <span id="sla-proc-count" style="color:#6b7280;font-size:13px"></span>
    </div>

    <!-- Tabela -->
    <div style="max-height:44vh;overflow:auto;border:1px solid #e5e7eb;border-radius:8px">
      <table class="ops-table" style="margin:0">
        <thead>
          <tr>
            <th>Nº Processo</th><th>Cliente</th><th>Assunto</th><th>Equipa</th>
            <th>SLA</th><th>Demorou</th><th>Excedeu</th>
            <th>Fila</th><th>Trabalho</th><th>Pausa</th><th>Encerrado</th>
            <th>Maior peso</th><th>Fechado por</th>
          </tr>
        </thead>
        <tbody id="sla-proc-body">
          <?php foreach ($processos as $p): ?>
            <tr class="sla-proc-row" data-numero="<?= e(mb_strtolower((string) $p['processo'])) ?>">
              <td><a href="/processes/<?= (int) $p['id'] ?>" target="_blank"><?= e((string) $p['processo']) ?></a></td>
              <td><?= e((string) $p['cliente']) ?></td>
              <td><?= e((string) $p['assunto']) ?></td>
              <td style="white-space:nowrap"><?= e((string) $p['equipa']) ?></td>
              <td style="white-space:nowrap"><?= e(sla_human((int) $p['sla_minutos'])) ?></td>
              <td style="white-space:nowrap;font-weight:600"><?= e(sla_human((int) $p['minutos'])) ?></td>
              <td style="white-space:nowrap;color:#a5251c;font-weight:700">+<?= e(sla_human((int) $p['excedeu'])) ?></td>
              <td style="white-space:nowrap"><?= e(sla_human((int) $p['fila'])) ?></td>
              <td style="white-space:nowrap"><?= e(sla_human((int) $p['trabalho'])) ?></td>
              <td style="white-space:nowrap"><?= (int) $p['pausa'] > 0 ? e(sla_human((int) $p['pausa'])) : '<span style="color:#9ca3af">—</span>' ?></td>
              <td style="white-space:nowrap"><?= (int) $p['encerrado'] > 0 ? e(sla_human((int) $p['encerrado'])) : '<span style="color:#9ca3af">—</span>' ?></td>
              <td style="white-space:nowrap;font-weight:600"><?= e((string) $p['maior_peso']) ?></td>
              <td><?= ((string) $p['fechado_por']) !== '' ? e((string) $p['fechado_por']) : '<span style="color:#9ca3af">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          <tr id="sla-proc-empty" style="display:none">
            <td colspan="13" style="text-align:center;color:#6b7280">Nenhum processo com esse número.</td>
          </tr>
        </tbody>
      </table>
    </div>

  <?php endif; ?>
</div>
