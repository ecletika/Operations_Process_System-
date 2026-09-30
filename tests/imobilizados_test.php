<?php

declare(strict_types=1);

/**
 * Testes do separador "Imobilizados (todos)" da Caixa de Entrada.
 *
 * A Caixa de Entrada é pessoal, e este separador é a excepção: mostra os
 * imobilizados de toda a gente. Por isso o que mais interessa verificar é o
 * limite — que ninguém passa a ver processos de departamentos fora do seu
 * âmbito por causa desta lista (RN-0011).
 *
 * Uso: php tests/imobilizados_test.php
 */

use App\Modules\Process\Repositories\ProcessRepository;

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
} else {
    require __DIR__ . '/../app/Core/autoload.php';
}

date_default_timezone_set('UTC');

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

// =====================================================================
echo "\n== A consulta respeita o âmbito de quem está a ver ==\n";

$repo = (new ReflectionClass(ProcessRepository::class))->newInstanceWithoutConstructor();
$metodo = new ReflectionMethod(ProcessRepository::class, 'listImobilizadosAbertos');

$check('o método recebe os departamentos visíveis', $metodo->getNumberOfParameters(), 1);
$check('aceita null para quem vê tudo',
    (string) $metodo->getParameters()[0]->getType(), '?array');

// Sem departamentos visíveis não se devolve nada — e sobretudo não se
// devolve TUDO, que era o erro fácil aqui.
$prop = new ReflectionProperty(ProcessRepository::class, 'pdo');
$prop->setAccessible(true);
$prop->setValue($repo, new class () extends PDO {
    public function __construct() {}
    public function prepare(string $q, array $o = []): PDOStatement|false
    {
        throw new RuntimeException('não devia ter chegado à base de dados');
    }
});

$semAcesso = $metodo->invoke($repo, []);
$check('lista vazia de departamentos não vai sequer à base de dados', $semAcesso, []);

// =====================================================================
echo "\n== O SQL diz o que tem de dizer ==\n";

$fonte = (string) file_get_contents(__DIR__ . '/../app/Modules/Process/Repositories/ProcessRepository.php');
$inicio = strpos($fonte, 'function listImobilizadosAbertos');
$sql = substr($fonte, (int) $inicio, 2600);

$check('só imobilizados', str_contains($sql, "sub.code = 'IMO'"), true);
$check('só processos abertos', str_contains($sql, "st.code NOT IN ('SOLVED', 'CLOSED')"), true);
$check('sem arquivados', str_contains($sql, 'p.archived = 0'), true);
$check('filtra por departamento quando há âmbito', str_contains($sql, 'd.id IN ('), true);
$check('traz a equipa para a coluna', str_contains($sql, 'AS equipa'), true);
$check('mais antigo primeiro', str_contains($sql, 'ORDER BY p.created_at ASC'), true);
$check('usa parâmetros ligados, não concatenação',
    (bool) preg_match("/:dep'\s*\.\s*\\\$i/", $sql), true);

// =====================================================================
echo "\n== O ecrã ==\n";

$vista = (string) file_get_contents(__DIR__ . '/../app/Modules/Process/Views/mine.php');

$check('a aba antiga passou a "Meus Imobilizados"', str_contains($vista, 'Meus Imobilizados'), true);
$check('existe a aba "Imobilizados (todos)"', str_contains($vista, 'Imobilizados (todos)'), true);
$check('a aba nova aponta para a sua vista', str_contains($vista, 'view=imobilizados_todos'), true);
$check('mostra quantos carros estão parados', str_contains($vista, 'Carros parados'), true);
$check('destaca os que não têm responsável', str_contains($vista, 'Sem responsável'), true);
$check('mostra há quanto tempo cada um está parado', str_contains($vista, 'Parado há'), true);

// O filtro por assunto sai de cena nessa aba; sem guardas no JS, levava o
// filtro por matrícula à frente.
$check('o JS aguenta a falta do filtro por assunto',
    str_contains($vista, 'if (subject) { subject.addEventListener'), true);
