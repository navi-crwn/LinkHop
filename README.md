<p align="center"><b>English</b> · <a href="README.id.md">Bahasa Indonesia</a></p>

<p align="center"><picture><source media="(prefers-color-scheme: light)" srcset="docs/readme/hero-en-light.webp"><img src="docs/readme/hero-en-dark.webp" alt="LinkHop: short links, QR codes and click analytics" width="100%"></picture></p>

<p align="center">
  <b>Shorten links, count every click, and analyze your audience.<br>Free, open source, and yours to host.</b>
</p>

<p align="center">
  <a href="https://hel.ink"><b>🔗 Try hel.ink</b></a> ·
  <a href="https://navi-crwn.github.io/hel.ink/">Website</a> ·
  <a href="https://hel.ink/b/hel">Bio example</a> ·
  <a href="#install">Install</a> ·
  <a href="#api">API</a> ·
  <a href="#faq">FAQ</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CREDITS.md">Credits</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 12"/>
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.2+"/>
  <img src="https://img.shields.io/badge/Tailwind-4.x-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind CSS"/>
  <img src="https://img.shields.io/badge/License-MIT-green?style=flat-square" alt="MIT License"/>
  <img src="https://img.shields.io/github/stars/navi-crwn/hel.ink?style=flat-square&color=yellow" alt="Stars"/>
</p>

---

## What is LinkHop?

