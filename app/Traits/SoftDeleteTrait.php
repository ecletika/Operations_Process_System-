<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * OPS-PRD-001 5.19 / 10.16 - nenhuma tabela usa DELETE físico.
 */
trait SoftDeleteTrait
{
    protected function notDeletedClause(string $alias = ''): string
    {
        $column = $alias !== '' ? "{$alias}.deleted_at" : 'deleted_at';

        return "{$column} IS NULL";
    }
}
