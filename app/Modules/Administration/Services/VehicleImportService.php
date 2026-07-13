<?php

declare(strict_types=1);

namespace App\Modules\Administration\Services;

use App\Modules\Process\Repositories\CustomerRepository;
use App\Modules\Process\Repositories\VehicleRepository;
use App\Traits\AuditTrait;
use RuntimeException;

/**
 * 📥 Importação de Viaturas a partir de Excel/CSV — colunas esperadas:
 * Nome, Matrícula, Marca, Modelo (em qualquer ordem). A ligação à Pessoa
 * é feita pelo campo Nome, que tem de corresponder exatamente a um
 * Cliente já existente (importe primeiro os Clientes).
 */
final class VehicleImportService
{
    use AuditTrait;

    public function __construct(
        private readonly VehicleRepository $vehicles = new VehicleRepository(),
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
            throw new RuntimeException('Não encontrei uma coluna de Nome no ficheiro. É pelo Nome que a viatura é ligada ao Cliente.');
        }

        if ($columns['plate'] === null) {
            throw new RuntimeException('Não encontrei uma coluna de Matrícula no ficheiro. Colunas esperadas: Nome, Matrícula, Marca, Modelo.');
        }

        $result = ['created' => 0, 'duplicates' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $lineNumber => $row) {
            $excelLine = $lineNumber + 2; // +1 (0-index) +1 (cabeçalho)

            $name = trim((string) ($row[$columns['name']] ?? ''));
            $plate = trim((string) ($row[$columns['plate']] ?? ''));
            $brand = $columns['brand'] !== null ? trim((string) ($row[$columns['brand']] ?? '')) : '';
            $model = $columns['model'] !== null ? trim((string) ($row[$columns['model']] ?? '')) : '';
            $yearRaw = $columns['year'] !== null ? trim((string) ($row[$columns['year']] ?? '')) : '';
            $year = (bool) preg_match('/^\d{4}$/', $yearRaw) ? (int) $yearRaw : null;

            if ($name === '' && $plate === '') {
                continue; // linha em branco, ignora silenciosamente
            }

            if ($name === '') {
                $result['skipped']++;
                $result['errors'][] = "Linha {$excelLine}: sem Nome de cliente, ignorada.";
                continue;
            }

            if ($plate === '') {
                $result['skipped']++;
                $result['errors'][] = "Linha {$excelLine} ({$name}): sem Matrícula, ignorada.";
                continue;
            }

            $matches = $this->customers->findAllByName($name);
            if ($matches === []) {
                $result['skipped']++;
                $result['errors'][] = "Linha {$excelLine} ({$name}): cliente não encontrado. Importe primeiro os Clientes.";
                continue;
            }

            if (count($matches) > 1) {
                $result['skipped']++;
                $result['errors'][] = "Linha {$excelLine} ({$name}): existe mais de um cliente com este nome, ligação ambígua.";
                continue;
            }

            $existing = $this->vehicles->findByPlate($plate);
            if ($existing !== null) {
                $result['duplicates']++;
                continue;
            }

            $customerId = (int) $matches[0]['id'];

            $vehicleId = $this->vehicles->create(
                $plate,
                $customerId,
                $userId,
                $brand !== '' ? $brand : null,
                $model !== '' ? $model : null,
                $year
            );
            $this->logAudit('CREATE', 'tb_vehicle', $vehicleId, null, ['plate' => $plate, 'customer_id' => $customerId, 'import' => true]);
            $result['created']++;
        }

        return $result;
    }

    /**
     * @param array<int, string> $header
     * @return array{name:?int, plate:?int, brand:?int, model:?int, year:?int}
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
            // Só "nome" — colunas como "Nº Cliente" não podem ser confundidas
            // com o Nome, pois é pelo Nome (e só pelo Nome) que a viatura é
            // ligada ao Cliente nesta importação.
            'name' => $find(['nome']),
            'plate' => $find(['matricula', 'placa', 'plate']),
            'brand' => $find(['marca', 'brand']),
            'model' => $find(['modelo', 'model']),
            'year' => $find(['ano modelo', 'ano', 'year']),
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
