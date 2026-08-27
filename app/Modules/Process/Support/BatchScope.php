<?php

declare(strict_types=1);

namespace App\Modules\Process\Support;

use App\Core\Session;

/**
 * Âmbito de departamento do utilizador com sessão iniciada — num único
 * sítio, para todos os controllers aplicarem exatamente a mesma regra
 * (era lógica repetida, e foi assim que ficaram ações sem guarda).
 */
final class BatchScope
{
    /**
     * Lotes em que o utilizador pode AGIR (assumir, concluir, observar...).
     * null = sem restrição (chefias com process.view_all). O view_all_batches
     * da ficha não abre exceção — serve só para criar noutro departamento.
     *
     * @return array<int>|null
     */
    public static function allowed(): ?array
    {
        if (in_array('process.view_all', (array) Session::get('permissions', []), true)) {
            return null;
        }

        return self::own();
    }

    /**
     * Lotes de trabalho do utilizador (o seu departamento mais os que a
     * ficha lhe autoriza), sem exceções — nem para chefias.
     * (Usado pelo "Próximo Processo": dá sempre o trabalho de quem clica.)
     *
     * @return array<int>
     */
    public static function own(): array
    {
        $allowed = Session::get('allowed_batch_ids');

        if ($allowed === null) {
            // Sessão iniciada antes desta versão: usa o lote principal.
            $single = Session::get('batch_id');

            return $single !== null ? [(int) $single] : [];
        }

        return array_map('intval', (array) $allowed);
    }

    /**
     * Pode contribuir (observações/anexos) neste processo? Sim se for do seu
     * departamento, se for chefia, ou se for o CRIADOR do processo — o
     * criador acompanha e interage mesmo quando outro departamento o trata
     * (funcionalidade explícita da Caixa de Entrada / Processos Criados).
     *
     * @param array<string, mixed> $process
     */
    public static function canContribute(array $process): bool
    {
        $allowed = self::allowed();

        return $allowed === null
            || in_array((int) $process['batch_id'], $allowed, true)
            || (int) ($process['created_by'] ?? 0) === (int) Session::get('user_id');
    }
}
