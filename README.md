# Operations Process System (OPS)

> "Transformando contactos em processos, e processos em conhecimento."

Fonte de verdade do produto: [`docs/🚀 PROJECT CHARTER.docx`](docs/🚀%20PROJECT%20CHARTER.docx) (Project Charter + PRD OPS-PRD-001 completo, 15 capítulos + dicionário de dados OPS-DB-002 + ERD OPS-DB-003).

## Estado atual

Roadmap definido em OPS-PRD-001 §11.26:

- ✅ **Fase 1 — Infraestrutura**: base de dados completa (25 tabelas + `tb_process_sequence`), framework mínimo em PHP puro (Router, PDO, Sessão, Env), módulo `Auth` (login, bloqueio, ACL granular), Dashboard inicial.
- ✅ **Fase 2 — Core**: módulo `Process` completo.
  - Criação de processo com deteção de duplicado por Matrícula+Assunto (RN-0017/0018) e **Janela de Reincidência** configurável (RN-0021 a RN-0024, com prompt "reabrir ou criar novo?").
  - Número de processo `PR-AAAA-XXXXXXXX` gerado por contador atómico (RN-0002).
  - **Fila Inteligente™** (`/processes/queue`) e **Meus Processos** (`/processes/mine`).
  - Assumir Processo com bloqueio transacional (row lock, RN-0012) para evitar dois operadores a assumirem o mesmo processo.
  - Máquina de estados (RN-0029) que impede saltos de estado inválidos.
  - Concluir / Reabrir Processo com cálculo de SLA e tempos sempre a partir de timestamps (nunca armazenados — decisão §10.20).
  - **Timeline Viva™** e Eventos imutáveis gerados automaticamente por toda ação (RN-0025 a RN-0028).
  - Interações (contactos) com incremento automático de `contact_count` / `last_contact_at` (RN-0013 a RN-0016).
  - **DNA do Processo™** na página de detalhe do processo.
- ✅ **Fase 3 — Operação**:
  - **Observações** com edição em janela de 10 minutos; depois disso cria versão nova preservando o original (RN-0033/0034, RF-0023/0024).
  - **Anexos** (PDF/JPG/PNG/DOCX/XLSX/ZIP, máx. 20MB) guardados fora do document root, com checksum SHA-256 anti-duplicado e pré-visualização inline para imagens/PDF (RF-0025/0026).
  - **Centro de Operações™**: dashboard do Operador (críticos, SLA próximo, meus processos, em espera, próximo processo a trabalhar, Minha Caixa de Entrada™) e dashboard Supervisor/Administrador (criados hoje, em fila, em tratamento, concluídos, % SLA, tempo médio, operadores online, ranking) — capítulo 8.
  - **Relatórios**: exportação CSV de processos com filtro de datas, auditada (RF-0027/0043).
- ✅ **Fase 4 — Inteligência**:
  - **Centro de Notificações** (`/notifications`, sino no menu com contador de não lidas) — RF-0038 a RF-0040.
  - **RF-0038**: ao criar um processo, supervisores/administradores da empresa são notificados em tempo real.
  - **RN-0056 — Processo Esquecido**: job agendado (`database/run_intelligence.php`) deteta processos sem qualquer evento na Timeline Viva™ há mais de N horas (configurável) e notifica responsável + supervisores.
  - **RN-0057 — Sobrecarga de Operador**: `Assumir Processo` passa a bloquear quando o operador já tem N processos ativos (configurável).
  - **RF-0039 — SLA Próximo**: o mesmo job avisa o operador responsável antes do SLA vencer.
  - **RN-0059/0060 — Cliente Frequente / Viatura Recorrente**: calculados em tempo real (sem guardar estado) e mostrados como badge no detalhe do processo.
  - Todos os limites são configuráveis via `tb_setting` (sem alterar código).
- ✅ **Fase 5 — API REST v1**: o roadmap do próprio PRD (Sprint 5 / Versão 1.5) coloca a API como o entregável concreto desta fase; Mobile e IA ficam para a "Versão 2.0" descrita em OPS-ARC-001.
  - Autenticação por **Bearer token** (`tb_api_token`), independente da sessão web — stateless, pronta para apps móveis e integrações (§11.23 "API Ready").
  - Endpoints para login/logout, Processos (criar/consultar/assumir/concluir/reabrir/observações/anexos) e Notificações.
  - Mesmas regras de negócio e ACL do sistema web (reutiliza `ProcessService`, `NotificationService`, etc. — zero duplicação de lógica).
  - Documentação completa com exemplos `curl` em [`docs/API.md`](docs/API.md).
