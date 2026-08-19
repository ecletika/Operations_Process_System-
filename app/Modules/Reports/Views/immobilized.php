<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Imobilizados — Cumprimento de Prazos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
  <style>
    .imob-legenda{display:flex;gap:18px;flex-wrap:wrap;font-size:13px;align-items:center;margin:2px 0}
    .imob-chip{display:inline-flex;align-items:center;gap:6px;font-weight:600}
    .imob-dot{width:11px;height:11px;border-radius:3px;display:inline-block}
    .imob-dot.ok{background:#16a34a}.imob-dot.late{background:#dc2626}
    .imob-scroller{overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
    table.imob{border-collapse:separate;border-spacing:0;width:max-content;min-width:100%;font-size:13px}
    table.imob th,table.imob td{border-right:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb;padding:8px 10px;vertical-align:top;text-align:left}
    table.imob thead th{background:#f1f5f9;color:#334155;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;position:sticky;top:0;z-index:3}
    .imob-s1{position:sticky;left:0;z-index:2;background:#fff;min-width:118px}
    table.imob thead .imob-s1{z-index:4;background:#f1f5f9}
    .imob-s2{position:sticky;left:118px;z-index:2;background:#fff;min-width:92px}
    table.imob thead .imob-s2{z-index:4;background:#f1f5f9}
    table.imob tbody tr:hover td{background:#f8fafc}
    table.imob tbody tr:hover .imob-s1,table.imob tbody tr:hover .imob-s2{background:#f8fafc}
    .imob-pnum a{color:#2563eb;text-decoration:none;font-weight:700}
    .imob-compl{display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid;white-space:nowrap}
    .imob-compl.ok{color:#16a34a;background:#eafaf0;border-color:#bbf7d0}
    .imob-compl.late{color:#dc2626;background:#fdecec;border-color:#fecaca}
    .cc{min-width:210px;max-width:240px}
    .cc.ok{background:#eafaf0}.cc.late{background:#fdecec}
    .cc .badge{display:inline-flex;align-items:center;gap:5px;font-weight:700;font-size:12px;padding:1px 8px;border-radius:999px;border:1px solid;background:#fff}
    .cc.ok .badge{color:#16a34a;border-color:#bbf7d0}.cc.late .badge{color:#dc2626;border-color:#fecaca}
    .cc .when{font-weight:700;margin-top:5px}
    .cc .canal{color:#6b7280;font-size:12px;margin-top:1px}
    .cc .fala{margin-top:5px;font-size:12.5px;color:#1f2937;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;cursor:help}
    .cc-empty{min-width:60px;color:#cbd5e1;text-align:center}
    .imob-hint{font-size:12px;color:#6b7280;margin:6px 2px 0}
  </style>
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/reports" style="color:#6b7280;text-decoration:none">← Relatórios</a></p>
      <h1>🅿️ Imobilizados — Cumprimento de Prazos</h1>
      <p style="color:#6b7280">
        Cada linha é um processo Imobilizado; os contactos leem-se da esquerda para a direita.
        Prazo: contactar o cliente no máximo a cada <strong>16h úteis</strong> — o relógio reinicia a cada contacto.
      </p>

      <?php $summary = $report['summary']; ?>

      <!-- FILTROS -->
      <form method="GET" action="/reports/imobilizados" class="ops-panel" style="display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;max-width:none">
        <div class="ops-form-row" style="margin:0"><label for="from">Data de</label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="ops-form-row" style="margin:0"><label for="to">Data até</label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
        <div class="ops-form-row" style="margin:0"><label for="plate">Matrícula</label><input type="text" id="plate" name="plate" value="<?= e($plate) ?>" placeholder="ex.: 67-22-XB"></div>
        <div class="ops-form-row" style="margin:0"><label for="vehicle">Viatura (marca/modelo)</label><input type="text" id="vehicle" name="vehicle" value="<?= e($vehicle) ?>" placeholder="ex.: SEAT Ibiza"></div>
        <button type="submit" class="ops-btn ops-btn-sm">Filtrar</button>
        <button type="submit" formaction="/reports/imobilizados.xls" class="ops-btn ops-btn-sm" style="background:#16a34a">⬇ Exportar Excel</button>
      </form>

      <!-- KPIs -->
      <div class="ops-kpis">
        <div class="ops-kpi"><div class="value"><?= (int) $summary['processes'] ?></div><div class="label">Processos Imobilizados</div></div>
        <div class="ops-kpi"><div class="value"><?= (int) $summary['contacts'] ?></div><div class="label">Contactos registados</div></div>
        <div class="ops-kpi"><div class="value" style="color:#16a34a"><?= (int) $summary['onTime'] ?></div><div class="label">No prazo</div></div>
        <div class="ops-kpi"><div class="value" style="color:#dc2626"><?= (int) $summary['late'] ?></div><div class="label">Em atraso</div></div>
        <div class="ops-kpi"><div class="value"><?= $summary['pct'] === null ? '—' : (int) $summary['pct'] . '%' ?></div><div class="label">Cumprimento</div></div>
      </div>

      <div class="ops-panel" style="padding:10px 16px;max-width:none">
        <div class="imob-legenda">
          <span class="imob-chip" style="color:#16a34a"><span class="imob-dot ok"></span>🟢 Contacto no prazo (≤ 16h úteis)</span>
          <span class="imob-chip" style="color:#dc2626"><span class="imob-dot late"></span>🔴 Contacto em atraso (&gt; 16h úteis)</span>
          <span class="imob-chip" style="color:#6b7280">Passe o rato sobre um contacto para ler o que foi falado na íntegra</span>
        </div>
      </div>

      <?php if (!empty($report['truncated'])): ?>
        <div class="ops-panel" style="max-width:none;border-left:4px solid #b45309;color:#92400e;background:#fffbeb">
          A mostrar os primeiros <?= (int) $report['limit'] ?> processos (os mais recentes). Refine o intervalo de datas ou a matrícula para ver os restantes.
        </div>
      <?php endif; ?>

      <?php if ($report['processes'] === []): ?>
        <div class="ops-panel" style="max-width:none;color:#6b7280">Nenhum processo Imobilizado encontrado com estes filtros.</div>
      <?php else: ?>
        <?php $maxC = max(1, (int) $report['maxContacts']); ?>
        <div class="imob-scroller">
          <table class="imob">
            <thead>
              <tr>
                <th class="imob-s1">Nº Processo</th>
                <th class="imob-s2">Matrícula</th>
                <th>Viatura</th>
                <th>Cliente</th>
                <th>Início</th>
                <th>Cumprimento</th>
                <?php for ($i = 1; $i <= $maxC; $i++): ?>
                  <th>Contacto <?= $i ?></th>
                <?php endfor; ?>
                <th>Próximo previsto</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($report['processes'] as $p): ?>
                <tr>
                  <td class="imob-s1 imob-pnum">
                    <a href="/processes/<?= (int) $p['id'] ?>"><?= e($p['process_number']) ?></a>
                    <div style="color:#6b7280;font-weight:400"><?= e($p['status_name']) ?></div>
                  </td>
                  <td class="imob-s2" style="white-space:nowrap"><strong><?= e($p['plate']) ?></strong></td>
                  <td style="white-space:nowrap"><?= e($p['vehicle'] ?: '—') ?></td>
                  <td><?= e($p['customer_name']) ?></td>
                  <td style="white-space:nowrap"><?= e($p['start']) ?></td>
                  <td>
                    <?php if ($p['late'] > 0): ?>
                      <span class="imob-compl late">⚠ <?= (int) $p['late'] ?> em atraso</span>
                    <?php elseif ($p['pct'] !== null): ?>
                      <span class="imob-compl ok">✓ <?= (int) $p['onTime'] ?>/<?= (int) ($p['onTime'] + $p['late']) ?> · 100%</span>
                    <?php else: ?>
                      <span style="color:#9ca3af">—</span>
                    <?php endif; ?>
                  </td>

                  <?php for ($i = 0; $i < $maxC; $i++): ?>
                    <?php $c = $p['contacts'][$i] ?? null; ?>
                    <?php if ($c === null): ?>
                      <td class="cc-empty">—</td>
                    <?php else: ?>
                      <td class="cc <?= $c['onTime'] ? 'ok' : 'late' ?>">
                        <span class="badge"><?= $c['onTime'] ? '🟢 ' . e($c['humanGap']) : '🔴 ' . e($c['humanGap']) . ' · +' . e($c['humanOver']) ?></span>
                        <div class="when"><?= e($c['when']) ?></div>
                        <div class="canal"><?= e($c['channel']) ?><?= $c['who'] !== '' ? ' · ' . e($c['who']) : '' ?></div>
                        <?php if ($c['text'] !== ''): ?>
                          <div class="fala" title="<?= e($c['text']) ?>"><?= e($c['text']) ?></div>
                        <?php endif; ?>
                      </td>
                    <?php endif; ?>
                  <?php endfor; ?>

                  <td class="cc <?= $p['closed'] ? '' : ($p['next'] && $p['next']['overdue'] ? 'late' : 'ok') ?>">
                    <?php if ($p['closed']): ?>
                      <span style="color:#9ca3af">— concluído —</span>
                    <?php elseif ($p['next'] !== null && $p['next']['overdue']): ?>
                      <span class="badge">🔴 Em atraso</span>
                      <div class="when"><?= e($p['next']['due']) ?></div>
                      <div class="canal">Prazo ultrapassado há <?= e($p['next']['humanOver']) ?></div>
                    <?php elseif ($p['next'] !== null): ?>
                      <span class="badge">🟢 Dentro do prazo</span>
                      <div class="when"><?= e($p['next']['due']) ?></div>
                      <div class="canal">Faltam <?= e($p['next']['humanRemaining']) ?></div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="imob-hint">↔ A grelha desliza na horizontal quando um processo tem muitos contactos. As colunas Nº Processo e Matrícula ficam fixas à esquerda.</p>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
