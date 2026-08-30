# Relatórios de SLA — Plano de Implementação

> **Para quem executa:** as tarefas estão em caixas (`- [ ]`) para acompanhamento. Cada tarefa produz software a funcionar e termina em commit.

**Objetivo:** dar à direção sete relatórios que respondam a *onde se perde o tempo*, *porquê* e *estamos a melhorar* — e não apenas *quem cumpriu*.

**Arquitetura:** o módulo de Relatórios já é genérico: a vista `report.php` descobre as colunas com `array_keys($rows[0])`, esconde as que terminam em `_id`, e o Excel reaproveita as mesmas linhas. Um relatório novo é, portanto, um método em `AnalyticsRepository` que devolve linhas com chaves em português, mais uma entrada em `ReportController::REPORTS` e um caso no `match`. Nada de vistas novas para os cinco primeiros.

**Regra de ouro:** todo o tempo de SLA passa por `sla_process_minutes()` / `sla_elapsed_minutes()`. Nunca `TIMESTAMPDIFF`. Estes números pagam prémios.

**Stack:** PHP 8.4 sem framework, MySQL/MariaDB, migrations `.sql` para phpMyAdmin, testes em `tests/sla_test.php` (correm sem base de dados, com `C:\xampp\php\php.exe`).

---

## Estrutura de ficheiros

| Ficheiro | Responsabilidade |
|---|---|
| `app/Modules/Reports/Repositories/AnalyticsRepository.php` | Modificar: sete métodos novos, um por relatório |
| `app/Modules/Reports/Controllers/ReportController.php` | Modificar: entradas em `REPORTS` e casos no `match` |
| `app/Modules/Process/Repositories/ProcessRepository.php` | Modificar: gravar o motivo da pausa |
| `app/Modules/Process/Services/ProcessService.php` | Modificar: passar o motivo ao repositório |
| `database/037_motivo_da_pausa.sql` | Criar: coluna do motivo + backfill do histórico |
| `database/038_fecho_mensal_sla.sql` | Criar: tabela de fecho mensal + preenchimento inicial |
| `tests/sla_test.php` | Modificar: cobertura dos cálculos novos |

Um relatório por método, sem métodos partilhados entre relatórios — é o que mantém cada um legível e alterável sem medo.

---

## Fase 1 — Os cinco que já têm todos os dados

### Tarefa 1: Cumpriu, mas voltou

Processos fechados dentro do prazo e reabertos a seguir. É o contrapeso ao prémio: sem ele, fechar à pressa para bater o relógio compensa.

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [x] **Passo 1: método no repositório**

Acrescentar a `AnalyticsRepository`, imediatamente antes de `operators()`:

```php
    /**
     * Cumpriu o prazo, mas o processo voltou. Um processo fechado dentro do
     * SLA e reaberto pouco depois não foi resolvido — foi despachado. Desde
     * que o SLA paga prémios, isto tem de estar à vista.
     *
     * Não se filtra por uma janela de dias: mostra-se quantos dias passaram
     * até à reabertura e deixa-se quem lê decidir o que é "cedo demais". Uma
     * reabertura ao fim de um dia diz algo muito diferente de uma ao fim de
     * três semanas, e o número em bruto é mais honesto do que um corte fixo.
     */
    public function reopenedWithinSla(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT p.id, p.process_number, p.created_at, p.closed_at,
                   p.sla_paused_total_minutes, p.sla_closed_minutes, p.reopen_count,
                   pr.name AS prioridade, pr.default_sla_minutes AS sla_minutos,
                   c.name AS cliente,
                   CONCAT(br.name, ' · ', d.name) AS equipa,
                   TRIM(CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, ''))) AS fechado_por,
                   (SELECT MIN(e.created_at) FROM tb_event e
                     WHERE e.process_id = p.id AND e.event_type = 'PROCESS_CLOSED'
                       AND e.deleted_at IS NULL) AS primeiro_fecho,
                   (SELECT MIN(e.created_at) FROM tb_event e
                     WHERE e.process_id = p.id AND e.event_type = 'PROCESS_REOPENED'
                       AND e.deleted_at IS NULL) AS primeira_reabertura
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_customer c ON c.id = p.customer_id
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_batch bt ON bt.id = p.batch_id
            JOIN tb_department d ON d.id = bt.department_id
            JOIN tb_branch br ON br.id = d.branch_id
            LEFT JOIN tb_user u ON u.id = p.closed_by
            WHERE p.deleted_at IS NULL AND p.reopen_count > 0
              AND p.closed_at IS NOT NULL AND st.code IN ('SOLVED', 'CLOSED') {$period}
            ORDER BY p.created_at DESC
        ", $params);

        $resultado = [];
        foreach ($rows as $row) {
            // Só interessa quem cumpriu o prazo: quem já falhou está no
            // Relatório SLA, e apareceria aqui a duplicar o mesmo problema.
            $minutos = sla_process_minutes($row);
            if (self::withinSla($minutos, $row['sla_minutos']) !== 1) {
                continue;
            }

            // Dias corridos entre o primeiro fecho e a primeira reabertura,
            // lidos da Timeline e não de closed_at — ao reabrir, o closed_at
            // do fecho anterior é limpo, e só os eventos guardam essa data.
            // Tempo real e não minutos de atendimento: a pergunta é quanto
            // tempo o cliente esteve a pensar que o assunto estava resolvido.
            $dias = '—';
            if ($row['primeiro_fecho'] !== null && $row['primeira_reabertura'] !== null) {
                $delta = strtotime((string) $row['primeira_reabertura']) - strtotime((string) $row['primeiro_fecho']);
                $dias = max(0, (int) floor($delta / 86400));
            }

            $resultado[] = [
                'id' => (int) $row['id'],
                'processo' => $row['process_number'],
                'cliente' => $row['cliente'],
                'equipa' => $row['equipa'],
                'prioridade' => $row['prioridade'],
                'fechado_por' => $row['fechado_por'],
                'tempo_ate_fechar' => sla_human($minutos),
                'dias_ate_reabrir' => $dias,
                'reaberturas' => (int) $row['reopen_count'],
            ];
        }

        return $resultado;
    }
```

