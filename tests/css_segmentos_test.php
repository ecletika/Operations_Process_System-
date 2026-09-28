<?php

declare(strict_types=1);

/**
 * Verifica a cascata dos segmentos de filtro.
 *
 * Existe por causa de um defeito real: dentro de um .ops-form-row, as regras
 * genéricas do formulário (label a display:block, input a width:100%) têm a
 * mesma especificidade que .ops-seg, e ganhavam consoante a ordem do
 * ficheiro. Resultado no ecrã: os segmentos empilhados em coluna e os
 * círculos dos radios à vista, que é o oposto do desenho.
 *
 * O teste lê o CSS e compara especificidades, em vez de confiar em que
 * ninguém volte a acrescentar uma regra genérica por cima.
 *
 * Uso: php tests/css_segmentos_test.php
 */

$css = (string) file_get_contents(dirname(__DIR__) . '/public/css/app.css');

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

/** Especificidade de um seletor simples: [ids, classes+atributos+pseudo, elementos]. */
$especificidade = static function (string $seletor): array {
    $s = trim($seletor);
    $ids = preg_match_all('/#[\w-]+/', $s);
    $classes = preg_match_all('/\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/', $s);
    $elementos = preg_match_all('/(^|[\s>+~])([a-z]+[\w-]*)/i', $s);

    return [$ids, $classes, $elementos];
};

/** A primeira vence a segunda? (só compara especificidade, não ordem) */
$vence = static function (array $a, array $b): bool {
    for ($i = 0; $i < 3; $i++) {
        if ($a[$i] !== $b[$i]) {
            return $a[$i] > $b[$i];
        }
    }

    return false;
};

// ---------------------------------------------------------------------
echo "\n== As regras dos segmentos vencem as do formulário ==\n";

$generica = $especificidade('.ops-form-row label');
$nossa = $especificidade('.ops-form-row .ops-seg label');

$check('a regra do segmento é mais específica que a genérica do formulário',
    $vence($nossa, $generica), true);

$genericaInput = $especificidade('.ops-form-row input');
$nossaInput = $especificidade('.ops-form-row .ops-seg input');
$check('o mesmo para os inputs', $vence($nossaInput, $genericaInput), true);

// ---------------------------------------------------------------------
echo "\n== O CSS declara o que é preciso ==\n";

$check('os segmentos ficam em linha',
    (bool) preg_match('/\.ops-form-row \.ops-seg[,\s{][^}]*display:\s*inline-flex/s', $css), true);

// Sem isto, as labels herdam display:block e empilham-se — foi o defeito.
$check('as labels do segmento deixam de ser block',
    (bool) preg_match('/\.ops-form-row \.ops-seg label[^}]*display:\s*inline-flex/s', $css), true);

$check('as labels perdem a margem do formulário',
    (bool) preg_match('/\.ops-form-row \.ops-seg label[^}]*margin:\s*0/s', $css), true);

$check('os radios e checkboxes ficam escondidos',
    (bool) preg_match('/\.ops-form-row \.ops-seg input[^}]*opacity:\s*0/s', $css), true);

$check('e perdem a largura total do formulário',
    (bool) preg_match('/\.ops-form-row \.ops-seg input[^}]*width:\s*1px/s', $css), true);

$check('o escolhido distingue-se',
    (bool) preg_match('/\.ops-seg input:checked \+ label/', $css), true);

$check('o foco de teclado é visível',
    (bool) preg_match('/\.ops-seg input:focus-visible \+ label[^}]*outline/s', $css), true);

$check('respeita quem reduziu o movimento',
    (bool) preg_match('/prefers-reduced-motion[^}]*\}[^}]*\.ops-seg label/s', $css)
    || (bool) preg_match('/prefers-reduced-motion.*?\.ops-seg/s', $css), true);

// ---------------------------------------------------------------------
echo "\n== O CSS chega ao browser depois de um deploy ==\n";

$vistas = glob(dirname(__DIR__) . '/app/Modules/*/Views/*.php') ?: [];
$estaticas = [];
foreach ($vistas as $v) {
    $conteudo = (string) file_get_contents($v);
    if (str_contains($conteudo, 'href="/css/app.css"')) {
        $estaticas[] = basename($v);
    }
}
$check('nenhuma vista liga o CSS sem versão', $estaticas, []);

require_once dirname(__DIR__) . '/app/Helpers/functions.php';
$url = asset('/css/app.css');
$check('asset() acrescenta a versão', (bool) preg_match('#^/css/app\.css\?v=\d+$#', $url), true);
$check('um ficheiro que não existe fica sem versão', asset('/css/nao-existe.css'), '/css/nao-existe.css');

echo "\n" . ($falhas === 0 ? "TODOS OS TESTES PASSARAM\n\n" : "{$falhas} TESTE(S) FALHARAM\n\n");
exit($falhas === 0 ? 0 : 1);
