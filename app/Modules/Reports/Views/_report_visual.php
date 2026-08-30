<?php
/**
 * Bloco de ajuda ("ℹ️ Como ler este relatório") e gráficos de apoio,
 * incluído pela vista genérica de relatórios.
 *
 * Os gráficos são HTML e CSS puros — sem bibliotecas nem SVG gerado — para
 * não trazer dependências novas a um projeto que não tem build. Cada um vem
 * sempre acompanhado da tabela por baixo, que é a leitura exata: o gráfico
 * serve para ver a forma, a tabela para ler o número.
 *
 * Cores: paleta categórica de quatro tons (azul, laranja, verde-água,
 * amarelo) validada para daltonismo, com rótulo escrito em cada fatia —
 * a cor nunca é a única forma de identificar uma parcela.
 *
 * @var string $code
 * @var array<int,array<string,mixed>> $rows
 * @var array{o_que:string, decisao:string, como_ler:string}|null $help
 */

/** Converte "2h04m" ou "45m" de volta a minutos, para desenhar as barras. */
$emMinutos = static function (string $texto): int {
    if (preg_match('/^(\d+)h(\d+)m$/', $texto, $m) === 1) {
        return (int) $m[1] * 60 + (int) $m[2];
    }
    if (preg_match('/^(\d+)m$/', $texto, $m) === 1) {
        return (int) $m[1];
    }

    return 0;
};

$pct = static fn (string $texto): float => (float) rtrim($texto, '%');

$paleta = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];
?>

<?php if ($help !== null): ?>
  <details class="rel-help">
    <summary>ℹ️ Como ler este relatório</summary>
    <div class="rel-help-body">
      <p><strong>O que mostra.</strong> <?= e($help['o_que']) ?></p>
      <p><strong>Que decisão ajuda a tomar.</strong> <?= e($help['decisao']) ?></p>
      <p><strong>Como se lê.</strong> <?= e($help['como_ler']) ?></p>
    </div>
  </details>
<?php endif; ?>