- [x] **Passo 2: registar o relatório**

Em `ReportController::REPORTS`, a seguir à linha do `'sla'`:

```php
        'sla_reopened' => ['↩️ Cumpriu, mas voltou', 'Processos fechados dentro do SLA que foram reabertos — o contrapeso ao prémio.'],
```

E em `buildReportRows()`, dentro do `match ($code)`:

```php
            'sla_reopened' => $repository->reopenedWithinSla($from, $to),
```

- [x] **Passo 3: verificar sintaxe**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
C:\xampp\php\php.exe -l app/Modules/Reports/Controllers/ReportController.php
```

Esperado: `No syntax errors detected` nos dois.

- [x] **Passo 4: abrir `/reports/view/sla_reopened` e confirmar**

Confirmar: a coluna `id` não aparece (é escondida por terminar em `id`), a coluna `processo` está clicável, e o botão de Excel descarrega.

- [x] **Passo 5: commit**

```bash
git add app/Modules/Reports
git commit -m "Relatorio: processos que cumpriram o SLA mas foram reabertos"
```

---

### Tarefa 2: Onde se perde o tempo

Decompõe o ciclo em fila, trabalho, pausa e encerrado. É o relatório que diz *em que frente atacar*.

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [x] **Passo 1: método no repositório**

```php
    /**
     * Onde se perde o tempo, por equipa: quanto do ciclo foi espera na fila,
     * trabalho, pausa e tempo encerrado entre reaberturas.
     *
     * Cada parcela aponta para uma acção diferente — fila alta é
     * dimensionamento, pausa alta é processo com clientes e fornecedores,
     * trabalho alto é formação ou ferramentas. Sem as separar decide-se às
     * cegas, que é a situação de hoje.
     */
    public function timeBreakdown(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT CONCAT(br.name, ' · ', d.name) AS equipa,
                   p.created_at, p.assumed_at, p.closed_at,
                   p.sla_paused_total_minutes, p.sla_closed_minutes
            FROM tb_process p
            JOIN tb_status st ON st.id = p.status_id
            JOIN tb_batch bt ON bt.id = p.batch_id
            JOIN tb_department d ON d.id = bt.department_id
            JOIN tb_branch br ON br.id = d.branch_id
            WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL
              AND st.code IN ('SOLVED', 'CLOSED') {$period}
            ORDER BY equipa ASC
        ", $params);

        $grupos = [];
        foreach ($rows as $row) {
            $equipa = (string) $row['equipa'];
            $grupos[$equipa] ??= ['n' => 0, 'fila' => 0, 'trabalho' => 0, 'pausa' => 0, 'encerrado' => 0];

            $pausa = max(0, (int) $row['sla_paused_total_minutes']);
            $encerrado = max(0, (int) $row['sla_closed_minutes']);

            // Fila = da entrada até alguém assumir. Sem assumed_at (processos
            // antigos), conta zero em vez de inventar.
            $fila = $row['assumed_at'] !== null
                ? sla_elapsed_minutes((string) $row['created_at'], (string) $row['assumed_at'])
                : 0;

            // Trabalho = o que sobra do tempo de SLA depois de tirar a fila.
            $trabalho = max(0, sla_process_minutes($row) - $fila);

            $grupos[$equipa]['n']++;
            $grupos[$equipa]['fila'] += $fila;
            $grupos[$equipa]['trabalho'] += $trabalho;
            $grupos[$equipa]['pausa'] += $pausa;
            $grupos[$equipa]['encerrado'] += $encerrado;
        }

        $resultado = [];
        foreach ($grupos as $equipa => $g) {
            $total = $g['fila'] + $g['trabalho'] + $g['pausa'] + $g['encerrado'];
            $pct = static fn (int $parcela): string => $total > 0
                ? round($parcela / $total * 100) . '%'
                : '—';

            $resultado[] = [
                'equipa' => $equipa,
                'concluidos' => $g['n'],
                'na_fila' => sla_human((int) round($g['fila'] / $g['n'])),
                'pct_fila' => $pct($g['fila']),
                'a_trabalhar' => sla_human((int) round($g['trabalho'] / $g['n'])),
                'pct_trabalho' => $pct($g['trabalho']),
                'em_pausa' => sla_human((int) round($g['pausa'] / $g['n'])),
                'pct_pausa' => $pct($g['pausa']),
                'encerrado' => sla_human((int) round($g['encerrado'] / $g['n'])),
                'pct_encerrado' => $pct($g['encerrado']),
            ];
        }

        return $resultado;
    }