- ✅ **Fase 6 — Configurações & Exportações** (itens que ficaram pendentes das fases anteriores):
  - **Configurações** (`/admin`, permissão `settings.manage`): editar parâmetros de `tb_setting` (SLA, janela de reincidência, limites de sobrecarga, etc. — RF-0044), Estados (RF-0045), Prioridades (RF-0046) e Assuntos (RF-0047) sem tocar em código. Os *códigos* de Estado usados na máquina de estados (`ProcessService::TRANSITIONS`) são fixos por desenho; só nome/ordem/ativação são editáveis.
  - **Exportação Excel** (RF-0041): sem Composer disponível, usa a técnica (amplamente suportada) de tabela HTML servida como `.xls` — sem dependências externas.
  - **Exportação PDF** (RF-0042): gerador de PDF escrito à mão (`SimplePdfWriter`), sem bibliotecas externas — suficiente para relatórios tabulares paginados.
- ✅ **Fase 7 — Operations Replay™** (proposta nova do PRD, OPS-UI-001 §24): reproduz visualmente a história completa de um processo, passo a passo, exatamente como aconteceu.
  - Botão **"🎬 Reproduzir Processo"** na página de detalhe → `/processes/{id}/replay`.
  - Reaproveita 100% a Timeline Viva™ já existente (nenhum dado novo é guardado — decisão §10.20); só calcula tempos decorridos entre passos.
  - Player em JavaScript puro (sem frameworks, §11.3): Play/Pause, Anterior/Seguinte, 3 velocidades, e uma track clicável com um ponto por evento.
  - Cada passo mostra: ícone, título, descrição, autor, timestamp e tempo decorrido desde o passo anterior e desde o início.
- ✅ **Fase 8 — Administração de Utilizadores & Estrutura Organizacional** (RF-0028 a RF-0035):
  - **Utilizadores** (`/admin/users`, permissão `users.manage`): criar, editar, associar a Perfil/Empresa/Filial/Departamento e a vários Lotes (N:N). RF-0030 — nunca elimina, só desativa; um admin não consegue desativar a própria conta.
  - **Organização** (`/admin/organization`, permissão `companies.manage`): criar/ativar/desativar Empresas, Filiais, Departamentos e Lotes, em hierarquia.
  - `tb_user_batch` é único por `(user_id, batch_id)` independentemente de soft delete — a sincronização de lotes de um utilizador "ressuscita" associações antigas em vez de duplicar (evita violar a constraint ao remover e voltar a adicionar o mesmo lote).
  - Antes desta fase só existia um utilizador (`admin`, criado por script) — agora a equipa consegue crescer pela UI.
- ✅ **Auditoria de segurança pós-Fase 8**: revisão cruzada de rotas, permissões, imports e CSRF em todo o projeto. Encontrada e corrigida uma vulnerabilidade real — `Assumir/Concluir/Reabrir Processo` não validava o token CSRF no servidor apesar do formulário o enviar.
- ✅ **Fase 9 — Pesquisa Global** (RF-0036/RF-0037): caixa de pesquisa (`/search`) sempre visível no menu lateral.
  - Procura por Nº Processo, Cliente, Matrícula, Telefone, Assunto ou Responsável, num único campo.
  - **Pesquisa Inteligente**: normaliza matrículas/números de processo (letras+dígitos) e telefones (só dígitos) antes de comparar — escrever `AA12BB` encontra `AA-12-BB`, `912345678` encontra `+351 912 345 678`.
- ✅ **Testes num servidor real (XAMPP) + correções**: primeira execução real do sistema encontrou e corrigiu bugs que nenhuma leitura de código apanhava:
  - **7 queries** com o mesmo nome de parâmetro PDO repetido na mesma query (`SQLSTATE[HY093]`, inválido com `EMULATE_PREPARES=false`) — em `InteractionRepository`, `ApiTokenRepository`, `AttachmentRepository`, `NoteRepository` (x2), `ProcessRepository` (x2).
  - Acentos corrompidos ao importar os `.sql` no Windows (charset da consola) — corrigido com `SET NAMES utf8mb4;`.
  - Contador do número de processo a começar em `00000000` em vez de `00000001` (idioma `LAST_INSERT_ID` do MySQL mal aplicado).
  - Timezone PHP/MySQL desalinhado, desalinhando o "Tempo Total" do processo — forçado UTC em ambos.
  - `Concluir Processo` não permitia concluir diretamente a partir da Fila (`QUEUE → SOLVED` em falta na máquina de estados).
