# Changelog

All notable changes to LinkHop (hel.ink) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixes
- The report form and the password and age pages (a link's and a bio page's) follow the visitor's language, `lang` attribute included.
- A destination with a login in it (`https://paypal.com@evil.example/`) or on a private address (loopback, private and link-local ranges, cloud metadata, `localhost`, `*.local` and the like, also written as `0x7f000001` or `127.1`) is refused everywhere a destination is taken: forms, API, `shorten-web`, CSV import, routing and fallback addresses, bio blocks. Webhooks already refused both. Links that already exist keep working until edited.
- A CSV row with a quote that is never closed is an error in the preview, not one long destination marked Ready.
- `composer.json` pins `hop/ui` to the commit in the lock (`6b4210c`, same tree as before); the old pin was a commit outside its main history.
- `robots.txt` names this app's own sitemap (`link.hel.ink/sitemap.xml`); the hub's host has none.
- `scripts/check-string-casts.sh` also finds `$request->string()`, which fails on an array the same way as a cast.
- A bio page in draft no longer links to its public address (the list, the editor, View live and QR code on its analytics page): those answered 404, also to the owner.
- The icon-only main action of a page on a phone has an accessible name (New link, New folder, New tag, New bio page).
- The 500 page no longer says the team has been notified: nothing reports an error to anyone. It asks to try again, and names the address to write to, in all nine languages.
- The not-found page of a bio page (`/b/...`) follows the visitor's language.
- A query value sent as an array (`?q[]=x`) no longer gives signed-in pages a 500: My links, the dashboard, Analytics, Settings, the folder and tag pages and `/api/links` treat it as not given. The same value in a form field (a destination, the start and end dates, a bio page's slug, the export's minimum clicks) is answered as a validation error or ignored.

### Signing out at the hub now ends the session here too (even when LinkHop was down)
- Signing in through HOP ID uses `remember: true`, so the remember cookie (400 days) brought a person straight back in after their session rows were deleted, and the hub's "end sessions" action and its chained logout could not reach an app that was down at that moment. The hub now queues a signed `session.revoked` to every app; `POST /hop/events` handles it: the session rows of that account are deleted (database and file drivers) and its `remember_token` is rotated, so the cookie stops working. The hub retries for about 16 hours while LinkHop is unreachable.
- `account.deleted` also ends the account's sessions, after the data purge that was already there. An event the hub sends twice (a retry) is handled once; a type LinkHop does not know gets a 200 so the hub stops retrying it.
- `account.status` drops the cached short links of that account, so a suspended owner's links stop redirecting at once instead of after the five-minute cache. The suspended person is still signed out on their next request and sent to the hub's notice, as before.
- Needs `hop/auth` with the event receiver (`^0.1.3`, the tag that holds it) and an `apps` row for `linkhop` with the `events` capability at the hub.