<?php if (!empty($rows)): ?>

  <?php // ---------- Onde se perde o tempo: composição do ciclo ---------- ?>
  <?php if ($code === 'sla_breakdown'): ?>
    <figure class="rel-fig">
      <figcaption>Em que se gasta o tempo de cada processo, por equipa</figcaption>
      <div class="rel-legend">
        <?php foreach (['Na fila', 'A trabalhar', 'Em pausa', 'Encerrado'] as $i => $nome): ?>
          <span class="rel-key"><i style="background:<?= $paleta[$i] ?>"></i><?= e($nome) ?></span>
        <?php endforeach; ?>
      </div>
      <?php foreach ($rows as $r): ?>
        <?php
          $fatias = [
            ['Na fila', $pct((string) $r['pct_fila']), (string) $r['na_fila']],
            ['A trabalhar', $pct((string) $r['pct_trabalho']), (string) $r['a_trabalhar']],
            ['Em pausa', $pct((string) $r['pct_pausa']), (string) $r['em_pausa']],
            ['Encerrado', $pct((string) $r['pct_encerrado']), (string) $r['encerrado']],
          ];
        ?>
        <div class="rel-row">
          <div class="rel-row-label"><?= e((string) $r['equipa']) ?></div>
          <div class="rel-stack">
            <?php foreach ($fatias as $i => [$nome, $percent, $tempo]): ?>
              <?php if ($percent <= 0) continue; ?>
              <div class="rel-seg" style="width:<?= $percent ?>%;background:<?= $paleta[$i] ?>"
                   title="<?= e($nome) ?>: <?= e($tempo) ?> em média (<?= $percent ?>%)">
                <?php if ($percent >= 12): ?><span><?= $percent ?>%</span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </figure>
  <?php endif; ?>

  <?php // ---------- Tempo até alguém pegar: mediana por hora ---------- ?>
  <?php if ($code === 'sla_pickup'): ?>
    <?php
      // Uma faixa por equipa, com a hora no eixo — a mediana é o caso típico,
      // e é a leitura que interessa aqui (a média fica na tabela).
      $porEquipa = [];
      foreach ($rows as $r) {
          $porEquipa[(string) $r['equipa']][] = $r;
      }
      $maior = 1;
      foreach ($rows as $r) {
          $maior = max($maior, $emMinutos((string) $r['espera_mediana']));
      }
    ?>
    <figure class="rel-fig">
      <figcaption>Espera típica na fila (mediana), pela hora a que o processo entrou</figcaption>
      <p class="rel-note">Metade dos processos esperou menos do que a barra mostra. A média está na tabela e é sempre maior quando há casos esquecidos.</p>
      <?php foreach ($porEquipa as $equipa => $linhas): ?>
        <div class="rel-row rel-row-block">
          <div class="rel-row-label"><?= e($equipa) ?></div>
          <div class="rel-cols">
            <?php foreach ($linhas as $r): ?>
              <?php $min = $emMinutos((string) $r['espera_mediana']); ?>
              <div class="rel-col"
                   title="<?= e((string) $r['hora_de_entrada']) ?> · <?= (int) $r['processos'] ?> processo(s) · mediana <?= e((string) $r['espera_mediana']) ?> · pior caso <?= e((string) $r['pior_caso']) ?>">
                <div class="rel-col-bar" style="height:<?= max(2, (int) round($min / $maior * 100)) ?>%;background:<?= $paleta[0] ?>"></div>
                <div class="rel-col-x"><?= e(str_replace('h', '', (string) $r['hora_de_entrada'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </figure>
  <?php endif; ?>

  <?php // ---------- Prazos por assunto: cumprimento ---------- ?>
  <?php if ($code === 'sla_subject'): ?>
    <figure class="rel-fig">
      <figcaption>Cumprimento do prazo por assunto</figcaption>
      <?php foreach ($rows as $r): ?>
        <?php
          $valor = $pct((string) $r['pct_dentro_sla']);
          // Cor de estado, não da paleta de séries: aqui a cor diz "isto está
          // bem ou mal", e vem sempre com o número escrito ao lado.
          $cor = $valor >= 90 ? '#146c43' : ($valor >= 70 ? '#9a5b06' : '#a5251c');
        ?>
        <div class="rel-row">
          <div class="rel-row-label">
            <?= e((string) $r['assunto']) ?>
            <span class="rel-sub"><?= e((string) $r['prioridade']) ?> · <?= (int) $r['concluidos'] ?> proc.</span>
          </div>
          <div class="rel-bar-track">
            <div class="rel-bar" style="width:<?= max(1, $valor) ?>%;background:<?= $cor ?>"></div>
          </div>
          <div class="rel-row-value" style="color:<?= $cor ?>"><?= e((string) $r['pct_dentro_sla']) ?></div>
        </div>
      <?php endforeach; ?>
    </figure>
  <?php endif; ?>

  <?php // ---------- Carga contra capacidade ---------- ?>
  <?php if ($code === 'sla_load'): ?>
    <?php
      // Duas faixas separadas em vez de dois eixos no mesmo gráfico: são
      // grandezas diferentes (contagem e percentagem) e sobrepô-las daria
      // uma leitura falsa.
      $maxEntrados = 1;
      foreach ($rows as $r) {
          $maxEntrados = max($maxEntrados, (int) $r['entrados']);
      }
    ?>
    <figure class="rel-fig">
      <figcaption>Entradas por dia e hora</figcaption>
      <div class="rel-cols rel-cols-wide">
        <?php foreach ($rows as $r): ?>
          <div class="rel-col" title="<?= e((string) $r['dia']) ?> às <?= e((string) $r['hora_de_entrada']) ?> · <?= (int) $r['entrados'] ?> entrados">
            <div class="rel-col-bar" style="height:<?= max(2, (int) round((int) $r['entrados'] / $maxEntrados * 100)) ?>%;background:<?= $paleta[0] ?>"></div>
          </div>
        <?php endforeach; ?>
      </div>
    </figure>
    <figure class="rel-fig">
      <figcaption>Percentagem fora do prazo, nas mesmas horas</figcaption>
      <div class="rel-cols rel-cols-wide">
        <?php foreach ($rows as $r): ?>
          <?php $fora = $r['pct_fora'] === '—' ? 0 : $pct((string) $r['pct_fora']); ?>
          <div class="rel-col" title="<?= e((string) $r['dia']) ?> às <?= e((string) $r['hora_de_entrada']) ?> · <?= e((string) $r['pct_fora']) ?> fora do prazo">
            <div class="rel-col-bar" style="height:<?= max(2, (int) round($fora)) ?>%;background:<?= $fora >= 50 ? '#a5251c' : $paleta[1] ?>"></div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="rel-note">As duas faixas seguem a mesma ordem de dias e horas, para se comparar de cima para baixo. Não estão no mesmo gráfico de propósito: uma é uma contagem, a outra uma percentagem.</p>
    </figure>
  <?php endif; ?>

<?php endif; ?>
