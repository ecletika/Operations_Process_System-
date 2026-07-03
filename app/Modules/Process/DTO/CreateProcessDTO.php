<?php

declare(strict_types=1);

namespace App\Modules\Process\DTO;

use App\Helpers\PhoneHelper;

/**
 * Nenhum Controller recebe diretamente o $_POST (OPS-PRD-001 11.12).
 */
final class CreateProcessDTO
{
    /** Tipos de interação aceites no Novo Processo. */
    public const CHANNELS = ['PHONE', 'WHATSAPP', 'EMAIL', 'IN_PERSON'];

    public function __construct(
        public readonly string $plate,
        public readonly string $customerName,
        public readonly string $contactChannel,
        public readonly string $contactValue,
        public readonly ?string $customerPhone,
        public readonly ?string $customerEmail,
        public readonly int $subjectId,
        public readonly int $priorityId,
        public readonly string $description,
        public readonly bool $reopenIfEligible,
    ) {
    }

    public static function fromArray(array $input): self
    {
        $channel = strtoupper(trim((string) ($input['contact_channel'] ?? 'PHONE')));
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'PHONE';
        }

        // Compatibilidade com a API REST v1 (docs/API.md), que ainda envia
        // "customer_phone" em vez do novo Tipo de Interação.
        $contactValue = trim((string) ($input['contact_value'] ?? $input['customer_phone'] ?? ''));

        $phone = null;
        $email = null;

        if ($channel === 'EMAIL') {
            $email = $contactValue;
        } elseif ($channel === 'IN_PERSON' && str_contains($contactValue, '@')) {
            // Presencial: campo livre, tanto pode ser telefone como email.
            $email = $contactValue;
        } elseif ($contactValue !== '') {
            $phone = PhoneHelper::normalize($contactValue);
        }

        return new self(
            plate: trim((string) ($input['plate'] ?? '')),
            customerName: trim((string) ($input['customer_name'] ?? '')),
            contactChannel: $channel,
            contactValue: $contactValue,
            customerPhone: $phone,
            customerEmail: $email,
            subjectId: (int) ($input['subject_id'] ?? 0),
            priorityId: (int) ($input['priority_id'] ?? 0),
            description: trim((string) ($input['description'] ?? '')),
            reopenIfEligible: (bool) ($input['reopen_if_eligible'] ?? false),
        );
    }
}
