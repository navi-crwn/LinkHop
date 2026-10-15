# Changelog

All notable changes to HEL.ink will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed
- A short link or bio slug can no longer be the first part of an app route (`analytics`, `api-docs`, `goodbye`, `up`, ...): the route always won, so such a link never opened. The list is read from the router, so new routes are covered automatically; matching ignores case. Links that already have such a slug can still be edited
- The example short link for the API reference is now `hel.ink/documentation`; `hel.ink/api-docs` is the app's API page for members. `ExampleBioSeeder` removes its old `api-docs` link only when the example owner still has it pointing at the docs
- Logout no longer shows "419 Page Expired" when the page was left open past the session lifetime: the user is signed out anyway. Other forms with an expired token go back with their input (never passwords) and the message "Sesi habis, silakan coba lagi."; JSON requests still get a 419 JSON reply. Open tabs fetch a fresh token from `/csrf-token` when shown again
- The inactivity logout now uses `SESSION_LIFETIME` (default 120 minutes) instead of a separate 60-minute setting, and actually fires (it compared times the wrong way round). `SESSION_INACTIVITY_LOGOUT` is gone
- Admin bio page: text, divider, embed, FAQ and contact-card blocks no longer show as blank rows
- Public bio pages: header, FAQ and contact-card text follows the theme color, so it stays readable on dark themes

### Changed
- Guest links are deleted at 00:00 (in `GUEST_LINK_TIMEZONE`) after 14 full days in a row without a click, and after day 30 at most; the server `.env` can no longer stretch the idle limit past 14 days. `links:prune-guest` now runs at midnight
- Home page: the Cloudflare security check sits to the right of the Shorten button on wide screens and only takes room when Cloudflare asks for a click

### Added
- `ExampleBioSeeder` builds the example bio page (`/b/hel`) and short links shown in the README and on the website
- Slugs of deleted guest links stay blocked for 90 days (`RETIRED_SLUG_DAYS`) and show a "Link Expired" page (HTTP 410), so nobody can take over a link that is still shared somewhere

### Security
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