- ✅ **Módulo 09 (Auditoria) e Módulo 16 (Logs)**: as duas únicas secções do capítulo 6 do PRD que tinham dados a ser gravados mas nenhuma página para consultar. `/admin/audit` lista `tb_audit` (login/logout/alterações, filtrável por tabela); `/admin/logs` mostra `storage/logs/*.log` (novo `App\Core\Logger`, grava tentativas de login falhadas e acessos negados por permissão).
- ⬜ Versão 2.0 (fora do escopo atual): Mobile, IA, Workflow/Rules Engine.

## Stack

PHP 8.4+, MySQL 8, HTML/CSS/JS puro (sem framework — decisão OPS-PRD-001 §11.3). Arquitetura em módulos: `Controller → Service → Repository → Database`.

## Setup local

1. **Base de dados** — execute os scripts pela ordem numérica. **Importante no Windows**: o cliente `mysql` costuma assumir o codepage da consola (ex.: CP850) em vez de UTF-8 ao ler os ficheiros, corrompendo acentos (ex.: "Assistência" vira "Assist├¬ncia"). Use sempre `--default-character-set=utf8mb4`:
   ```
   mysql --default-character-set=utf8mb4 -u root -p < database/001_database.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/002_tables.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/003_indexes.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/004_foreign_keys.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/005_views.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/006_process_sequence.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/007_api_tokens.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/009_seeders.sql
   mysql --default-character-set=utf8mb4 -u root -p < database/010_user_default_batch.sql
   ```
   (`009_seeders.sql` também tem `SET NAMES utf8mb4;` como primeira instrução, para o caso de esquecer a flag.)
2. **Configuração** — copie `.env.example` para `.env` e ajuste as credenciais da base de dados.
3. **Utilizador administrador** (usa `password_hash()` do PHP, por isso corre à parte do SQL):
   ```
   php database/seed_admin.php
   ```
   Cria `admin` / `Admin@123` — altere a password após o primeiro login.
4. **Job de Inteligência Operacional™ (opcional, mas recomendado)** — agende no cron do SO:
   ```
   */15 * * * * php /caminho/para/ops/database/run_intelligence.php >> storage/logs/cron.log 2>&1
   ```
5. **Servidor de desenvolvimento**:
   ```
   php -S localhost:8000 -t public public/router.php
   ```
   Abra `http://localhost:8000/login`. A API REST fica disponível em `http://localhost:8000/api/v1` (ver [`docs/API.md`](docs/API.md)).

> Nota: este ambiente de desenvolvimento não tem PHP/MySQL instalados, por isso o código não foi executado aqui — foi escrito e revisto manualmente contra o PRD. Corra os passos acima na sua máquina/servidor para validar.

## Estrutura

```
app/
  Core/         Router, Database (PDO), Request, Response, Session, ApiContext, Env, Settings
  Middleware/   Authenticate, PermissionMiddleware, ApiAuthenticate, ApiPermissionMiddleware
  Traits/       UuidTrait, TimestampTrait, SoftDeleteTrait, AuditTrait
  Modules/
    Auth/       Controller, Services (Auth/ApiToken), Repositories, Validator, View
    Dashboard/  Controller, Service, View
    Process/       Controllers (Process/Note/Attachment), Services, Repositories, DTO, Validator, Views
    Notification/  Controller, Service, Repository, View (centro de notificações)
    Intelligence/  Service (RN-0056/0057/0059/0060, RF-0039)
    Reports/       Controller, Services (Report/Excel/SimplePdfWriter), View (exportação CSV/Excel/PDF)
    Api/           Controllers da API REST v1 (Auth/Process/Notification)
    Administration/ Controllers (Settings/User/Organization), Services, Repositories, Views
database/       Scripts SQL numerados (OPS-SQL-001) + seed_admin.php + run_intelligence.php
docs/           API.md (documentação da API REST)
public/         Front controller (index.php), assets, .htaccess
routes/         web.php (sessão) + api.php (Bearer token)
```

Cada novo módulo (Process, Interaction, Timeline, ...) deve seguir exatamente esta mesma estrutura interna, conforme decidido em OPS-PRD-001 §11.24.

## Regras que o código já aplica

- Nenhum `DELETE` físico — tudo usa `deleted_at` (soft delete).
- Toda a autenticação gera `tb_login_log` + `tb_audit` (RN-0024).
- Bloqueio automático do utilizador após N tentativas inválidas (`tb_setting.login_max_attempts`).
- ACL por permissão granular (não por perfil fixo), para permitir novos perfis sem alterar código (OPS-PRD-001 §3.13).
