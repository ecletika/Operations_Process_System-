<?php

declare(strict_types=1);

/**
 * Testes da expiração de sessão — a regra que decide quando alguém é posto
 * fora da plataforma.
 *
 * Existe porque utilizadores estavam a ser desligados a meio do trabalho: o
 * tempo configurado na aplicação não valia nada (quem mandava era o
 * gc_maxlifetime do alojamento, tipicamente 24 minutos) e as sessões viviam
 * num diretório partilhado com outras contas do servidor.
 *
 * Uso: php tests/sessao_test.php
 */

use App\Core\Env;
use App\Core\Session;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

$falhas = 0;
$check = static function (string $nome, mixed $obtido, mixed $esperado) use (&$falhas): void {
    $ok = $obtido === $esperado;
    if (!$ok) {
        $falhas++;
    }
    printf("  [%s] %s\n", $ok ? 'OK  ' : 'FALHA', $nome);
    if (!$ok) {
        printf("        obtido: %s\n      esperado: %s\n", var_export($obtido, true), var_export($esperado, true));
    }
};

/** Repõe a cache do Env para se poder testar vários valores. */
$definirEnv = static function (?string $valor): void {
    $r = new ReflectionProperty(Env::class, 'loaded');
    $r->setAccessible(true);
    $r->setValue(null, true);

    if ($valor === null) {
        unset($_ENV['SESSION_LIFETIME_MINUTES']);
        putenv('SESSION_LIFETIME_MINUTES');

        return;
    }

    $_ENV['SESSION_LIFETIME_MINUTES'] = $valor;
    putenv('SESSION_LIFETIME_MINUTES=' . $valor);
};

// =====================================================================
echo "\n== Tempo de vida da sessão ==\n";

$definirEnv(null);
$check('sem configuração, 8 horas (um dia de trabalho)', Session::lifetimeMinutes(), 480);

$definirEnv('60');
$check('respeita o valor do .env', Session::lifetimeMinutes(), 60);

$definirEnv('0');
$check('zero não desliga a sessão instantaneamente', Session::lifetimeMinutes(), 480);

$definirEnv('-5');
$check('valor negativo cai no padrão', Session::lifetimeMinutes(), 480);

$definirEnv('480');

// =====================================================================
// A decisão de expirar é da aplicação, não do servidor. Replica-se aqui a
// mesma regra do middleware, que é privada.
echo "\n== Quando é que a sessão expira ==\n";

$expirou = static function (int $ultimoContacto, int $minutos): bool {
    if ($ultimoContacto === 0) {
        return false;
    }

    return (time() - $ultimoContacto) > $minutos * 60;
};

$check('acabado de usar: continua', $expirou(time(), 480), false);
$check('parado há 7 horas: continua', $expirou(time() - 7 * 3600, 480), false);
$check('parado há 9 horas: expira', $expirou(time() - 9 * 3600, 480), true);
$check('parado há 25 min não expira (era o limite do servidor)',
    $expirou(time() - 25 * 60, 480), false);

// Uma sessão aberta antes desta versão não tem marca de atividade. Expirá-la
// de imediato punha toda a gente fora no momento do deploy.
$check('sessão antiga, sem marca, não é expulsa no deploy', $expirou(0, 480), false);

// =====================================================================
echo "\n== Onde ficam guardadas as sessões ==\n";

$pasta = dirname(__DIR__) . '/storage/sessions';
$check('a pasta própria existe', is_dir($pasta), true);
$check('é gravável', is_writable($pasta), true);

$gitignore = (string) file_get_contents(dirname(__DIR__) . '/.gitignore');
$check('o conteúdo não vai para o git', str_contains($gitignore, '/storage/sessions/*'), true);

// =====================================================================
echo "\n== Contrato do Session ==\n";

$check('lifetimeMinutes é público (o middleware usa-o)',
    (new ReflectionMethod(Session::class, 'lifetimeMinutes'))->isPublic(), true);

$origem = (string) file_get_contents(dirname(__DIR__) . '/app/Core/Session.php');
$check('define gc_maxlifetime em vez de aceitar o do servidor',
    str_contains($origem, 'session.gc_maxlifetime'), true);
$check('usa uma pasta de sessões própria',
    str_contains($origem, 'session_save_path'), true);
$check('o cookie fica httponly', str_contains($origem, "'httponly' => true"), true);

echo "\n" . ($falhas === 0 ? "TODOS OS TESTES PASSARAM\n\n" : "{$falhas} TESTE(S) FALHARAM\n\n");
exit($falhas === 0 ? 0 : 1);
