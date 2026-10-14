/**
 * Runs one journey in a fresh browser context with a fresh persona, checking
 * every step, and shrinks a failing journey to the shortest one that still fails.
 */
import fs from 'node:fs';
import path from 'node:path';
import type { Browser, BrowserContextOptions, Route } from '@playwright/test';
import { ACTION_BY_ID, type Persona, type StateId } from '../model/app-graph';
import { START, step as advance, walk, type Journey, type Role } from './journeys';
import { Recorder, a11y, checkStep, type Finding } from './oracle';

export interface RunResult {
  ok: boolean;
  failure?: Finding;
  problems: Finding[];
  warnings: Finding[];
  visited: StateId[];
  executed: string[];
  a11y: Record<string, string[]>;
  /** Screenshot of the page when the journey failed. */
  screenshot?: Buffer;
}

export interface RunOptions {
  baseURL: string;
  contextOptions?: BrowserContextOptions;
  axe?: boolean;
  stepTimeout?: number;
}

/** Script CDNs the app needs to work (charts, QR codes, maps). */
const CDN_HOSTS = new Set(['cdn.jsdelivr.net', 'unpkg.com']);

/**
 * Fonts, analytics and other third parties are cut off so they cannot slow or
 * flake a journey. CDN scripts are loaded for real, or from E2E_CDN_MIRROR
 * (filled by tests/e2e/cdn-mirror.sh) when the machine has no internet.
 */
