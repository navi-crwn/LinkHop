# Changelog

All notable changes to LinkHop (hel.ink) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Everything since 2.0.0 (12 December 2025). The big steps are the split of the HOP family (accounts moved to the hub, short links moved to `hel.ink/l/`, the control panel moved to Imperium), the link features below, and the move to nine languages and one-command installers.

### Installers, backups and updates
- `install.sh`, `update.sh` and `uninstall.sh` at the repository root. The installer checks the computer, asks a few questions with defaults, shows a summary and waits for a yes. It can connect to an existing HOP ID hub or install the hub (HEL.ink) next to LinkHop. `--yes`, `--dry-run` and `--help` are supported, and it is safe to run again. After a good install it asks once whether to open the GitHub page; nothing is sent anywhere.
- `.env` is written atomically and is private (mode 0600) from the first byte. A plain `http` install runs as `local`, an `https` install as `production` with `LOG_LEVEL=warning`, and `TRUSTED_PROXIES=127.0.0.1` is set. PHP, Composer and artisan run through `PHP_BIN` (`php8.3` when installed).
- `update.sh` copies the database first (SQLite through `sqlite3 .backup` or `VACUUM INTO`, MySQL/MariaDB through `mysqldump --single-transaction` with the password in a private temporary file) into `backups/`. If no copy can be made it stops; `--no-backup` is the explicit opt-out. A failure before the migrations puts the previous code, packages and front end back; from the migrations on, the site stays in maintenance mode and the way back is printed.
- `uninstall.sh` removes only generated parts unless `--purge` is given, and then asks for the site name.
- The cache, jobs and admin-table migrations skip tables the hub already created, so LinkHop installs on a fresh shared database.
- `spatie/laravel-backup` runs daily (`backup:run` at 02:45, `backup:clean`, `backup:monitor`): database, `storage/app/public` and `.env`, to the disks in `BACKUP_DISKS`.

### Nine languages
- The app, the documentation site and the READMEs ship English, 简体中文 (zh-CN), Deutsch, Bahasa Indonesia, 日本語, Português (Brasil), Español, Français and Русский. German and Russian are new. Arabic and all right-to-left handling are gone, and pages are always left to right.
- Old codes keep working: `zh` becomes `zh-CN`, `pt` becomes `pt-BR`, and a stored `ar` falls back to English. The language comes from `?lang=` (kept in the `hop_lang` cookie), then the cookie; the browser's `Accept-Language` is ignored, so English stays the default. Language menus list the nine languages by their own names.
- Before this, the dedicated pages were translated into eight languages (the same set with Arabic and without German).

### Content warning
- A link can be marked `sensitive` or `adult`, with an optional note of up to 255 characters. `/l/{slug}` then shows a notice with a continue button (an 18+ confirmation for adult links), a back button, and an option to remember the choice for 24 hours on that device. The link check page shows a badge and the note.
- `POST /api/shorten` takes `content_warning` and `warning_reason` and returns them. The HOP control center can mark a link as sensitive or adult, and clear the mark, through the admin API. Guests find the setting under Advanced on the home page.

### Pages on link.hel.ink
- `/features`, `/about`, `/help`, `/faq`, `/contact`, `/terms`, `/privacy` and `/docs` (a public API reference) are LinkHop's own pages, with a header menu, a back link and a footer that lists the HOP family. `/status` checks the app, the database and the queue heartbeat. `/check` shows where a short link goes without clicking it.
- The terms and privacy pages say they work alongside the master HOP terms and privacy policy at `hel.ink`.
- `hel.ink/l` and `hel.ink/l/` redirect to the dashboard home. A `/l/{name}` that matches a hub page name and is not a real link redirects to that hub page (`302`, never cached, and the query string is not passed on); real links always win, and inactive or password-protected links are not exposed.