### Encrypted NoteHop links are refused
- An encrypted NoteHop note keeps its key in the link after the `#` (`https://n.hel.ink/ID#k=KEY`). LinkHop stored the whole destination and sent the visitor on with it, so the key of a note ended up in the database. Every way of adding a destination (the home form, the dashboard, the API, CSV import, routing and split addresses, bio blocks) now refuses an address on a NoteHop host whose fragment carries `k=` or `a=`, with a message that says why and what to do instead. Other hosts and other fragments are unchanged.
- The hosts are `note.hel.ink` and `n.hel.ink` plus `NOTEHOP_URL`, `NOTEHOP_SHARE_URL` and the registered NoteHop app; `HOP_NOTE_HOSTS` adds more.
- The destination monitor, the title and preview fetches, and the Safe Browsing and URLhaus lookups no longer send a fragment. Activity-log lines and the broken-destination email no longer print it.
- `php artisan links:scrub-note-keys` lists links that were saved with a key (counts and ids only, dry run by default). With `--apply` it removes the `k` and `a` parameters (and only those) from every stored copy it knows of (links, activity logs, the webhook outbox, the audit log, idempotency keys, abuse reports, bio blocks and sessions), deletes cached copies, switches those links off and tells nobody. Each link and each batch of 500 rows is one transaction, so a run that stops half way can be run again. Backups and log files made before the fix are not touched.
- The address is read the way a browser reads it before it is compared: full-width and other compatibility letters, `U+3002`-style dots, soft hyphens, zero-width characters and `%2e`-style escapes in the host, control characters and spaces around the address, any number of slashes after `https:`, `#?k=` (NoteHop reads that as `k`), and the fallback address of an Android `intent://` link. This needs the PHP `intl` extension, which is now a requirement.
- A refused form no longer sends the key back in the page or stores it in the session: the fragment is cut from the address before the form is shown again, the CSV preview kept in the cache, and an abuse report (and the mail to the admin).
- The destination monitor now really connects to the addresses it checked (the `stream` option made Guzzle ignore the pin), and no longer reads the body.
- A key that still reaches a database error (a note address pasted into a free-text field, a row saved before this fix) is cut out of the log line before `laravel.log` (and the other text log channels) write it, and an `APP_DEBUG=true` error page that would print one is replaced by the plain error response. Keep `APP_DEBUG=false` in production.
- A link saved with a key and not scrubbed yet still redirects, but its address is no longer kept in the redirect cache, and webhooks send it without the key.
- The key is now refused in every field a person can fill in, not only in destinations and the main text fields: rich-text and code blocks, FAQ items, block settings, map, vCard and countdown fields, page background, custom CSS, QR settings, socials, a link's preview image, QR logo, warning text and comments, folder and tag names (also from a CSV row), and webhook addresses. An address written with HTML entities or CSS escapes, or with a line break in it, counts. A NoteHop link without a key and ordinary HTML are accepted as before, and any refused form comes back without the key.
- `links:scrub-note-keys` now also takes the key out of those fields on rows saved earlier: bio page socials, background, CSS, text and QR settings, every text field of a bio block, abuse report reasons, a link's preview, warning text and comments, folder and tag names, and webhook addresses. Each column gets a line in the output, a link that only held a key there stays live, and a key it cannot cut out (encoded twice inside another address) is reported by row instead of being passed over.

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
- The account menu and the dashboard texts are translated in all eight languages.

### Fixed
- Searching My links used a column that does not exist on the links table (`title` instead of `custom_title`), which fails on MySQL.
- Top bars keep their buttons on one line on phones: the "Sign up" button no longer breaks onto two lines, the logo is no longer squeezed, and the link-check page bar fits a 320 px screen.
- The folder, tag, API and analytics pages no longer make the page wider than a phone.
- The title and Open Graph lookups and the QR logo fetch connect only to the addresses that were checked (no second DNS lookup, no redirects), and an address with another scheme is refused instead of getting `https://` put in front of it. The QR logo fetch is cut off once it passes the size limit.
- QR codes download as PNG, JPG and SVG on servers without the imagick extension, and a logo is drawn on the code.
- A query parameter given as an array (`?lang[]=de`, `?url[]=x`, `?next[]=x`) gets a normal answer instead of a server error.
- `/l` and `/l/` send visitors to this install's own address, `/up` is a plain answer that loads no script or font from another host, the footer language links keep the query string, the settings tab follows the address, and bulk delete says which links could not be deleted.
- In the dashboard API, `example.com:8080/x` is read as a host and a port, as the web forms read it.
- A page shown to a signed-in person is answered with `Cache-Control: no-store, private`, so the Back button after signing out does not show it again. Public files and responses with their own cache policy are not changed.
- The `<title>` of every page is built as `Page · LinkHop` and follows the page language (it was English on the public pages and `LinkHop - Page` when signed in); the bio editor is titled `Edit bio page: <name>` instead of the home-page tagline.
- The 404, 429 and 500 pages and the "link not found / expired" page are translated into the nine languages. An address that matches no route used to skip the language middleware, so its 404 page was always English; it now follows `?lang=` and the language cookie.
- `robots.txt` no longer names `/products` (a 404) and keeps `/links`, `/bio`, `/folders`, `/tags`, `/analytics` and `/api-docs` out of search results.
- The info pages no longer put a `<main>` inside the layout's `<main>`, and the bio editor's theme button no longer repeats an id.
- A long address no longer makes the link analytics page wider than a 320 px screen, and the bio analytics page fits 320 px too. On a phone the bio editor header wraps so the Save button stays on screen, and the tab strip fades at its edge to show it scrolls.
- Code blocks on `/docs` and `/api-docs` and the busiest-hours chart can be scrolled with the keyboard.
- README: PHP 8.2+ with `intl`, Node.js 20.19+, MySQL/MariaDB/SQLite (not PostgreSQL), the Content Security Policy is enforced (not report-only), FlagCDN is gone, and routing, deep links, content warning, destination monitor, idempotency keys and webhooks are listed.
- The test run asks PHP for 512 MB (`phpunit.xml`): the suite no longer fits in the default 128 MB.

