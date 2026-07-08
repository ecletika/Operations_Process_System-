<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Helpers\PhoneHelper;
use App\Modules\Process\Repositories\CustomerRepository;
use App\Traits\AuditTrait;
use RuntimeException;

/**
 * 📥 Importação de Clientes a partir de Excel/CSV — colunas esperadas:
 * Nome, Telefone, Email, NIF (em qualquer ordem, aceita variações de
 * cabeçalho como "Nº de Telefone" / "Nº Contribuinte").
 */
final class CustomerImportService
{
    use AuditTrait;

    public function __construct(
        private readonly CustomerRepository $customers = new CustomerRepository(),
    ) {
    }

    /**
     * @param array<int, array<int, string>> $rows primeira linha = cabeçalho
     * @return array{created:int, duplicates:int, skipped:int, errors:string[]}
     */
    public function import(array $rows, int $userId): array
    {
        if ($rows === []) {
            throw new RuntimeException('O ficheiro está vazio.');
        }

        $header = array_shift($rows);
        $columns = $this->mapHeader($header);

        if ($columns['name'] === null) {
            throw new RuntimeException('Não encontrei uma coluna de Nome no ficheiro. Colunas esperadas: Nome, Telefone, Email, NIF.');
        }

        $result = ['created' => 0, 'duplicates' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $lineNumber => $row) {
            $excelLine = $lineNumber + 2; // +1 (0-index) +1 (cabeçalho)

            $name = trim((string) ($row[$columns['name']] ?? ''));
            if ($name === '') {
                continue; // linha em branco, ignora silenciosamente
            }

            $phoneRaw = $columns['phone'] !== null ? trim((string) ($row[$columns['phone']] ?? '')) : '';
            $email = $columns['email'] !== null ? trim((string) ($row[$columns['email']] ?? '')) : '';
            $nif = $columns['nif'] !== null ? trim((string) ($row[$columns['nif']] ?? '')) : '';

            $phone = $phoneRaw !== '' ? PhoneHelper::normalize($phoneRaw) : '';

            if ($phone === '' && $email === '') {
                $result['skipped']++;
                $result['errors'][] = "Linha {$excelLine} ({$name}): sem telefone nem email, ignorada.";
                continue;
            }

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = "Linha {$excelLine} ({$name}): email \"{$email}\" inválido, gravado na mesma sem email.";
                $email = '';
            }

            $existing = $phone !== '' ? $this->customers->findByPhone($phone) : null;
            if ($existing === null && $email !== '') {
                $existing = $this->customers->findByEmail($email);
            }

            if ($existing !== null) {
                $result['duplicates']++;
                continue;
            }

            $customerId = $this->customers->create(
                $name,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $userId,
                $nif !== '' ? $nif : null
            );
            $this->logAudit('CREATE', 'tb_customer', $customerId, null, ['name' => $name, 'import' => true]);
            $result['created']++;
        }

        return $result;
    }

    /**
     * Identifica em que coluna está cada campo, tolerando variações de
     * cabeçalho (maiúsculas, acentos, "Nº de Telefone" vs "Telefone", etc.).
     *
     * @param array<int, string> $header
     * @return array{name:?int, phone:?int, email:?int, nif:?int}
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(
            static fn (string $h): string => self::stripAccents(strtolower(trim($h))),
            $header
        );

        $find = static function (array $candidates) use ($normalized): ?int {
            foreach ($normalized as $index => $value) {
                foreach ($candidates as $candidate) {
                    if (str_contains($value, $candidate)) {
                        return $index;
                    }
                }
            }

            return null;
        };

        return [
            'name' => $find(['nome']),
            'phone' => $find(['telefone', 'telemovel', 'contacto', 'phone']),
            'email' => $find(['email', 'e-mail']),
            'nif' => $find(['nif', 'contribuinte']),
        ];
    }

    private static function stripAccents(string $value): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ];

        return strtr($value, $map);
    }
}
