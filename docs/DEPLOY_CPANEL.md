# Deploy no cPanel — Operations Process System

Guia passo a passo para `ileiteprocessos.it2.pt` (conta `ileiteprocessosi`), mas serve para qualquer cPanel.

## 1. Confirmar a versão do PHP

**Software → Select PHP Version** (ou **MultiPHP Manager**). Escolha **PHP 8.2** ou superior (o projeto foi testado em 8.2). Depois de escolher a versão, entre em **PHP Extensions** e confirme que estão ativas: `pdo_mysql`, `mbstring`, `fileinfo`, `json` (normalmente já vêm ativas por defeito).

## 2. Estrutura de pastas — muito importante

O ponto de entrada público (`index.php`) tem de ficar na pasta que o Apache serve para o domínio (normalmente `public_html/` ou `public_html/ileiteprocessos.it2.pt/` se for um addon domain — confirme em **Domains**). **Todo o resto do projeto (app/, database/, routes/, storage/) tem de ficar FORA dessa pasta pública**, por segurança — senão qualquer pessoa consegue descarregar o código-fonte ou aceder à base de dados diretamente pelo URL.

Estrutura recomendada dentro da conta cPanel:

```
/home/ileiteprocessosi/
├── ops/                    ← todo o projeto, FORA do document root
│   ├── app/
│   ├── database/
│   ├── routes/
│   ├── storage/
│   ├── .env                ← credenciais reais, nunca no Git
│   └── (composer.json, .env.example, README.md, etc.)
└── public_html/            ← document root do domínio
    └── ileiteprocessos.it2.pt/   (se for addon domain)
        ├── index.php        ← copiado/adaptado de ops/public/index.php
        ├── router.php
        ├── .htaccess
        └── css/
```

Como `public/index.php` referencia `app/`, `routes/` etc. com caminhos relativos (`__DIR__.'/../app/...'`), a forma mais simples é:

1. Enviar a pasta `ops/` completa para `/home/ileiteprocessosi/ops/` (fora do document root).
2. Copiar apenas o **conteúdo** de `ops/public/` para dentro do document root do domínio.
3. Editar `index.php` copiado: onde diz `__DIR__ . '/../app/Core/autoload.php'` etc., ajustar para apontar para `/home/ileiteprocessosi/ops/app/Core/autoload.php` (caminho absoluto) — ou, mais simples, criar um `index.php` no document root que só faz:
   ```php
   <?php
   chdir('/home/ileiteprocessosi/ops/public');
   require '/home/ileiteprocessosi/ops/public/index.php';
   ```
   Isto mantém os caminhos relativos do ficheiro original a funcionar sem editar nada.

## 3. Enviar os ficheiros

- **File Manager** → comprimir o projeto localmente em `.zip`, fazer upload para `/home/ileiteprocessosi/`, e usar "Extract" no File Manager (mais rápido que FTP para muitos ficheiros pequenos).
- Ou **FTP Accounts** (visível no seu painel) se preferir um cliente como FileZilla.
- Ou **Git™ Version Control**, se colocar o projeto num repositório Git (recomendo — facilita atualizações futuras: só precisa de `git pull` a cada alteração).

## 4. Base de dados

1. **MySQL® Database Wizard**: criar uma base de dados (ficará algo como `ileiteprocessosi_ops`) e um utilizador com password forte, associá-lo com todos os privilégios.
2. **phpMyAdmin**: selecionar a base de dados criada → separador **Importar**.
   - **Atenção ao charset**: no phpMyAdmin, antes de importar, confirme que o "Character set of the file" está definido como `utf8mb4` (fica por cima do botão de escolher ficheiro). Isto evita o mesmo problema de acentos corrompidos que apanhámos localmente.
   - Importe os ficheiros de `database/` **por esta ordem**: `001_database.sql`, `002_tables.sql`, `003_indexes.sql`, `004_foreign_keys.sql`, `005_views.sql`, `006_process_sequence.sql`, `007_api_tokens.sql`, `009_seeders.sql`.
   - Note que `001_database.sql` faz `CREATE DATABASE` — como o cPanel já criou a base de dados, pode saltar esse ficheiro e simplesmente selecionar a base de dados certa em phpMyAdmin antes de importar os restantes (ou editar o ficheiro para remover o `CREATE DATABASE`/`USE ops;` e trocar pelo nome real).

## 5. Ficheiro `.env`

No File Manager, dentro de `/home/ileiteprocessosi/ops/`, criar `.env` (copiar de `.env.example`) com as credenciais reais:

```
DB_HOST=localhost
DB_DATABASE=ileiteprocessosi_ops
DB_USERNAME=ileiteprocessosi_opsuser
DB_PASSWORD=<password que definiu no passo 4>
APP_DEBUG=false
```

**`APP_DEBUG=false` em produção** — com `true`, erros PHP mostram detalhes internos (caminhos do servidor, queries) a qualquer visitante.

## 6. Criar o utilizador administrador

`database/seed_admin.php` é um script de linha de comandos. Se o seu plano cPanel tiver **Terminal** (ícone costuma estar perto do File Manager, procure por "Terminal" na pesquisa do painel), corra:

```bash
cd ~/ops
php database/seed_admin.php
```

**Se não tiver Terminal/SSH**, diga-me — adapto o script para correr uma única vez a partir do browser em segurança (token de acesso temporário) e depois eliminamos o ficheiro.

## 7. Cron Job (Fase 4 — Inteligência)

**Cron Jobs** (visível no seu painel, em Advanced) → adicionar novo:
- Comando: `php /home/ileiteprocessosi/ops/database/run_intelligence.php >> /home/ileiteprocessosi/ops/storage/logs/cron.log 2>&1`
- Frequência: a cada 15 minutos.

## 8. Permissões de escrita

`storage/uploads/` e `storage/logs/` precisam de permissão de escrita para o utilizador do Apache. No File Manager, botão direito → **Change Permissions** → `755` costuma bastar em cPanel (o Apache corre como o dono da conta).

## 9. Testar

Abrir `https://ileiteprocessos.it2.pt/login` — se aparecer o ecrã de login com o mesmo visual que viu localmente, está no ar.

---

Este guia não foi executado por mim (não tenho acesso às suas credenciais de cPanel) — precisa de seguir os passos manualmente. Se encravar nalgum, mostre-me o ecrã/erro e ajudo a resolver.