```

- [x] **Passo 2: registar o relatório**

Em `REPORTS`:

```php
        'sla_breakdown' => ['⏳ Onde se perde o tempo', 'Fila, trabalho, pausa e tempo encerrado — por equipa, em média por processo.'],
```

No `match`:

```php
            'sla_breakdown' => $repository->timeBreakdown($from, $to),
```

- [x] **Passo 3: verificar sintaxe**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
```

Esperado: `No syntax errors detected`.

- [x] **Passo 4: teste do cálculo**

Acrescentar a `tests/sla_test.php`, antes da secção "Contrato de changeStatus":

```php
// =====================================================================
echo "\n== Decomposição do tempo: as parcelas têm de fechar ==\n";
$horarioLigado(true);

// Entrou 09:00, assumido 09:30, fechado 12:00, com 30 min de pausa.
$decomposto = [
    'created_at' => $utc('2026-08-26 09:00'),
    'assumed_at' => $utc('2026-08-26 09:30'),
    'closed_at' => $utc('2026-08-26 12:00'),
    'sla_paused_total_minutes' => 30,
    'sla_closed_minutes' => 0,
];
$fila = sla_elapsed_minutes($decomposto['created_at'], $decomposto['assumed_at']);
$trabalho = max(0, sla_process_minutes($decomposto) - $fila);

$check('fila: da entrada até ser assumido', $fila, 30);
$check('trabalho: o resto do tempo de SLA', $trabalho, 120);
$check('fila + trabalho + pausa = tempo útil total',
    $fila + $trabalho + 30, sla_elapsed_minutes($decomposto['created_at'], $decomposto['closed_at']));
```

- [x] **Passo 5: correr os testes**

```bash
C:\xampp\php\php.exe tests/sla_test.php
```

Esperado: `TODOS OS TESTES PASSARAM`.

- [x] **Passo 6: commit**

```bash
git add app/Modules/Reports tests/sla_test.php
git commit -m "Relatorio: onde se perde o tempo (fila, trabalho, pausa, encerrado)"
```

---

### Tarefa 3: Tempo até alguém pegar

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [x] **Passo 1: método no repositório**

```php
    /**
     * Tempo entre a entrada do processo e o momento em que alguém o assume,
     * por equipa e hora do dia. É a única parcela do SLA inteiramente sob
     * controlo da casa — não depende de clientes nem de fornecedores.
     */
    public function timeToAssume(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT CONCAT(br.name, ' · ', d.name) AS equipa,
                   p.created_at, p.assumed_at
            FROM tb_process p
            JOIN tb_batch bt ON bt.id = p.batch_id
            JOIN tb_department d ON d.id = bt.department_id
            JOIN tb_branch br ON br.id = d.branch_id
            WHERE p.deleted_at IS NULL AND p.assumed_at IS NOT NULL {$period}
            ORDER BY equipa ASC
        ", $params);

        $grupos = [];
        foreach ($rows as $row) {
            $minutos = sla_elapsed_minutes((string) $row['created_at'], (string) $row['assumed_at']);
            // A hora que interessa é a da ENTRADA — é quando a fila cresce.
            $hora = (new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')))
                ->setTimezone(app_timezone())
                ->format('H');

            $chave = $row['equipa'] . '|' . $hora;
            $grupos[$chave] ??= ['equipa' => $row['equipa'], 'hora' => $hora, 'tempos' => []];
            $grupos[$chave]['tempos'][] = $minutos;
        }

        $resultado = [];
        foreach ($grupos as $g) {
            $tempos = $g['tempos'];
            sort($tempos);
            $n = count($tempos);

            $resultado[] = [
                'equipa' => $g['equipa'],
                'hora_de_entrada' => $g['hora'] . 'h',
                'processos' => $n,
                'espera_media' => sla_human((int) round(array_sum($tempos) / $n)),
                // A mediana diz mais do que a média quando há um ou dois
                // processos esquecidos a puxar tudo para cima.
                'espera_mediana' => sla_human((int) $tempos[intdiv($n, 2)]),
                'pior_caso' => sla_human((int) $tempos[$n - 1]),
            ];
        }

        usort($resultado, static fn (array $a, array $b): int
            => [$a['equipa'], $a['hora_de_entrada']] <=> [$b['equipa'], $b['hora_de_entrada']]);

        return $resultado;
    }
```

- [x] **Passo 2: registar o relatório**

Em `REPORTS`:

```php
        'sla_pickup' => ['🕐 Tempo até alguém pegar', 'Quanto tempo os processos esperam na fila, por equipa e hora de entrada.'],
```

No `match`:

```php
            'sla_pickup' => $repository->timeToAssume($from, $to),
```

