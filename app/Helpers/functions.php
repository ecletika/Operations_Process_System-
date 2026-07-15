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

if (!function_exists('sla_minutes_left')) {
    /**
     * Minutos que faltam para o prazo do SLA de um processo, contando desde a
     * criação (prazo = created_at + SLA da prioridade). Devolve:
     *   - int positivo → ainda dentro do prazo (minutos a faltar);
     *   - int negativo → prazo ultrapassado (minutos de atraso);
     *   - null → sem SLA definido, sem data, ou processo já concluído.
     * Tudo em UTC (o app usa UTC de ponta a ponta), coerente com o resto.
     */
    function sla_minutes_left(?string $createdAt, ?string $closedAt, int|string|null $slaMinutes): ?int
    {
        if ($slaMinutes === null || $slaMinutes === '' || $createdAt === null || $createdAt === '' || $closedAt !== null) {
            return null;
        }

        $elapsed = (int) floor((time() - strtotime($createdAt)) / 60);

        return (int) $slaMinutes - $elapsed;
    }
}

if (!function_exists('sla_badge')) {
    /**
     * Etiqueta "tempo para o SLA" de um processo (para listas e detalhe).
     * Verde = folga; laranja = a menos de 30 min do prazo; vermelho = atrasado.
     */
    function sla_badge(?string $createdAt, ?string $closedAt, int|string|null $slaMinutes): string
    {
        $left = sla_minutes_left($createdAt, $closedAt, $slaMinutes);
        if ($left === null) {
            return '<span style="color:#9ca3af">—</span>';
        }

        $abs = abs($left);
        $txt = $abs >= 60 ? intdiv($abs, 60) . 'h' . str_pad((string) ($abs % 60), 2, '0', STR_PAD_LEFT) . 'm' : $abs . 'm';

        if ($left < 0) {
            return '<span title="Prazo do SLA ultrapassado" style="color:#dc2626;font-weight:600;white-space:nowrap">🔴 -' . $txt . '</span>';
        }

        $warn = $left <= 30;
        $color = $warn ? '#b45309' : '#16a34a';
        $emoji = $warn ? '🟠' : '🟢';

        return '<span title="Tempo restante até ao prazo do SLA" style="color:' . $color . ';white-space:nowrap">' . $emoji . ' ' . $txt . '</span>';
    }
}