### Security and hardening
- The content security policy is one enforced header with a per-request nonce and `strict-dynamic`, `script-src-attr 'none'` and a report address (`/csp-report`, logged to `storage/logs/csp-*.log`). The earlier second, report-only header is gone.
- The hub's signed requests (`DELETE /internal/accounts/{id}`, the export, `POST /hop/events`) accept a previous secret during a rotation (`HOP_SSO_SECRET_PREV`).
- Purging an account works by id, also when the hub already deleted the `users` row: links, folders, tags, webhooks and their deliveries, idempotency keys, comments, clicks and, with bio on, bio pages and uploads are found through `user_id`. QR files and bio uploads listed in the hub's call are removed.
- The link quota is counted in the same transaction as the insert, in the dashboard, `POST /api/shorten`, `POST /api/shorten-web` and the CSV import. A cached allow or deny decision can no longer let a last slot be taken twice.
- The destination monitor resolves the host once per hop and pins the request to the checked addresses, so a host cannot change its answer between the safety check and the request. Webhook deliveries already worked this way.
- Lifting an IP ban takes effect on the next request (one cache key for the check and the admin API).
- `POST /api/shorten-web` returns the link's `custom_title` and `target_url` (it read columns that do not exist).
- The sign-out chain may reach every HOP subdomain (`form-action`), so signing out no longer stalls half way.
- Pages served from `hel.ink` link to files on the app host with absolute addresses and the policy allows that origin (`ASSET_URL`, default `APP_URL`). The content-warning gate and password form post to the page's own address, which avoids a 419. Open Graph data of other sites is resolved against the fetched page.
- Click logs keep only a keyed hash of the visitor address, and the owner never sees an address, city or provider of a single visit.
- `composer.lock` pins the rewritten history of `hop/ui`, `hop/auth` and `hop/safety`, and `hop/auth` is `^0.1.2`. `symfony/mime`, `follow-redirects` and other dependencies were updated.

### Hub integration
- `GET /internal/accounts/{id}/summary` (signed, counts only: links and clicks in the last 7 days) feeds the card on the hub dashboard.
- `GET /api/hop/x/analytics` gives the control center a global breakdown: countries, referrers, 30 days of clicks and top links, and it is listed in the manifest.

### Copy and documentation
- English copy edits: sentence case, plain wording, no emoji or exclamation marks in status text, and corrected help text (the QR preview needs only a slug). The documentation site is rendered from `docs/locales.ini` in the nine languages.
- READMEs in the nine languages, rewritten against the code, with screenshots of the current product in `docs/readme/` (retaken by `scripts/readme-images.mjs`). `SECURITY.md`, issue templates, and a CI check that the README keeps the canonical HOP family table.
- HOP family typography (Inter, Instrument Serif and JetBrains Mono) and the family list from the hub.

### Performance and tests
- An index on `link_clicks.clicked_at`. The 3D home scene and Three.js load as separate chunks on demand.
- A synthetic-user browser suite in `tests/e2e/` (model-based journeys, a check after every step, shrinking of failures) runs in CI next to the Pest suite, which also runs with bio switched off.

### Open in the app (deep links)
- A link can **try the installed app first** on iOS and Android. Choose "Open the app when the page belongs to a known app" (YouTube, Spotify, WhatsApp, Telegram, Instagram profiles, X profiles and posts, TikTok profiles, Zoom meetings, App Store and Google Play are recognised and the app address is built for you) or "Open the app address I give" (a URL scheme such as `myapp://item/42`, an https app link, or an Android `intent://...#Intent;...;end` address, separately for iOS and Android).
- A small page does the work: it opens the app, and sends the visitor on to the App Store or Google Play page when you gave one, otherwise to the destination, if the app does not take over. It has two buttons for the cases where a phone wants a tap, and nothing to download first.
- Desktops, crawlers and visitors on other systems keep the plain redirect. A visit still counts like any other; when a routing rule or the split chose another address, a typed app address is not used for it (a recognised one is).
- Addresses that could dial, write, read files, run script or hand over data (`tel:`, `sms:`, `mailto:`, `javascript:`, `file:`, `data:`, plain `http:`...) are refused, an https address goes through the same safety checks as the destination, an Android intent address has to name a package and may not name a component. Store pages have to be on the App Store or Google Play.
- Available in the create window, the edit page and the API (`app_links`); the link list shows an "App" chip.