### Dashboard chrome
- The top bar holds settings-type controls (primary action, language, theme and an account menu with your HOP ID, settings and sign out). The other HOP apps are listed once, in the sidebar, with their marks; the app pill and grid are gone from the top bar, and the sidebar's user card became the account menu.

### LinkHop becomes a HOP ID consumer (Fase C, 28 October 2026)
The accounts moved to the hub (`navi-crwn/HEL.ink`, hel.ink + hub.hel.ink); LinkHop keeps the links. Nothing changes for production until the family cutover (`APP_URL` decides the host).
- **Sign-in through the hub.** `/login` starts the hub's SSO (`hop/auth`: `/auth/sso`, `/auth/sso/callback`, `/auth/sso/logout`); a verified ticket signs the same user in from the shared `users` table (`Auth::loginUsingId`). `/logout` ends the session here and goes on to the hub, which ends it in the other apps. Protected pages use the `hop.auth` middleware. A suspended account (`users.status`) is signed out and sent to the hub's notice
- **Removed:** registration, password sign-in and reset, Google sign-in, email verification, two-factor, Turnstile on auth forms, onboarding, the profile and security pages, account export and deletion, API token creation, the SSO issuer for PixelHop/NoteHop (`/sso/{app}`, `/api/pixelhop/*`, `/api/apps/{app}/*`), the HOP ID profile and user-admin API (`/api/hop/profile*`, `/users*`, `/stats`, `/audit`, `/search`, `/health`), and the marketing pages, landing page, ToS and guest shortening form (all the hub's now). `/profile` and the Settings page point at the hub
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

### HOP ID
- Content side of the HOP admin API for Imperium: `/api/hop/manifest`, `/content` (links, bio pages: list, detail, disable/enable/unpublish/publish/delete/transfer/rescan, owner's content protected), `/reports` (people's reports and scanner findings in one queue with investigate/close/confirm/restore), `/x/ip` (bans incl. expiry, watchlist), `/x/domains` (blacklist), `/x/queue` (worker heartbeat, backlog, last abuse scan), `/settings` (Site & SEO editable, .env facts read-only). Every change lands in `hop_audit`
- **LinkHop** is the new name of this app; the domain stays hel.ink ("Hop Easy Link"). It is the account server ("HOP ID") of the HOP family: the sign-in and sign-up buttons say "Login with HOP" and "Join HOP", and the sign-in page says which app you are on your way to
- **App registry.** The other HOP apps are rows in the new `apps` table instead of `PIXELHOP_*` / `NOTEHOP_*` keys: `/sso/{app}`, `/sso/{app}/google` and `/apps/{app}` work for any registered app, the sidebar lists them, and an app can be put in maintenance or switched off. `php artisan hop:bootstrap` moves the old keys over once (`/pixelhop` and `/notehop` redirect to `/apps/…`)
- **Roles.** `owner`, `admin`, `moderator`, `support` and app-scoped `admin` (new `roles` table) replace the single `SUPERADMIN_EMAIL` check, which stays as a fallback so the owner is never locked out. Admin pages now also require the two-factor check when the admin has 2FA on
- **One profile for every app.** Username (one rule everywhere: 3–30 lowercase letters, digits, dots, dashes, underscores; reserved names; a released name stays on hold 90 days), bio, website, location, a profile photo stored here, and "show my name and photo on what I share". New accounts get a username automatically; `hop:usernames` assigns one to existing accounts and can import the ones PixelHop and NoteHop already use (earliest claim wins)
- **SSO ticket v2** carries the profile, roles, `profile_version`, `verified` and `mfa`; every v1 field is unchanged
- **HOP App Protocol.** Signed server-to-server API for the apps and the Imperium control center: `GET /api/hop/profile/{id}`, `/api/hop/profiles`, `/api/hop/apps`, and `/api/apps/{app}/login|register` for an app's own forms (the old `/api/pixelhop/*` endpoints keep working for PixelHop 3.x). Secrets can be rotated with a grace period
- **Admin API for Imperium.** Signed `/api/hop/*` endpoints for an app registered with the `admin_client` capability (anyone else gets `403 forbidden`): `GET /stats` (KPIs and 14-day signup/click series, cached a minute), `GET /users` (search, status and role filters, id cursor), `GET /users/{id}` ("User 360": profile, counts, sessions, logins, activity, roles, notes, recent links), `POST /users/{id}/actions` (`suspend`, `activate`, `revoke_sessions`, `revoke_tokens`, `set_roles`, `note`, `delete`; the owner can never be suspended or deleted and the last owner cannot be demoted), `GET /audit` (every action, with the `X-HOP-Actor` and the calling app, in the new `hop_audit` table), `GET /search` (users, links, bio pages) and `GET /health` (signed only). `GET /apps` also returns each app's secret to an admin client. Admin notes live in the new `hop_user_notes` table
- **Imperium is a hidden app.** `hop:bootstrap` registers it from `IMPERIUM_URL` / `IMPERIUM_SSO_SECRET` with `apps.hidden` set, so it signs in through HOP ID and is allowed as a CSP form-action origin but never shows in the sidebar
- **The built-in admin panel is retired.** Imperium takes over through the signed admin API: the `/admin/*` pages, their controllers and views are gone, `GET /admin` (and anything under it) sends an admin to `HOP_CONTROL_URL` or, when that is not set, shows a page saying where the panel went. Admins get the normal dashboard with a banner and one sidebar item (`HOP_CONTROL_NAME`, default "Control center") instead of the Admin menus. The scanner (`abuse:scan`), IP bans, the watchlist, the domain blacklist, SEO settings and `queue:heartbeat` keep working underneath; `/api/hop/search` now returns public URLs for links and bio pages and no URL for users
- **Events.** Apps that declare the `events` capability are told about profile changes, status changes (suspend) and account deletion (`POST /hop/events`, signed, retried with backoff)
- Sign-ins with Google and into the apps are now recorded in the login history like password sign-ins
- Sign-in, sign-up, forgot- and reset-password forms: one full-width HOP button, the Cloudflare check as wide as the form, and the secondary links (forgot password, join, log in) as small text underneath; the header shows a HOP ID chip. Mails get a HOP ID header (dark band with the logo) and a footer naming every HOP app
- Password reset speaks HOP ID: the mail (with the logo) says the new password works on every HOP app, the form shows the address the link is for without letting it be edited (the token was always bound to that address), and opening the link while signed in as another account signs that session out instead of bouncing to the dashboard


### Fixed
- A short link or bio slug can no longer be the first part of an app route (`analytics`, `api-docs`, `goodbye`, `up`, ...): the route always won, so such a link never opened. The list is read from the router, so new routes are covered automatically; matching ignores case. Links that already have such a slug can still be edited
- The example short link for the API reference is now `hel.ink/documentation`; `hel.ink/api-docs` is the app's API page for members. `ExampleBioSeeder` removes its old `api-docs` link only when the example owner still has it pointing at the docs
- Logout no longer shows "419 Page Expired" when the page was left open past the session lifetime: the user is signed out anyway. Other forms with an expired token go back with their input (never passwords) and the message "Sesi habis, silakan coba lagi."; JSON requests still get a 419 JSON reply. Open tabs fetch a fresh token from `/csrf-token` when shown again
- The inactivity logout now uses `SESSION_LIFETIME` (default 120 minutes) instead of a separate 60-minute setting, and actually fires (it compared times the wrong way round). `SESSION_INACTIVITY_LOGOUT` is gone
- Admin bio page: text, divider, embed, FAQ and contact-card blocks no longer show as blank rows
- Public bio pages: header, FAQ and contact-card text follows the theme color, so it stays readable on dark themes

### Changed
- Tailwind CSS 3 → 4 (`@tailwindcss/vite`, CSS-first config in `resources/css/app.css`; `tailwind.config.js`, `postcss.config.js`, `autoprefixer` and `postcss` removed). The v3 look is kept: v3 border color, button cursor and `space-x/y` rule are put back, and the component classes keep their v3 order. `npm audit` is clean and CI now runs it in full. `scripts/ui-screenshots.mjs` compares the main pages before and after a styling change
- `storage/framework/disposable_domains.json` (written by `disposable:update`) is git-ignored
- README: why Cloudflare Email Obfuscation and Rocket Loader fill the CSP report log, and what to do about it
- Guest links are deleted at 00:00 (in `GUEST_LINK_TIMEZONE`) after 14 full days in a row without a click, and after day 30 at most; the server `.env` can no longer stretch the idle limit past 14 days. `links:prune-guest` now runs at midnight
- Home page: the Cloudflare security check sits to the right of the Shorten button on wide screens and only takes room when Cloudflare asks for a click

### Added
- `ExampleBioSeeder` builds the example bio page (`/b/hel`) and short links shown in the README and on the website
- Slugs of deleted guest links stay blocked for 90 days (`RETIRED_SLUG_DAYS`) and show a "Link Expired" page (HTTP 410), so nobody can take over a link that is still shared somewhere

### Security
- `npm audit fix` (no `--force`): 21 advisories (2 critical) down to 7, all from Tailwind CSS 3 build tooling; axios 1.20, vite 7.3 and concurrently 9.2.5 within their ranges. CI now fails on critical npm advisories
- Bio shortlink endpoints (`/api/bio/shorten`, `/api/shorten-web`) now enforce the domain blacklist, quota and rate limits
- Bio blocks reject `javascript:`/`data:` URLs and blocked domains; public bio pages sanitize HTML/text blocks, custom CSS and background styles
- Users can no longer edit or delete bio blocks that belong to another page (scoped route bindings)
- Registration no longer derives a role from the email domain, rejects disposable emails and is rate limited per IP
- Dependencies updated within their constraints (Laravel 12.70); `composer audit` is clean

### Added
- Project website on GitHub Pages (`docs/`, English and Indonesian, light and dark) and an Indonesian README (`README.id.md`); README images are rendered from the website by `scripts/readme-images.mjs`
- `abuse:scan` command, scheduled hourly (new/edited items) and nightly (full sweep + purge): blacklist, keyword categories (CSAM, gambling, phishing, scam, adult), risky URL traits and optional Google Safe Browsing / URLhaus lookups (`SAFE_BROWSING_KEY`, `URLHAUS_AUTH_KEY`)
- Automated findings on the admin Abuse page with Confirm / Restore
- `config/abuse.php` for patterns, weights and thresholds

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

### Upgrading to 2.0.0
1. Run database migrations: `php artisan migrate`
2. Clear caches: `php artisan optimize:clear`
3. Rebuild assets: `npm run build`
4. Update environment variables if needed

### New Environment Variables (2.0.0)
No new required environment variables. Bio pages use existing database.