- [x] **Passo 3: verificar sintaxe e testes**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
C:\xampp\php\php.exe tests/sla_test.php
```

Esperado: sem erros de sintaxe e `TODOS OS TESTES PASSARAM`.

- [x] **Passo 4: commit**

```bash
git add app/Modules/Reports
git commit -m "Relatorio: tempo ate um processo ser assumido, por equipa e hora"
```

---

### Tarefa 4: Prazos que ninguém cumpre

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [x] **Passo 1: método no repositório**

```php
    /**
     * Cumprimento por ASSUNTO em vez de por pessoa. Quando um assunto falha
     * em toda a gente, o problema é o prazo e não a equipa — e um prémio
     * assente num prazo impossível é contestado com razão.
     */
    public function slaBySubject(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT sub.name AS assunto, pr.name AS prioridade,
                   pr.default_sla_minutes AS sla_minutos,
                   p.created_at, p.closed_at,
                   p.sla_paused_total_minutes, p.sla_closed_minutes,
                   COUNT(DISTINCT p.closed_by) OVER (PARTITION BY sub.id, pr.id) AS operadores
            FROM tb_process p
            JOIN tb_subject sub ON sub.id = p.subject_id
            JOIN tb_priority pr ON pr.id = p.priority_id
            JOIN tb_status st ON st.id = p.status_id
            WHERE p.deleted_at IS NULL AND p.closed_at IS NOT NULL
              AND st.code IN ('SOLVED', 'CLOSED') {$period}
        ", $params);

        $grupos = [];
        foreach ($rows as $row) {
            $chave = $row['assunto'] . '|' . $row['prioridade'];
            $grupos[$chave] ??= [
                'assunto' => $row['assunto'],
                'prioridade' => $row['prioridade'],
                'sla_minutos' => (int) $row['sla_minutos'],
                'operadores' => (int) $row['operadores'],
                'n' => 0, 'dentro' => 0, 'soma' => 0, 'tempos' => [],
            ];

            $minutos = sla_process_minutes($row);
            $grupos[$chave]['n']++;
            $grupos[$chave]['dentro'] += self::withinSla($minutos, $row['sla_minutos']);
            $grupos[$chave]['soma'] += $minutos;
            $grupos[$chave]['tempos'][] = $minutos;
        }

        $resultado = [];
        foreach ($grupos as $g) {
            $pct = (int) round($g['dentro'] / $g['n'] * 100);
            sort($g['tempos']);

            $resultado[] = [
                'assunto' => $g['assunto'],
                'prioridade' => $g['prioridade'],
                'sla_minutos' => $g['sla_minutos'],
                'concluidos' => $g['n'],
                'pct_dentro_sla' => $pct . '%',
                'tempo_medio' => sla_human((int) round($g['soma'] / $g['n'])),
                'tempo_mediano' => sla_human((int) $g['tempos'][intdiv($g['n'], 2)]),
                'operadores_envolvidos' => $g['operadores'],
                // Falha em toda a gente e em vários operadores: é o prazo.
                'veredicto' => ($pct < 50 && $g['operadores'] >= 3)
                    ? 'Prazo provavelmente irrealista'
                    : '',
            ];
        }

        usort($resultado, static fn (array $a, array $b): int
            => (int) $a['pct_dentro_sla'] <=> (int) $b['pct_dentro_sla']);

        return $resultado;
    }
```

- [x] **Passo 2: registar o relatório**

Em `REPORTS`:

```php
        'sla_subject' => ['🎯 Prazos por assunto', 'Cumprimento por assunto — mostra que prazos estão mal calibrados.'],
```

No `match`:

```php
            'sla_subject' => $repository->slaBySubject($from, $to),
```

- [x] **Passo 3: verificar sintaxe**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
```

Esperado: `No syntax errors detected`.

- [x] **Passo 4: confirmar que a função de janela é suportada**

`COUNT(...) OVER (PARTITION BY ...)` exige MySQL 8 ou MariaDB 10.2+. Confirmar no phpMyAdmin:

```sql
SELECT VERSION();
```

Se for anterior, substituir a coluna `operadores` por um subselect:

```sql
                   (SELECT COUNT(DISTINCT p2.closed_by) FROM tb_process p2
                     WHERE p2.subject_id = p.subject_id AND p2.priority_id = p.priority_id
                       AND p2.deleted_at IS NULL AND p2.closed_at IS NOT NULL) AS operadores
```

- [x] **Passo 5: commit**

```bash
git add app/Modules/Reports
git commit -m "Relatorio: cumprimento de SLA por assunto (prazos mal calibrados)"
```

---

### Tarefa 5: Carga contra capacidade

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [x] **Passo 1: método no repositório**

