# Credits

The open-source libraries, data and services behind LinkHop (link.hel.ink). Versions are the ones in `composer.lock`, `package-lock.json` and `public/vendor/README.md`; licences come from each package. LinkHop itself is MIT licensed (see `LICENSE`).

## Language and framework

- PHP 8.2 or newer (8.3 is what CI and the installer prefer)
- Laravel 12 (MIT)

## PHP packages (Composer)

Runtime:

- laravel/framework 12.70 (MIT) and laravel/tinker (MIT)
- hop/auth, hop/ui and hop/safety: the shared HOP packages, from `navi-crwn/hop-auth`, `hop-ui` and `hop-safety` (MIT)
- hop/bio: the bundled bio package in `packages/bio` (MIT)
- simplesoftwareio/simple-qrcode 4.2 (MIT) with bacon/bacon-qr-code (BSD-2-Clause): QR codes
- matomo/device-detector 6.5 (LGPL-3.0-or-later): device, browser and bot detection
- geoip2/geoip2 3.4 (Apache-2.0): reads the local country and city databases
- symfony/html-sanitizer 7.4 (MIT)
- spatie/laravel-backup 10.3 (MIT)
- sentry/sentry-laravel 4.29 (MIT): error tracking

Development and tests:

- pestphp/pest 4 and pest-plugin-laravel (MIT), mockery/mockery (BSD-3-Clause), fakerphp/faker (MIT), nunomaduro/collision (MIT)
- laravel/pint (MIT), laravel/pail (MIT), laravel/sail (MIT)

## JavaScript packages (npm)

Built into the front end:

- Tailwind CSS 4 with @tailwindcss/forms (MIT), Vite 7 with laravel-vite-plugin (MIT)
- Alpine.js 3 with @alpinejs/collapse (MIT), axios (MIT), SortableJS (MIT)
- GSAP 3 (the standard no-charge GSAP licence), Lenis (MIT), Three.js (MIT): the home page scenes

Development and tests: @playwright/test and @axe-core/playwright (Apache-2.0, MPL-2.0), concurrently (MIT).

## Libraries served from `public/vendor`

Self-hosted, so no page loads a script from a CDN. Each file keeps its licence next to it.

- Chart.js 4.4.0 (MIT) and chartjs-plugin-zoom 2.0.1 (MIT)
- Leaflet 1.9.4 (BSD-2-Clause)
- qr-code-styling 1.6.0-rc.1 (MIT)
- Alpine.js 3.15.2 (MIT), for the editor preview
- Country outlines for the analytics map: johan/world.geo.json, derived from Natural Earth (public domain)

## Icons, flags and fonts

- Simple Icons: the brand icons in `public/images/brands` (CC0 1.0)
- flag-icons: the country flags in `public/images/flags` (MIT)
- Inter, Instrument Serif and JetBrains Mono (SIL Open Font License 1.1): the HOP family typography, served by hop/ui from hel.ink
- The bio package's font choices (Inter, Poppins, Roboto, Playfair Display, Space Grotesk and others, SIL Open Font License 1.1 or Apache-2.0) are self-hosted in `public/fonts/bio`

## Data and services

- IP Geolocation by [DB-IP](https://db-ip.com): the DB-IP Lite city and ASN databases (CC BY 4.0), downloaded by `geolite:update`. With `GEOLITE_LICENSE_KEY` set, MaxMind GeoLite2 is used instead.
- When the local database has no answer, visitor location can come from freeipapi.com, ip-api.com, ipapi.co, ipinfo.io or abstractapi.com, each under its own terms.
- Proxy and VPN checks can use proxycheck.io, IPQualityScore, vpnapi.io and the ISP patterns in the code, depending on the keys you set.
- Cloudflare Turnstile protects the guest shortener and the report form (Cloudflare terms of service).
- OpenStreetMap tiles under the analytics map, © OpenStreetMap contributors (ODbL).
- Optional abuse lookups: Google Safe Browsing and URLhaus.

## Brand

The LinkHop logo, mark and favicon in `resources/brand` are the project's own designs. See `resources/brand/README.md`.

## Inspiration

The link-in-bio package took ideas from [LinkStack](https://github.com/LinkStackOrg/LinkStack) (AGPL-3.0), [LittleLink](https://github.com/sethcottle/littlelink) (MIT) and Linktree. No code or assets were copied from any of them.
