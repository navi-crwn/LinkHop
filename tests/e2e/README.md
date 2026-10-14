# Synthetic users (E2E)

Model-based end-to-end tests: the app is described as a graph of states and
actions (`model/app-graph.ts`), journeys are generated from it
(`lib/journeys.ts`), and every step of every journey is checked
(`lib/oracle.ts`). A failing journey is shrunk to the shortest list of steps
that still fails, and saved so it can be replayed.

## Run locally

```bash
npm ci && npm run build
npx playwright install chromium        # once
npx playwright test -c tests/e2e       # starts the app via serve.sh on :8130 (SQLite)
```

`serve.sh` writes `.env.e2e` (git-ignored) and uses a throwaway database. The
test-only hooks in `routes/e2e.php` are loaded only with `E2E_HOOKS=true` outside
production.

| Variable | Default | Meaning |
| --- | --- | --- |
| `E2E_MODE` | `pr` | `pr`: every action once + 4 random walks. `nightly`: also every action pair, 40 walks, mobile viewport |
| `E2E_SEED` | `20261014` | First seed for random walks; the same seed gives the same walks |
| `E2E_WALKS` | 4 / 40 | Number of random walks |
| `E2E_REPLAY` | | Path to a `repro-*.json`; runs only that journey |
| `E2E_SHRINK` | on | `0` disables shrinking |
| `E2E_SHRINK_BUDGET` / `E2E_SHRINK_MINUTES` | 12 / 5 (pr), 25 / 10 (nightly) | Limits for shrinking |
| `E2E_AXE` | on | `0` skips the accessibility scan |
| `E2E_CDN_MIRROR` | | Folder from `cdn-mirror.sh`, for machines without internet |
| `E2E_BASE_URL` | | Run against an already running app (no `serve.sh`) |

## What is checked after every step

Fails the journey:
- JavaScript errors and `console.error`
- enforced CSP violations
- HTTP 5xx, 419 (CSRF), 429 (rate limit), and 4xx documents unless the action expects one
- an empty full-screen layer that blocks the page outside a modal
- another persona's marker (`zq` + 8 characters) visible to a normal user
- a password-protected bio page whose HTML contains the protected link
- action-level invariants (block order survives a reload, other users' links are not readable)
- database invariants after the journey (`GET /_e2e/invariants`)

Reported only (`reports/runs/coverage.md`): report-only CSP violations,
failing sub-resources, and serious/critical axe findings.

## When a journey fails

The test output shows the shortest failing journey and a replay command:

```bash
E2E_REPLAY=tests/e2e/reports/runs/repro-tr-user-001.json npx playwright test -c tests/e2e
```

In CI the `reports/runs` folder (repro files, coverage report, traces) is
uploaded as an artifact.

## Adding a page or control

Add its state and actions to `model/app-graph.ts`. The generator test
(`specs/generator.spec.ts`) fails if an action cannot be reached.

## Load test

`tests/load/k6.js` (nightly in CI against the E2E server). Locally:

```bash
bash tests/e2e/serve.sh &   # or any running instance with E2E_HOOKS=true
k6 run tests/load/k6.js
```

Against a staging server without hooks, pass `BASE_URL`, `BIO_SLUG`,
`LINK_SLUG` (and optionally `LOGIN_EMAIL` / `LOGIN_PASSWORD`). Never run it
against production.