```php
    /**
     * Volume de entrada contra incumprimento, por dia da semana e hora. Se o
     * SLA falha onde há mais volume, falta gente; se falha onde há pouco, o
     * problema é outro — e evita-se contratar sem necessidade.
     */
    public function loadVersusFailures(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT p.created_at, p.closed_at, p.sla_paused_total_minutes, p.sla_closed_minutes,
                   pr.default_sla_minutes AS sla_minutos
            FROM tb_process p
            JOIN tb_priority pr ON pr.id = p.priority_id
            WHERE p.deleted_at IS NULL {$period}
        ", $params);

        $dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        $grupos = [];

        foreach ($rows as $row) {
            $entrada = (new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')))
                ->setTimezone(app_timezone());
            $chave = $entrada->format('w') . '|' . $entrada->format('H');

            $grupos[$chave] ??= [
                'ordem' => (int) $entrada->format('w') * 100 + (int) $entrada->format('H'),
                'dia' => $dias[(int) $entrada->format('w')],
                'hora' => $entrada->format('H') . 'h',
                'entrados' => 0, 'concluidos' => 0, 'fora' => 0,
            ];

            $grupos[$chave]['entrados']++;

            if ($row['closed_at'] !== null) {
                $grupos[$chave]['concluidos']++;
                if (self::withinSla(sla_process_minutes($row), $row['sla_minutos']) === 0) {
                    $grupos[$chave]['fora']++;
                }
            }
        }

        usort($grupos, static fn (array $a, array $b): int => $a['ordem'] <=> $b['ordem']);

        return array_map(static function (array $g): array {
            return [
                'dia' => $g['dia'],
                'hora_de_entrada' => $g['hora'],
                'entrados' => $g['entrados'],
                'concluidos' => $g['concluidos'],
                'fora_do_sla' => $g['fora'],
                'pct_fora' => $g['concluidos'] > 0
                    ? round($g['fora'] / $g['concluidos'] * 100) . '%'
                    : '—',
            ];
        }, $grupos);
    }
```

- [x] **Passo 2: registar o relatório**

Em `REPORTS`:

```php
        'sla_load' => ['📈 Carga contra capacidade', 'Entradas e incumprimentos por dia da semana e hora — onde falta gente.'],
```

No `match`:

```php
            'sla_load' => $repository->loadVersusFailures($from, $to),
```

- [x] **Passo 3: verificar sintaxe e testes**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
C:\xampp\php\php.exe tests/sla_test.php
```

Esperado: sem erros e `TODOS OS TESTES PASSARAM`.

- [x] **Passo 4: commit**

```bash
git add app/Modules/Reports
git commit -m "Relatorio: carga de entrada contra incumprimento de SLA"
```

---

## Fase 2 — Motivo da pausa

Hoje o motivo só existe no texto do evento (`"Em espera: Aguarda Cliente (SLA em pausa)"`). Para se poder agrupar por motivo, tem de ficar num campo.

### Tarefa 6: gravar o motivo da pausa

**Ficheiros:**
- Modificar: `app/Modules/Process/Repositories/ProcessRepository.php`
- Modificar: `app/Modules/Process/Services/ProcessService.php`
- Criar: `database/037_motivo_da_pausa.sql`

- [ ] **Passo 1: migration com a coluna e o backfill**

Criar `database/037_motivo_da_pausa.sql`:

```sql
-- Guarda QUAL o motivo de cada pausa do SLA.
--
-- Os motivos já existem como estados marcados com is_waiting=1 (Aguarda
-- Cliente/Peças/Oficina/Terceiros, e os que o Administrador acrescentar).
-- Só que, ao pôr um processo em espera, o motivo ficava apenas escrito no
-- texto do evento da Timeline — legível para uma pessoa, inútil para agrupar.
--
-- Passa a haver tb_process.wait_status_id, preenchido ao entrar em espera.
-- Para o histórico, o motivo é recuperado do título do evento, que tem sempre
-- a forma "Em espera: <nome do estado> (SLA em pausa)".
--
-- Idempotente. Correr no phpMyAdmin (aba SQL).
SET NAMES utf8mb4;

-- 1) Coluna no processo: em que motivo está parado AGORA.
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_process'
             AND column_name = 'wait_status_id');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_process ADD COLUMN wait_status_id BIGINT UNSIGNED NULL AFTER wait_started_at',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Coluna no evento: qual o motivo de CADA pausa (é o que os relatórios leem).
SET @c := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'tb_event'
             AND column_name = 'wait_status_id');
