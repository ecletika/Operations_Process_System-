<?php

declare(strict_types=1);

/**
 * Renderiza o ecra "Todos os Processos" com os filtros novos e verifica o
 * resultado: segmentos, etiquetas de filtro activo, e os links que removem
 * um valor de cada vez.
 */
$root = 'C:/SISTEMAS/Operations_Process_System--main';
$bs = chr(92);
date_default_timezone_set('UTC');

spl_autoload_register(function (string $c) use ($root, $bs) {
    $rel = str_replace($bs, '/', $c);
    if (str_starts_with($rel, 'App/')) {
        $rel = 'app/' . substr($rel, 4);
    }
    if (is_file($f = $root . '/' . $rel . '.php')) {
        require $f;
    }
});
require $root . '/app/Helpers/functions.php';

session_start();
$_SESSION['permissions'] = ['process.view_all'];
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Teste';
$_SESSION['role_name'] = 'Admin';

$falhas = 0;
$check = function (string $nome, mixed $obtido, mixed $esperado) use (&$falhas): void {
    $ok = $obtido === $esperado;
    if (!$ok) {
        $falhas++;
    }
    printf("  [%s] %s\n", $ok ? 'OK  ' : 'FALHA', $nome);
    if (!$ok) {
        printf("        obtido: %s\n      esperado: %s\n", var_export($obtido, true), var_export($esperado, true));
    }
};

// A vista carrega a barra lateral, que precisa de base de dados: substitui-se
// por um ficheiro vazio, como se fez no teste do ecra de utilizadores.
$sp = sys_get_temp_dir();
$vista = $sp . '/ops_all_view_teste.php';
$origem = (string) file_get_contents($root . '/app/Modules/Process/Views/all.php');
file_put_contents($vista, implode("\n", array_filter(
    explode("\n", $origem),
    static fn (string $l): bool => !str_contains($l, '_sidebar.php')
)));

/**
 * Só o que o utilizador vê. O <style> e o <script> da página levam, nos
 * comentários e nos seletores, as mesmas palavras que se procuram no corpo
 * ("Ctrl+clique", "ops-filtro-chip") e davam contagens falsas.
 */
$corpo = static fn (string $html): string => (string) preg_replace(
    ['#<style\b[^>]*>.*?</style>#is', '#<script\b[^>]*>.*?</script>#is'],
    '',
    $html
);

$render = function (array $filtros) use ($vista, $sp): string {
    extract([
        'processes' => [],
        'statuses' => [
            ['id' => 1, 'name' => 'Novo'], ['id' => 2, 'name' => 'Em Fila'],
            ['id' => 4, 'name' => 'Em Tratamento'], ['id' => 6, 'name' => 'Aguarda Peças'],
        ],
        'batches' => [
            ['id' => 7, 'branch_name' => 'IL - 132', 'department_name' => 'CRM'],
            ['id' => 8, 'branch_name' => 'IL - 132', 'department_name' => 'Colisão'],
        ],
        'priorities' => [
            ['id' => 1, 'name' => 'Crítica'], ['id' => 2, 'name' => 'Alta'],
            ['id' => 3, 'name' => 'Média'], ['id' => 4, 'name' => 'Baixa'],
        ],
        'subjects' => [['id' => 5, 'name' => 'Agendamento'], ['id' => 6, 'name' => 'Colisão']],
        'users' => [['id' => 3, 'first_name' => 'Rita', 'last_name' => 'Santos', 'active' => 1]],
        'tab' => 'all',
        'filters' => $filtros,
    ]);
    ob_start();
    require $vista;

    return (string) ob_get_clean();
};

$base = [
    'tab' => 'all', 'scope_department_ids' => null, 'q' => '', 'situacao' => '',
    'status_id' => [], 'batch_id' => [], 'priority_id' => [], 'subject_id' => [],
    'assigned_to' => [], 'date_from' => '', 'date_to' => '',
];

// ---------------------------------------------------------------- sem filtros
echo "\n== Sem filtros ==\n";
$html = $corpo($render($base));