### No scripts, styles or images from other hosts
- Chart.js, its zoom plugin, Leaflet, qr-code-styling and Alpine (for the editor preview) are served from `public/vendor` (pinned versions, licences and a README next to them) instead of jsdelivr and unpkg. The analytics map reads its country outlines from `public/vendor/geo` instead of raw.githubusercontent.com. The only other host a signed-in page contacts is OpenStreetMap, for the map's background tiles.
- Countries without a local flag file show their code instead of a picture from flagcdn.com; the unused Simple Icons URL helper and the editor's Simple Icons requests are gone (local brand icons are used).
- The error page no longer loads the Tailwind CDN. Bio pages no longer ask Google Fonts for a custom font (system fonts until fonts are self-hosted there, as in BioHop).
- The content security policy no longer names any CDN, font host or tracker host; the hosts for a bio page's own optional Google Analytics and Meta Pixel are added only when bio pages are switched on.

### Routing, A/B split and campaign tags
- A link can have up to **20 routing rules**: send visitors to another address by country, language (their first Accept-Language entry), device, operating system or browser. A rule can combine conditions (all must fit), the first rule that fits decides, and everyone else goes to the link's own address. The country is looked up only when a rule that gets that far asks for it.
- A link can have an **A/B split** of 2 to 5 addresses with weights. The visitor's keyed hash (no raw IP) picks the address, so a returning visitor keeps seeing the same one. Each click records which variant it went to, and the link's analytics and edit page show clicks per variant. A rule that fits comes before the split.
- Crawlers and link-preview bots always get the link's own address, never a rule or a variant. Every rule and variant address goes through the same safety checks as the main address and cannot be another short link.
- A **campaign tags (UTM) builder** with presets (newsletter, social networks, Google Ads, QR on print, ...) writes `utm_source`, `utm_medium`, `utm_campaign`, `utm_term` and `utm_content` into the destination field of the create form and the edit page. Presets live in the code; nothing else is stored.
- The API takes and returns `routing_rules` and `ab_variants`. My links marks a link with "N rules" or "A/B".

### Link insights
- **Unique visitors** next to total clicks, per link and for all links, and on the dashboard for the week. A visitor is a keyed hash of the address and browser that changes every day, so someone who comes back tomorrow counts again and nobody can be followed from day to day. The address itself is never stored.
- **QR scans against direct clicks.** Every QR code LinkHop makes for a short link (the stored image, the downloads and the preview in the create dialog) points at the `?src=qr` form, and the click records where it came from (`direct`, `qr` or `nfc`). The parameter is never passed on to the destination and survives the password step. QR codes made earlier keep working and count as direct clicks until they are downloaded again.
- **In-app browsers.** Visits opened inside Instagram, Facebook, Messenger, TikTok, LINE, WeChat, Snapchat, X, LinkedIn and others are named in a new tab of the Technology card.
- Link previewers (WhatsApp, Telegram, Slack, Discord, Facebook, X, LinkedIn, Skype and more) and HTTP tools such as curl are recognised as bots: they are not counted and do not use up a click limit.
- **Destination monitor.** Once a day LinkHop asks the destination of every live link whether it still answers (a polite HEAD, a GET if the server dislikes HEAD, short timeouts, redirects checked one by one, addresses on internal networks refused). After three failed checks in a row the link shows "Destination not responding" in My links, the dashboard and the link page, and the owner gets one email until it works again. It can be switched off in Settings, under Notifications.

### Link limits
- A link can **stop after a number of clicks**. The click is taken before the visitor is sent on, so exactly that many people get through even when many arrive at once; crawlers and link-preview bots are not counted and do not use clicks up.
- A link can have a **start time**: before it, visitors see "Not live yet" (HTTP 404, never cached).
- A link can have a **fallback address**, used when the expiry date has passed or the clicks have run out; without one, visitors see a notice (HTTP 410). The fallback cannot be another short link, so it cannot loop.
- The redirect type can be **308** (permanent, keeps the method).
- Making a second link to an address you already have asks you to confirm: the same form sent again makes it. The check ignores the case of the host, a trailing slash and a fragment.
- The API takes and returns `starts_at`, `max_clicks`, `fallback_url` and `redirect_type`. The admin lists show a scheduled link as "scheduled".
- My links and the dashboard show "Starts in …", "Limit reached" and how many of the allowed clicks are used.

