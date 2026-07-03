<?php

declare(strict_types=1);

namespace App\Modules\Auth\Validators;

final class LoginValidator
{
    /**
     * @return string[] lista de erros (vazia se válido)
     */
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string) ($input['username'] ?? '')) === '') {
            $errors[] = 'O utilizador é obrigatório.';
        }

        if (trim((string) ($input['password'] ?? '')) === '') {
            $errors[] = 'A password é obrigatória.';
        }

        return $errors;
    }
}
