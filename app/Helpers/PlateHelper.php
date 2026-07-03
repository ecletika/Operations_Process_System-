<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * OPS-PRD-001 §12 (RF-0037) - normaliza matrículas para que "AA12BB" e
 * "AA-12-BB" resultem sempre na mesma pesquisa/registo.
 */
final class PlateHelper
{
    public static function normalize(string $plate): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate) ?? '');
    }
}
