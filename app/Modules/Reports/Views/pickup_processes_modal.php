<?php
/**
 * Fragmento injetado no modal do "Tempo até alguém pegar": os processos de
 * uma equipa numa hora, um a um, ordenados do mais rápido para o mais lento.
 *
 * O gráfico é de valores ORDENADOS e não de dispersão por ordem de chegada:
 * ordenado, o ponto em que a linha dispara é o ponto de rutura, e vê-se de
 * relance quantos casos estragam a média. Por ordem de chegada não se via
 * nada — só uma nuvem de pontos.
 *
 * Escala vertical linear, de propósito: é ela que faz os casos extremos
 * saltarem para fora do gráfico. Uma escala logarítmica arrumava-os
 * bonitos e escondia exatamente o que se quer mostrar.
 *
 * @var array<int,array<string,mixed>> $processos  ordenados por minutos ASC
 * @var array{q1:?int,q3:?int,anomalo:?int,extremo:?int} $limites
 * @var int $mediana @var string $label @var string $hora
 * @var int $count @var int $media @var int $anomalos
 */

$maximo = $count > 0 ? max(1, (int) $processos[$count - 1]['minutos']) : 1;
$altura = static fn (int $min): float => max(0.8, $min / $maximo * 100);

$cores = ['normal' => '#2a78d6', 'anomalo' => '#9a5b06', 'extremo' => '#a5251c'];
$rotulos = ['normal' => 'Dentro do normal', 'anomalo' => '⚠️ Anómalo', 'extremo' => '🚨 Extremo'];
?>
<div style="font-size:14px;color:#1f2937">

  <div style="margin-bottom:12px">
    <div style="font-size:18px;font-weight:700"><?= e($label) ?></div>
    <div style="color:#6b7280">
      Processos que entraram às <strong><?= e($hora) ?>h</strong> · ordenados do mais rápido ao mais lento
    </div>
  </div>

  <?php if ($count === 0): ?>
    <p style="color:#6b7280">Sem processos assumidos nesta hora e neste período.</p>
  <?php else: ?>

    <!-- Resumo -->
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px">
      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px">
        <div style="font-size:20px;font-weight:800"><?= (int) $count ?></div>
        <div style="color:#6b7280;font-size:12px">Processos</div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px">
        <div style="font-size:20px;font-weight:800"><?= e(sla_human($mediana)) ?></div>
        <div style="color:#6b7280;font-size:12px">Mediana (caso típico)</div>
      </div>
      <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px">
        <div style="font-size:20px;font-weight:800"><?= e(sla_human($media)) ?></div>
        <div style="color:#6b7280;font-size:12px">Média</div>
      </div>
      <div style="background:<?= $anomalos > 0 ? '#fbf0de' : '#f8fafc' ?>;border:1px solid <?= $anomalos > 0 ? '#9a5b06' : '#e5e7eb' ?>;border-radius:10px;padding:10px 14px">
        <div style="font-size:20px;font-weight:800;color:<?= $anomalos > 0 ? '#9a5b06' : '#1f2937' ?>"><?= (int) $anomalos ?></div>
        <div style="color:#6b7280;font-size:12px">Fora do normal</div>
      </div>
    </div>

    <!-- Gráfico: valores ordenados -->
    <figure style="margin:0 0 8px">
      <figcaption style="font-weight:600;font-size:13.5px;color:#374151;margin-bottom:6px">
        Espera de cada processo, do mais rápido ao mais lento
      </figcaption>
      <div style="position:relative;height:150px;display:flex;align-items:flex-end;gap:1px;
                  border-left:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:0 2px 0 3px;overflow-x:auto">

        <?php if ($limites['anomalo'] !== null && $limites['anomalo'] < $maximo): ?>
          <?php // Linha do limiar: acima dela, o caso já não é normal. ?>
          <div title="Acima desta linha o caso sai do normal (Q3 + 1,5×IQR = <?= e(sla_human((int) $limites['anomalo'])) ?>)"
               style="position:absolute;left:0;right:0;bottom:<?= $altura((int) $limites['anomalo']) ?>%;
                      border-top:1px dashed #9a5b06;pointer-events:none;z-index:1">
            <span style="position:absolute;right:2px;top:-15px;font-size:10.5px;color:#9a5b06;background:#fff;padding:0 3px">
              limiar do anómalo · <?= e(sla_human((int) $limites['anomalo'])) ?>
            </span>
          </div>
        <?php endif; ?>

        <?php foreach ($processos as $p): ?>
          <div title="#<?= (int) $p['ordem'] ?> · <?= e((string) $p['processo']) ?> · <?= e(sla_human((int) $p['minutos'])) ?> · <?= e($rotulos[$p['classe']]) ?>"
               style="flex:1;min-width:3px;height:<?= $altura((int) $p['minutos']) ?>%;
                      background:<?= $cores[$p['classe']] ?>;border-radius:2px 2px 0 0;z-index:2"></div>
        <?php endforeach; ?>
      </div>
      <p style="margin:8px 0 0;font-size:12.5px;color:#6b7280;max-width:80ch">
        A linha mantém-se rasteira enquanto a fila corre normalmente. O ponto onde dispara é onde estão
        os processos que destroem a média — são esses que vale a pena abrir.
        <?php if ($limites['anomalo'] !== null): ?>
          O limiar é calculado a partir dos próprios dados desta hora (quartis), não é um valor escolhido à mão.
        <?php else: ?>
          Com menos de quatro processos não se marca nada como anómalo: não há distribuição que o sustente.
        <?php endif; ?>
      </p>
    </figure>

    <!-- Tabela -->
    <div style="max-height:40vh;overflow:auto;border:1px solid #e5e7eb;border-radius:8px;margin-top:14px">
      <table class="ops-table" style="margin:0">
        <thead>
          <tr>
            <th>#</th><th>Nº Processo</th><th>Cliente</th><th>Matrícula</th>
            <th>Entrada</th><th>Espera</th><th>Assumido por</th><th>Estado</th><th>Situação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($processos as $p): ?>
            <tr<?= $p['classe'] !== 'normal' ? ' style="background:' . ($p['classe'] === 'extremo' ? '#fbeae8' : '#fbf0de') . '"' : '' ?>>
              <td style="color:#9ca3af"><?= (int) $p['ordem'] ?></td>
              <td><a href="/processes/<?= (int) $p['id'] ?>" target="_blank"><?= e((string) $p['processo']) ?></a></td>
              <td><?= e((string) $p['cliente']) ?></td>
              <td style="white-space:nowrap"><?= e((string) $p['matricula']) ?></td>
              <td style="white-space:nowrap"><?= e((string) $p['entrada']) ?></td>
              <td style="white-space:nowrap;font-weight:600;color:<?= $cores[$p['classe']] ?>">
                <?= e(sla_human((int) $p['minutos'])) ?>
              </td>
              <td><?= ((string) $p['assumido_por']) !== '' ? e((string) $p['assumido_por']) : '<span style="color:#9ca3af">—</span>' ?></td>
              <td><?= e((string) $p['estado']) ?></td>
              <td style="white-space:nowrap;color:<?= $cores[$p['classe']] ?>"><?= e($rotulos[$p['classe']]) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php endif; ?>
</div>