### API and editor
- **Webhooks**: add up to five https addresses in Settings, Webhooks, and get a signed JSON message for `link.created`, `link.updated`, `link.deleted`, `link.limit_reached` (the last allowed click) and `link.expired`. Each message carries `X-LinkHop-Signature` (HMAC-SHA256 of the timestamp and the body with a secret that is shown once and stored encrypted), is tried again after 1 minute, 5 minutes, 30 minutes and 2 hours, and appears in a delivery log (the last 50 per webhook) with a test button. Only public https addresses are called: private, loopback, link-local and shared-address ranges are refused when the webhook is added and again at every delivery, the connection is pinned to the address that was checked, and redirects are not followed. A webhook that fails ten deliveries in a row is switched off.
- **Idempotency keys**: send `Idempotency-Key` with `POST /api/shorten`; a retry with the same key and body returns the first answer (`Idempotent-Replayed: true`) and creates nothing twice, the same key with another body is a 422, and keys are kept for 24 hours.
- **CSV import** (Links, Import CSV): upload up to 500 rows (destination, slug, tags, folder, expires_at, max_clicks), see a verdict for every row with the form's own rules, safety checks and your link allowance, duplicates reported, then confirm. A sample file can be downloaded; the file is read on the server only.
- **Command palette**: Ctrl or Cmd + K opens a search for pages, actions and your own links (Enter opens, Shift Enter edits). The slug is checked as you type in the new-link window and the edit page, pasting a destination shows the page title at once, and the copy buttons in the lists react immediately and say so when copying fails.

### Home
- The home page has a scene for each thing LinkHop does, with motion that follows the scroll: a long address loses its characters until the short link is left (pinned, with a custom slug you can type), a link preview card you edit live (title, description, picture), a QR code that tries colour pairs, an analytics view that draws itself, protect, organise, check, API and self-host terminals that type their command, FAQ cards, and the other four HOP apps as 3D marks you can turn. GSAP and Lenis ship in our own bundle; with reduced motion every scene is complete and still.
- The shared home engine is synced from HEL.ink assets v2: the LinkHop rabbit head, and every app mark in a row now assembles (one used to freeze or show the flat logo). Its files are requested with a content hash.

### Dashboard
- The dashboard home greets you by time of day, shows clicks this week against last week with a bar for each day, the totals, and a six-step list for getting more out of LinkHop. Every recent link and the top performer have a seven-day line.
- Analytics compares the period with the one before it and shows the busiest weekday and hour of the last 30 days. My links shows when a link ends and has a seven-day line per link.
- The account menu and the dashboard texts are translated in all nine languages.

### Fixed
- Searching My links used a column that does not exist on the links table (`title` instead of `custom_title`), which fails on MySQL.
- Top bars keep their buttons on one line on phones: the "Sign up" button no longer breaks onto two lines, the logo is no longer squeezed, and the link-check page bar fits a 320 px screen.
- The folder, tag, API and analytics pages no longer make the page wider than a phone.

### Dashboard chrome
- The top bar holds settings-type controls (primary action, language, theme and an account menu with your HOP ID, settings and sign out). The other HOP apps are listed once, in the sidebar, with their marks; the app pill and grid are gone from the top bar, and the sidebar's user card became the account menu.