$check('nao ha instrucao de Ctrl+clique', str_contains($html, 'Ctrl+clique'), false);
// Todo o select de FILTRO tem de ser pesquisavel. O select de reatribuir
// responsavel, numa linha da tabela, nao e filtro e fica de fora.
// Exige name= para nao apanhar o "<select multiple>" escrito nos comentarios
// do proprio JavaScript da pagina.
preg_match_all('/<select[^>]*\sname="[^"]+"[^>]*multiple[^>]*>/', $html, $selects);
$semMelhoria = array_values(array_filter(
    $selects[0],
    static fn (string $s): bool => !str_contains($s, 'ss-enhance')
));
$check('todos os filtros de lista sao pesquisaveis', $semMelhoria, []);
$check('sao quatro listas de filtro', count($selects[0]), 4);
$check('segmentos de Situacao presentes', substr_count($html, 'name="situacao"'), 4);
$check('"Todos" vem escolhido', str_contains($html, 'id="sit_todos"' . "\n" . '                       value="" checked') || str_contains($html, 'value="" checked'), true);
$check('segmentos de Prioridade presentes', substr_count($html, 'name="priority_id[]"'), 4);
$check('diz que nao ha filtros', str_contains($html, 'Sem filtros'), true);
$check('sem filtros nao mostra "Limpar filtros"', str_contains($html, 'Limpar filtros'), false);

// ---------------------------------------------------------------- com filtros
echo "\n== Com filtros postos ==\n";
$comFiltros = $base + [];
$comFiltros['situacao'] = 'aguardar';
$comFiltros['batch_id'] = [7, 8];
$comFiltros['priority_id'] = [1];
$comFiltros['date_from'] = '2026-09-01';
$comFiltros['date_to'] = '2026-09-30';
$html = $corpo($render($comFiltros));

$check('etiqueta da situacao', str_contains($html, 'A aguardar'), true);
$check('etiqueta de cada departamento', substr_count($html, 'ops-filtro-chip'), 5);
$check('nome do departamento com a filial', str_contains($html, 'IL - 132 · CRM'), true);
$check('etiqueta do periodo sem trocar o dia', str_contains($html, '01/09/2026 a 30/09/2026'), true);
$check('a prioridade escolhida fica marcada',
    (bool) preg_match('/id="prio_1"[^>]*checked/', $html), true);
$check('agora aparece "Limpar filtros"', str_contains($html, 'Limpar filtros'), true);

// Cada etiqueta tem de remover SO o seu valor.
preg_match_all('/href="([^"]*)" title="Remover este filtro"/', $html, $m);
$links = array_map(static fn (string $u): string => html_entity_decode($u), $m[1]);
$check('uma ligacao de remocao por etiqueta', count($links), 5);

$linkCrm = '';
foreach ($links as $i => $u) {
    if (str_contains($html, 'IL - 132 · CRM')) { $linkCrm = $links[1] ?? ''; break; }
}
parse_str((string) parse_url($linkCrm, PHP_URL_QUERY), $q);
$check('remover um departamento mantem o outro', ($q['batch_id'] ?? []) === ['8'] || ($q['batch_id'] ?? []) === ['7'], true);
$check('remover um departamento nao mexe na situacao', $q['situacao'] ?? '', 'aguardar');
$check('remover um departamento nao mexe nas datas', $q['date_from'] ?? '', '2026-09-01');

// A etiqueta do periodo limpa as duas datas de uma vez.
$linkPeriodo = end($links);
parse_str((string) parse_url($linkPeriodo, PHP_URL_QUERY), $qp);
$check('remover o periodo tira as duas datas',
    !isset($qp['date_from']) && !isset($qp['date_to']), true);
$check('remover o periodo mantem os departamentos', count($qp['batch_id'] ?? []), 2);

// ---------------------------------------------------------------- seguranca
echo "\n== Escape ==\n";
$html = $corpo($render($base));
$check('as etiquetas passam por e()', str_contains($origem, 'e((string) $a[\'valor\'])'), true);
$check('os links passam por e()', str_contains($origem, 'e($a[\'url\'])'), true);

@unlink($vista);

echo "\n" . ($falhas === 0 ? "TUDO CERTO\n\n" : "{$falhas} FALHA(S)\n\n");
exit($falhas === 0 ? 0 : 1);