$check('e não rebenta ao limpar', str_contains($vista, "if (subject) { subject.value = ''; }"), true);

// =====================================================================
echo "\n== O controlador ==\n";

$ctrl = (string) file_get_contents(__DIR__ . '/../app/Modules/Process/Controllers/ProcessController.php');

$check('reconhece a vista nova', str_contains($ctrl, "\$view === 'imobilizados_todos'"), true);
$check('passa o âmbito de visibilidade à consulta',
    str_contains($ctrl, 'listImobilizadosAbertos($this->viewScopeDepartmentIds())'), true);
$check('não carrega as listas pessoais nessa aba',
    (bool) preg_match('/\$imobilizadosTodos\s*\?\s*\[\]/', $ctrl), true);

// =====================================================================
echo "\n== Quem vê o separador ==\n";

$check('a vista só é aberta com a permissão',
    str_contains($ctrl, "\$view === 'imobilizados_todos' && \$podeVerTodosImobilizados"), true);
$check('a permissão é lida da sessão',
    str_contains($ctrl, "'process.view_all_imobilizados'"), true);
$check('o separador esconde-se sem permissão',
    str_contains($vista, 'if (!empty($podeVerTodosImobilizados)):'), true);

// Quem não tem a permissão e escreve o endereço à mão não entra: o
// controlador força $imobilizadosTodos a falso e a página cai no "Em curso".
$check('sem permissão, o endereço à mão não abre a lista',
    (bool) preg_match('/\$imobilizadosTodos = \$view === .imobilizados_todos. && \$podeVerTodosImobilizados/', $ctrl), true);

$migracao = (string) file_get_contents(__DIR__ . '/../database/039_permissao_imobilizados_todos.sql');
$check('a permissão é criada por migração', str_contains($migracao, 'process.view_all_imobilizados'), true);
$check('a migração não duplica se correr outra vez',
    str_contains($migracao, 'WHERE NOT EXISTS'), true);
$check('fica ligada para Admin e Supervisor',
    str_contains($migracao, "r.code IN ('ROLE_ADMIN', 'ROLE_SUPERVISOR')"), true);
$check('o prefixo põe-na no grupo "Processos" do ecrã de permissões',
    str_starts_with('process.view_all_imobilizados', 'process.'), true);

// =====================================================================
echo "\n== A lista é só de leitura ==\n";

$check('avisa que é só para consultar', str_contains($vista, 'só para consultar'), true);
$check('só os seus processos abrem a ficha',
    str_contains($vista, "(int) (\$process['assigned_to'] ?? 0) === \$userId"), true);
$check('conta também os que criou',
    str_contains($vista, "(int) (\$process['created_by'] ?? 0) === \$userId"), true);
$check('os dos outros não são ligação',
    (bool) preg_match('/<span style="color:#374151"><\?= e\(\$process\[.process_number.\]\) \?><\/span>/', $vista), true);

// Não há aqui nenhum controlo que mude dados — nem botão, nem formulário.
// Isola-se a TABELA, e não o ecrã todo: o painel de filtros por cima é
// partilhado com os outros separadores e tem o seu botão "Limpar", que não
// altera processo nenhum.
$blocoInicio = strpos($vista, '<?php if ($imobilizadosTodos): ?>');
$blocoFim = strpos($vista, '<?php else: ?>', (int) $blocoInicio);
$bloco = substr($vista, (int) $blocoInicio, (int) $blocoFim - (int) $blocoInicio);

$check('o recorte apanhou mesmo a tabela dos imobilizados',
    str_contains($bloco, 'Carros parados') && str_contains($bloco, 'Parado há'), true);

$check('a tabela não tem formulários', str_contains($bloco, '<form'), false);
$check('nem botões de ação', str_contains($bloco, '<button'), false);
$check('nem selects para reatribuir', str_contains($bloco, '<select'), false);

echo "\n" . ($falhas === 0 ? "TODOS OS TESTES PASSARAM\n\n" : "{$falhas} TESTE(S) FALHARAM\n\n");
exit($falhas === 0 ? 0 : 1);