### LinkHop becomes a HOP ID consumer (Fase C, 28 October 2026)
The accounts moved to the hub (`navi-crwn/HEL.ink`, hel.ink + hub.hel.ink); LinkHop keeps the links. `APP_URL` decides the host.
- **Sign-in through the hub.** `/login` starts the hub's SSO (`hop/auth`: `/auth/sso`, `/auth/sso/callback`, `/auth/sso/logout`); a verified ticket signs the same user in from the shared `users` table (`Auth::loginUsingId`). `/logout` ends the session here and goes on to the hub, which ends it in the other apps. Protected pages use the `hop.auth` middleware. A suspended account (`users.status`) is signed out and sent to the hub's notice
- **Removed:** registration, password sign-in and reset, Google sign-in, email verification, two-factor, Turnstile on auth forms, onboarding, the profile and security pages, account export and deletion, API token creation, the SSO issuer for PixelHop/NoteHop (`/sso/{app}`, `/api/pixelhop/*`, `/api/apps/{app}/*`), the HOP ID profile and user-admin API (`/api/hop/profile*`, `/users*`, `/stats`, `/audit`, `/search`, `/health`), and the hub-wide marketing pages and terms. The landing page and the guest shortener came back on `link.hel.ink` afterwards (`/` shows LinkHop's own home page to guests and the dashboard once signed in). `/profile` and the Settings page point at the hub
- **Hosts:** `APP_URL=https://link.hel.ink` is the dashboard and API, `PUBLIC_URL=https://hel.ink` the short links, old `/{slug}` redirect, `/b/*`, sitemap and QR codes. `/` on the dashboard host goes to the dashboard or the hub's sign-in. The sitemap lists only this app's public pages
- **Shared packages** from GitHub (`navi-crwn/hop-auth`, `hop-ui`, `hop-safety`, `dev-main`): `hop/ui` draws the app switcher (pill in the top bar, "HOP apps" in the sidebar, from `config/hop.php`, a copy of the hub's); `hop/safety` replaces the local `LinkSafety`, `UrlGuard`, `SafeContent`, `DomainBlacklist`, `abuse:feeds` and `config/abuse.php` (now `config('safety')`); `hop/auth` validates API tokens
- **API** stays at `/api/…` on this app: `POST /api/shorten` needs scope `link:create` (BioHop uses it), new `GET /api/links/{slug}` (`link:read`) and `DELETE /api/links/{slug}` (`link:delete`); a token without scopes keeps full access. Request and response documented in README, "API"
- **Hub-to-app endpoints** signed with the shared SSO secret (`hub.signed`, `X-Hop-Timestamp` + `X-Hop-Signature`): `GET /internal/accounts/{id}/export` (links, folders, tags) and `DELETE /internal/accounts/{id}` (purge this app's rows and files; the hub deletes the user)
- **Migrations:** the account tables (`users`, `login_histories`, `activity_logs`, `api_tokens`, `sessions`, `password_reset_tokens`, roles, username history) moved to `database/migrations-archive` and are not run any more; a new idempotent migration keeps the `apps` registry (Imperium's signed admin API) with LinkHop. Tests and the E2E server build the schema from the archive
- **E2E:** the suite signs personas in through `POST /_e2e/login` (E2E_HOOKS only) instead of the login form; the guest states are the link checker and bio pages
- Dropped dependencies: laravel/socialite, pragmarx/google2fa-laravel, propaganistas/laravel-disposable-email, laravel/breeze

### Short links moved to hel.ink/l/{slug} (28 October 2026)
hel.ink is becoming the home of several HOP apps (the hub, LinkHop, BioHop, PixelHop), so short links got their own path. This is the first step of that split, released on its own so the 30-day clock starts now.
- **Short links** are `hel.ink/l/{slug}` (new route, GET and POST for link passwords). The dashboard, link lists, QR codes, `short_url` in the API, the HOP admin API and the account export show the new form; the slug inputs say `hel.ink/l/`
- **The old form** `hel.ink/{slug}` answers with a 301 to `/l/{slug}` for a slug that is or was a link, and 404 otherwise, until `LEGACY_SLUG_UNTIL` (27 November 2026). After that date it is a 404 here too; at cutover the router stops forwarding it. The `hel.ink/{slug}` alias for bio pages is gone: bio pages are only at `hel.ink/b/{slug}`
- **`PUBLIC_URL`** (default `APP_URL`) is where the public reaches short links and bio pages; `public_url()` and `public_host()` build every public URL (short links, QR codes, `og:url`, the sitemap, the API). `url()` and `route()` keep pointing at `APP_URL`, so the dashboard can move to `link.hel.ink` later without touching links
- **Reserved slugs** no longer include every app route segment (there is no collision under `/l/`); `l`, `b` and `api` are always reserved, plus the configured words
- **Notice to users:** a banner on the dashboard with the date, the page `/changes/short-links` explaining what to do, and `php artisan links:announce-migration` which queues an email to every account that owns links listing them in the new form (`--reminder` on day 23, `--dry-run`, `--user=`). The Terms of Service gain section 11A: the service is free, changes to it are announced on the dashboard beforehand and must be followed
- **Session cookie** is `linkhop_session` (`SESSION_COOKIE`), so the HOP apps on `*.hel.ink` never share a cookie name
- **Link in Bio is the `hop/bio` package** in `packages/bio` (Composer path repository, namespace `Hop\Bio`): controllers, models, policy, support classes, views (`bio::`), migrations, themes config and tests. It reaches the app only through four contracts bound in `AppServiceProvider` (`ShortlinkCreator`, `UrlSafety`, `GeoLookup`, `UserAgentParser`). `BIO_ENABLED=false` switches it off: no `/bio` or `/b/` routes, no bio menu or quota, and `abuse:scan`, `privacy:prune`, the account export, account deletion, the sitemap, the link checker and the HOP admin API work without the bio tables (CI runs the suite both ways). `BIO_SLUG_MIN_LENGTH` moved from `shortener` to `bio.slug_min_length`; the generic QR endpoint `/api/qr-generate` is `QrController` in the app
- The link checker understands `hel.ink/l/slug`, the old form and the public host
- The plan behind this: `docs-planning/PLAN-pemisahan-aplikasi.md`

### Admin API for the HOP control center
- Content side of the signed `/api/hop/*` API for Imperium, for an app registered with the `admin_client` capability (anyone else gets `403`): `/manifest`, `/content` (links and bio pages: list, detail, disable, enable, unpublish, publish, delete, transfer, rescan, mark and unmark as sensitive or adult; the owner's content is protected), `/reports` (people's reports and scanner findings in one queue with investigate, close, confirm and restore), `/x/ip` (bans with expiry, watchlist), `/x/domains` (blacklist), `/x/queue` (worker heartbeat, backlog, last abuse scan), `/settings` (site and SEO editable, `.env` facts read-only), and `/stats`, `/health`. Every change is written to `hop_audit`.
- The built-in admin panel is retired. The `/admin/*` pages, their controllers and views are gone; `GET /admin` sends an admin to `HOP_CONTROL_URL` or, when that is not set, shows a page saying where the panel went. Admins get the normal dashboard with a banner and one sidebar item (`HOP_CONTROL_NAME`, default "Control center"). The scanner (`abuse:scan`), IP bans, the watchlist, the domain blacklist, SEO settings and `queue:heartbeat` keep working underneath.
- `hop:bootstrap` registers Imperium as a hidden app from `IMPERIUM_URL` and `IMPERIUM_SSO_SECRET`, so it signs in through HOP ID and is allowed as a CSP `form-action` origin but never shows in the sidebar.
- Apps that declare the `events` capability are told about profile changes, status changes and account deletion (`POST /hop/events`, signed, retried with backoff).

### Moved to the hub
For a few days (27 and 28 October 2026) LinkHop was the HOP ID account server: it gained an app registry, roles, one profile for every app, SSO ticket v2, the signed HOP App Protocol, user administration ("User 360") and HOP-styled sign-in, sign-up and password-reset pages and mails. That work now lives in the hub (`navi-crwn/HEL.ink`); see the Fase C section above for what LinkHop kept and dropped.

### Fixed
- A short link or bio slug can no longer be the first part of an app route (`analytics`, `api-docs`, `goodbye`, `up`, ...): the route always won, so such a link never opened. The list is read from the router, so new routes are covered automatically; matching ignores case. Links that already have such a slug can still be edited
- The example short link for the API reference is now `hel.ink/documentation`; `hel.ink/api-docs` is the app's API page for members. `ExampleBioSeeder` removes its old `api-docs` link only when the example owner still has it pointing at the docs
- Logout no longer shows "419 Page Expired" when the page was left open past the session lifetime: the user is signed out anyway. Other forms with an expired token go back with their input (never passwords) and a short message; JSON requests still get a 419 JSON reply. Open tabs fetch a fresh token from `/csrf-token` when shown again
- The inactivity logout now uses `SESSION_LIFETIME` (default 120 minutes) instead of a separate 60-minute setting, and actually fires (it compared times the wrong way round). `SESSION_INACTIVITY_LOGOUT` is gone
- Admin bio page: text, divider, embed, FAQ and contact-card blocks no longer show as blank rows
- Public bio pages: header, FAQ and contact-card text follows the theme color, so it stays readable on dark themes

### Changed
- Tailwind CSS 3 → 4 (`@tailwindcss/vite`, CSS-first config in `resources/css/app.css`; `tailwind.config.js`, `postcss.config.js`, `autoprefixer` and `postcss` removed). The v3 look is kept: v3 border color, button cursor and `space-x/y` rule are put back, and the component classes keep their v3 order. `npm audit` is clean and CI now runs it in full. `scripts/ui-screenshots.mjs` compares the main pages before and after a styling change
- `storage/framework/disposable_domains.json` (written by `disposable:update`) is git-ignored
- README: Cloudflare Email Obfuscation and Rocket Loader inject scripts without the nonce and are blocked by the policy; the README says to turn them off
- Guest links are deleted at 00:00 (in `GUEST_LINK_TIMEZONE`) after 14 full days in a row without a click, and after day 30 at most; the server `.env` can no longer stretch the idle limit past 14 days. `links:prune-guest` now runs at midnight
- Home page: the Cloudflare security check sits to the right of the Shorten button on wide screens and only takes room when Cloudflare asks for a click

### Added
- `ExampleBioSeeder` builds the example bio page (`/b/hel`) and short links used on the website
- Slugs of deleted guest links stay blocked for 90 days (`RETIRED_SLUG_DAYS`) and show a "Link Expired" page (HTTP 410), so nobody can take over a link that is still shared somewhere

### Security
- `npm audit fix` (no `--force`): 21 advisories (2 critical) down to 7, all from Tailwind CSS 3 build tooling; axios 1.20, vite 7.3 and concurrently 9.2.5 within their ranges. CI now fails on critical npm advisories
- Bio shortlink endpoints (`/api/bio/shorten`, `/api/shorten-web`) now enforce the domain blacklist, quota and rate limits
- Bio blocks reject `javascript:`/`data:` URLs and blocked domains; public bio pages sanitize HTML/text blocks, custom CSS and background styles
- Users can no longer edit or delete bio blocks that belong to another page (scoped route bindings)
- Registration no longer derives a role from the email domain, rejects disposable emails and is rate limited per IP
- Dependencies updated within their constraints (Laravel 12.70); `composer audit` is clean

### Added
- Project website on GitHub Pages (`docs/`, light and dark, now in nine languages) and an Indonesian README (`README.id.md`, now one of nine)
- `abuse:scan` command, scheduled hourly (new/edited items) and nightly (full sweep + purge): blacklist, keyword categories (CSAM, gambling, phishing, scam, adult), risky URL traits and optional Google Safe Browsing / URLhaus lookups (`SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY`)
- Automated findings in an abuse queue with Confirm and Restore (now in the HOP control center). Patterns, weights and thresholds moved from `config/abuse.php` to `hop/safety`

### Fixed
- Bio editor: the URL field disappeared after once choosing "Select existing link"
- Bio editor: the URL field was rewritten while typing
- Bio editor: smooth drag & drop (no re-render or Sortable re-init per drop; SortableJS bundled instead of loaded twice from CDN)
- Bio editor: visibility toggles in the block list were not saved
- Bio editor: requests broke after renaming the page slug
- Bio link clicks were never recorded (public links bypassed the click tracker); disabled blocks no longer redirect
- Bio click tracking crashed (missing `jenssegers/agent` dependency)
- Fresh installs failed on the early `2024_12_05` bio migrations
- Login of `@superadmin.com` accounts errored on a non-existent route

### Removed
- Unused legacy bio editor views and the `/test-modal` debug route

## [2.0.0] - 2025-12-12

### Added
- **Link in Bio** - Create beautiful bio pages at hel.ink/b/username
  - 20+ themes with dark/light variants
  - Background animations (rain, snow, stars, confetti, fireflies)
  - Hover effects (glow, scale, tilt, shake)
  - Entrance animations (fade, slide, pop, bounce)
  - Attention animations (pulse, bounce, glow, shake, heartbeat)
  - Custom fonts (Inter, Poppins, Roboto, Playfair, Space Grotesk, JetBrains Mono)
  - Social media icons with multiple positions
  - Block types: links, text, video embeds, Spotify, maps, countdowns, dividers
  - Password protection for bio pages
  - QR code generation for bio pages
  - Analytics tracking (views, clicks, referrers, devices, countries)
- **Brand Icons** - 150+ SVG brand icons from Simple Icons
- **Animation System** - Full animation support for bio page elements
- **Admin Bio Dashboard** - Monitor and manage all bio pages

### Changed
- Rebranded from "Hop Easy Link" to "HEL.ink"
- Updated all marketing pages with casual professional tone
- Improved README with collapsible sections
- Cleaned up codebase (removed debug logs, redundant comments)
- Updated footer and navigation branding

### Fixed
- Fixed broken brand icons in bio pages
- Fixed "Explore Features" link pointing to non-existent route
- Fixed emdash usage across marketing pages
- Fixed inconsistent branding across error pages

### Security
- Added Content Security Policy headers
- Improved rate limiting on API endpoints
- Enhanced abuse reporting system

## [1.0.0] - 2025-11-18

### Added
- Initial release
- URL shortening with custom slugs
- Password protection for links
- Link expiration with custom dates
- Folder organization system
- Tag-based categorization
- QR code generation (PNG/SVG)
- Click analytics with geographic data
- Device and browser tracking
- Referrer tracking
- Admin dashboard
- User management
- IP ban system
- Domain blacklist
- Abuse reporting
- Google OAuth integration
- Two-factor authentication
- API with token authentication
- Cloudflare Turnstile integration
- Email verification system

---

## Migration Notes

### Upgrading from 2.0.0 to the next release
1. LinkHop needs a HOP ID hub ([HEL.ink](https://github.com/navi-crwn/HEL.ink)) on the same database. Set `HOP_APP=linkhop`, `HOP_HUB_URL` and `HOP_SSO_SECRET` (the hub's secret, at least 32 characters), `APP_URL` for the dashboard and `PUBLIC_URL` for short links. Remove `GOOGLE_*`; Turnstile keys stay for the guest form.
2. Run `composer install --no-dev -o` and `npm ci && npm run build`; Composer now fetches `hop/auth`, `hop/ui` and `hop/safety` from GitHub.
3. Run `php artisan migrate --force`. Only LinkHop's own tables are migrated; the account tables belong to the hub.
4. Run a queue worker and the scheduler (`* * * * * php artisan schedule:run`). On a self-hosted install, `update.sh` does steps 2 and 3 and copies the database first.
5. Short links open at `hel.ink/l/{slug}`; the old `hel.ink/{slug}` form redirects until `LEGACY_SLUG_UNTIL` (27 November 2026). `php artisan links:announce-migration` emails link owners.

### Upgrading to 2.0.0
1. Run database migrations: `php artisan migrate`
2. Clear caches: `php artisan optimize:clear`
3. Rebuild assets: `npm run build`
4. Update environment variables if needed

### New Environment Variables (2.0.0)
No new required environment variables. Bio pages use existing database.
