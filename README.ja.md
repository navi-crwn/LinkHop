<p align="center">🌐 <a href="README.md">English</a> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <b>日本語</b> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="LinkHop のトップページ。長いリンクを貼る入力欄、Shorten ボタン、3D の島のシーン" width="100%"></picture></p>

<p align="center">
  <b>短縮リンク、QR コード、クリック分析。<br>無料でオープンソース、自分でホスティングもできます。</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>LinkHop を試す</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">ウェブサイト</a> ·
  <a href="#インストール">インストール</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">変更履歴</a> ·
  <a href="CREDITS.md">クレジット</a> ·
  <a href="SECURITY.md">セキュリティ</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

LinkHop はリンクを短くし、クリックを数え、リンクごとに QR コードを作ります。HOP ファミリーのリンク短縮サービスで、[link.hel.ink](https://link.hel.ink) で動いています。短縮リンクは `hel.ink/l/slug` で開きます。

アカウントがなくても誰でもリンクを短縮できます。無料の HOP ID があると、リンクが保存され、その統計を見られます。HOP ID はすべての HOP アプリで共通の 1 つのアカウントで、`hel.ink` と `hub.hel.ink` の[ハブ](https://github.com/navi-crwn/HEL.ink)が管理しています。LinkHop には独自のログイン、新規登録、プロフィールのページがありません。ハブがログインを行い、短時間だけ有効なチケットを付けてこちらへ戻し、各アプリは 1 つのデータベースを共有します。

## HOP ファミリー

| アプリ | アドレス | リポジトリ | 役割 |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · アカウントは `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | すべての HOP アプリで使える 1 つのアカウント。 |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | 短縮リンク、QR コード、クリック分析。 |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | あなたの活動をまとめる 1 枚のリンクインバイオページ。 |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | アルバムと直接リンクに対応した画像ホスティング。 |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | リンクで共有できる暗号化ノート。 |
| **Imperium** | 非公開（オーナーのみ） | 非公開 | HOP のコントロールパネル。 |

すべての機能がどのアカウントでも使え、有料プランはありません。コードは MIT ライセンスなので、自分のサーバーと自分のドメインで動かすこともできます。

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="LinkHop のダッシュボード。1 日ごとの棒で示す今週のクリック数、合計、はじめてのチェックリスト、最近のリンク、成績の良いリンク" width="100%"></picture></p>

ログイン後のページのスクリーンショットは、実在のアカウントではなく架空のデモデータです。

## 機能

- 短縮リンク。スラッグはランダムでも自分で決めても構いません（3 ～ 255 文字）。任意でパスワードや有効期限を付けられ、QR コードは PNG、SVG、JPG で取得できます。
- リンクの制限。開始時刻、最大クリック数、有効期限切れやクリック上限に達したときの代替アドレス、リダイレクトの種類（301、302、307、308）。
- ルーティング。国、言語、デバイス、OS、ブラウザーで訪問者を別のアドレスへ振り分ける最大 20 件のルールと、重み付きで 2 ～ 5 件のアドレスに分ける A/B 分割。キャンペーンタグ（UTM）ビルダーが `utm_*` パラメーターを入力します。
- アプリで開く。iOS と Android では、インストール済みのアプリを先に試し、だめなら App Store または Google Play へ誘導できます。
- 分析。クリック数、ユニーク訪問者、国と都市、デバイス、ブラウザー、アプリ内ブラウザー、参照元、そして QR スキャンと直接クリックの比較。選んだ期間は直前の期間と比較されます。クリックごとのログは 30 日間保存されます。
- 整理。フォルダー、タグ、検索、一括操作、リンクへのコメント、最大 500 行の CSV インポート、Ctrl または Cmd + K のコマンドパレット。設定では、リンクと統計を CSV でエクスポートできます。
- `/check` のリンクチェック。`hel.ink` のリンクを貼ると、開く前に行き先を確認できます。クリックも集計もされません。
- 行き先の監視。LinkHop は 1 日 1 回、有効な各リンクの行き先がまだ応答するかを確認し、3 回続けて失敗するとオーナーにメールを送ります。設定でオフにできます。
- コンテンツの警告。リンクを「センシティブ」または「成人向け」に設定でき、訪問者は先へ進む前に注意書きを見ます。
- 不正利用対策。レート制限とクォータ、ドメインのブロックリスト、IP のブロック、訪問者に対するプロキシと VPN のチェック、報告フォーム、フィッシングや詐欺などの有害なリンクを 1 時間ごとにスキャンする仕組み（Google Safe Browsing と URLhaus への問い合わせは任意）。
- API と Webhook。スコープ付きトークンと冪等キーを備えた REST API、5 種類のリンクイベントに対する署名付き Webhook、ShareX の設定。
- 9 言語。English、简体中文、Deutsch、Bahasa Indonesia、日本語、Português (Brasil)、Español、Français、Русский。どのページでも `?lang=de` のように付けると切り替わり、選択は Cookie に保存されます。
- プライバシー。広告なし、サードパーティのトラッカーなし、CDN からのスクリプト・フォント・画像もなし。ライブラリーはバンドルするか `public/vendor` に置いています。残る外部ホストは、ゲスト用フォームの Cloudflare Turnstile と、分析マップの OpenStreetMap タイルの 2 つです。
- セルフホスティング。ワンコマンドのインストーラー、更新とアンインストールのスクリプト、毎晩のバックアップ、HOP コントロールセンター向けの署名付き管理 API。

プロフィールページ（`hel.ink/b/あなた`）は LinkHop ではなく [BioHop](https://github.com/navi-crwn/BioHop) で作ります。LinkHop が今もそれを配信する唯一のケースは、[Bio ページ](#bio-ページ)を参照してください。

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="Analytics ページ。フィルター、前の期間と比べたクリック数とユニーク訪問者、上位の国とデバイス、クリック数の推移グラフ" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="My links ページ。フォルダー、タグ、クリック数、7 日間の推移線を備えた検索可能なリンク一覧" width="100%"></picture></p>

## 何がどこにあるか

| 内容 | URL |
| --- | --- |
| 短縮リンク | `hel.ink/l/slug` |
| 短縮リンクの旧形式 | `hel.ink/slug`。2026 年 11 月 27 日（`LEGACY_SLUG_UNTIL`）まで `hel.ink/l/slug` にリダイレクトされます |
| Bio ページ（BioHop） | `hel.ink/b/name` |
| ダッシュボードと API | `link.hel.ink`。`hub.hel.ink` のハブ経由でログインします |
| あなたのアカウント | `hub.hel.ink`。プロフィール、パスワード、2 要素認証、API トークン、データのダウンロード、アカウントの削除 |

`hel.ink` が複数の HOP アプリを受け持てるよう、短縮リンクは 2026 年 10 月 28 日に `hel.ink/slug` から `hel.ink/l/slug` へ移りました。削除されたものはありません。ダッシュボード、QR コード、API、アカウントのエクスポートには新しい形式が表示されます。自分のインストールでは、公開ホストは `PUBLIC_URL`（既定は `APP_URL`）で、`php artisan links:announce-migration` はすべてのリンク所有者に新形式のリンクをメールで知らせます（`--reminder` で 2 通目を送信）。

## インストール

インストーラーはコンピューターを確認し、いくつか質問をして、必要なら LinkHop の隣に HOP ID ハブをセットアップします。

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

クローンしたフォルダーからは `./install.sh` を実行します。更新は `./update.sh`、LinkHop の削除は `./uninstall.sh` です。`--yes` を付けるとすべて既定値で進み、`--dry-run` は実行せずに手順だけを表示し、`--help` でそのほかのオプションが分かります。

- `install.sh` には PHP 8.2 以降（`php8.3` が入っていればそれを使います。別のものは `PHP_BIN` で指定）、Composer、Node.js 20 以降、データベース（SQLite または MySQL/MariaDB）が必要です。非公開の `.env` と、Web サーバー、キューワーカー、スケジューラーを起動する `start.sh` を書き出します。
- `update.sh` は最初にデータベースを `backups/` にコピーし（SQLite または MySQL/MariaDB）、サイトをメンテナンスモードにして、新しいコードを取得し、ビルドとマイグレーションを行います。マイグレーションより前の手順が失敗した場合は、元のバージョンに戻します。データベースのコピーを作れないときは停止します。`--no-backup` を付けるとコピーなしで続行します。
- `uninstall.sh` はインストールしたパッケージとビルドしたファイルを削除し、`.env`、データベース、アップロード、ログは残します。`--purge` を付けるとそれらも削除し、先にサイト名の入力を求めます。

> [!NOTE]
> LinkHop はアカウントを HOP ID ハブ（[HEL.ink](https://github.com/navi-crwn/HEL.ink)）で管理しており、ハブなしのモードはありません。インストーラーは LinkHop の隣にハブをセットアップするか（既定、約 1 分）、すでに運用しているハブに接続できます。ハブと複数のアプリを一度にインストールするには、HEL.ink リポジトリの `install-family.sh` を使います。

### 手動インストール

PHP 8.2 以降（Laravel の一般的な拡張機能と、PNG の QR コード用の `gd` または `imagick` が必要）、Composer、Node.js 20.19 以降、そしてデータベースが必要です。[ハブ](https://github.com/navi-crwn/HEL.ink)を同じデータベースに対して動かしておく必要があります。アカウントのテーブル（`users`、`api_tokens` など）はハブのもので、ログインもハブが行います。

```bash
# 共有パッケージ hop/auth、hop/ui、hop/safety は Composer 経由で GitHub から取得されます。
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # そのあと .env を編集します（下記）
php artisan migrate --force       # このアプリのテーブルだけ
php artisan storage:link
php artisan serve --port=8001
```

ローカルで使うには、`APP_ENV=local`、`APP_URL=http://localhost:8001`、`PUBLIC_URL=http://localhost:8001`、`HOP_HUB_URL=http://localhost:8000`（`php artisan serve` で動かしたハブ）、`SESSION_SECURE_COOKIE=false` を設定し、ハブ側では `LINKHOP_URL=http://localhost:8001` を設定します。ゲストが LinkHop を開くとハブのログイン画面に送られ、ログインした状態で戻ってきます。

本番環境では、キューワーカー（`php artisan queue:work --tries=3`）とスケジューラー（cron の 1 行 `* * * * * cd /path/to/linkhop && php artisan schedule:run`）の 2 つを動かし続けてください。スケジューラーは、不正利用のスキャン、行き先の監視、Webhook の再送、プライバシー関連のクリーンアップ、ゲストリンクの削除、バックアップを実行します。

**データベースの所有。** HOP の各アプリは、共有データベースに対して自分のマイグレーションだけを実行します。LinkHop のものは `database/migrations` にあります。アカウントのテーブルの履歴は `database/migrations-archive` に残してあり、もう実行されません。テストはそこからスキーマを作ります。ハブが使っているデータベースに対して `migrate:fresh` を実行しないでください。

<details>
<summary><b><code>.env</code> の設定</b></summary>

`.env.example` にすべての項目が載っています。手を入れる可能性が高いのは次の設定です。

| 設定 | 意味 |
| --- | --- |
| `APP_URL` | ダッシュボードと API（ホスティング版では `https://link.hel.ink`）。 |
| `PUBLIC_URL` | 短縮リンク、QR コード、サイトマップにアクセスする場所（`https://hel.ink`）。単一ホストのインストールでは `APP_URL` と同じ値にします。 |
| `HOP_APP`、`HOP_HUB_URL`、`HOP_SSO_SECRET` | ハブとの接続。`HOP_APP` は `linkhop`。シークレットはハブの `.env` と同じ 32 文字以上のものです。ログイン、Google ログイン、2 要素認証、パスワードのリセットはハブの担当なので、ここで設定するものはありません。 |
| `DB_*` | ハブと同じデータベース。 |
| `MAIL_*` | 送信メール。リンク所有者への通知、行き先の監視、バックアップのアラート。 |
| `TURNSTILE_SITE_KEY`、`TURNSTILE_SECRET_KEY` | ゲスト用の短縮フォームと報告フォームの Cloudflare Turnstile。本番ではシークレットキーがないと、ゲストフォームはすべての送信を拒否します。 |
| `GUEST_LINK_IDLE_DAYS`、`GUEST_LINK_MAX_DAYS` | ゲストのリンクは、クリックなしで 14 日、長くても 30 日で削除されます。サーバー側ではこれを短くすることしかできません。 |
| `LIMIT_*` | `config/limits.php` のクォータ。たとえば 1 アドレスあたり 1 分に 10 件、1 日に 200 件、1 アカウントあたり 1 日 200 件で有効なリンクは 1000 件。 |
| `SAFE_BROWSING_KEY`、`URLHAUS_AUTH_KEY` | 不正利用スキャン用の任意の問い合わせ。 |
| `GEOLITE_LICENSE_KEY` | 任意。なければ `geolite:update` が無料の DB-IP Lite データベースをダウンロードします。 |
| `BIO_ENABLED`、`BIOHOP_URL`、`PIXELHOP_URL` | 同梱の bio パッケージと、ほかのアプリのアドレス。 |
| `HOP_CONTROL_URL` | `/admin` が管理者を送る先。HOP コントロールセンター。 |
| `LEGACY_SLUG_UNTIL` | 旧形式 `hel.ink/slug` のリダイレクトが終わる日付。新規インストールでは空にしておきます。 |
| `TRUSTED_PROXIES` | リバースプロキシのアドレス、または `cloudflare`。 |
| `SENTRY_LARAVEL_DSN`、`BACKUP_DISKS` | エラー追跡と、バックアップの書き込み先ディスク（既定は `local`）。 |

**訪問者の位置。** 国と都市は、`geolite:update` が毎週更新するローカルの DB-IP Lite データベースから取得します。公開アドレスについてそこに答えがない場合、LinkHop は最大 2 つのウェブの検索サービス（freeipapi.com、ip-api.com、ipapi.co、ipinfo.io、abstractapi.com）に問い合わせます。訪問者のアドレスは、鍵付きのハッシュとしてしか保存されません。

**バックアップ。** スケジューラーは毎日 02:45 に `backup:run` を実行します。アーカイブにはデータベース、`storage/app/public`、`.env` が含まれ、`BACKUP_DISKS` のディスクに書き込まれます。MySQL または MariaDB のバックアップには、サーバーに `mysqldump` が必要です。`.env` にはシークレットが入っているので、バックアップ用ディスクを保護するか、`BACKUP_ARCHIVE_PASSWORD` を設定してください。

**Cloudflare の背後で。** LinkHop は、ページの nonce を持つスクリプトだけを信頼するコンテンツセキュリティポリシーを送ります。Cloudflare の Email Address Obfuscation（Scrape Shield）と Rocket Loader（Speed、Optimization）は、その nonce のない独自のスクリプトを挿入するため、ブラウザーにブロックされ、そのブロックは `storage/logs/csp-*.log` に記録されます。どちらも Cloudflare のダッシュボードでオフにしてください。LinkHop には不要です。nonce を使うポリシーでは `'strict-dynamic'` がホストの許可リストを無視するため、ポリシーに追加しても機能しません。

**単一ホスト。** ホスティング版では LinkHop を 2 つに分けています。ダッシュボードは `link.hel.ink`、短縮リンクは `hel.ink/l/` です。この分割のための nginx のルールは、ハブのリポジトリの `deploy/nginx/` にあります。単一ホストなら、`APP_URL` と `PUBLIC_URL` を同じ値にします。

</details>

## 使い方

**リンクを短縮する。** 長いアドレスを貼り付け、必要ならスラッグ、パスワード、有効期限を選び、Shorten を押して結果をコピーします。トップページの Advanced にゲスト向けのオプションがあります。ログイン後は New link で上記のすべてのオプションを使えます。

**Bio ページ。** 同じ HOP ID で [bio.hel.ink](https://bio.hel.ink) にログインし、名前を決めて `hel.ink/b/yourname` を共有します。

## API

API トークンはハブで作成し（HOP ID、Settings、API）、すべての HOP アプリで使えます。LinkHop は共有の `api_tokens` テーブルからトークンを読み取ります。トークンにはスコープがあり、`link:create`、`link:read`、`link:delete` の 3 つです。スコープができる前に作られたトークンはスコープを持たず、すべての操作が可能です。API は `link.hel.ink/api` で応答し、`hel.ink/api` も 2026 年 11 月 27 日までそこへ転送します。レート制限はトークンごとで、トークンが独自の上限を持たない限り 1 時間に 100 リクエストです。

短縮リンクを作成します（`link:create`）。bio ブロックが短縮リンクを必要とするとき、BioHop がこの呼び出しを行います。

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| フィールド | | |
| --- | --- | --- |
| `url` | 必須 | `http://` または `https://` で始まる完全なアドレス（2048 文字まで）。安全チェックで問題が見つかると拒否されます。 |
| `alias` | 任意 | スラッグ。`A-Z a-z 0-9 _ -` の 3 ～ 255 文字。予約語と使用済みのスラッグは拒否されます。省略するとランダムになります。 |
| `folder` | 任意 | アカウント内のフォルダー名。存在しなければ作成されます。 |
| `password` | 任意 | 4 ～ 50 文字。訪問者が入力する必要があります。 |
| `expires_at` | 任意 | 未来の ISO 日時。 |
| `starts_at` | 任意 | この日時より前は、訪問者に "Not live yet" が表示されます。 |
| `max_clicks` | 任意 | このクリック数に達するとリンクが止まります（1 ～ 10,000,000）。 |
| `fallback_url` | 任意 | 有効期限またはクリック上限のあとに訪問者が向かう先。別の短縮リンクにはできません。 |
| `redirect_type` | 任意 | `301`、`302`（既定）、`307`、`308`。 |
| `content_warning`、`warning_reason` | 任意 | `none`、`sensitive`、`adult` のいずれかと、訪問者に表示する 255 文字までのメモ。 |
| `routing_rules`、`ab_variants`、`app_links` | 任意 | 訪問者ごとのルーティング、A/B 分割、ディープリンク。形式はダッシュボードの API docs ページにあります。 |

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

エラーの形式は共通で、`{"success": false, "error": "…", "message": "…"}` です。`401` は不明または期限切れのトークン、`403` はスコープ不足、または停止中か未確認のアカウント、`422` は検証エラー（`messages` に該当フィールドが並びます）、`429` はクォータまたはレート制限です。

`POST /api/shorten` に `Idempotency-Key` ヘッダーを付けると、安全に再試行できます。同じキーと同じ本文なら最初の応答が返り（`Idempotent-Replayed: true`）、二重には作成されません。同じキーで本文が違う場合は `422` です。キーは 24 時間保存されます。

リンクの取得（`link:read`）は `GET /api/links/{slug}` で、同じ `data` に `clicks` が加わります。削除（`link:delete`）は `DELETE /api/links/{slug}` です。どちらも、トークンのアカウントにないスラッグには `404` を返します。

Webhook は設定で登録します。https アドレスを最大 5 件まで登録でき、それぞれ `link.created`、`link.updated`、`link.deleted`、`link.limit_reached`、`link.expired` のときに呼び出されます。メッセージには、タイムスタンプと本文の HMAC-SHA256 である `X-LinkHop-Signature` が付きます。配信に失敗した場合は 1 分後、5 分後、30 分後、2 時間後に再試行し、10 回続けて配信に失敗した Webhook は無効になります。

ShareX の設定は、ダッシュボードの API docs ページからダウンロードできます。

## FAQ

**無料ですか？** はい。有料プランはなく、制限された機能もありません。

**アカウントは必要ですか？** いいえ。アカウントなしで誰でもリンクを短縮できます。ゲストのリンクは、クリックなしで 14 日、長くても 30 日で削除されます。無料の HOP ID があると、リンクが保存され、統計を見られ、ほかの HOP アプリにもログインできます。

**自分の bio ページはどこですか？** BioHop の `hel.ink/b/` に、選んだ名前を続けたアドレスです。編集は [bio.hel.ink](https://bio.hel.ink) で行います。

**クリックでは何を記録しますか？** 時刻、国と都市、デバイス、ブラウザー、参照元、そしてユニーク訪問者を数えるための、アドレスとブラウザーから作る毎日変わる鍵付きハッシュです。アドレスそのものは保存されず、クリックのログは 30 日後に削除されます。

**問題のあるリンクはどう報告しますか？** `/report-abuse` の報告フォームを使います。報告は、自動スキャンの検出結果と並んで HOP コントロールセンターの不正利用キューに入ります。

**独自ドメインは使えますか？** はい。ハブの隣に自分のコピーをホスティングします。`APP_URL` がダッシュボードのホスト、`PUBLIC_URL` が短縮リンク用のホスト、`HOP_HUB_URL` がハブです。

### Bio ページ

hel.ink では、bio ページは BioHop のものです。BioHop がない単一サーバーのインストールでは、`BIO_ENABLED=true` にすると、同梱の `hop/bio` パッケージ（`packages/bio`）から引き続き配信できます。隣に BioHop があるときは `false` のままにしてください。その場合、ルートは登録されず、テーブルも不要です。`php artisan db:seed --class=ExampleBioSeeder` は、開発用のインストールにサンプルの bio ページと数件のサンプル短縮リンクを作ります。

## 技術構成

| レイヤー | 技術 |
| --- | --- |
| バックエンド | Laravel 12、PHP 8.2 以降、SQLite または MySQL/MariaDB |
| フロントエンド | Tailwind CSS 4、Alpine.js 3、Vite 7、トップページには GSAP、Lenis、Three.js |
| グラフと地図 | Chart.js 4、OpenStreetMap タイルを使う Leaflet 1.9 |
| アカウント | ハブの HOP ID。`hop/auth`（SSO クライアントと、`hop.auth`、`hop.token` ミドルウェア）経由 |
| 共有パッケージ | `hop/auth`、`hop/ui`（アプリ切り替え、フォント）、`hop/safety`（URL とコンテンツの安全性）、`hop/bio`（`packages/bio` に同梱） |
| サービス | Cloudflare Turnstile、訪問者の位置情報のための DB-IP Lite と任意のウェブ検索サービス、任意の Google Safe Browsing と URLhaus、Sentry |

| フォルダー | 内容 |
| --- | --- |
| `app/`、`routes/`、`resources/` | Laravel アプリ本体。コントローラー、モデル、Blade ビュー、スクリプト、スタイル。 |
| `config/hop.php`、`config/limits.php`、`config/shortener.php` | HOP ファミリーの一覧（ハブと同じ `apps` リスト）、リンクのクォータ、ゲストリンク、スラッグ、監視の設定。安全性のパターンとしきい値は `hop/safety`（`config('safety')`）から来ます。 |
| `database/migrations/`、`database/migrations-archive/` | LinkHop 自身のテーブルと、アカウントのテーブルの履歴（現在はハブのもので、実行されません）。 |
| `lang/` | 9 言語のファイル。 |
| `tests/` | Pest と PHPUnit のテスト（`php artisan test`）、および `tests/e2e/` の疑似ユーザーによるブラウザーテスト（[実行方法](tests/e2e/README.md)）。 |
| `docs/` | [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/) のウェブサイト。`docs/readme/` に README のスクリーンショットがあり、`scripts/readme-images.mjs` で撮り直せます。 |
| `scripts/` | 補助スクリプト。`build-changelog.py`（アプリ内の変更履歴）、`check-hop-family.sh`（英語版 README のファミリー表）、`readme-images.mjs`。 |

すべての依存関係とそのライセンスは [CREDITS.md](CREDITS.md) にあります。

## コントリビュート

1. リポジトリをフォークしてブランチを作ります。
2. 変更を加え、`vendor/bin/pint` と `php artisan test` を実行します。
3. プッシュして `main` へのプルリクエストを開きます。

新しい動作にはテストを追加してください。セキュリティ上の問題は [SECURITY.md](SECURITY.md) の手順に従って非公開で報告し、それ以外は Issue テンプレートを使ってください。

## ライセンス

MIT。[LICENSE](LICENSE) を参照してください。

---

<p align="center">作者 <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">ウェブサイト</a></p>
