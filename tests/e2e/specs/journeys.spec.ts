/**
 * Synthetic users: every generated journey is one test. On failure the journey
 * is shrunk and a replay file is written to tests/e2e/reports/runs/.
 */
import { test } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { plan, type Journey } from '../lib/journeys';
import { runJourney, shrink } from '../lib/runner';

import { RUNS_DIR } from '../lib/paths';

function journeys(): Journey[] {
  // E2E_REPLAY=path/to/repro.json runs exactly one saved journey.
  if (process.env.E2E_REPLAY) {
    const r = JSON.parse(fs.readFileSync(process.env.E2E_REPLAY, 'utf8'));
    return [{ id: `replay-${r.id}`, role: r.role, steps: r.shrunk ?? r.steps }];
  }
  return plan();
}

for (const j of journeys()) {
  test(`${j.id} (${j.role}, ${j.steps.length} steps)`, async ({ browser, baseURL }, info) => {
    test.setTimeout(60_000 + j.steps.length * 20_000);
    const opts = { baseURL: baseURL!, axe: process.env.E2E_AXE !== '0' };
    const r = await runJourney(browser, j, opts);
    fs.mkdirSync(RUNS_DIR, { recursive: true });
    fs.appendFileSync(path.join(RUNS_DIR, `results-${info.workerIndex}.jsonl`), JSON.stringify({
      id: j.id, role: j.role, ok: r.ok, visited: r.visited, executed: r.executed, warnings: r.warnings, a11y: r.a11y, problems: r.problems,
    }) + '\n');
    if (r.ok) return;

    const f = r.failure!;
    if (r.screenshot) await info.attach('failing-step.png', { body: r.screenshot, contentType: 'image/png' });
    let shrunk = j.steps.slice(0, f.step + 1);
    let replays = 0;
    if (process.env.E2E_SHRINK !== '0' && !process.env.E2E_REPLAY) {
      const budget = Number(process.env.E2E_SHRINK_BUDGET ?? (process.env.E2E_MODE === 'nightly' ? 25 : 12));
      const minutes = Number(process.env.E2E_SHRINK_MINUTES ?? (process.env.E2E_MODE === 'nightly' ? 10 : 5));
      test.setTimeout(info.timeout + (minutes + 2) * 60_000);
      ({ steps: shrunk, replays } = await shrink(browser, j, f, opts, budget, Date.now() + minutes * 60_000));
    }
    const repro = { id: j.id, role: j.role, seed: j.seed, failure: f, steps: j.steps, shrunk, replays };
    const file = path.join(RUNS_DIR, `repro-${j.id}.json`);
    fs.writeFileSync(file, JSON.stringify(repro, null, 2));
    await info.attach('repro.json', { path: file, contentType: 'application/json' });
    throw new Error([
      `${f.kind} at step ${f.step + 1} (${f.action}) on ${f.url}: ${f.message}`,
      `shortest failing journey (${shrunk.length} of ${j.steps.length} steps, ${replays} replays): ${shrunk.join(' > ')}`,
      ...r.problems.slice(1).map(p => `also: ${p.kind} at step ${p.step + 1} (${p.action}): ${p.message}`),
      `replay: E2E_REPLAY=${path.relative(process.cwd(), file)} npx playwright test -c tests/e2e`,
    ].join('\n'));
  });
}
