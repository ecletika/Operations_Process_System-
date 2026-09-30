<?php

declare(strict_types=1);

/**
 * Testes do separador "Imobilizados" de Perfis & Permissões, onde se escolhem
 * os departamentos que podem ver a lista de todos os carros parados.
 *
 * O que interessa provar: que cada departamento aparece de um lado e só de
 * um, que os escolhidos vêm marcados, e que o ecrã continua a funcionar sem
 * JavaScript — o formulário é que manda, não o que se arrasta no ecrã.
 *
 * Uso: php tests/permissoes_imobilizados_test.php
 */

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

session_start();
$_SESSION['_csrf_token'] = str_repeat('a', 64);

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

$departamentos = [
    ['id' => 1, 'name' => 'Oficina', 'branch_name' => 'IL - 132'],
    ['id' => 2, 'name' => 'Colisão', 'branch_name' => 'IL - 132'],
    ['id' => 3, 'name' => 'Contabilidade', 'branch_name' => 'IL - 132'],
    ['id' => 4, 'name' => 'CRM', 'branch_name' => 'IL - 132'],
    ['id' => 5, 'name' => 'Oficina', 'branch_name' => 'IL - 170'],
];

$render = static function (array $escolhidos) use ($departamentos): string {
    extract(['departments' => $departamentos, 'imobilizadosDepartmentIds' => $escolhidos]);
    ob_start();
    require __DIR__ . '/../app/Modules/Administration/Views/_permissions_imobilizados.php';

    return (string) ob_get_clean();
};

/** Recorta uma das colunas do ecrã, para se poder olhar só para ela. */
$coluna = static function (string $html, string $qual): string {
    $inicio = strpos($html, 'data-coluna="' . $qual . '"');
    $outra = $qual === 'tem' ? 'data-coluna="nao"' : '</div>

  </div>';
    $fim = strpos($html, $outra, (int) $inicio);

    return substr($html, (int) $inicio, ($fim !== false ? $fim : strlen($html)) - (int) $inicio);
};

// =====================================================================
echo "\n== Com dois departamentos escolhidos ==\n";
$html = $render([1, 5]);

$comAcesso = $coluna($html, 'tem');
$semAcesso = $coluna($html, 'nao');

$check('os escolhidos ficam do lado "com acesso"',
    substr_count($comAcesso, '<li data-nome'), 2);
$check('os restantes ficam do outro lado',
    substr_count($semAcesso, '<li data-nome'), 3);
$check('nenhum departamento aparece nas duas colunas',
    substr_count($html, '<li data-nome'), count($departamentos));

$check('a Oficina da 132 está do lado com acesso',
    str_contains($comAcesso, 'IL - 132 · Oficina'), true);
$check('a Contabilidade está do lado sem acesso',
    str_contains($semAcesso, 'IL - 132 · Contabilidade'), true);

// Os dois departamentos chamam-se "Oficina": sem a filial no nome, o
// administrador não saberia qual dos dois estava a marcar.
$check('a filial distingue departamentos com o mesmo nome',
    substr_count($html, 'Oficina') >= 2 && str_contains($html, 'IL - 170 · Oficina'), true);

$check('os escolhidos vêm marcados',
    substr_count($comAcesso, 'checked'), 2);
$check('os outros não vêm marcados', substr_count($semAcesso, 'checked'), 0);
$check('as contagens estão certas',
    (bool) preg_match('/data-conta>2</', $html) && (bool) preg_match('/data-conta>3</', $html), true);

// =====================================================================
echo "\n== O formulário é que manda ==\n";

$check('todos submetem o mesmo campo',
    substr_count($html, 'name="departments[]"'), count($departamentos));
$check('vai para a sua própria rota',
    str_contains($html, 'action="/admin/permissions/imobilizados"'), true);
$check('leva o token da sessão', str_contains($html, '_csrf'), true);

// Sem JavaScript o item não muda de coluna, mas o checkbox alterna na mesma
// e o formulário grava o que se marcou: o ecrã continua utilizável.
$check('cada item é um label com o seu checkbox',
    substr_count($html, '<label class="dep-item">'), count($departamentos));

// =====================================================================
echo "\n== Casos extremos ==\n";

$nenhum = $render([]);
$check('sem nenhum escolhido, a coluna esquerda vem vazia',
    substr_count($coluna($nenhum, 'tem'), '<li data-nome'), 0);
$check('e explica o que fazer',
    str_contains($nenhum, 'Nenhum departamento tem acesso'), true);
// Procura-se " checked>" e não "checked": a palavra também aparece no
// JavaScript do próprio ecrã, e a procura simples dava falso positivo.
$check('sem nenhum escolhido, nada vem marcado', str_contains($nenhum, ' checked>'), false);

$todos = $render([1, 2, 3, 4, 5]);
$check('com todos escolhidos, a coluna direita vem vazia',
    substr_count($coluna($todos, 'nao'), '<li data-nome'), 0);
$check('e diz que todos têm acesso',
    str_contains($todos, 'Todos os departamentos têm acesso'), true);

// =====================================================================
echo "\n== Pesquisa ==\n";

$check('há campo de pesquisa', str_contains($html, 'id="dep-procurar"'), true);
$check('com ícone de lupa', str_contains($html, '<circle cx="7" cy="7" r="4.5"/>'), true);
$check('cada item traz o nome em minúsculas para filtrar',
    str_contains($html, 'data-nome="il - 132 · oficina"'), true);
$check('avisa quando a pesquisa não encontra nada',
    str_contains($html, 'Nenhum departamento com esse nome'), true);

// =====================================================================
echo "\n== Ligação ao resto ==\n";

$ctrl = (string) file_get_contents(__DIR__ . '/../app/Modules/Process/Controllers/ProcessController.php');
$check('o departamento também abre o separador',
    str_contains($ctrl, 'imobilizadosViewAllIds()'), true);

$repo = (string) file_get_contents(__DIR__ . '/../app/Modules/Administration/Repositories/DepartmentRepository.php');
$check('gravar limpa os antigos antes de marcar os novos',
    str_contains($repo, 'SET imobilizados_view_all = 0'), true);
$check('aguenta a migração ainda não aplicada',
    str_contains($repo, "hasColumn('tb_department', 'imobilizados_view_all')"), true);

$vistaPerm = (string) file_get_contents(__DIR__ . '/../app/Modules/Administration/Views/permissions.php');
$check('o separador aparece em Perfis & Permissões',
    str_contains($vistaPerm, 'tab=imobilizados'), true);

echo "\n" . ($falhas === 0 ? "TODOS OS TESTES PASSARAM\n\n" : "{$falhas} TESTE(S) FALHARAM\n\n");
exit($falhas === 0 ? 0 : 1);
