<?php

declare(strict_types=1);

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__, 2) . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return \App\Core\Session::pullFlash('_old_' . $key, $default);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(\App\Core\Session::csrfToken()) . '">';
    }
}

if (!function_exists('app_timezone')) {
    /**
     * Fuso horário de apresentação (OPS-PRD-001 §10.2: guardamos sempre em
     * UTC; só a apresentação é convertida). Configurável em .env com
     * APP_TIMEZONE; por omissão Europe/Lisbon, que trata sozinho do horário
     * de verão/inverno.
     */
    function app_timezone(): DateTimeZone
    {
        static $tz = null;

        if ($tz === null) {
            $name = (string) \App\Core\Env::get('APP_TIMEZONE', 'Europe/Lisbon');
            try {
                $tz = new DateTimeZone($name);
            } catch (Exception) {
                $tz = new DateTimeZone('Europe/Lisbon');
            }
        }

        return $tz;
    }
}

if (!function_exists('dt')) {
    /**
     * Mostra uma data/hora da base de dados (gravada em UTC) na hora local
     * portuguesa. Usar SEMPRE que se mostra um timestamp ao utilizador —
     * sem isto, aparecia 1h atrás no horário de verão.
     */
    function dt(?string $utcDateTime, string $format = 'Y-m-d H:i'): string
    {
        if ($utcDateTime === null || trim($utcDateTime) === '') {
            return '—';
        }

        try {
            return (new DateTimeImmutable($utcDateTime, new DateTimeZone('UTC')))
                ->setTimezone(app_timezone())
                ->format($format);
        } catch (Exception) {
            return e($utcDateTime);
        }
    }
}

if (!function_exists('is_user_online')) {
    /**
     * Mesma janela de presença da Tela Operacional (🖥️): o middleware toca
     * last_activity_at a cada 2 min enquanto o utilizador navega; online =
     * atividade nos últimos 5 min.
     */
    function is_user_online(?string $lastActivityAt): bool
    {
        if ($lastActivityAt === null || $lastActivityAt === '') {
            return false;
        }

        return strtotime($lastActivityAt) >= (time() - 5 * 60);
    }
}

if (!function_exists('online_dot')) {
    /** Bolinha verde/vermelha de presença, para junto do nome de um utilizador. */
    function online_dot(?string $lastActivityAt): string
    {
        $online = is_user_online($lastActivityAt);
        $color = $online ? '#22c55e' : '#dc2626';
        $title = $online ? 'Online' : 'Offline';

        return '<span title="' . $title . '" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . $color . ';margin-right:5px;flex-shrink:0"></span>';
    }
}

if (!function_exists('sla_state')) {
    /**
     * Estado do SLA de um processo. Duas regras, ambas ligáveis/desligáveis
     * em Configurações → Parâmetros Globais (sem mexer em código):
     *
     *  1. sla_renew_on_interaction → o prazo conta a partir do ÚLTIMO
     *     CONTACTO; cada interação dá ao operador o tempo do SLA outra vez.
     *     Desligada, conta sempre desde a criação do processo.
     *  2. sla_pause_on_waiting → o relógio fica EM PAUSA enquanto se aguarda
     *     Cliente/Peças/Oficina/Terceiros; o tempo já acumulado em pausa
     *     (sla_paused_minutes) empurra o prazo. Desligada, o relógio nunca
     *     pára.
     *
     * Com as duas desligadas volta ao comportamento original (prazo fixo
     * desde a criação).
     *
     * Nota: isto mede a resposta do operador, não a espera do cliente — para
     * essa, ver o "Tempo Total" (sempre desde a criação, nunca reiniciado).
     *
     * @param array<string, mixed> $p linha do processo
     * @return array{status:'none'|'paused'|'running', minutes_left:?int}
     */
    function sla_state(array $p): array
    {
        $renew = (string) \App\Core\Settings::get('sla_renew_on_interaction', '1') === '1';
        $pause = (string) \App\Core\Settings::get('sla_pause_on_waiting', '1') === '1';

        $sla = $p['default_sla_minutes'] ?? null;
        $base = $renew
            ? ($p['last_contact_at'] ?? $p['created_at'] ?? null)
            : ($p['created_at'] ?? null);

        if ($sla === null || $sla === '' || $base === null || $base === '' || ($p['closed_at'] ?? null) !== null) {
            return ['status' => 'none', 'minutes_left' => null];
        }

        $paused = $pause ? (int) ($p['sla_paused_minutes'] ?? 0) : 0;

        // Em espera → o relógio está parado; o tempo não corre contra ninguém.
        if ($pause && !empty($p['wait_started_at'])) {
            $ateAEspera = max(0, (int) floor((strtotime((string) $p['wait_started_at']) - strtotime((string) $base)) / 60));

            return ['status' => 'paused', 'minutes_left' => (int) $sla - $ateAEspera + $paused];
        }

        $elapsed = (int) floor((time() - strtotime((string) $base)) / 60);

        return ['status' => 'running', 'minutes_left' => (int) $sla - $elapsed + $paused];
    }
}

if (!function_exists('sla_human')) {
    /** Formata minutos como "2h05m" ou "45m". */
    function sla_human(int $minutes): string
    {
        $abs = abs($minutes);

        return $abs >= 60
            ? intdiv($abs, 60) . 'h' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . 'm'
            : $abs . 'm';
    }
}

if (!function_exists('sla_badge')) {
    /**
     * Etiqueta "tempo para o SLA" (listas e detalhe).
     * ⏸ em pausa (a aguardar) · 🟢 folga · 🟠 falta ≤30 min · 🔴 atrasado.
     *
     * @param array<string, mixed> $p linha do processo
     */
    function sla_badge(array $p): string
    {
        $state = sla_state($p);

        if ($state['status'] === 'none') {
            return '<span style="color:#9ca3af">—</span>';
        }

        $left = (int) $state['minutes_left'];
        $txt = sla_human($left);

        if ($state['status'] === 'paused') {
            // Um processo pode ficar em pausa já depois de o prazo ter sido
            // ultrapassado — nesse caso mostra-se o atraso (com sinal), senão
            // parecia que tinha imenso tempo de folga.
            $atrasado = $left < 0;

            return '<span title="' . ($atrasado
                    ? 'SLA em pausa, mas o prazo já tinha sido ultrapassado antes da espera'
                    : 'SLA em pausa — a aguardar resposta. O tempo de espera não conta para o SLA.')
                . '" style="color:' . ($atrasado ? '#dc2626' : '#2563eb') . ';white-space:nowrap">⏸ '
                . ($atrasado ? '-' : '') . $txt . '</span>';
        }

        if ($left < 0) {
            return '<span title="Prazo do SLA ultrapassado" style="color:#dc2626;font-weight:600;white-space:nowrap">🔴 -' . $txt . '</span>';
        }

        $warn = $left <= 30;

        return '<span title="Tempo restante até ao prazo do SLA (conta desde o último contacto)"'
            . ' style="color:' . ($warn ? '#b45309' : '#16a34a') . ';white-space:nowrap">'
            . ($warn ? '🟠' : '🟢') . ' ' . $txt . '</span>';
    }
}
