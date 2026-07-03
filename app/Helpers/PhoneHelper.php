<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * OPS-PRD-001 §11.17 - normaliza telefones para pesquisa/comparação
 * independentemente de espaços, traços ou indicativo.
 */
final class PhoneHelper
{
    public static function normalize(string $phone): string
    {
        return preg_replace('/\D/', '', $phone) ?? '';
    }
}
