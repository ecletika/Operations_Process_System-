<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Throwable;

/**
 * OPS-PRD-001 3.8 - Processo de Login: sem sessão válida, sem acesso.
 */
final class Authenticate
{
    /** Intervalo mínimo entre "toques" de presença (segundos). */
    private const ACTIVITY_TOUCH_SECONDS = 120;

    public function handle(Request $request): void
    {
        if (!Session::has('user_id')) {
            Response::redirect('/login');
        }

        $this->touchActivity((int) Session::get('user_id'));
    }

    /**
     * Presença para a Tela Operacional: marca a última atividade do
     * utilizador, no máximo a cada 2 minutos (throttle em sessão) para não
     * pesar cada pedido com um UPDATE. Nunca pode quebrar a navegação.
     */
    private function touchActivity(int $userId): void
    {
        $last = (int) Session::get('last_activity_touch', 0);
        if (time() - $last < self::ACTIVITY_TOUCH_SECONDS) {
            return;
        }

        try {
            $stmt = Database::connection()->prepare('
                UPDATE tb_user SET last_activity_at = NOW() WHERE id = :id
            ');
            $stmt->execute(['id' => $userId]);
            Session::put('last_activity_touch', time());
        } catch (Throwable) {
            // silencioso de propósito (ex.: migração 017 ainda não aplicada)
        }
    }
}