SET @s := IF(@c = 0,
  'ALTER TABLE tb_event ADD COLUMN wait_status_id BIGINT UNSIGNED NULL AFTER event_type',
  'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 3) Histórico: extrai o motivo do título do evento e liga-o ao estado.
--    "Em espera: Aguarda Cliente (SLA em pausa)" -> "Aguarda Cliente"
UPDATE tb_event e
  JOIN tb_status s
    ON s.is_waiting = 1
   AND e.title = CONCAT('Em espera: ', s.name, ' (SLA em pausa)')
   SET e.wait_status_id = s.id
 WHERE e.event_type = 'PROCESS_WAITING'
   AND e.wait_status_id IS NULL;

SELECT
    (SELECT COUNT(*) FROM tb_event
      WHERE event_type = 'PROCESS_WAITING' AND deleted_at IS NULL) AS pausas_no_historico,
    (SELECT COUNT(*) FROM tb_event
      WHERE event_type = 'PROCESS_WAITING' AND deleted_at IS NULL
        AND wait_status_id IS NOT NULL) AS com_motivo_identificado;
```

- [ ] **Passo 2: correr a migration no phpMyAdmin**

Colar o ficheiro na aba SQL. Conferir o resultado: `com_motivo_identificado` deve estar próximo de `pausas_no_historico`. Uma diferença pequena é normal — são estados de espera que entretanto foram renomeados, e por isso já não coincidem com o texto gravado.

- [ ] **Passo 3: `changeStatus` passa a receber o estado de espera**

Em `ProcessRepository::changeStatus()`, no ramo `if ($isWaiting)`, substituir o UPDATE por:

```php
            $stmt = $this->pdo->prepare('
                UPDATE tb_process
                SET status_id = :status_id,
                    wait_started_at = COALESCE(wait_started_at, NOW()),
                    wait_status_id = :status_id_motivo,
                    updated_by = :user_id, updated_at = NOW()
                WHERE id = :id
            ');
```

e no ramo `else`, acrescentar `wait_status_id = NULL,` a seguir a `wait_started_at = NULL,`.

Depois, no bloco de parâmetros:

```php
        $params = ['id' => $id, 'status_id' => $statusId, 'user_id' => $userId];
        if ($isWaiting) {
            $params['status_id_motivo'] = $statusId;
        } else {
            $params['paused_minutes'] = max(0, $pausedMinutesToAdd);
            $params['paused_total'] = max(0, $pausedMinutesToAdd);
        }
```

- [ ] **Passo 4: o evento passa a guardar o motivo**

Em `EventRepository::create()`, acrescentar o parâmetro opcional:

```php
    public function create(int $processId, string $eventType, string $title, ?string $description, ?int $userId, ?int $waitStatusId = null): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO tb_event (uuid, process_id, event_type, wait_status_id, title, description, user_id, created_at)
            VALUES (UUID(), :process_id, :event_type, :wait_status_id, :title, :description, :user_id, NOW())
        ');
        $stmt->execute([
            'process_id' => $processId,
            'event_type' => $eventType,
            'wait_status_id' => $waitStatusId,
            'title' => $title,
            'description' => $description,
            'user_id' => $userId,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
```

Em `TimelineService::record()`, acrescentar o mesmo parâmetro e passá-lo:

```php
    public function record(int $processId, string $eventType, string $title, ?string $description, ?int $userId, ?int $waitStatusId = null): void
    {
        $eventId = $this->events->create($processId, $eventType, $title, $description, $userId, $waitStatusId);

        $preset = self::PRESETS[$eventType] ?? ['icon' => 'circle', 'color' => '#6b7280'];

        $this->timeline->create($processId, $eventId, $title, $description, $preset['icon'], $preset['color']);
    }
```

Em `ProcessService::changeStatus()`, na chamada ao `timeline->record`, passar o estado quando é uma espera:

```php
        $this->timeline->record(
            $processId,
            $isWaiting ? 'PROCESS_WAITING' : 'PROCESS_RESUMED',
            $isWaiting ? "Em espera: {$label} (SLA em pausa)" : "Tratamento retomado ({$label}) — SLA a contar",
            null,
            $userId,
            $isWaiting ? $statusId : null
        );
```

- [ ] **Passo 5: verificar sintaxe**

```bash
C:\xampp\php\php.exe -l app/Modules/Process/Repositories/ProcessRepository.php
C:\xampp\php\php.exe -l app/Modules/Process/Repositories/EventRepository.php
C:\xampp\php\php.exe -l app/Modules/Process/Services/TimelineService.php
C:\xampp\php\php.exe -l app/Modules/Process/Services/ProcessService.php
```

Esperado: `No syntax errors detected` nos quatro.

- [ ] **Passo 6: confirmar na aplicação**

Pôr um processo em espera, retomá-lo, e confirmar no phpMyAdmin:

```sql
SELECT e.title, e.wait_status_id, s.name
FROM tb_event e LEFT JOIN tb_status s ON s.id = e.wait_status_id
WHERE e.event_type = 'PROCESS_WAITING' ORDER BY e.id DESC LIMIT 3;
```

Esperado: a linha mais recente tem `wait_status_id` preenchido e o nome do estado correto.

- [ ] **Passo 7: commit**

```bash
git add app/Modules/Process database/037_motivo_da_pausa.sql
git commit -m "Pausas do SLA passam a guardar o motivo, e nao so o texto"
```

---

### Tarefa 7: relatório dos motivos de pausa

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [ ] **Passo 1: método no repositório**

```php
    /**
     * Porque é que os processos param: distribuição das pausas pelo motivo,
     * com quanto tempo cada uma custa em média.
     *
     * Lê o motivo de tb_event.wait_status_id (migration 037) e mede cada
     * pausa até ao PROCESS_RESUMED seguinte, em minutos de atendimento.
     */
    public function pauseReasons(?string $from, ?string $to): array
    {
        [$period, $params] = $this->periodClause($from, $to);

        $rows = $this->run("
            SELECT e.process_id, e.event_type, e.created_at,
                   IFNULL(s.name, 'Motivo não registado') AS motivo
            FROM tb_event e
            JOIN tb_process p ON p.id = e.process_id AND p.deleted_at IS NULL
            LEFT JOIN tb_status s ON s.id = e.wait_status_id
            WHERE e.event_type IN ('PROCESS_WAITING', 'PROCESS_RESUMED')
              AND e.deleted_at IS NULL {$period}
            ORDER BY e.process_id ASC, e.created_at ASC, e.id ASC
        ", $params);

        // Emparelha cada espera com o retomar seguinte, dentro do mesmo
        // processo. Uma espera ainda a decorrer não entra: ainda não custou
        // o seu tempo todo.
        $motivos = [];
        $processoAtual = null;
        $inicio = null;
        $motivoAberto = null;

        foreach ($rows as $row) {
            if ((int) $row['process_id'] !== $processoAtual) {
                $processoAtual = (int) $row['process_id'];
                $inicio = null;
                $motivoAberto = null;
            }

            if ($row['event_type'] === 'PROCESS_WAITING') {
                if ($inicio === null) {
                    $inicio = (string) $row['created_at'];
                    $motivoAberto = (string) $row['motivo'];
                }
                continue;
            }

            if ($inicio !== null) {
                $minutos = sla_elapsed_minutes($inicio, (string) $row['created_at']);
                $motivos[$motivoAberto] ??= ['n' => 0, 'soma' => 0, 'processos' => []];
                $motivos[$motivoAberto]['n']++;
                $motivos[$motivoAberto]['soma'] += $minutos;
                $motivos[$motivoAberto]['processos'][$processoAtual] = true;
                $inicio = null;
                $motivoAberto = null;
            }
        }

        $totalMinutos = array_sum(array_column($motivos, 'soma'));

        $resultado = [];
        foreach ($motivos as $motivo => $m) {
            $resultado[] = [
                'motivo' => $motivo,
                'pausas' => $m['n'],
                'processos_afetados' => count($m['processos']),
                'tempo_total' => sla_human($m['soma']),
                'tempo_medio' => sla_human((int) round($m['soma'] / $m['n'])),
                'pct_do_tempo_parado' => $totalMinutos > 0
                    ? round($m['soma'] / $totalMinutos * 100) . '%'
                    : '—',
            ];
        }

        usort($resultado, static fn (array $a, array $b): int => $b['pausas'] <=> $a['pausas']);

        return $resultado;
    }
```

- [ ] **Passo 2: registar o relatório**

Em `REPORTS`:

```php
        'sla_pauses' => ['⏸️ Porque param os processos', 'Motivos de pausa, quanto tempo custam e quantos processos afetam.'],
```

No `match`:

```php
            'sla_pauses' => $repository->pauseReasons($from, $to),
```

- [ ] **Passo 3: verificar sintaxe e testes**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
C:\xampp\php\php.exe tests/sla_test.php
```

Esperado: sem erros e `TODOS OS TESTES PASSARAM`.

- [ ] **Passo 4: commit**

```bash
git add app/Modules/Reports
git commit -m "Relatorio: motivos de pausa do SLA e o tempo que custam"
```

---

## Fase 3 — Fecho do mês

O tempo é calculado a cada consulta. Se o horário de atendimento ou um feriado mudarem, os meses anteriores mudam com eles — e um prémio já pago deixa de bater certo com o relatório. O fecho congela o apuramento.

### Tarefa 8: tabela de fecho mensal

**Ficheiros:**
- Criar: `database/038_fecho_mensal_sla.sql`

- [ ] **Passo 1: migration**

Criar `database/038_fecho_mensal_sla.sql`:

```sql
-- Fecho mensal do SLA: congela o apuramento de cada mês.
--
-- PORQUÊ: o tempo de SLA é calculado a cada consulta, a partir do horário de
-- atendimento e dos feriados em vigor NESSE momento. Basta alterar um horário
-- ou acrescentar um feriado para os meses anteriores mudarem — e um prémio já
-- pago deixa de bater certo com o relatório que o justificou.
--
-- A partir do fecho, o mês passa a ser lido desta tabela.
--
-- Idempotente: reescreve as linhas do mês que se está a fechar.
-- Correr no phpMyAdmin (aba SQL).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tb_sla_monthly_close (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ano            SMALLINT UNSIGNED NOT NULL,
    mes            TINYINT UNSIGNED NOT NULL,
    batch_id       BIGINT UNSIGNED NOT NULL,
    priority_id    BIGINT UNSIGNED NOT NULL,
    concluidos     INT UNSIGNED NOT NULL DEFAULT 0,
    dentro_sla     INT UNSIGNED NOT NULL DEFAULT 0,
    tempo_medio_min INT UNSIGNED NOT NULL DEFAULT 0,
    fechado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fecho (ano, mes, batch_id, priority_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fecha TODOS os meses já completos (não fecha o mês corrente, que ainda
-- está a mudar). Usa fn_ops_minutos_uteis, instalada pela migration 034.
REPLACE INTO tb_sla_monthly_close
    (ano, mes, batch_id, priority_id, concluidos, dentro_sla, tempo_medio_min)
SELECT YEAR(x.closed_at), MONTH(x.closed_at), x.batch_id, x.priority_id,
       COUNT(*),
       SUM(CASE WHEN x.minutos <= x.sla_minutos THEN 1 ELSE 0 END),
       ROUND(AVG(x.minutos))
  FROM (
    SELECT p.closed_at, p.batch_id, p.priority_id,
           pr.default_sla_minutes AS sla_minutos,
           GREATEST(0, fn_ops_minutos_uteis(p.created_at, p.closed_at)
                       - p.sla_paused_total_minutes - p.sla_closed_minutes) AS minutos
      FROM tb_process p
      JOIN tb_priority pr ON pr.id = p.priority_id
      JOIN tb_status s ON s.id = p.status_id
     WHERE p.deleted_at IS NULL
       AND p.closed_at IS NOT NULL
       AND s.code IN ('SOLVED', 'CLOSED')
       AND pr.default_sla_minutes IS NOT NULL
       AND p.closed_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
  ) x
 GROUP BY YEAR(x.closed_at), MONTH(x.closed_at), x.batch_id, x.priority_id;

SELECT CONCAT(ano, '-', LPAD(mes, 2, '0')) AS mes_fechado,
       SUM(concluidos) AS concluidos,
       CONCAT(ROUND(SUM(dentro_sla) / SUM(concluidos) * 100), '%') AS pct_dentro_sla
  FROM tb_sla_monthly_close
 GROUP BY ano, mes
 ORDER BY ano DESC, mes DESC;
```

- [ ] **Passo 2: correr no phpMyAdmin**

Colar na aba SQL. Esperado: uma linha por mês já completo, com a percentagem de cumprimento.

Se der `FUNCTION ... fn_ops_minutos_uteis does not exist`, correr primeiro a `034_recalcular_sla_pausas.sql`, que a instala.

- [ ] **Passo 3: commit**

```bash
git add database/038_fecho_mensal_sla.sql
git commit -m "Fecho mensal do SLA: congela o apuramento de cada mes"
```

---

### Tarefa 9: relatório de tendência

**Ficheiros:**
- Modificar: `app/Modules/Reports/Repositories/AnalyticsRepository.php`
- Modificar: `app/Modules/Reports/Controllers/ReportController.php`

- [ ] **Passo 1: método no repositório**

```php
    /**
     * O SLA mês a mês, a partir dos meses já fechados (tb_sla_monthly_close).
     * Lê-se do fecho e não dos processos porque um mês fechado não pode mudar
     * de valor à conta de uma alteração ao horário de atendimento.
     */
    public function slaMonthlyTrend(): array
    {
        $rows = $this->run("
            SELECT c.ano, c.mes,
                   CONCAT(br.name, ' · ', d.name) AS equipa,
                   SUM(c.concluidos) AS concluidos,
                   SUM(c.dentro_sla) AS dentro_sla,
                   ROUND(AVG(c.tempo_medio_min)) AS tempo_medio_min
              FROM tb_sla_monthly_close c
              JOIN tb_batch bt ON bt.id = c.batch_id
              JOIN tb_department d ON d.id = bt.department_id
              JOIN tb_branch br ON br.id = d.branch_id
             GROUP BY c.ano, c.mes, br.name, d.name
             ORDER BY c.ano DESC, c.mes DESC, equipa ASC
        ", []);

        $anterior = [];
        $resultado = [];

        // A ordem é do mês mais recente para o mais antigo, por isso a
        // comparação com o mês anterior faz-se numa segunda passagem.
        foreach (array_reverse($rows) as $row) {
            $concluidos = (int) $row['concluidos'];
            $pct = $concluidos > 0 ? (int) round((int) $row['dentro_sla'] / $concluidos * 100) : 0;
            $equipa = (string) $row['equipa'];

            $variacao = '—';
            if (isset($anterior[$equipa])) {
                $delta = $pct - $anterior[$equipa];
                $variacao = ($delta > 0 ? '+' : '') . $delta . ' pp';
            }
            $anterior[$equipa] = $pct;

            $resultado[] = [
                'mes' => $row['ano'] . '-' . str_pad((string) $row['mes'], 2, '0', STR_PAD_LEFT),
                'equipa' => $equipa,
                'concluidos' => $concluidos,
                'pct_dentro_sla' => $pct . '%',
                'variacao' => $variacao,
                'tempo_medio' => sla_human((int) $row['tempo_medio_min']),
            ];
        }

        return array_reverse($resultado);
    }
```

- [ ] **Passo 2: registar o relatório**

Em `REPORTS`:

```php
        'sla_trend' => ['📅 SLA mês a mês', 'Evolução do cumprimento por equipa, a partir dos meses fechados.'],
```

No `match`:

```php
            'sla_trend' => $repository->slaMonthlyTrend(),
```

Este relatório ignora o filtro de período (mostra sempre todos os meses fechados) — é o que se quer numa tendência.

- [ ] **Passo 3: verificar sintaxe e testes**

```bash
C:\xampp\php\php.exe -l app/Modules/Reports/Repositories/AnalyticsRepository.php
C:\xampp\php\php.exe tests/sla_test.php
```

Esperado: sem erros e `TODOS OS TESTES PASSARAM`.

- [ ] **Passo 4: commit e push**

```bash
git add app/Modules/Reports
git commit -m "Relatorio: SLA mes a mes a partir dos meses fechados"
git push origin main
```

---

## Depois de implementar

O fecho mensal precisa de correr uma vez por mês. Enquanto não houver automatismo, fica como tarefa: correr `038_fecho_mensal_sla.sql` no início de cada mês. É idempotente — voltar a correr não estraga nada.

Fica de fora deste plano, por decisão expressa: o **simulador de prémios**. Definir o limiar do bónus e o que é justo é decisão da empresa, e estes sete relatórios dão-lhe a base para a tomar.
