# Fundação da Intranet — desenho

Data: 2026-10-09
Estado: aprovado em conversa, a aguardar revisão do documento

## 1. Contexto e objetivo

O OPS (Operations Process System) passa a ser a base de uma **intranet** com vários
sistemas dentro: Processos (o que já existe), Gestão de Serviços e Gestão de
Reclamações. Todos os utilizadores entram pelo mesmo login e vêem apenas os
sistemas que lhes foram atribuídos.

Este documento cobre só o **sub-projeto 0 — Fundação**: o portal, a noção de
"sistema", a atribuição de acessos e a navegação. Serviços e Reclamações têm
cada um o seu desenho, a fazer depois.

## 2. Decisões já tomadas

| Tema | Decisão |
|---|---|
| Arquitetura | Uma só aplicação (a atual), a crescer por módulos. Sem OIDC, sem adaptador do sistema legado, sem bases separadas |
| Dados existentes | Vêm todos na viragem: copia-se a base de produção, correm-se as migrations, troca-se o endereço |
| Onde corre durante os testes | **Só local (XAMPP)**. O endereço no servidor fica para mais tarde e não condiciona o código |
| Reclamações | Sistema **totalmente separado**: tabelas, estados, fila e SLA próprios |
| Atribuição de acesso | **Por utilizador**, num módulo dentro do Admin. Nunca por departamento |
| Papéis | **Um papel por sistema, por utilizador**. A matriz de Processos mantém-se como está |
| Administrador | Tem acesso a tudo, em todos os sistemas, sem precisar de atribuição |
| Página inicial | Cartões dos sistemas + notificações existentes. Sem resumo do dia, atalhos nem pesquisa global |
| Ordem do trabalho | 0 Fundação, depois 1 Serviços, depois 2 Reclamações, depois 3 Viragem |

## 3. Modelo de dados

Só tabelas novas e uma coluna nova. Nenhuma tabela existente perde ou muda dados.

- `tb_system` — `id`, `uuid`, `code` (`processos`, `servicos`, `reclamacoes`), `name`,
  `description`, `icon`, `color`, `home_url`, `sort_order`, `active`.
- `tb_user_system` — `id`, `uuid`, `user_id`, `system_id`, `role_id` (anulável), e as colunas
  de auditoria habituais. Único por (`user_id`, `system_id`).
- `tb_role.system_id` — cada papel pertence a um sistema. Os papéis atuais
  (Administrador, Supervisor, Operador, Consulta e os restantes) ficam em `processos`.

### Papel em Processos

`tb_user.role_id` **continua a ser a fonte** do papel em Processos. A linha de
`processos` em `tb_user_system` serve só para dizer *se tem acesso*, e fica com `role_id` NULL; o papel
efetivo em Processos continua a ser o de `tb_user.role_id`. Assim não há duas
fontes a divergir.

### Migrations (`.sql`, idempotentes, para o phpMyAdmin)

1. Cria `tb_system` e `tb_user_system`, acrescenta `tb_role.system_id` e semeia os três sistemas.
2. Dá a cada utilizador ativo o acesso a `processos` (com `role_id` NULL). Depois disto ninguém vê diferença.

## 4. Regras de acesso

- **Entrar num sistema** exige acesso a esse sistema **e** a permissão específica
  da ação. O primeiro nível decide o que o portal mostra; o servidor verifica sempre os dois.
- Sem acesso ao sistema: o cartão não aparece, e um pedido direto por URL dá **403**
  (negar por omissão).
- **Administrador** (`ROLE_ADMIN`): acesso implícito a todos os sistemas, sem linha em `tb_user_system`.
- Só `users.manage` atribui, altera ou retira acessos. Ninguém atribui a si próprio um sistema nem sobe o seu papel.
- Utilizador desativado: os acessos deixam de contar, sem apagar nada.
- Todas as atribuições, alterações e retiradas ficam na auditoria existente.

### Permissões na sessão

No login, a lista de permissões passa a ser a **união** das permissões do papel
de cada sistema a que a pessoa tem acesso. Os códigos têm prefixo por sistema
(`process.*`, `servicos.*`, `reclamacoes.*`), por isso não colidem, e o
`PermissionMiddleware` e as rotas atuais continuam a funcionar sem alterações.

## 5. Portal e navegação

- **Portal** em `/portal`: saudação, cartões só dos sistemas autorizados, notificações existentes.
- **Login** aterra no portal. Quem tem **um só sistema** entra direto nele.
- **URLs de Processos não mudam** (`/dashboard`, `/processes/...`). Os sistemas novos usam
  `/servicos/...` e `/reclamacoes/...`. Sem prefixo comum, os ~280 links absolutos ficam como estão.
- Cada sistema tem a **sua barra lateral**, no mesmo estilo visual. A de Processos fica como está.
- **Seletor de sistema** no topo de cada barra: sistema atual, lista dos outros acessíveis e "Início".
  Quem tem um só sistema não o vê.
- Estrutura de código: `app/Modules/Portal`, `Servicos`, `Reclamacoes` (Controllers,
  Repositories, Services, Views), e `SistemaAcesso` dentro de `Administration`.

## 6. Administração: Admin → Sistemas e Acessos

Tabela de utilizadores contra sistemas. Cada célula mostra o papel nesse sistema
ou "sem acesso". Pesquisa por nome e filtro por sistema. Quem tem acesso aparece
separado de quem não tem, como no separador de Imobilizados, para não haver dúvidas.

## 7. Robustez no dia da viragem

Se as migrations ainda não correram, o código **verifica a existência das tabelas**
(`Database::hasColumn` / equivalente para tabelas) antes de as usar. Nesse caso o login
comporta-se como hoje e ninguém fica trancado fora da plataforma.

## 8. Ambiente e testes

- Base `ops` local no XAMPP, criada a partir de `INSTALL_CPANEL.sql` e das migrations, com `.env` local.
- Testes em `tests/*.php`, corridos com `C:\xampp\php\php.exe`, no formato dos atuais.
- Cobertura mínima: matriz de acesso (com e sem sistema, Administrador, utilizador
  desativado); 403 por URL direto; ninguém se auto-atribui acessos; só `users.manage` atribui;
  **zero regressão em Processos** (SLA, sessão, filtros e imobilizados continuam verdes);
  verificação visual do portal com um, três e nenhum sistema.

## 9. Ordem de entrega

1. Ambiente local a funcionar (MariaDB reparado, `.env`, base `ops`).
2. Migrations e testes de acesso.
3. Administração dos acessos.
4. Portal e seletor de sistema.
5. Revisão contigo.

Cada passo termina com testes verdes e commit.

## 10. Fora de âmbito

Resumo do dia, atalhos, pesquisa global; qualquer ecrã de Serviços ou Reclamações;
alteração dos URLs de Processos; escolha do endereço no servidor.

## 11. Pontos em aberto

- **MariaDB local corrompido** (`Missing MLOG_CHECKPOINT`). Backup feito em
  `C:\xampp\mysql\data_backup_20261009`. Falta mover `ib_logfile0`, `ib_logfile1` e `ibtmp1`
  para fora de `data/` (comando entregue ao utilizador) e arrancar o MySQL.
- **Endereço no servidor** durante o ano de testes (subdomínio ou pasta): decide-se quando
  houver algo para mostrar. O desenho não depende dele.
- **Papéis de Serviços e de Reclamações** definem-se nos desenhos desses sistemas.
