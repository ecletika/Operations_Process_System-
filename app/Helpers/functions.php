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
