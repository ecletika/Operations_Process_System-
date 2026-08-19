<?php

declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

/**
 * Fonte de dados do Relatório de Imobilizados. Existe para o serviço depender
 * de um contrato e não da implementação concreta (PDO/MySQL), o que permite
 * testar o serviço com um duplo em memória, sem base de dados.
 */
interface ImmobilizedReportSource
{
    /**
     * @param array{from?:?string, to?:?string, plate?:string, vehicle?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function processes(string $subjectCode, array $filters, int $limit = 500): array;

    /**
     * @param list<int> $processIds
     * @return array<int, list<array{ts:string, kind:string, channel:string, who:string, text:string}>>
     */
    public function contactsByProcess(array $processIds): array;
}