**LinkHop** is the link shortener of the HOP family ("Hop Easy Link"). The family shares one account, **HOP ID**, kept by the [hub](https://github.com/navi-crwn/HEL.ink) at `hel.ink` / `hub.hel.ink`; LinkHop's dashboard lives at `link.hel.ink`, short links stay at `hel.ink/l/slug`, and the hub's app switcher takes you to [BioHop](https://github.com/navi-crwn/BioHop) (link in bio), [PixelHop](https://github.com/navi-crwn/PixelHop) (image hosting), and [NoteHop](https://github.com/navi-crwn/NoteHop) (encrypted notes). LinkHop has no sign-in, sign-up or profile pages of its own: the hub signs you in with a short-lived SSO ticket, and the apps share one database.

Long links are hard to share and you never know who clicks them. LinkHop makes them **short** (with your own slug if you like), **counts every click** by country, device and referrer, and protects visitors with automated URL threat detection. Personal landing pages are now powered by [BioHop](https://github.com/navi-crwn/BioHop) at `hel.ink/b/you`.

### HOP Family Ecosystem

<!-- hop-family:start -->
<!-- Generated from HEL.ink resources/hop-assets/apps.source.json; edit there, then run scripts/hop_assets.py build. -->
| App | Address | Repository | What it does |
| --- | --- | --- | --- |
| **HOP ID** | [hel.ink](https://hel.ink) · accounts at `hub.hel.ink` | [navi-crwn/HEL.ink](https://github.com/navi-crwn/HEL.ink) | One account for every HOP app. |
| **LinkHop** | [link.hel.ink](https://link.hel.ink) · `hel.ink/l/…` | [navi-crwn/LinkHop](https://github.com/navi-crwn/LinkHop) | Short links, QR codes and click analytics. |
| **BioHop** | [bio.hel.ink](https://bio.hel.ink) · `hel.ink/b/…` | [navi-crwn/BioHop](https://github.com/navi-crwn/BioHop) | One link-in-bio page for everything you do. |
| **PixelHop** | [p.hel.ink](https://p.hel.ink) | [navi-crwn/PixelHop](https://github.com/navi-crwn/PixelHop) | Image hosting with albums and direct links. |
| **NoteHop** | [note.hel.ink](https://note.hel.ink) · `n.hel.ink/…` | [navi-crwn/NoteHop](https://github.com/navi-crwn/NoteHop) | Encrypted notes you share with a link. |
| **Imperium** | private (owner only) | private | The HOP control panel. |
<!-- hop-family:end -->

Everything is included for every account. No premium tier, no locked features. And because it's open source, you can also run the whole thing on your own server and your own domain.

**Good for you if you:**
- share links on social media and want to see what works,
- want a link shortener you control, on your own server.

<p align="center"><picture><source media="(prefers-color-scheme: light)" srcset="docs/readme/features-en-light.webp"><img src="docs/readme/features-en-dark.webp" alt="Everything LinkHop can do" width="100%"></picture></p>

Looking for one page for all your links? That is [BioHop](https://github.com/navi-crwn/BioHop), LinkHop's sister app at [bio.hel.ink](https://bio.hel.ink), on the same HOP ID.

📖 **The full tour, the self-host guide and the FAQ:** [navi-crwn.github.io/hel.ink](https://navi-crwn.github.io/hel.ink/)

## Example

<img src="docs/readme/example-bio.webp" alt="The example bio page at hel.ink/b/hel" width="280" align="right">

**Bio page:** [hel.ink/b/hel](https://hel.ink/b/hel), made with [BioHop](https://bio.hel.ink): the Galaxy theme, link buttons with brand icons, a text block, an FAQ and a contact card.

**Short links:**

| Short link | Goes to |
| --- | --- |
| [hel.ink/l/github](https://hel.ink/l/github) | The source code |
| [hel.ink/l/website](https://hel.ink/l/website) | The website |
| [hel.ink/l/selfhost](https://hel.ink/l/selfhost) | The self-host guide |
| [hel.ink/l/documentation](https://hel.ink/l/documentation) | The API reference |
| [hel.ink/l/changelog](https://hel.ink/l/changelog) | What changed in each release |

To load the same example on your own install, run `php artisan db:seed --class=ExampleBioSeeder`.

<br clear="right">

## Features

- **🔗 Short links.** Random or custom slugs, an optional password and expiry date, and a QR code (PNG, SVG or JPG) for every link.
- **🙋 No account needed.** Anyone can shorten a link. Guest links are deleted after 14 days without a click, and after 30 days at most; sign up to keep them and see their stats.
- **🪪 Bio pages live in BioHop.** On hel.ink, pages at `hel.ink/b/you` (blocks, 20 themes, animations, brand icons, private pages) are made in [BioHop](https://github.com/navi-crwn/BioHop). A single-server install can still turn on the bundled bio package (`BIO_ENABLED=true`, see the FAQ).
- **📊 Analytics.** Clicks, unique visitors, countries and cities, devices, browsers and referrers, in real time, with CSV export.
- **🗂️ Folders & tags.** Sort, tag and search your links, act on many at once, and leave comments on a link.
- **🔌 API & ShareX.** A REST API with Bearer tokens, plus a ready-made ShareX config in Settings → API.
- **🔐 Safe sign-in.** Email and password or Google login, two-factor codes (TOTP) and a login history.
- **🛡️ Abuse protection.** Rate limits and quotas, a domain blacklist, proxy/VPN checks, a report form, and an hourly scan for phishing, scams and other harmful links, with optional Google Safe Browsing and URLhaus lookups.
- **🏛️ HOP control center.** Moderation and settings (users, links, bio pages, abuse reports, IP bans, the domain blacklist, SEO, the job queue) live in Imperium, the HOP control center, through the signed admin API (`/api/hop/*`); `/admin` sends admins there (`HOP_CONTROL_URL`). Self-hosters without it can call the API directly or use the artisan commands (`abuse:scan`, `hop:bootstrap`, `queue:heartbeat`, …).
- **🏠 Self-hostable.** Laravel 12 on your own server with MySQL, PostgreSQL or SQLite.

## Install

**You need:** PHP 8.3+, Composer, Node.js 18+, a database (MySQL 8.0+, MariaDB or SQLite) and the [hub](https://github.com/navi-crwn/HEL.ink) running against the same database: the hub owns the accounts (`users`, `api_tokens`, …) and signs people in here. Redis is optional, for caching.

```bash
# 1. Get the code (the shared packages hop/auth, hop/ui and hop/safety come from GitHub through Composer)
git clone https://github.com/navi-crwn/LinkHop.git
cd LinkHop
composer install && npm install

# 2. Set it up: the same DB_* and HOP_SSO_SECRET as the hub's .env, HOP_HUB_URL pointing at the hub
cp .env.example .env
php artisan key:generate
php artisan migrate          # only this app's tables; the hub has already made the account tables
php artisan storage:link

# 3. Build and run
npm run build
php artisan serve --port=8001
```

Then open `http://localhost:8001`: a guest is sent to the hub's sign-in and comes back signed in. For local work set `APP_URL=http://localhost:8001`, `PUBLIC_URL=http://localhost:8001` and `HOP_HUB_URL=http://localhost:8000` (the hub on `php artisan serve`), and at the hub `LINKHOP_URL=http://localhost:8001`.

**Database ownership.** Every HOP app runs only its own migrations against the shared database. LinkHop's are in `database/migrations` (links, clicks, folders, tags, comments, abuse, IP bans, the domain blacklist, the app registry); the account tables' history is kept in `database/migrations-archive` and is not run any more (the tests build the schema from it). Do not point `migrate:fresh` at a database the hub uses.

<details>
<summary><b>Settings in <code>.env</code></b></summary>

**Database**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=helink
DB_USERNAME=your_user
DB_PASSWORD=your_pass
```

**Email**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-app-password
```

**HOP ID (the hub)**
```env
HOP_APP=linkhop
HOP_HUB_URL=https://hub.hel.ink
HOP_SSO_SECRET=the-same-32-plus-character-secret-as-the-hub
```
Google sign-in, two-factor and password reset are the hub's; there is nothing to configure for them here.

**Your own domains:** `APP_URL` is the dashboard and the API (`link.hel.ink`); `PUBLIC_URL` is where short links, QR codes, the sitemap and bio pages are reached (`hel.ink`). On a single-host install set both to the same value.

**Behind Cloudflare:** LinkHop sends a strict, report-only Content Security Policy that only trusts scripts carrying the page's nonce, and logs what it would block to `storage/logs/csp-*.log`. Cloudflare's **Email Address Obfuscation** (Scrape Shield) and **Rocket Loader** (Speed → Optimization) inject their own scripts from `/cdn-cgi/scripts/.../cloudflare-static/` without that nonce, so they fill the log. Either turn them off in the Cloudflare dashboard (recommended: LinkHop doesn't need them, and it keeps the policy ready to enforce), or skip them only where needed with a Cloudflare Configuration Rule. Allowing them in the policy doesn't work with a nonce policy (`'strict-dynamic'` ignores host and `'self'` allowlists); it would mean dropping the nonce, which weakens the policy for everything else.

**Production:** keep a queue worker running.
```bash
php artisan queue:work
```

</details>

<details>
<summary><b>Cutover: moving this app from hel.ink to link.hel.ink</b></summary>

LinkHop keeps running at `hel.ink` with `APP_URL=https://hel.ink` until every HOP app is ready; the switch is one window for all of them (the plan: `docs/planning/PLAN-pemisahan-aplikasi.md` in the hub repo). What this app needs on that day, in order:

1. The hub is live at `hub.hel.ink` with the same database and `HOP_SSO_SECRET`, and lists this app (`LINKHOP_URL=https://link.hel.ink`).
2. In this app's `.env`: `APP_URL=https://link.hel.ink`, `PUBLIC_URL=https://hel.ink`, `HOP_APP=linkhop`, `HOP_HUB_URL=https://hub.hel.ink`, `HOP_SSO_SECRET=…`, `BIOHOP_URL`, `PIXELHOP_URL`. Remove `GOOGLE_*`; Turnstile keys may stay (abuse report form). `BIO_ENABLED=false` once BioHop serves `/b/*`.
3. Code and dependencies: `git pull`, then `/usr/bin/php8.3 /usr/local/bin/composer install --no-dev -o` (the server's default `php` is 8.5 and lacks `zip`), `npm ci && npm run build`.
4. `php artisan migrate --force` (only LinkHop tables; the `apps` migration is a no-op where the table exists).
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`, then `systemctl restart queue-worker` so the worker picks up the new code and `.env`.
6. nginx: `link.hel.ink` → this app; on `hel.ink` only `/l/*`, `/{slug}` (301 to `/l/`, until 27 November 2026), `/b/*` (BioHop) and `/api/*` (308 to `link.hel.ink/api`) reach the apps, everything else is the hub (the hub repo has the full config in `deploy/nginx/`).
7. Check: `https://link.hel.ink/up`, `https://link.hel.ink/` sends a guest to the hub and back signed in, `https://hel.ink/l/<slug>` redirects, `curl -H "Authorization: Bearer …" https://link.hel.ink/api/links/<slug>` answers, the sidebar's app switcher opens BioHop and PixelHop.
8. Rollback: the previous release with `APP_URL=https://hel.ink` and the old nginx config; the database needs nothing (the hub's migrations only added tables and columns).

</details>

## How to use it

**Shorten a link**
1. Paste your long URL.
2. Optional: pick a custom slug, a password or an expiry date.
3. Press **Shorten** and copy your link.

**Where your links live (URLs)**

| What | URL |
| --- | --- |
| Short link | `hel.ink/l/slug` |
| Bio page (BioHop) | `hel.ink/b/name` |
| Old short link form | `hel.ink/slug`: redirects to `hel.ink/l/slug` until **27 November 2026**, then stops |
| Dashboard and API | `link.hel.ink` (sign in through the hub, `hub.hel.ink`) |
| Your account | `hub.hel.ink`: profile, password, two-factor, API tokens, data download, deleting the account |

hel.ink is becoming the home of several HOP apps, so short links got their own path on 28 October 2026. Nothing was deleted; the dashboard, QR codes, the API and the account export show the new form. On your own install the public host is `PUBLIC_URL` (default `APP_URL`), the date is `LEGACY_SLUG_UNTIL`, and `php artisan links:announce-migration` mails every link owner their links in the new form (`--reminder` for the second mail).

**Make a bio page**
Bio pages are made in BioHop: sign in at [bio.hel.ink](https://bio.hel.ink) with the same HOP ID, pick a name and share `hel.ink/b/yourname`.

## API

API tokens are made at the hub (**HOP ID → Settings → API**) and work in every HOP app; this app reads them from the shared `api_tokens` table through `hop/auth`. A token carries **scopes**: `link:create`, `link:read`, `link:delete` (a token made before scopes existed has none and may do everything). The API answers at `link.hel.ink/api` (and at `hel.ink/api`, which forwards there until 27 November 2026). The rate limit is per token (100 requests an hour by default).

**Create a short link** (`link:create`). This is the call BioHop makes when a bio block wants a short link.

```bash
curl -X POST https://link.hel.ink/api/shorten \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com", "alias": "my-link"}'
```

| Field | | |
| --- | --- | --- |
| `url` | required | The destination (`https://` is added when the scheme is missing). Refused when the safety check flags it. |
| `alias` | optional | The slug, 3–255 characters of `A-Z a-z 0-9 _ -`; reserved words and taken slugs are refused. Random when omitted. |
| `folder` | optional | Folder name on the account; created when it does not exist. |
| `expires_at` | optional | ISO date-time in the future. |
| `password` | optional | 4–50 characters; visitors must type it. |

```json
HTTP 201
{"success": true, "message": "Link created successfully",
 "data": {"url": "https://hel.ink/l/my-link", "slug": "my-link", "target_url": "https://example.com",
          "status": "active", "created_at": "2026-10-28T10:00:00+00:00", "expires_at": null}}
```

Errors keep one shape, `{"success": false, "error": "…", "message": "…"}`: `401` unknown or expired token, `403` missing scope, suspended or unverified account, `422` validation (`messages` lists the fields), `429` quota or rate limit.

**Read a link** (`link:read`): `GET /api/links/{slug}` → the same `data` plus `clicks`. **Delete a link** (`link:delete`): `DELETE /api/links/{slug}` → `{"success": true}`. Both answer `404` for a slug that is not on the token's account.

A ShareX config is ready to download on the **API docs** page of the dashboard.

## FAQ

**Is it really free?**
Yes. There's no paid plan and no locked feature, and the code is MIT licensed, so you can run, change and share it.

**Do I need an account?**
Yes, a HOP ID (free, made at the hub); it also signs you in to the other HOP apps. Guest links made on the old home page still work and are deleted after 14 days without a click, and after 30 days at most.

**Where is my bio page?**
In BioHop, at `hel.ink/b/` followed by the name you pick, for example [hel.ink/b/hel](https://hel.ink/b/hel). Edit it at [bio.hel.ink](https://bio.hel.ink).

**Can I use my own domain?**
Yes, by hosting your own copy next to the hub: `APP_URL` is the dashboard's host, `PUBLIC_URL` the public one for short links, and `HOP_HUB_URL` the hub.

**Does LinkHop still include bio pages?**
Only for a single-server install without BioHop: `BIO_ENABLED=true` in `.env` turns on the bundled `hop/bio` package (`packages/bio`). With BioHop next to it, as on hel.ink, keep it `false`; off, it registers no routes and needs no tables.

**What does a click record?**
The time, country and city, device, browser and referrer.

**How do I report a bad link?**
Use the report form (`/report-abuse`). Reports land in the HOP control center's abuse queue, next to the findings of the automatic scan.

## Under the hood

| Layer | Technologies |
|---|---|
| **Backend** | Laravel 12, PHP 8.2+, MySQL / PostgreSQL / SQLite |
| **Frontend** | Tailwind CSS 4, Alpine.js 3, Vite 7 |
| **Charts & maps** | Chart.js 4, Leaflet 1.9 |
| **Icons** | Heroicons, Simple Icons (150+ brands) |
| **Accounts** | HOP ID at the hub; `hop/auth` (SSO client, `hop.auth` and `hop.token` middleware) |
| **Shared packages** | `hop/auth`, `hop/ui` (app switcher), `hop/safety` (URL and content safety), `hop/bio` |
| **Services** | Cloudflare Turnstile (abuse report form), IP-API, FlagCDN |

| Folder | What's in it |
|---|---|
| `app/`, `routes/`, `resources/` | The Laravel app: controllers, models, Blade views and the bio editor. |
| `config/bio-themes.php` | The bio page themes. |
| `config/hop.php`, `config/limits.php` | The HOP family (same `apps` list as the hub) and link quotas. Safety patterns and thresholds come from `hop/safety` (`config('safety')`). |
| `database/migrations-archive/` | The account tables' history (now the hub's); not run. |
| `tests/` | PHPUnit tests (`php artisan test`) and the Playwright synthetic users in `tests/e2e/`. |
| `docs/` | The website (GitHub Pages). `docs/readme/` holds the README images, drawn from the website with `node scripts/readme-images.mjs`. |

Full dependency list: [CREDITS.md](CREDITS.md).

## Contributing

1. Fork the repo.
2. Create a branch (`git checkout -b feature/cool-thing`).
3. Commit your changes and run `php artisan test`.
4. Push and open a pull request.

Please follow PSR-12 and add tests for new features.

---

<p align="center">MIT License · Made by <a href="https://github.com/navi-crwn">Ivan Novskies (@navi-crwn)</a> · <a href="https://hel.ink">hel.ink</a> · <a href="https://navi-crwn.github.io/hel.ink/">Website</a></p>
