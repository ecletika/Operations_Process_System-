<?php

declare(strict_types=1);

namespace App\Modules\Process\Validators;

use App\Modules\Process\DTO\CreateProcessDTO;

/**
 * "Todas as validações devem ocorrer no servidor, mesmo que existam
 * validações em JavaScript." (Critérios Gerais de Aceitação, cap. 6)
 */
final class CreateProcessValidator
{
    /** @return string[] */
    public function validate(CreateProcessDTO $dto): array
    {
        $errors = [];

        if ($dto->plate === '' || strlen($dto->plate) < 5) {
            $errors[] = 'Indique uma matrícula válida.';
        }

        if ($dto->customerName === '') {
            $errors[] = 'O nome do cliente é obrigatório.';
        }

        if ($dto->contactValue === '') {
            $errors[] = match ($dto->contactChannel) {
                'EMAIL' => 'O email do cliente é obrigatório.',
                'IN_PERSON' => 'Indique o telefone ou email do cliente.',
                default => 'O telefone do cliente é obrigatório.',
            };
        } elseif ($dto->contactChannel === 'EMAIL' && !filter_var($dto->contactValue, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Indique um email válido.';
        }

        if ($dto->subjectId <= 0) {
            $errors[] = 'Selecione um assunto.';
        }

        if ($dto->priorityId <= 0) {
            $errors[] = 'Selecione uma prioridade.';
        }

        if ($dto->description === '') {
            $errors[] = 'Descreva o motivo do contacto.';
        }

        return $errors;
    }
}
