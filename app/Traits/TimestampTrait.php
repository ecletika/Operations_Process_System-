<?php

declare(strict_types=1);

namespace App\Traits;

trait TimestampTrait
{
    protected function now(): string
    {
        return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}
