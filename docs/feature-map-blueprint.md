# The enterprise shortener blueprint, mapped to LinkHop

Source: the owner's blueprint built from the `awesome-url-shortener` directory (102 features in four modules, 50 engineering rules). Numbers are the blueprint's own. HOP rules applied: free forever (no billing), one VPS (no teams, no custom domains, no multi-region), no AI features, no trackers or third-party platform data, no CDN scripts.

**Have** = in LinkHop today. **Done now** = added in the home and dashboard round. **Next** = feasible and worth building, in this order. **Later** = feasible, lower value or larger. **No** = against the rules above.

## Module 1: core link engine
| # | Feature | Status |
|---|---|---|
| 1, 3 | Custom slug, edit the destination | Have |
| 2 | Collision-free generator | Have (random slug, unique index); Sqids not needed |
| 4 | Choose 301/302/307/308 | **Done now** (Batch A adds 308) |
| 6 | Expiry by date | Have |
| 7 | Expire after N clicks | **Done now** (Batch A: counted at the redirect, crawlers excluded) |
| 8 | Fallback URL when expired | **Done now** (Batch A: also when the clicks run out; loops refused) |
| 9 | Scheduled start | **Done now** (Batch A: "Not live yet" page before the start) |
| 10 | Password | Have |
| 11-13 | Redirect by country, device, OS, browser, language | **Done now** (Batch B: up to 20 rules per link, first match wins, conditions combine, crawlers get the default) |
| 15 | A/B split by weight | **Done now** (Batch B: 2 to 5 variants, keyed visitor hash, clicks per variant) |
| 16 | UTM builder with presets | **Done now** (Batch B: `x-utm-builder`, presets in code, nothing stored) |
| 17 | Forward query parameters | Later |
| 18 | Custom social card (title, description, picture) | Have (served to chat bots) |
| 19 | Link cloaking in an iframe | No (hides the destination, bad for abuse handling) |
| 21 | Folders and tags | Have |
| 22 | Soft delete and slug cooldown | Have (`retired_slugs`); a trash view is Later |
| 23 | Duplicate URL warning | **Done now** (Batch A: warns once, the same form sent again goes through) |
| 24 | Notes and comments | Have |
| 25 | Preview with a `+` suffix | Partly (`enable_preview`); the suffix is Later |
| 5 | Bulk create, tag, delete | Partly (bulk delete); CSV import is **Next 8** |
| 5, 92 | Import from Bitly, Short.io, Rebrandly | Later (after CSV import) |

## Module 2: domains, TLS, high availability
| # | Feature | Status |
|---|---|---|
| 26-35, 40, 41, 43-46, 48-50 | Custom domains, on-demand TLS, wildcard, failover, multi-region, IDN | No (one VPS, owner decision). Emoji and Unicode slugs: Later |
| 20 | Case-sensitivity toggle | Later |
| 29 | Custom 404 target | Later |
| 31, 38 | Redis/edge cache, read-only fallback | Later (MySQL is fast enough at this size; measure first) |
| 36 | `noindex` control per link | Later |
| 37 | Destination broken-link monitor | **Next 9** (scheduled check, notify the owner) |
| 42, 47 | Slash normalisation, flood limits | Have (rate limits and throttles) |

## Module 3: analytics and QR
| # | Feature | Status |
|---|---|---|
| 51 | Live click stream | Later |
| 52 | Unique visitors | **Next 10** (keyed hash of IP and agent per day; no raw IP) |
| 53, 56 | Bot and chat-previewer filtering, in-app browser parsing | Partly: crawlers are already not counted (`LogLinkClick`); in-app browser parsing is **Next 11** |
| 54, 55, 57, 58 | Geo, referrers, devices, browsers, OS, ISP | Have |
| 59 | Time series and hourly heat map | Have, heat map **Done now** |
| 60 | Conversion and revenue attribution | No (needs the visitor's other site to report back; a tracking pixel) |
| 61 | Retargeting pixels | No (HOP has no trackers) |
| 62-64 | QR studio, logo, PNG and SVG | Have |
| 65 | QR scan against direct click | **Next 12** (`?src=qr` as in BioHop) |
| 66-67 | UTM and folder roll-ups | Later |
| 68 | Shareable read-only analytics link | Later |
| 69 | CSV and JSON export | Have |
| 70 | Weekly report by e-mail | Later |
| 71 | Link-in-bio | Have (BioHop, inside LinkHop) |
| 72 | Leaderboard | Have (top links) |
| 73 | Cookieless, no raw IP | Have |
| 74 | Streaming to PostHog, Mixpanel, GA4 | No (third-party platform data) |
| 75 | Period against period | **Done now** |

## Module 4: security, API, governance
| # | Feature | Status |
|---|---|---|
| 76-77 | Malware and phishing scanning (Google Web Risk, VirusTotal) | Partly (own checks, `UrlGuard`, link check page); third-party scanners No |
| 78 | Destination allowlist or blocklist | Have (domain blacklist); allowlist Later |
| 79 | Abuse reporting | Have |
| 80-81 | REST API, scoped tokens | Have |
| 82 | Idempotency keys | **Next 13** |
| 83-84 | Webhooks with HMAC signature | **Next 14** (BioHop's `Webhooks` is the model) |
| 85 | MCP server for AI agents | No (no AI features) |
| 86 | SDKs and a CLI | Later |
| 87 | Async click queue | Have (queue); Redis Streams not needed |
| 88-91, 97 | Workspaces, RBAC, enterprise SSO | No (no teams, owner decision); audit logs exist for admins |
| 93-96 | Chat bots, browser extension, Raycast, Zapier | Later |
| 98 | 2FA | Have at the hub (TOTP); passkeys Later |
| 99 | Turnstile | Have |
| 100 | Custom system pages | No |
| 101 | Billing and metering | No (free forever); quotas exist |
| 102 | Public status page | Have (`/status`) |

## UX rules (39-50)
Command palette (39), live preview on paste (40), optimistic copy (41), slug check as you type (49): **Next 15**, as one editor round. A sandbox on the front page (42) is Have. Cursor pagination and virtual tables (44): Later, not needed until a user has thousands of links.

## Engineering rules (1-38) that matter here
Zero-blocking redirect and negative caching are already how the redirect route works for the traffic LinkHop has; a separate Go engine, ClickHouse and PgBouncer are the wrong size for one VPS and would make self-hosting harder (MIT, "yours to host"). Anti-SSRF and loop rejection are in `hop/safety`. Revisit the data-plane split only when the redirect route shows up in a measurement.

## Order proposed
Next 1-3 (limits, fallback, schedule) share one migration and one form section. Next 4-5 (routing, A/B) reuse BioHop's rule engine. Next 6-8 are form and import work. Next 9-12 are background jobs and analytics. Next 13-15 are API and editor polish. Each is one PR after the owner approves the plan.
