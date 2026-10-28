# Changelog

All notable changes to LinkHop (hel.ink) will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
