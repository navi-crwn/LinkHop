<p align="center">🌐 <b>English</b> · <a href="README.zh-CN.md">简体中文</a> · <a href="README.de.md">Deutsch</a> · <a href="README.id.md">Bahasa Indonesia</a> · <a href="README.ja.md">日本語</a> · <a href="README.pt-BR.md">Português (Brasil)</a> · <a href="README.es.md">Español</a> · <a href="README.fr.md">Français</a> · <a href="README.ru.md">Русский</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/home-dark.webp"><img src="docs/readme/home-light.webp" alt="The LinkHop home page: a box to paste a long link, the Shorten button, and the 3D island scene" width="100%"></picture></p>

<p align="center">
  <b>Short links, QR codes and click analytics.<br>Free, open source, and yours to host.</b>
</p>

<p align="center">
  <a href="https://link.hel.ink"><b>Try LinkHop</b></a> ·
  <a href="https://navi-crwn.github.io/LinkHop/">Website</a> ·
  <a href="#install">Install</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CREDITS.md">Credits</a> ·
  <a href="SECURITY.md">Security</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2 or newer"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS 4"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/LinkHop?style=flat-square&color=yellow" alt="GitHub stars"/>
</p>

LinkHop shortens links, counts the clicks, and makes a QR code for each link. It is the link shortener of the HOP family, and it runs live at [link.hel.ink](https://link.hel.ink). Short links open at `hel.ink/l/slug`.

Anyone can shorten a link without an account. A free HOP ID keeps your links and shows their statistics. HOP ID is the one account shared by all HOP apps, and it lives in the [hub](https://github.com/navi-crwn/HEL.ink) at `hel.ink` and `hub.hel.ink`. LinkHop has no sign-in, sign-up or profile pages of its own: the hub signs you in and sends you back with a short-lived ticket, and the apps share one database.

## HOP family

<!-- hop-family:start -->
<!-- Generated from HEL.ink resources/hop-assets/apps.source.json; edit there, then run scripts/hop_assets.py build. -->
| App | Address | Repository | What it does |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · accounts at `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | One account for every HOP app. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes and click analytics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | One link-in-bio page for everything you do. |
| **PixelHop** | [pixel.hel.ink](https://pixel.hel.ink) · `p.hel.ink/…` | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes you share with a link. |
| **Imperium** | private (owner only) | private | The HOP control panel. |
<!-- hop-family:end -->

Everything is included for every account, with no premium tier. The code is MIT licensed, so you can also run it on your own server and your own domain.

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/dashboard-dark.webp"><img src="docs/readme/dashboard-light.webp" alt="The LinkHop dashboard: clicks this week with a bar for each day, totals, a getting-started checklist, recent links and the top performer" width="100%"></picture></p>

Screenshots of signed-in pages show invented demo data, not a real account.

## Features

- Short links with a random or custom slug (3 to 255 characters), an optional password, an expiry date, and a QR code as PNG, SVG or JPG.
- Link limits: a start time, a maximum number of clicks, a fallback address for when the link has expired or run out of clicks, and a redirect type of 301, 302, 307 or 308.
- Routing: up to 20 rules that send visitors elsewhere by country, language, device, operating system or browser, and an A/B split of 2 to 5 addresses with weights. A campaign tags (UTM) builder fills in the `utm_*` parameters.
- Open in the app: on iOS and Android a link can try the installed app first, with the App Store or Google Play as the fallback.
- Analytics: clicks, unique visitors, countries and cities, devices, browsers, in-app browsers, referrers, and QR scans against direct clicks. The period is compared with the one before it. Per-click logs are kept for 30 days.
- Organising: folders, tags, search, bulk actions, comments on a link, CSV import of up to 500 rows, and a Ctrl or Cmd + K command palette. Settings has a CSV export of your links with their statistics.
- Link check at `/check`: paste a `hel.ink` link to see its destination before you open it. Nothing is clicked or counted.
- Destination monitor: once a day LinkHop asks each live destination whether it still answers, and emails the owner after three failed checks in a row. It can be switched off in Settings.
- Content warning: a link can be marked sensitive or adult, and visitors see a notice before they continue.
- Abuse protection: rate limits and quotas, a domain blacklist, IP bans, proxy and VPN checks on visitors, a report form, and an hourly scan for phishing, scams and other harmful links, with optional Google Safe Browsing and URLhaus lookups.
- API and webhooks: a REST API with scoped tokens and idempotency keys, signed webhooks for five link events, and a ShareX config.
- Nine languages: English, 简体中文, Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français and Русский. Add `?lang=de` to any page to switch; the choice is kept in a cookie.
- Privacy: no ads, no third-party trackers, and no scripts, fonts or images from a CDN. Libraries are bundled or live in `public/vendor`. The two remaining third-party hosts are Cloudflare Turnstile on the guest form and OpenStreetMap tiles under the analytics map.
- Self-hosting: one-command installer, update and uninstall scripts, nightly backups, and a signed admin API for the HOP control center.

Bio pages (`hel.ink/b/you`) are made in [BioHop](https://github.com/navi-crwn/BioHop), not in LinkHop. See [Bio pages](#bio-pages) for the one case where LinkHop still serves them.

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/analytics-dark.webp"><img src="docs/readme/analytics-light.webp" alt="The Analytics page: filters, clicks and unique visitors against the previous period, top country and device, and a clicks-over-time chart" width="100%"></picture></p>

<p align="center"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/readme/links-dark.webp"><img src="docs/readme/links-light.webp" alt="The My links page: a searchable list with folders, tags, a click count and a seven-day line for each link" width="100%"></picture></p>

## Where things live

| What | URL |
| --- | --- |
| Short link | `hel.ink/l/slug` |
| Old short link form | `hel.ink/slug`, which redirects to `hel.ink/l/slug` until 27 November 2026 (`LEGACY_SLUG_UNTIL`) |
| Bio page (BioHop) | `hel.ink/b/name` |
| Dashboard and API | `link.hel.ink`, with sign-in through the hub at `hub.hel.ink` |
| Your account | `hub.hel.ink`: profile, password, two-factor, API tokens, data download, deleting the account |

Short links moved from `hel.ink/slug` to `hel.ink/l/slug` on 28 October 2026, so that `hel.ink` can host several HOP apps. Nothing was deleted. The dashboard, QR codes, the API and the account export show the new form. On your own install the public host is `PUBLIC_URL` (it defaults to `APP_URL`), and `php artisan links:announce-migration` emails every link owner their links in the new form (`--reminder` sends the second email).

## Install

The installer checks your computer, asks a few questions, and can set up the HOP ID hub next to LinkHop:

```bash
curl -fsSL https://raw.githubusercontent.com/navi-crwn/LinkHop/main/install.sh | bash
```

From a clone, run `./install.sh`. To update, run `./update.sh`; to remove LinkHop, run `./uninstall.sh`. Add `--yes` to take every default, `--dry-run` to see the steps without running them, and `--help` for the other options.

- `install.sh` needs PHP 8.2 or newer (it uses `php8.3` when that is installed; set `PHP_BIN` to choose another), Composer, Node.js 20 or newer, and a database: SQLite or MySQL/MariaDB. It writes a private `.env` and a `start.sh` that runs the web server, the queue worker and the scheduler.
- `update.sh` copies the database to `backups/` first (SQLite or MySQL/MariaDB), puts the site in maintenance mode, pulls the new code, rebuilds, migrates, and puts the old version back if a step fails before the migrations. If it cannot make a database copy it stops; `--no-backup` continues without one.
- `uninstall.sh` removes the installed packages and built files and keeps your `.env`, database, uploads and logs. `--purge` also deletes those and asks you to type the site name first.

> [!NOTE]
> LinkHop keeps accounts in a HOP ID hub ([HEL.ink](https://github.com/navi-crwn/HEL.ink)); there is no hub-free mode. The installer can set up a hub next to LinkHop (the default, about a minute) or connect to one you already run. To install the hub and several apps in one go, use `install-family.sh` from the HEL.ink repository.

### Manual install

You need PHP 8.2 or newer with the usual Laravel extensions and `gd` or `imagick` (PNG QR codes), Composer, Node.js 20.19 or newer, and a database. The [hub](https://github.com/navi-crwn/HEL.ink) must run against the same database: it owns the account tables (`users`, `api_tokens`, and others) and signs people in.

```bash
# The shared packages hop/auth, hop/ui and hop/safety come from GitHub through Composer.
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate          # then edit .env, see below
php artisan migrate --force       # only this app's tables
php artisan storage:link
php artisan serve --port=8001
```

For local work set `APP_ENV=local`, `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001`, `HOP_HUB_URL=http://localhost:8000` (the hub on `php artisan serve`) and `SESSION_SECURE_COOKIE=false`, and at the hub `LINKHOP_URL=http://localhost:8001`. A guest who opens LinkHop is sent to the hub's sign-in and comes back signed in.

In production, keep two things running: a queue worker (`php artisan queue:work --tries=3`) and the scheduler (a cron line `* * * * * cd /path/to/linkhop && php artisan schedule:run`). The scheduler runs the abuse scan, the destination monitor, webhook retries, the privacy clean-up, guest link deletion and the backup.

**Database ownership.** Each HOP app runs only its own migrations against the shared database. LinkHop's are in `database/migrations`. The account tables' history is kept in `database/migrations-archive` and is not run any more; the tests build the schema from it. Do not run `migrate:fresh` against a database the hub uses.

<details>
<summary><b>Settings in <code>.env</code></b></summary>

The file `.env.example` lists everything. These are the settings you will most likely touch.

| Setting | Meaning |
| --- | --- |
| `APP_URL` | The dashboard and the API (`https://link.hel.ink` on the hosted service). |
| `PUBLIC_URL` | Where short links, QR codes and the sitemap are reached (`https://hel.ink`). On a single-host install, use the same value as `APP_URL`. |
| `HOP_APP`, `HOP_HUB_URL`, `HOP_SSO_SECRET` | The hub connection. `HOP_APP` is `linkhop`; the secret is the same one of at least 32 characters as in the hub's `.env`. Sign-in, Google login, two-factor and password reset are the hub's, so nothing else is needed here. |
| `DB_*` | The same database as the hub. |
| `MAIL_*` | Outgoing email: link-owner notices, destination monitor and backup alerts. |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Cloudflare Turnstile on the guest shortener and the report form. In production, without a secret key the guest form refuses every submission. |
| `GUEST_LINK_IDLE_DAYS`, `GUEST_LINK_MAX_DAYS` | Guest links are deleted after 14 days without a click, and after 30 days at most. The server can only make these shorter. |
| `LIMIT_*` | Quotas, from `config/limits.php`: for example 10 links a minute and 200 a day per address, 200 a day and 1000 active links per account. |
| `SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY` | Optional lookups for the abuse scan. |
| `GEOLITE_LICENSE_KEY` | Optional. Without it, `geolite:update` downloads the free DB-IP Lite databases. |
| `BIO_ENABLED`, `BIOHOP_URL`, `PIXELHOP_URL` | The bundled bio package and the other apps' addresses. |
| `HOP_CONTROL_URL` | Where `/admin` sends admins: the HOP control center. |
| `LEGACY_SLUG_UNTIL` | The date the old `hel.ink/slug` redirect stops. Leave it empty on a new install. |
| `TRUSTED_PROXIES` | The reverse proxy addresses, or `cloudflare`. |
| `SENTRY_LARAVEL_DSN`, `BACKUP_DISKS` | Error tracking, and the disks the backup is written to (default `local`). |

**Visitor location.** Country and city come from a local DB-IP Lite database that `geolite:update` refreshes weekly. When it has no answer for a public address, LinkHop asks up to two web lookup services: freeipapi.com, ip-api.com, ipapi.co, ipinfo.io or abstractapi.com. The visitor's address is stored only as a keyed hash.

**Backups.** The scheduler runs `backup:run` at 02:45 every day. The archive holds the database, `storage/app/public` and `.env`, and goes to the disks in `BACKUP_DISKS`. A MySQL or MariaDB backup needs `mysqldump` on the server. `.env` holds your secrets, so protect the backup disk or set `BACKUP_ARCHIVE_PASSWORD`.

**Behind Cloudflare.** LinkHop sends a Content Security Policy that trusts scripts carrying the page's nonce. Cloudflare's Email Address Obfuscation (Scrape Shield) and Rocket Loader (Speed, Optimization) inject their own scripts without that nonce, so browsers block them and the blocks are written to `storage/logs/csp-*.log`. Turn both off in the Cloudflare dashboard. LinkHop does not need them. Adding them to the policy would not work with a nonce policy, because `'strict-dynamic'` ignores host allowlists.

**Single host.** The hosted service splits LinkHop in two: the dashboard at `link.hel.ink` and the short links at `hel.ink/l/`. The nginx rules for that split are in the hub repository under `deploy/nginx/`. For a single host, set `APP_URL` and `PUBLIC_URL` to the same value.

</details>

## Using it

**Shorten a link.** Paste the long address, optionally pick a slug, a password or an expiry date, press Shorten and copy the result. Open Advanced on the home page for the guest options; after signing in, New link has all the options above.

**Bio pages.** Sign in at [bio.hel.ink](https://bio.hel.ink) with the same HOP ID, pick a name and share `hel.ink/b/yourname`.

## API

API tokens are made in the hub (HOP ID, Settings, API) and work in every HOP app. LinkHop reads them from the shared `api_tokens` table. A token carries scopes: `link:create`, `link:read` and `link:delete`. A token made before scopes existed has none and may do everything. The API answers at `link.hel.ink/api`, and at `hel.ink/api`, which forwards there until 27 November 2026. The rate limit is per token, 100 requests an hour unless the token sets its own.

Create a short link (`link:create`). BioHop makes this call when a bio block wants a short link.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Field | | |
| --- | --- | --- |
| `url` | required | A full `http://` or `https://` address, up to 2048 characters. Refused when the safety check flags it. |
| `alias` | optional | The slug: 3 to 255 characters from `A-Z a-z 0-9 _ -`. Reserved words and taken slugs are refused. Random when omitted. |
| `folder` | optional | A folder name on the account. It is created when it does not exist. |
| `password` | optional | 4 to 50 characters; visitors must type it. |
| `expires_at` | optional | An ISO date-time in the future. |
| `starts_at` | optional | A date-time before which visitors see "Not live yet". |
| `max_clicks` | optional | The link stops after this many clicks (1 to 10,000,000). |
| `fallback_url` | optional | Where visitors go after the expiry date or the click limit. It cannot be another short link. |
| `redirect_type` | optional | `301`, `302` (the default), `307` or `308`. |
| `content_warning`, `warning_reason` | optional | `none`, `sensitive` or `adult`, and a note of up to 255 characters shown to visitors. |
| `routing_rules`, `ab_variants`, `app_links` | optional | Routing by visitor, an A/B split, and deep links. The shapes are on the API docs page of the dashboard. |

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

Errors keep one shape, `{"success": false, "error": "…", "message": "…"}`. `401` is an unknown or expired token, `403` a missing scope or a suspended or unverified account, `422` a validation error (`messages` lists the fields), and `429` a quota or rate limit.

Send an `Idempotency-Key` header with `POST /api/shorten` to retry safely. The same key with the same body returns the first answer (`Idempotent-Replayed: true`) and creates nothing twice. The same key with another body is a `422`. Keys are kept for 24 hours.

Read a link (`link:read`) with `GET /api/links/{slug}`, which returns the same `data` plus `clicks`. Delete one (`link:delete`) with `DELETE /api/links/{slug}`. Both answer `404` for a slug that is not on the token's account.

Webhooks are set up in Settings: up to five https addresses, each called for `link.created`, `link.updated`, `link.deleted`, `link.limit_reached` and `link.expired`. A message carries `X-LinkHop-Signature`, an HMAC-SHA256 of the timestamp and the body. A failed delivery is tried again after 1 minute, 5 minutes, 30 minutes and 2 hours, and a webhook that fails ten deliveries in a row is switched off.

A ShareX config can be downloaded from the API docs page of the dashboard.

## FAQ

**Is it free?** Yes. There is no paid plan and no locked feature.

**Do I need an account?** No. Anyone can shorten a link. Guest links are deleted after 14 days without a click, and after 30 days at most. A free HOP ID keeps your links, shows their statistics, and signs you in to the other HOP apps.

**Where is my bio page?** In BioHop, at `hel.ink/b/` followed by the name you pick. Edit it at [bio.hel.ink](https://bio.hel.ink).

**What does a click record?** The time, country and city, device, browser and referrer, and a keyed hash of the address and browser that changes every day, which is used to count unique visitors. The address itself is not stored, and click logs are deleted after 30 days.

**How do I report a bad link?** Use the report form at `/report-abuse`. Reports go to the HOP control center's abuse queue, next to the findings of the automatic scan.

**Can I use my own domain?** Yes, by hosting your own copy next to the hub. `APP_URL` is the dashboard's host, `PUBLIC_URL` the host for short links, and `HOP_HUB_URL` the hub.

### Bio pages

On hel.ink, bio pages are BioHop's. A single-server install without BioHop can still serve them from the bundled `hop/bio` package (`packages/bio`) by setting `BIO_ENABLED=true`. With BioHop next to it, keep it `false`: it then registers no routes and needs no tables. `php artisan db:seed --class=ExampleBioSeeder` builds an example bio page and a few example short links on a development install.

## Under the hood

| Layer | Technologies |
| --- | --- |
| Backend | Laravel 12, PHP 8.2 or newer, SQLite or MySQL/MariaDB |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 7, GSAP, Lenis and Three.js for the home page |
| Charts and maps | Chart.js 4, Leaflet 1.9 with OpenStreetMap tiles |
| Accounts | HOP ID at the hub, through `hop/auth` (SSO client, and the `hop.auth` and `hop.token` middleware) |
| Shared packages | `hop/auth`, `hop/ui` (app switcher, fonts), `hop/safety` (URL and content safety), `hop/bio` (bundled in `packages/bio`) |
| Services | Cloudflare Turnstile, DB-IP Lite and optional web lookup services for visitor location, optional Google Safe Browsing and URLhaus, Sentry |

| Folder | What is in it |
| --- | --- |
| `app/`, `routes/`, `resources/` | The Laravel app: controllers, models, Blade views, scripts and styles. |
| `config/hop.php`, `config/limits.php`, `config/shortener.php` | The HOP family list (the same `apps` list as the hub), link quotas, and guest link, slug and monitor settings. Safety patterns and thresholds come from `hop/safety` (`config('safety')`). |
| `database/migrations/`, `database/migrations-archive/` | LinkHop's own tables, and the account tables' history (now the hub's, not run). |
| `lang/` | The nine language files. |
| `tests/` | Pest and PHPUnit tests (`php artisan test`), and the synthetic-user browser tests in `tests/e2e/` ([how to run them](tests/e2e/README.md)). |
| `docs/` | The website at [navi-crwn.github.io/LinkHop](https://navi-crwn.github.io/LinkHop/). `docs/readme/` holds the README screenshots; `scripts/readme-images.mjs` retakes them. |
| `scripts/` | Helpers: `build-changelog.py` (the in-app changelog), `check-hop-family.sh` (the family table above), and `readme-images.mjs`. |

Every dependency and its licence is listed in [CREDITS.md](CREDITS.md).

## Contributing

1. Fork the repository and create a branch.
2. Make your change, run `vendor/bin/pint` and `php artisan test`.
3. Push and open a pull request against `main`.

Please add tests for new behaviour. Report security problems privately, as described in [SECURITY.md](SECURITY.md), and use the issue templates for everything else.

## License

MIT. See [LICENSE](LICENSE).

---

<p align="center">Made by <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://link.hel.ink">LinkHop</a> · <a href="https://navi-crwn.github.io/LinkHop/">Website</a></p>
