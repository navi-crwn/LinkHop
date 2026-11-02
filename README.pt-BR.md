<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <b>Português (Brasil)</b> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="A página inicial do LinkHop: um campo para colar um link longo, o botão Shorten e a cena 3D de ilhas" width="100%"></picture></p>

<p align="center">
  <b>Links curtos, códigos QR e estatísticas de cliques.<br>Gratuito, de código aberto e seu para hospedar.</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>Experimente o LinkHop</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">Site</a> ·
  <a href="#instalação">Instalação</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CREDITS.md">Créditos</a> ·
  <a href="SECURITY.md">Segurança</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

O LinkHop encurta links, conta os cliques e gera um código QR para cada link. É o encurtador de links da família HOP e funciona em [link.hel.ink](https://link.hel.ink). Os links curtos abrem em `hel.ink/l/slug`.

Qualquer pessoa pode encurtar um link sem conta. Um HOP ID gratuito guarda seus links e mostra as estatísticas deles. O HOP ID é a conta única de todos os apps HOP e fica no [hub](https://github.com/navi-crwn/HEL.ink), em `hel.ink` e `hub.hel.ink`. O LinkHop não tem páginas próprias de login, cadastro ou perfil: o hub faz o login e devolve você com um tíquete de curta duração, e os apps compartilham um único banco de dados.

## Família HOP

| App | Endereço | Repositório | O que faz |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · contas em `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | Uma conta para todos os apps HOP. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Links curtos, códigos QR e estatísticas de cliques. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | Uma página de link na bio para tudo o que você faz. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Hospedagem de imagens com álbuns e links diretos. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Notas criptografadas que você compartilha com um link. |
| **Imperium** | privado (somente o dono) | privado | O painel de controle do HOP. |

Tudo está incluído em toda conta, sem plano pago. O código tem licença MIT, então você também pode rodá-lo no seu próprio servidor e no seu próprio domínio.

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="O painel do LinkHop: cliques da semana com uma barra por dia, totais, uma lista para começar, links recentes e o link de melhor desempenho" width="100%"></picture></p>

As capturas de tela das páginas após o login mostram dados de demonstração inventados, não uma conta real.

## Recursos

- Links curtos com slug aleatório ou personalizado (de 3 a 255 caracteres), senha opcional, data de expiração e um código QR em PNG, SVG ou JPG.
- Limites do link: horário de início, número máximo de cliques, um endereço alternativo para quando o link expirou ou os cliques acabaram, e o tipo de redirecionamento 301, 302, 307 ou 308.
- Roteamento: até 20 regras que enviam os visitantes para outro lugar por país, idioma, dispositivo, sistema operacional ou navegador, e uma divisão A/B de 2 a 5 endereços com pesos. Um construtor de tags de campanha (UTM) preenche os parâmetros `utm_*`.
- Abrir no app: em iOS e Android, um link pode tentar primeiro o app instalado, com a App Store ou o Google Play como alternativa.
- Estatísticas: cliques, visitantes únicos, países e cidades, dispositivos, navegadores, navegadores dentro de apps, origens de referência e leituras de QR em comparação com cliques diretos. O período é comparado com o anterior. Os registros de cada clique ficam guardados por 30 dias.
- Organização: pastas, tags, busca, ações em massa, comentários em um link, importação de CSV de até 500 linhas e uma paleta de comandos com Ctrl ou Cmd + K. Em Configurações há uma exportação CSV dos seus links com as estatísticas.
- Verificação de link em `/check`: cole um link `hel.ink` para ver o destino antes de abri-lo. Nada é clicado nem contado.
- Monitor de destino: uma vez por dia o LinkHop pergunta a cada destino ativo se ele ainda responde e envia um e-mail ao dono depois de três verificações seguidas com falha. Dá para desligar em Configurações.
- Aviso de conteúdo: um link pode ser marcado como sensível ou adulto, e os visitantes veem um aviso antes de continuar.
- Proteção contra abuso: limites de taxa e cotas, uma lista de bloqueio de domínios, bloqueio de IPs, verificações de proxy e VPN nos visitantes, um formulário de denúncia e uma varredura a cada hora em busca de phishing, golpes e outros links nocivos, com consultas opcionais ao Google Safe Browsing e ao URLhaus.
- API e webhooks: uma API REST com tokens com escopo e chaves de idempotência, webhooks assinados para cinco eventos de link e uma configuração do ShareX.
- Nove idiomas: English, 简体中文, Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français e Русский. Acrescente `?lang=pt-BR` a qualquer página para trocar; a escolha fica guardada em um cookie.
- Privacidade: sem anúncios, sem rastreadores de terceiros e sem scripts, fontes ou imagens de CDN. As bibliotecas vêm empacotadas ou ficam em `public/vendor`. Os dois hosts de terceiros que restam são o Cloudflare Turnstile no formulário de visitantes e os blocos do OpenStreetMap sob o mapa de estatísticas.
- Auto-hospedagem: instalador de um comando, scripts de atualização e desinstalação, backups noturnos e uma API de administração assinada para o centro de controle do HOP.

As páginas de bio (`hel.ink/b/voce`) são feitas no [BioHop](https://github.com/navi-crwn/BioHop), não no LinkHop. O único caso em que o LinkHop ainda as serve está em [Páginas de bio](#páginas-de-bio).

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="A página Analytics: filtros, cliques e visitantes únicos em comparação com o período anterior, principal país e dispositivo, e um gráfico de cliques ao longo do tempo" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="A página My links: uma lista pesquisável com pastas, tags, contagem de cliques e uma linha de sete dias para cada link" width="100%"></picture></p>

## Onde fica cada coisa

| O quê | URL |
| --- | --- |
| Link curto | `hel.ink/l/slug` |
| Forma antiga do link curto | `hel.ink/slug`, que redireciona para `hel.ink/l/slug` até 27 de novembro de 2026 (`LEGACY_SLUG_UNTIL`) |
| Página de bio (BioHop) | `hel.ink/b/name` |
| Painel e API | `link.hel.ink`, com login pelo hub em `hub.hel.ink` |
| Sua conta | `hub.hel.ink`: perfil, senha, verificação em duas etapas, tokens de API, download dos dados, exclusão da conta |

Os links curtos passaram de `hel.ink/slug` para `hel.ink/l/slug` em 28 de outubro de 2026, para que `hel.ink` possa abrigar vários apps HOP. Nada foi excluído. O painel, os códigos QR, a API e a exportação da conta mostram a forma nova. Na sua própria instalação, o host público é `PUBLIC_URL` (o padrão é `APP_URL`), e `php artisan links:announce-migration` envia por e-mail a cada dono de link os seus links na forma nova (`--reminder` envia o segundo e-mail).

## Instalação

O instalador verifica o seu computador, faz algumas perguntas e pode configurar o hub HOP ID ao lado do LinkHop:

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

A partir de um clone, execute `./install.sh`. Para atualizar, execute `./update.sh`; para remover o LinkHop, `./uninstall.sh`. Acrescente `--yes` para aceitar todos os padrões, `--dry-run` para ver os passos sem executá-los e `--help` para as demais opções.

- O `install.sh` precisa de PHP 8.2 ou mais novo (usa `php8.3` quando está instalado; defina `PHP_BIN` para escolher outro), Composer, Node.js 20 ou mais novo e um banco de dados: SQLite ou MySQL/MariaDB. Ele grava um `.env` privado e um `start.sh` que inicia o servidor web, o worker da fila e o agendador.
- O `update.sh` copia primeiro o banco de dados para `backups/` (SQLite ou MySQL/MariaDB), coloca o site em modo de manutenção, baixa o código novo, recompila, migra e restaura a versão antiga se algum passo falhar antes das migrações. Se não conseguir copiar o banco, ele para; `--no-backup` continua sem a cópia.
- O `uninstall.sh` remove os pacotes instalados e os arquivos compilados e mantém o seu `.env`, o banco de dados, os uploads e os logs. `--purge` apaga tudo isso também e pede que você digite o nome do site antes.

> [!NOTE]
> O LinkHop guarda as contas em um hub HOP ID ([HEL.ink](https://github.com/navi-crwn/HEL.ink)); não existe modo sem hub. O instalador pode configurar um hub ao lado do LinkHop (o padrão, cerca de um minuto) ou conectar-se a um hub que você já executa. Para instalar o hub e vários apps de uma vez, use o `install-family.sh` do repositório HEL.ink.

### Instalação manual

Você precisa de PHP 8.2 ou mais novo com as extensões usuais do Laravel e `gd` ou `imagick` (códigos QR em PNG), Composer, Node.js 20.19 ou mais novo e um banco de dados. O [hub](https://github.com/navi-crwn/HEL.ink) precisa rodar sobre o mesmo banco: ele é dono das tabelas de contas (`users`, `api_tokens` e outras) e faz o login das pessoas.

```bash
# Os pacotes compartilhados hop/auth, hop/ui e hop/safety vêm do GitHub pelo Composer.
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # depois edite o .env, veja abaixo
php artisan migrate --force       # apenas as tabelas deste app
php artisan storage:link
php artisan serve --port=8001
```

Para trabalhar localmente, defina `APP_ENV=local`, `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001`, `HOP_HUB_URL=http://localhost:8000` (o hub com `php artisan serve`) e `SESSION_SECURE_COOKIE=false`, e no hub `LINKHOP_URL=http://localhost:8001`. Um visitante que abre o LinkHop é enviado ao login do hub e volta já autenticado.

Em produção, mantenha duas coisas em execução: um worker da fila (`php artisan queue:work --tries=3`) e o agendador (uma linha de cron `* * * * * cd /caminho/para/linkhop && php artisan schedule:run`). O agendador executa a varredura de abuso, o monitor de destino, as novas tentativas de webhook, a limpeza de privacidade, a exclusão de links de visitantes e o backup.

**Propriedade do banco de dados.** Cada app HOP executa apenas as suas próprias migrações no banco compartilhado. As do LinkHop estão em `database/migrations`. O histórico das tabelas de contas fica em `database/migrations-archive` e não é mais executado; os testes montam o esquema a partir dele. Não execute `migrate:fresh` em um banco que o hub usa.

<details>
<summary><b>Configurações no <code>.env</code></b></summary>

O arquivo `.env.example` lista tudo. Estas são as configurações que você provavelmente vai mexer.

| Configuração | Significado |
| --- | --- |
| `APP_URL` | O painel e a API (`https://link.hel.ink` no serviço hospedado). |
| `PUBLIC_URL` | Onde os links curtos, os códigos QR e o sitemap são acessados (`https://hel.ink`). Em uma instalação com um único host, use o mesmo valor de `APP_URL`. |
| `HOP_APP`, `HOP_HUB_URL`, `HOP_SSO_SECRET` | A conexão com o hub. `HOP_APP` é `linkhop`; o segredo é o mesmo, de pelo menos 32 caracteres, que está no `.env` do hub. Login, login com Google, verificação em duas etapas e redefinição de senha são do hub, então não é preciso configurar nada disso aqui. |
| `DB_*` | O mesmo banco de dados do hub. |
| `MAIL_*` | E-mail de saída: avisos aos donos de links, monitor de destino e alertas de backup. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile no encurtador de visitantes e no formulário de denúncia. Em produção, sem a chave secreta o formulário de visitantes recusa todos os envios. |
| `GUEST_LINK_IDLE_DAYS`, `GUEST_LINK_MAX_DAYS` | Os links de visitantes são apagados após 14 dias sem clique e, no máximo, após 30 dias. O servidor só pode encurtar esses prazos. |
| `LIMIT_*` | Cotas de `config/limits.php`, por exemplo 10 links por minuto e 200 por dia por endereço, 200 por dia e 1000 links ativos por conta. |
| `SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY` | Consultas opcionais para a varredura de abuso. |
| `GEOLITE_LICENSE_KEY` | Opcional. Sem ela, `geolite:update` baixa os bancos gratuitos DB-IP Lite. |
| `BIO_ENABLED`, `BIOHOP_URL`, `PIXELHOP_URL` | O pacote de bio incluído e os endereços dos outros apps. |
| `HOP_CONTROL_URL` | Para onde `/admin` envia os administradores: o centro de controle do HOP. |
| `LEGACY_SLUG_UNTIL` | A data em que o redirecionamento antigo `hel.ink/slug` termina. Deixe vazio em uma instalação nova. |
| `TRUSTED_PROXIES` | Os endereços do proxy reverso, ou `cloudflare`. |
| `SENTRY_LARAVEL_DSN`, `BACKUP_DISKS` | O rastreamento de erros e os discos onde o backup é gravado (padrão `local`). |

**Localização dos visitantes.** País e cidade vêm de um banco local DB-IP Lite que `geolite:update` atualiza toda semana. Quando ele não tem resposta para um endereço público, o LinkHop consulta até dois serviços de consulta na web: freeipapi.com, ip-api.com, ipapi.co, ipinfo.io ou abstractapi.com. O endereço do visitante é guardado apenas como um hash com chave.

**Backups.** O agendador executa `backup:run` todos os dias às 02:45. O arquivo contém o banco de dados, `storage/app/public` e o `.env`, e vai para os discos definidos em `BACKUP_DISKS`. Um backup de MySQL ou MariaDB precisa do `mysqldump` no servidor. O `.env` guarda os seus segredos, então proteja o disco de backup ou defina `BACKUP_ARCHIVE_PASSWORD`.

**Atrás do Cloudflare.** O LinkHop envia uma Content Security Policy que só confia em scripts com o nonce da página. O Email Address Obfuscation (Scrape Shield) e o Rocket Loader (Speed, Optimization) do Cloudflare injetam scripts próprios sem esse nonce, por isso os navegadores os bloqueiam e os bloqueios vão para `storage/logs/csp-*.log`. Desligue os dois no painel do Cloudflare. O LinkHop não precisa deles. Acrescentá-los à política não funcionaria com uma política de nonce, porque `'strict-dynamic'` ignora listas de hosts permitidos.

**Host único.** O serviço hospedado divide o LinkHop em dois: o painel em `link.hel.ink` e os links curtos em `hel.ink/l/`. As regras do nginx para essa divisão estão no repositório do hub, em `deploy/nginx/`. Para um único host, defina `APP_URL` e `PUBLIC_URL` com o mesmo valor.

</details>

## Como usar

**Encurtar um link.** Cole o endereço longo, escolha opcionalmente um slug, uma senha ou uma data de expiração, pressione Shorten e copie o resultado. Abra Advanced na página inicial para as opções de visitante; depois de entrar, New link tem todas as opções acima.

**Páginas de bio.** Entre em [bio.hel.ink](https://bio.hel.ink) com o mesmo HOP ID, escolha um nome e compartilhe `hel.ink/b/seunome`.

## API

Os tokens de API são criados no hub (HOP ID, Settings, API) e funcionam em todos os apps HOP. O LinkHop os lê da tabela compartilhada `api_tokens`. Um token traz escopos: `link:create`, `link:read` e `link:delete`. Um token criado antes de os escopos existirem não tem nenhum e pode fazer tudo. A API responde em `link.hel.ink/api` e em `hel.ink/api`, que encaminha para lá até 27 de novembro de 2026. O limite de taxa é por token, 100 requisições por hora, a menos que o token defina o próprio limite.

Crie um link curto (`link:create`). O BioHop faz essa chamada quando um bloco de bio quer um link curto.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Campo | | |
| --- | --- | --- |
| `url` | obrigatório | Um endereço completo com `http://` ou `https://`, de até 2048 caracteres. Recusado quando a verificação de segurança o sinaliza. |
| `alias` | opcional | O slug: de 3 a 255 caracteres entre `A-Z a-z 0-9 _ -`. Palavras reservadas e slugs já usados são recusados. Aleatório quando omitido. |
| `folder` | opcional | O nome de uma pasta da conta. É criada se não existir. |
| `password` | opcional | De 4 a 50 caracteres; os visitantes precisam digitá-la. |
| `expires_at` | opcional | Uma data e hora ISO no futuro. |
| `starts_at` | opcional | Uma data e hora antes da qual os visitantes veem "Not live yet". |
| `max_clicks` | opcional | O link para depois desse número de cliques (de 1 a 10.000.000). |
| `fallback_url` | opcional | Para onde os visitantes vão depois da data de expiração ou do limite de cliques. Não pode ser outro link curto. |
| `redirect_type` | opcional | `301`, `302` (o padrão), `307` ou `308`. |
| `content_warning`, `warning_reason` | opcional | `none`, `sensitive` ou `adult`, e uma nota de até 255 caracteres mostrada aos visitantes. |
| `routing_rules`, `ab_variants`, `app_links` | opcional | Roteamento por visitante, uma divisão A/B e deep links. Os formatos estão na página API docs do painel. |

```json
HTTP 201
{"success": true,
 "data": {"url": "https://hel.ink/l/my-link", "slug": "my-link", "target_url": "https://example.com",
          "status": "active", "content_warning": "none", "warning_reason": null,
          "created_at": "2026-11-02T10:00:00+00:00", "expires_at": null, "starts_at": null,
          "max_clicks": null, "fallback_url": null, "routing_rules": [], "ab_variants": [],
          "app_links": null, "redirect_type": "302"},
 "message": "Link created successfully"}
```

Os erros mantêm sempre o mesmo formato, `{"success": false, "error": "…", "message": "…"}`. `401` é um token desconhecido ou expirado, `403` um escopo ausente ou uma conta suspensa ou não verificada, `422` um erro de validação (`messages` lista os campos) e `429` uma cota ou limite de taxa.

Envie um cabeçalho `Idempotency-Key` em `POST /api/shorten` para repetir a chamada com segurança. A mesma chave com o mesmo corpo devolve a primeira resposta (`Idempotent-Replayed: true`) e não cria nada duas vezes. A mesma chave com outro corpo resulta em `422`. As chaves ficam guardadas por 24 horas.

Leia um link (`link:read`) com `GET /api/links/{slug}`, que devolve o mesmo `data` mais `clicks`. Exclua um (`link:delete`) com `DELETE /api/links/{slug}`. Os dois respondem `404` para um slug que não está na conta do token.

Os webhooks são configurados em Configurações: até cinco endereços https, cada um chamado em `link.created`, `link.updated`, `link.deleted`, `link.limit_reached` e `link.expired`. Cada mensagem traz `X-LinkHop-Signature`, um HMAC-SHA256 do carimbo de data e hora e do corpo. Uma entrega que falha é repetida após 1 minuto, 5 minutos, 30 minutos e 2 horas, e um webhook que falha dez entregas seguidas é desligado.

Uma configuração do ShareX pode ser baixada na página API docs do painel.

## FAQ

**É gratuito?** Sim. Não há plano pago nem recurso bloqueado.

**Preciso de uma conta?** Não. Qualquer pessoa pode encurtar um link sem conta. Os links de visitantes são apagados após 14 dias sem clique e, no máximo, após 30 dias. Um HOP ID gratuito guarda seus links, mostra as estatísticas deles e também o conecta aos outros apps HOP.

**Onde está a minha página de bio?** No BioHop, em `hel.ink/b/` seguido do nome que você escolher. Edite-a em [bio.hel.ink](https://bio.hel.ink).

**O que um clique registra?** O horário, o país e a cidade, o dispositivo, o navegador e a origem de referência, além de um hash com chave do endereço e do navegador que muda todo dia e serve para contar visitantes únicos. O endereço em si não é guardado, e os registros de cliques são apagados após 30 dias.

**Como denuncio um link ruim?** Use o formulário de denúncia em `/report-abuse`. As denúncias vão para a fila de abusos do centro de controle do HOP, junto com o que a varredura automática encontra.

**Posso usar meu próprio domínio?** Sim, hospedando a sua própria cópia ao lado do hub. `APP_URL` é o host do painel, `PUBLIC_URL` é o host dos links curtos e `HOP_HUB_URL` é o hub.

### Páginas de bio

No hel.ink, as páginas de bio são do BioHop. Uma instalação em um único servidor sem o BioHop ainda pode servi-las a partir do pacote `hop/bio` incluído (`packages/bio`) definindo `BIO_ENABLED=true`. Com o BioHop ao lado, deixe `false`: assim o pacote não registra rotas e não precisa de tabelas. `php artisan db:seed --class=ExampleBioSeeder` cria, em uma instalação de desenvolvimento, uma página de bio de exemplo e alguns links curtos de exemplo.

## Por dentro

| Camada | Tecnologias |
| --- | --- |
| Backend | Laravel 12, PHP 8.2 ou mais novo, SQLite ou MySQL/MariaDB |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 7, além de GSAP, Lenis e Three.js na página inicial |
| Gráficos e mapas | Chart.js 4, Leaflet 1.9 com blocos do OpenStreetMap |
| Contas | HOP ID no hub, por meio do `hop/auth` (cliente SSO e os middlewares `hop.auth` e `hop.token`) |
| Pacotes compartilhados | `hop/auth`, `hop/ui` (seletor de apps, fontes), `hop/safety` (segurança de URLs e conteúdo), `hop/bio` (incluído em `packages/bio`) |
| Serviços | Cloudflare Turnstile, DB-IP Lite e serviços de consulta na web opcionais para a localização dos visitantes, Google Safe Browsing e URLhaus opcionais, Sentry |

| Pasta | O que contém |
| --- | --- |
| `app/`, `routes/`, `resources/` | O app Laravel: controllers, models, views Blade, scripts e estilos. |
| `config/hop.php`, `config/limits.php`, `config/shortener.php` | A lista da família HOP (a mesma lista `apps` do hub), as cotas de links e as configurações de links de visitantes, slugs e monitor. Os padrões e limites de segurança vêm do `hop/safety` (`config('safety')`). |
| `database/migrations/`, `database/migrations-archive/` | As tabelas do próprio LinkHop e o histórico das tabelas de contas (agora do hub, não executado). |
| `lang/` | Os nove arquivos de idioma. |
| `tests/` | Testes Pest e PHPUnit (`php artisan test`) e os testes de navegador com usuários sintéticos em `tests/e2e/` ([como executá-los](tests/e2e/README.md)). |
| `docs/` | O site em [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/). `docs/readme/` guarda as capturas de tela do README; `scripts/readme-images.mjs` as refaz. |
| `scripts/` | Scripts auxiliares: `build-changelog.py` (o changelog dentro do app), `check-hop-family.sh` (a tabela da família no README em inglês) e `readme-images.mjs`. |

Todas as dependências e suas licenças estão listadas em [CREDITS.md](CREDITS.md).

## Como contribuir

1. Faça um fork do repositório e crie uma branch.
2. Faça a sua alteração e execute `vendor/bin/pint` e `php artisan test`.
3. Envie com push e abra um pull request para `main`.

Adicione testes para comportamentos novos. Relate problemas de segurança de forma privada, como descrito em [SECURITY.md](SECURITY.md), e use os modelos de issue para todo o resto.

## Licença

MIT. Veja [LICENSE](LICENSE).

---

<p align="center">Feito por <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">Site</a></p>
