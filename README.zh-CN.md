<p align="center">🌐 <a href="README.md">English</a> · <b>简体中文</b> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="LinkHop 首页：粘贴长链接的输入框、Shorten 按钮和 3D 岛屿场景" width="100%"></picture></p>

<p align="center">
  <b>短链接、二维码和点击统计。<br>免费、开源，可自行托管。</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>试用 LinkHop</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">网站</a> ·
  <a href="#安装">安装</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">更新日志</a> ·
  <a href="CREDITS.md">致谢</a> ·
  <a href="SECURITY.md">安全</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

LinkHop 把链接变短，统计点击，并为每个链接生成二维码。它是 HOP 家族的短链接服务，线上地址是 [link.hel.ink](https://link.hel.ink)。短链接的地址形如 `hel.ink/l/slug`。

任何人无需账号就能缩短链接。免费的 HOP ID 会保留你的链接并显示它们的统计数据。HOP ID 是所有 HOP 应用共用的一个账号，由 `hel.ink` 和 `hub.hel.ink` 上的[中心服务](https://github.com/navi-crwn/HEL.ink)（hub）管理。LinkHop 没有自己的登录、注册或个人资料页面：由 hub 让你登录，并通过一张短期有效的凭证把你送回来，各个应用共用同一个数据库。

## HOP 家族

| 应用 | 地址 | 仓库 | 用途 |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · 账号位于 `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | 一个账号通用于所有 HOP 应用。 |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | 短链接、二维码和点击统计。 |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | 一个页面放下你的所有链接（Link in Bio）。 |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | 图片托管，支持相册和直链。 |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | 加密笔记，用链接分享。 |
| **Imperium** | 私有（仅所有者） | 私有 | HOP 控制中心。 |

每个账号都能使用全部功能，没有付费档位。代码采用 MIT 许可证，你也可以在自己的服务器和域名上运行。

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="LinkHop 仪表盘：本周点击量（每天一根柱）、总计、入门清单、最近的链接和表现最好的链接" width="100%"></picture></p>

登录后页面的截图使用的是虚构的演示数据，不是真实账号。

## 功能

- 短链接可用随机或自定义 slug（3 到 255 个字符），可选密码和过期时间，并为每个链接提供 PNG、SVG 或 JPG 格式的二维码。
- 链接限制：开始时间、最大点击数、链接过期或点击用完后的备用地址，以及 301、302、307 或 308 重定向类型。
- 路由：最多 20 条规则，可按国家、语言、设备、操作系统或浏览器把访客送到别处；还有由 2 到 5 个带权重的地址组成的 A/B 分流。活动标签（UTM）生成器会填好 `utm_*` 参数。
- 在应用中打开：在 iOS 和 Android 上，链接可以先尝试已安装的应用，失败时回退到 App Store 或 Google Play。
- 统计：点击、独立访客、国家和城市、设备、浏览器、应用内浏览器、来源页，以及二维码扫描与直接点击的对比。所选时段会与上一时段比较。每次点击的日志保留 30 天。
- 整理：文件夹、标签、搜索、批量操作、链接评论、最多 500 行的 CSV 导入，以及 Ctrl 或 Cmd + K 命令面板。设置页可以把你的链接连同统计导出为 CSV。
- 位于 `/check` 的链接检查：粘贴一个 `hel.ink` 链接，在打开之前先看它的目标地址。不会点击，也不会计数。
- 目标监测：LinkHop 每天询问每个有效目标是否还能访问，连续三次检查失败后给所有者发一封邮件。可以在设置中关闭。
- 内容警告：链接可以标记为敏感或成人内容，访客在继续之前会先看到提示。
- 滥用防护：频率限制和配额、域名黑名单、IP 封禁、对访客的代理和 VPN 检测、举报表单，以及每小时一次的钓鱼、诈骗和其他有害链接扫描，可选接入 Google Safe Browsing 和 URLhaus 查询。
- API 和 webhook：带权限范围的令牌和幂等键的 REST API、针对五种链接事件的签名 webhook，以及 ShareX 配置。
- 九种语言：English、简体中文、Deutsch、Bahasa Indonesia、日本語、Português (Brasil)、Español、Français 和 Русский。在任意页面后加 `?lang=de` 即可切换，选择会保存在 cookie 中。
- 隐私：没有广告，没有第三方跟踪器，也不从 CDN 加载脚本、字体或图片。库文件已打包或放在 `public/vendor`。剩下的两个第三方主机是访客表单上的 Cloudflare Turnstile，以及统计地图下的 OpenStreetMap 图块。
- 自托管：一条命令安装、更新和卸载脚本、每晚备份，以及面向 HOP 控制中心的签名管理 API。

Bio 页面（`hel.ink/b/你的名字`）在 [BioHop](https://github.com/navi-crwn/BioHop) 中制作，而不是在 LinkHop 中。LinkHop 仍会提供它们的那一种情况见 [Bio 页面](#bio-页面)。

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="Analytics 页面：筛选器、与上一时段对比的点击和独立访客、主要国家和设备，以及点击随时间变化的图表" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="My links 页面：可搜索的列表，每个链接带有文件夹、标签、点击数和七天走势线" width="100%"></picture></p>

## 各项内容的位置

| 内容 | URL |
| --- | --- |
| 短链接 | `hel.ink/l/slug` |
| 旧的短链接形式 | `hel.ink/slug`，到 2026 年 11 月 27 日（`LEGACY_SLUG_UNTIL`）前会重定向到 `hel.ink/l/slug` |
| Bio 页面（BioHop） | `hel.ink/b/name` |
| 仪表盘和 API | `link.hel.ink`，通过 `hub.hel.ink` 的 hub 登录 |
| 你的账号 | `hub.hel.ink`：个人资料、密码、双重验证、API 令牌、数据下载、删除账号 |

短链接于 2026 年 10 月 28 日从 `hel.ink/slug` 迁到 `hel.ink/l/slug`，好让 `hel.ink` 能承载多个 HOP 应用。没有删除任何内容。仪表盘、二维码、API 和账号导出都显示新的形式。在你自己的安装上，公开主机是 `PUBLIC_URL`（默认等于 `APP_URL`），`php artisan links:announce-migration` 会用邮件把新形式的链接发给每位链接所有者（`--reminder` 发送第二封）。

## 安装

安装脚本会检查你的电脑、问几个问题，并且可以在 LinkHop 旁边装好 HOP ID hub：

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

在克隆下来的仓库里运行 `./install.sh`。更新用 `./update.sh`，卸载 LinkHop 用 `./uninstall.sh`。加 `--yes` 使用所有默认值，加 `--dry-run` 只显示步骤而不执行，`--help` 列出其他选项。

- `install.sh` 需要 PHP 8.2 或更新版本（如果装了 `php8.3` 就用它；用 `PHP_BIN` 指定别的）、Composer、Node.js 20 或更新版本，以及一个数据库：SQLite 或 MySQL/MariaDB。它会写入一个私有的 `.env` 和一个 `start.sh`，后者会启动 Web 服务器、队列 worker 和计划任务。
- `update.sh` 先把数据库复制到 `backups/`（SQLite 或 MySQL/MariaDB），让站点进入维护模式，拉取新代码，重新构建并迁移；如果迁移之前的某一步失败，就恢复旧版本。无法复制数据库时它会停下；`--no-backup` 则在没有备份的情况下继续。
- `uninstall.sh` 删除已安装的软件包和构建文件，保留你的 `.env`、数据库、上传文件和日志。`--purge` 会一并删除它们，并要求你先输入站点名称。

> [!NOTE]
> LinkHop 的账号放在 HOP ID hub（[HEL.ink](https://github.com/navi-crwn/HEL.ink)）里，没有不带 hub 的模式。安装脚本可以在 LinkHop 旁边装一个 hub（默认，约一分钟），也可以连接你已经在运行的 hub。想一次装好 hub 和多个应用，请使用 HEL.ink 仓库里的 `install-family.sh`。

### 手动安装

你需要 PHP 8.2 或更新版本（带常用的 Laravel 扩展，以及用于 PNG 二维码的 `gd` 或 `imagick`）、Composer、Node.js 20.19 或更新版本，以及一个数据库。[hub](https://github.com/navi-crwn/HEL.ink) 必须连接同一个数据库运行：账号表（`users`、`api_tokens` 等）归它所有，登录也由它负责。

```bash
# 共用的软件包 hop/auth、hop/ui 和 hop/safety 通过 Composer 从 GitHub 获取。
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # 然后编辑 .env，见下文
php artisan migrate --force       # 只包含本应用的表
php artisan storage:link
php artisan serve --port=8001
```

本地开发时，设置 `APP_ENV=local`、`APP_URL=http://localhost:8001`、`PUBLIC_URL=http://localhost:8001`、`HOP_HUB_URL=http://localhost:8000`（用 `php artisan serve` 运行的 hub）和 `SESSION_SECURE_COOKIE=false`；在 hub 一侧设置 `LINKHOP_URL=http://localhost:8001`。访客打开 LinkHop 时会被送到 hub 的登录页，登录后回到这里。

生产环境要让两样东西持续运行：队列 worker（`php artisan queue:work --tries=3`）和计划任务（一行 cron：`* * * * * cd /path/to/linkhop && php artisan schedule:run`）。计划任务负责运行滥用扫描、目标监测、webhook 重试、隐私清理、访客链接删除和备份。

**数据库归属。** 每个 HOP 应用只对共用数据库运行自己的迁移。LinkHop 的迁移在 `database/migrations`。账号表的历史保存在 `database/migrations-archive`，不再运行；测试用它来构建表结构。不要对 hub 正在使用的数据库运行 `migrate:fresh`。

<details>
<summary><b><code>.env</code> 中的设置</b></summary>

`.env.example` 列出了全部设置。下面是你最可能改动的几项。

| 设置 | 含义 |
| --- | --- |
| `APP_URL` | 仪表盘和 API（托管服务上是 `https://link.hel.ink`）。 |
| `PUBLIC_URL` | 短链接、二维码和站点地图的访问地址（`https://hel.ink`）。单主机安装时与 `APP_URL` 取同一个值。 |
| `HOP_APP`、`HOP_HUB_URL`、`HOP_SSO_SECRET` | 与 hub 的连接。`HOP_APP` 是 `linkhop`；密钥与 hub 的 `.env` 中的相同，至少 32 个字符。登录、Google 登录、双重验证和密码重置都归 hub 管，这里不需要再配置。 |
| `DB_*` | 与 hub 相同的数据库。 |
| `MAIL_*` | 外发邮件：给链接所有者的通知、目标监测和备份告警。 |
| `TURNSTILE_SITE_KEY`、`TURNSTILE_SECRET_KEY` | 访客缩短表单和举报表单上的 Cloudflare Turnstile。在生产环境中，没有密钥（secret key）时访客表单会拒绝所有提交。 |
| `GUEST_LINK_IDLE_DAYS`、`GUEST_LINK_MAX_DAYS` | 访客链接在 14 天没有点击后删除，最长保留 30 天。服务器只能把这些值调得更短。 |
| `LIMIT_*` | 来自 `config/limits.php` 的配额，例如每个地址每分钟 10 个链接、每天 200 个，每个账号每天 200 个、有效链接 1000 个。 |
| `SAFE_BROWSING_KEY`、`URLHAUS_AUTH_KEY` | 滥用扫描的可选查询。 |
| `GEOLITE_LICENSE_KEY` | 可选。没有它时，`geolite:update` 下载免费的 DB-IP Lite 数据库。 |
| `BIO_ENABLED`、`BIOHOP_URL`、`PIXELHOP_URL` | 内置的 bio 包，以及其他应用的地址。 |
| `HOP_CONTROL_URL` | `/admin` 把管理员送往的地方：HOP 控制中心。 |
| `LEGACY_SLUG_UNTIL` | 旧的 `hel.ink/slug` 重定向停止的日期。新安装请留空。 |
| `TRUSTED_PROXIES` | 反向代理的地址，或 `cloudflare`。 |
| `SENTRY_LARAVEL_DSN`、`BACKUP_DISKS` | 错误追踪，以及备份写入的磁盘（默认 `local`）。 |

**访客位置。** 国家和城市来自本地的 DB-IP Lite 数据库，由 `geolite:update` 每周更新。当它对某个公网地址没有结果时，LinkHop 最多再询问两个网络查询服务：freeipapi.com、ip-api.com、ipapi.co、ipinfo.io 或 abstractapi.com。访客的地址只以带密钥的哈希形式保存。

**备份。** 计划任务每天 02:45 运行 `backup:run`。归档包含数据库、`storage/app/public` 和 `.env`，写入 `BACKUP_DISKS` 指定的磁盘。备份 MySQL 或 MariaDB 需要服务器上有 `mysqldump`。`.env` 里有你的密钥，请保护好备份磁盘，或设置 `BACKUP_ARCHIVE_PASSWORD`。

**在 Cloudflare 之后。** LinkHop 发送的内容安全策略（CSP）只信任带有该页面 nonce 的脚本。Cloudflare 的 Email Address Obfuscation（Scrape Shield）和 Rocket Loader（Speed，Optimization）会注入不带 nonce 的自有脚本，所以浏览器会拦截它们，拦截记录写入 `storage/logs/csp-*.log`。请在 Cloudflare 控制台里关掉这两项，LinkHop 不需要它们。把它们加进策略行不通，因为在 nonce 策略下 `'strict-dynamic'` 会忽略主机白名单。

**单主机。** 托管服务把 LinkHop 拆成两部分：仪表盘在 `link.hel.ink`，短链接在 `hel.ink/l/`。这种拆分的 nginx 规则在 hub 仓库的 `deploy/nginx/` 下。单主机时，把 `APP_URL` 和 `PUBLIC_URL` 设成同一个值。

</details>

## 使用

**缩短链接。** 粘贴长地址，可选地选择 slug、密码或过期时间，点击 Shorten 并复制结果。首页的 Advanced 里有访客可用的选项；登录后，New link 提供上述全部选项。

**Bio 页面。** 用同一个 HOP ID 登录 [bio.hel.ink](https://bio.hel.ink)，选一个名字，然后分享 `hel.ink/b/yourname`。

## API

API 令牌在 hub 里创建（HOP ID，Settings，API），在每个 HOP 应用中都可用。LinkHop 从共用的 `api_tokens` 表读取它们。令牌带有权限范围（scope）：`link:create`、`link:read` 和 `link:delete`。在出现权限范围之前创建的令牌没有范围，可以执行所有操作。API 的地址是 `link.hel.ink/api`，以及会转发到那里的 `hel.ink/api`（到 2026 年 11 月 27 日为止）。限流按令牌计算，默认每小时 100 次请求，除非令牌自己设置了上限。

创建短链接（`link:create`）。当 bio 区块需要短链接时，BioHop 会调用它。

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| 字段 | | |
| --- | --- | --- |
| `url` | 必填 | 完整的 `http://` 或 `https://` 地址，最长 2048 个字符。安全检查有异议时会被拒绝。 |
| `alias` | 可选 | slug：3 到 255 个字符，取自 `A-Z a-z 0-9 _ -`。保留词和已被占用的 slug 会被拒绝。省略时随机生成。 |
| `folder` | 可选 | 账号下的文件夹名称。不存在时会创建。 |
| `password` | 可选 | 4 到 50 个字符；访客必须输入。 |
| `expires_at` | 可选 | 将来的 ISO 日期时间。 |
| `starts_at` | 可选 | 在此之前访客看到 “Not live yet”。 |
| `max_clicks` | 可选 | 达到这个点击数后链接停止（1 到 10,000,000）。 |
| `fallback_url` | 可选 | 过期或点击数用完之后访客去的地方。不能是另一个短链接。 |
| `redirect_type` | 可选 | `301`、`302`（默认）、`307` 或 `308`。 |
| `content_warning`、`warning_reason` | 可选 | `none`、`sensitive` 或 `adult`，以及给访客看的说明（最长 255 个字符）。 |
| `routing_rules`、`ab_variants`、`app_links` | 可选 | 按访客路由、A/B 分流和深度链接。具体结构见仪表盘的 API docs 页面。 |

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

错误使用统一的格式：`{"success": false, "error": "…", "message": "…"}`。`401` 表示令牌未知或已过期，`403` 表示缺少权限范围或账号被停用、未验证，`422` 表示验证错误（`messages` 列出各字段），`429` 表示配额或频率限制。

在 `POST /api/shorten` 中发送 `Idempotency-Key` 头即可安全重试。同一个键加同样的请求体会返回第一次的响应（`Idempotent-Replayed: true`），不会重复创建。同一个键配不同的请求体会返回 `422`。键保留 24 小时。

用 `GET /api/links/{slug}` 读取链接（`link:read`），返回相同的 `data` 外加 `clicks`。用 `DELETE /api/links/{slug}` 删除（`link:delete`）。如果 slug 不属于令牌所在账号，两者都返回 `404`。

Webhook 在设置中配置：最多五个 https 地址，每个都会在 `link.created`、`link.updated`、`link.deleted`、`link.limit_reached` 和 `link.expired` 时被调用。消息带有 `X-LinkHop-Signature`，即对时间戳和消息体计算的 HMAC-SHA256。投递失败后会在 1 分钟、5 分钟、30 分钟和 2 小时后重试，连续十次投递失败的 webhook 会被关闭。

ShareX 配置可以从仪表盘的 API docs 页面下载。

## FAQ

**免费吗？** 是的。没有付费方案，也没有被锁定的功能。

**需要账号吗？** 不需要。任何人都可以不登录就缩短链接。访客链接在 14 天没有点击后删除，最长保留 30 天。免费的 HOP ID 会保留你的链接、显示统计数据，并让你登录其他 HOP 应用。

**我的 bio 页面在哪里？** 在 BioHop，地址是 `hel.ink/b/` 加上你选的名字。可在 [bio.hel.ink](https://bio.hel.ink) 编辑。

**一次点击记录什么？** 时间、国家和城市、设备、浏览器和来源页，以及由地址和浏览器生成、每天都会变化的带密钥哈希，用来统计独立访客。地址本身不会保存，点击日志在 30 天后删除。

**怎么举报有问题的链接？** 使用 `/report-abuse` 的举报表单。举报会进入 HOP 控制中心的滥用队列，与自动扫描的发现放在一起。

**可以用自己的域名吗？** 可以，在 hub 旁边托管你自己的副本。`APP_URL` 是仪表盘的主机，`PUBLIC_URL` 是短链接的主机，`HOP_HUB_URL` 是 hub。

### Bio 页面

在 hel.ink 上，bio 页面归 BioHop 所有。没有 BioHop 的单服务器安装仍然可以通过内置的 `hop/bio` 包（`packages/bio`）提供它们，方法是设置 `BIO_ENABLED=true`。旁边有 BioHop 时，请保持为 `false`：此时它不注册任何路由，也不需要任何表。`php artisan db:seed --class=ExampleBioSeeder` 会在开发环境里创建一个示例 bio 页面和几个示例短链接。

## 技术栈

| 层 | 技术 |
| --- | --- |
| 后端 | Laravel 12、PHP 8.2 或更新版本、SQLite 或 MySQL/MariaDB |
| 前端 | Tailwind CSS 4、Alpine.js 3、Vite 7；首页使用 GSAP、Lenis 和 Three.js |
| 图表和地图 | Chart.js 4、Leaflet 1.9 加 OpenStreetMap 图块 |
| 账号 | hub 中的 HOP ID，通过 `hop/auth`（SSO 客户端，以及 `hop.auth` 和 `hop.token` 中间件） |
| 共用软件包 | `hop/auth`、`hop/ui`（应用切换器、字体）、`hop/safety`（URL 和内容安全）、`hop/bio`（打包在 `packages/bio`） |
| 服务 | Cloudflare Turnstile；用于访客位置的 DB-IP Lite 和可选的网络查询服务；可选的 Google Safe Browsing 和 URLhaus；Sentry |

| 文件夹 | 内容 |
| --- | --- |
| `app/`、`routes/`、`resources/` | Laravel 应用：控制器、模型、Blade 视图、脚本和样式。 |
| `config/hop.php`、`config/limits.php`、`config/shortener.php` | HOP 家族列表（与 hub 相同的 `apps` 列表）、链接配额，以及访客链接、slug 和监测设置。安全规则和阈值来自 `hop/safety`（`config('safety')`）。 |
| `database/migrations/`、`database/migrations-archive/` | LinkHop 自己的表，以及账号表的历史（现在归 hub，不再运行）。 |
| `lang/` | 九种语言文件。 |
| `tests/` | Pest 和 PHPUnit 测试（`php artisan test`），以及 `tests/e2e/` 中的模拟用户浏览器测试（[运行方法](tests/e2e/README.md)）。 |
| `docs/` | 网站 [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/)。`docs/readme/` 存放 README 截图，`scripts/readme-images.mjs` 负责重新截取。 |
| `scripts/` | 辅助脚本：`build-changelog.py`（应用内的更新日志）、`check-hop-family.sh`（英文 README 中的家族表）和 `readme-images.mjs`。 |

所有依赖及其许可证都列在 [CREDITS.md](CREDITS.md) 中。

## 参与贡献

1. Fork 本仓库并新建分支。
2. 完成修改，运行 `vendor/bin/pint` 和 `php artisan test`。
3. 推送并向 `main` 提交 pull request。

新行为请附上测试。安全问题请按 [SECURITY.md](SECURITY.md) 的说明私下报告，其他问题请使用 issue 模板。

## 许可证

MIT。见 [LICENSE](LICENSE)。

---

<p align="center">作者 <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">网站</a></p>
