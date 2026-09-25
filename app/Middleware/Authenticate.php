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
    /**
     * Intervalo mínimo entre "toques" de presença (segundos). O pop-up de
     * notificações (#6) faz polling a cada 30s e passa por este middleware,
     * pelo que serve também de "heartbeat": o utilizador continua a marcar
     * presença mesmo que fique numa página sem navegar (corrige #9 — deixava
     * de aparecer online ao fim de ~30 min parado no mesmo separador).
     */
    private const ACTIVITY_TOUCH_SECONDS = 60;

    public function handle(Request $request): void
    {
        if (!Session::has('user_id')) {
            Response::redirect('/login');
        }

        if ($this->hasExpired()) {
            // Expirou de facto: termina a sessão e diz porquê. Antes, a
            // sessão desaparecia sem aviso e a plataforma parecia ir abaixo
            // a meio do trabalho.
            Session::destroy();
            Response::redirect('/login?expirou=1');
        }

        Session::put('last_seen', time());
        $this->touchActivity((int) Session::get('user_id'));
    }

    /**
     * Passou o tempo permitido sem qualquer atividade?
     *
     * A expiração é decidida AQUI e não pelo servidor: em alojamento
     * partilhado o gc_maxlifetime do PHP é o que o alojamento quiser (muitas
     * vezes 24 minutos), e o tempo configurado na aplicação não valia nada.
     */
    private function hasExpired(): bool
    {
        $ultimo = (int) Session::get('last_seen', 0);

        // Sessão iniciada antes desta versão: adota-se agora como referência
        // em vez de a expirar já, que expulsaria toda a gente no deploy.
        if ($ultimo === 0) {
            return false;
        }

        return (time() - $ultimo) > Session::lifetimeMinutes() * 60;
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
