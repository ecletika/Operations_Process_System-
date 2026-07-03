<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 manual (App\ -> app/), usado quando o Composer
 * não está disponível no ambiente. Se existir vendor/autoload.php
 * (Composer instalado), este ficheiro não é necessário.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__ . '/../Helpers/functions.php';