async function thirdParty(route: Route) {
  const url = new URL(route.request().url());
  // The analytics map downloads country shapes from GitHub; an empty map is enough here.
  if (url.hostname === 'raw.githubusercontent.com' && url.pathname.endsWith('.geo.json')) {
    return route.fulfill({ json: { type: 'FeatureCollection', features: [] }, headers: { 'Access-Control-Allow-Origin': '*' } });
  }
  if (!CDN_HOSTS.has(url.hostname)) return route.abort('blockedbyclient');
  const mirror = process.env.E2E_CDN_MIRROR;
  if (!mirror) return route.continue();
  let file = path.join(mirror, url.pathname.replace(/^\/npm\//, '/'));
  // jsDelivr minifies on the fly: x.min.js is served even when the package only ships x.js.
  if (!fs.existsSync(file)) file = file.replace(/\.min\.(js|css)$/, '.$1');
  if (!fs.existsSync(file)) return route.abort('blockedbyclient');
  const type = file.endsWith('.css') ? 'text/css' : 'application/javascript';
  return route.fulfill({ path: file, contentType: type });
}

let ipCounter = 0;
/** Each persona looks like its own visitor (198.18.0.0/15 is reserved for benchmarking). */
function fakeIp(): string {
  const n = (process.pid * 7919 + ++ipCounter) % 65534 + 1;
  return `198.18.${n >> 8}.${n & 255}`;
}

export async function createPersona(baseURL: string, role: Role, ip: string): Promise<Persona> {
  const res = await fetch(`${baseURL}/_e2e/persona`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Forwarded-For': ip },
    body: JSON.stringify({ admin: role === 'admin' }),
  });
  if (!res.ok) throw new Error(`persona endpoint returned ${res.status}; is the server running with E2E_HOOKS=true?`);
  return res.json() as Promise<Persona>;
}

export async function invariants(baseURL: string): Promise<string[]> {
  const res = await fetch(`${baseURL}/_e2e/invariants`, { headers: { Accept: 'application/json' } });
  const body = await res.json() as { violations: string[]; failed_jobs: number };
  return [...body.violations, ...(body.failed_jobs ? [`${body.failed_jobs} failed queue jobs`] : [])];
}

/** Two failures are "the same bug" when kind and message match once numbers are ignored. */
export const signature = (f: Finding) => `${f.kind}:${f.action}:${f.message.replace(/\d+/g, '#')}`;

export async function runJourney(browser: Browser, j: Pick<Journey, 'role' | 'steps'>, opts: RunOptions): Promise<RunResult> {
  const ip = fakeIp();
  const me = await createPersona(opts.baseURL, j.role, ip);
  const context = await browser.newContext({ ...opts.contextOptions, baseURL: opts.baseURL, extraHTTPHeaders: { 'X-Forwarded-For': ip } });
  await context.route(url => !url.href.startsWith(opts.baseURL), route => thirdParty(route));
  const page = await context.newPage();
  page.setDefaultTimeout(opts.stepTimeout ?? 15000);
  // "Leave site?" prompts: a person who clicked away means it.
  page.on('dialog', d => (d.type() === 'beforeunload' ? d.accept() : d.dismiss()).catch(() => {}));
  const rec = new Recorder(page, opts.baseURL);
  const result: RunResult = { ok: true, problems: [], warnings: [], visited: [START.state], executed: [], a11y: {} };
  const ctx = { page, me, others: () => [], memo: {} as Record<string, unknown> };
  let pos = START;
  try {
    await page.goto('/');
    for (const [n, id] of j.steps.entries()) {
      const action = ACTION_BY_ID[id];
      try {
        await action.run(ctx);
        await page.waitForLoadState('domcontentloaded');
      } catch (e) {
        // Say what the server answered, e.g. "... (server: POST /login -> 429)".
        const bad = rec.drain().responses.filter(r => r.status >= 400).map(r => `${r.method} ${new URL(r.url).pathname} -> ${r.status}`);
        const msg = (e as Error).message.split('\n')[0] + (bad.length ? ` (server: ${bad.slice(0, 3).join(', ')})` : '');
        result.problems.push({ kind: msg.startsWith('INVARIANT') ? 'invariant' : 'step-failed', message: msg, step: n, action: id, url: page.url() });
        break;
      }
      pos = advance(pos, action);
      result.executed.push(id);
      result.visited.push(pos.state);
      const { problems, warnings } = await checkStep({ page, rec, action, step: n, state: pos.state, role: j.role, myToken: me.token, lockedSecret: me.lockedSecret, unlocked: Boolean(ctx.memo.unlocked) });
      result.problems.push(...problems);
      result.warnings.push(...warnings);
      if (problems.length) break;
      if (opts.axe && !result.a11y[pos.state]) result.a11y[pos.state] = await a11y(page);
    }
    if (result.problems.length) result.screenshot = await page.screenshot().catch(() => undefined);
  } finally {
    await context.close();
  }
  if (!result.problems.length) {
    for (const v of await invariants(opts.baseURL)) result.problems.push({ kind: 'db-invariant', message: v, step: j.steps.length, action: 'end', url: '' });
  }
  result.ok = result.problems.length === 0;
  result.failure = result.problems[0];
  return result;
}

/**
 * Delta debugging: remove chunks of steps while the journey still fails with the
 * same signature. Candidates the graph says are impossible are skipped without
 * running them. `budget` caps the number of replays.
 */
export async function shrink(browser: Browser, j: Journey, failure: Finding, opts: RunOptions, budget = 25, deadline = Date.now() + 10 * 60_000): Promise<{ steps: string[]; replays: number }> {
  const sig = signature(failure);
  // Steps after the failing one never matter.
  let best = j.steps.slice(0, failure.step + 1);
  let replays = 0;
  const fails = async (steps: string[]) => {
    if (!walk(steps, j.role) || Date.now() > deadline) return false;
    replays++;
    const r = await runJourney(browser, { role: j.role, steps }, opts);
    return !r.ok && signature(r.failure!) === sig;
  };
  let chunk = Math.max(1, Math.floor(best.length / 2));
  while (chunk >= 1 && replays < budget && Date.now() < deadline) {
    let removed = false;
    for (let start = 0; start + chunk <= best.length - 1 && replays < budget; start += chunk) {
      // The last step is the one that fails; keep it.
      const candidate = [...best.slice(0, start), ...best.slice(start + chunk)];
      if (await fails(candidate)) {
        best = candidate;
        removed = true;
        start -= chunk;
      }
    }
    if (!removed) chunk = Math.floor(chunk / 2);
  }
  return { steps: best, replays };
}
