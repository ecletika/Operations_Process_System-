<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <title>OPS · Replay · <?= e($process['process_number']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/css/app.css">
</head>
<body>
  <div class="ops-shell">
    <?php require dirname(__DIR__, 2) . '/Dashboard/Views/_sidebar.php'; ?>
    <main class="ops-main">
      <p><a href="/processes/<?= (int) $process['id'] ?>">&larr; Voltar ao processo</a></p>
      <h1>🎬 Operations Replay™ · <?= e($process['process_number']) ?></h1>
      <p style="color:#6b7280">Reproduz a história completa do processo, passo a passo, exatamente como aconteceu.</p>

      <?php if (empty($steps)): ?>
        <p style="color:#6b7280">Sem eventos registados para reproduzir.</p>
      <?php else: ?>
        <div class="ops-replay">
          <div class="ops-replay-stage">
            <div class="ops-replay-icon" id="replayIcon">🎬</div>
            <div class="ops-replay-body">
              <div class="ops-replay-meta">
                <span id="replayStepCount">1 / <?= count($steps) ?></span>
                <span id="replayClock"></span>
                <span id="replayElapsed"></span>
              </div>
              <div class="ops-replay-title" id="replayTitle"></div>
              <div class="ops-replay-description" id="replayDescription"></div>
              <div class="ops-replay-actor" id="replayActor"></div>
            </div>
          </div>

          <div class="ops-replay-track" id="replayTrack"></div>

          <div class="ops-replay-controls">
            <button type="button" class="ops-btn ops-btn-sm" id="replayPrev">⏮ Anterior</button>
            <button type="button" class="ops-btn" id="replayPlay">▶ Reproduzir</button>
            <button type="button" class="ops-btn ops-btn-sm" id="replayNext">Seguinte ⏭</button>
            <select id="replaySpeed" style="padding:8px;border-radius:8px;border:1px solid #e5e7eb">
              <option value="2000">Lento</option>
              <option value="1200" selected>Normal</option>
              <option value="600">Rápido</option>
            </select>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <script>
    const steps = <?= json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const icons = {
      'plus-circle': '🆕', 'user-check': '🙋', 'phone': '☎️', 'file-text': '📝',
      'paperclip': '📎', 'refresh-cw': '🔄', 'check-circle': '✅', 'rotate-ccw': '↩️',
      'circle': '🔹',
    };

    function formatDuration(seconds) {
      if (seconds < 60) return seconds + 's';
      if (seconds < 3600) return Math.floor(seconds / 60) + 'min';
      if (seconds < 86400) return Math.floor(seconds / 3600) + 'h' + Math.floor((seconds % 3600) / 60) + 'min';
      return Math.floor(seconds / 86400) + 'd ' + Math.floor((seconds % 86400) / 3600) + 'h';
    }

    (function initReplay() {
      if (!steps.length) return;

      const track = document.getElementById('replayTrack');
      steps.forEach((step, index) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'ops-replay-dot';
        dot.style.background = step.color || '#6b7280';
        dot.title = step.title;
        dot.addEventListener('click', () => goTo(index));
        track.appendChild(dot);
      });

      let current = 0;
      let timer = null;

      function render() {
        const step = steps[current];
        document.getElementById('replayIcon').textContent = icons[step.icon] || '🔹';
        document.getElementById('replayIcon').style.background = step.color || '#6b7280';
        document.getElementById('replayStepCount').textContent = (current + 1) + ' / ' + steps.length;
        document.getElementById('replayClock').textContent = step.created_at;
        document.getElementById('replayElapsed').textContent = current === 0
          ? 'Início do processo'
          : '+' + formatDuration(step.seconds_since_previous) + ' desde o passo anterior · '
            + formatDuration(step.seconds_since_start) + ' desde o início';
        document.getElementById('replayTitle').textContent = step.title;
        document.getElementById('replayDescription').textContent = step.description || '';
        document.getElementById('replayActor').textContent = step.actor ? ('por ' + step.actor) : '';

        [...track.children].forEach((dot, index) => {
          dot.classList.toggle('is-active', index === current);
        });
      }

      function goTo(index) {
        current = Math.max(0, Math.min(steps.length - 1, index));
        render();
      }

      function stop() {
        clearInterval(timer);
        timer = null;
        document.getElementById('replayPlay').textContent = '▶ Reproduzir';
      }

      document.getElementById('replayPrev').addEventListener('click', () => { stop(); goTo(current - 1); });
      document.getElementById('replayNext').addEventListener('click', () => { stop(); goTo(current + 1); });

      document.getElementById('replayPlay').addEventListener('click', function () {
        if (timer) {
          stop();
          return;
        }

        if (current === steps.length - 1) {
          current = 0;
        }

        this.textContent = '⏸ Pausar';
        const speed = parseInt(document.getElementById('replaySpeed').value, 10);
        timer = setInterval(() => {
          if (current >= steps.length - 1) {
            stop();
            return;
          }
          goTo(current + 1);
        }, speed);
      });

      render();
    })();
  </script>
</body>
</html>
