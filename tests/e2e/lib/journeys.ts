/**
 * Journey generator: turns the app graph into lists of action ids.
 *
 *  - transitions: every action at least once (runs on every PR)
 *  - pairs:       every allowed "action A then action B" pair (nightly)
 *  - random:      seeded random walks, reproducible from the seed (nightly)
 *
 * Everything here is pure (no browser), so it is unit-tested in generator.spec.ts.
 */
import { ACTIONS, ACTION_BY_ID, MODAL_STATES, allowedFrom, type Action, type StateId } from '../model/app-graph';

export type Role = 'user' | 'admin';

export interface Journey {
  id: string;
  role: Role;
  steps: string[];
  seed?: number;
}

/** Where the walk is: the state, the page a modal was opened from, and whether the user is signed in. */
export interface Position { state: StateId; origin: StateId; authed: boolean }

export const START: Position = { state: 'guest_check', origin: 'guest_check', authed: false };

/** Only possible while signed out (the guest middleware redirects signed-in users). */
const GUEST_ONLY = new Set(['login', 'guest_dashboard_redirects_to_sso']);

/** Graph rule plus session state: a signed-in user on a public page can still use the app. */
export function can(action: Action, pos: Position, role: Role): boolean {
  if (GUEST_ONLY.has(action.id) && pos.authed) return false;
  // App-wide actions (sidebar, reload, log out) are not reachable while a modal covers the page.
  if (action.from === 'any-user') return pos.authed && !MODAL_STATES.includes(pos.state) && !(action.adminOnly && role !== 'admin');
  return allowedFrom(action, pos.state, role === 'admin');
}

/** Actions that close a modal and put you back where it was opened. */
const LEAVES_MODAL = new Set(['modal_close', 'close_folder_modal', 'close_tag_modal', 'block_editor_fill_and_close']);

export function step(pos: Position, action: Action): Position {
  const authed = action.id === 'login' ? true : action.id === 'logout' ? false : pos.authed;
  if (action.to === 'same') {
    if (MODAL_STATES.includes(pos.state) && LEAVES_MODAL.has(action.id)) return { state: pos.origin, origin: pos.origin, authed };
    return { ...pos, authed };
  }
  if (MODAL_STATES.includes(action.to)) return { state: action.to, origin: MODAL_STATES.includes(pos.state) ? pos.origin : pos.state, authed };
  return { state: action.to, origin: action.to, authed };
}

export function available(pos: Position, role: Role): Action[] {
  return ACTIONS.filter(a => can(a, pos, role));
}

/**
 * Replays a list of action ids through the graph. Returns the positions visited,
 * or null when some step is not possible from where the walk is (used by shrinking).
 */
export function walk(steps: string[], role: Role): Position[] | null {
  const out: Position[] = [START];
  let pos = START;
  for (const id of steps) {
    const action = ACTION_BY_ID[id];
    if (!action || !can(action, pos, role)) return null;
    pos = step(pos, action);
    out.push(pos);
  }
  return out;
}

const key = (p: Position) => `${p.state}<${p.origin}${p.authed ? '+' : ''}`;

/** Shortest list of actions from `pos` to a position where `goal` holds (BFS over the graph). */
export function pathTo(pos: Position, role: Role, goal: (p: Position) => boolean, avoid = new Set<string>()): string[] | null {
  if (goal(pos)) return [];
  const seen = new Set([key(pos)]);
  const queue: Array<[Position, string[]]> = [[pos, []]];
  while (queue.length) {
    const [cur, path] = queue.shift()!;
    for (const a of available(cur, role)) {
      // Detours never use actions that are expected to fail or that log out.
      if (a.expectsClientError || a.id === 'logout' || avoid.has(a.id)) continue;
      const next = step(cur, a);
      if (seen.has(key(next))) continue;
      const p = [...path, a.id];
      if (goal(next)) return p;
      seen.add(key(next));
      queue.push([next, p]);
    }
  }
  return null;
}

/**
 * Greedy cover: keep walking to the nearest uncovered target, cutting a new
 * journey (fresh browser, fresh persona) every `maxSteps` steps.
 */
function cover(role: Role, prefix: string, targets: string[][], maxSteps: number): Journey[] {
  const journeys: Journey[] = [];
  const left = new Set(targets.map(t => t.join('>')));
  let steps: string[] = [];
  let pos = START;
  const flush = () => {
    if (steps.length) journeys.push({ id: `${prefix}-${String(journeys.length + 1).padStart(3, '0')}`, role, steps });
    steps = [];
    pos = START;
  };
  while (left.size) {
    let best: { route: string[]; target: string[] } | null = null;
    for (const t of targets) {
      if (!left.has(t.join('>'))) continue;
      // Walk to a position from which the whole target (e.g. a pair) is possible.
      const route = pathTo(pos, role, p => walkFrom(p, t, role) !== null);
      if (!route) continue;
      if (!best || route.length < best.route.length) best = { route, target: t };
      if (route.length === 0) break;
    }
    if (!best) {
      if (steps.length === 0) break; // unreachable from a fresh start: nothing more to do
      flush();
      continue;
    }
    const next = [...best.route, ...best.target];
    if (steps.length && (steps.length + next.length > maxSteps || logins(steps) + logins(next) > MAX_LOGINS)) {
      flush();
      continue;
    }
    for (const id of [...best.route, ...best.target]) {
      pos = step(pos, ACTION_BY_ID[id]);
      steps.push(id);
    }
    left.delete(best.target.join('>'));
    // Detours count too: an action walked through on the way is covered.
    for (const id of best.route) left.delete(id);
  }
  flush();
  return journeys;
}

function walkFrom(pos: Position, steps: string[], role: Role): Position | null {
  for (const id of steps) {
    const a = ACTION_BY_ID[id];
    if (!can(a, pos, role)) return null;
    pos = step(pos, a);
  }
  return pos;
}

/** A journey signs in only a few times (each sign-in is a fresh session). */
const MAX_LOGINS = 4;
const logins = (steps: string[]) => steps.filter(s => s === 'login').length;

const roleActions = (role: Role) => ACTIONS.filter(a => (role === 'admin' ? a.adminOnly : !a.adminOnly));

export function transitionJourneys(maxSteps = 30): Journey[] {
  return [
    ...cover('user', 'tr-user', roleActions('user').map(a => [a.id]), maxSteps),
    ...cover('admin', 'tr-admin', roleActions('admin').map(a => [a.id]), maxSteps),
  ];
}

/** Every pair A>B where B is possible right after A. */
export function allPairs(role: Role): string[][] {
  const pairs: string[][] = [];
  const states = new Map<string, Position>();
  // Positions reachable from the start (with their modal origin).
  const queue = [START];
  states.set(key(START), START);
  while (queue.length) {
    const cur = queue.shift()!;
    for (const a of available(cur, role)) {
      const n = step(cur, a);
      if (!states.has(key(n))) { states.set(key(n), n); queue.push(n); }
    }
  }
  const seen = new Set<string>();
  for (const pos of states.values()) {
    for (const a of available(pos, role)) {
      const mid = step(pos, a);
      for (const b of available(mid, role)) {
        const k = `${a.id}>${b.id}`;
        if (!seen.has(k)) { seen.add(k); pairs.push([a.id, b.id]); }
      }
    }
  }
  return pairs.filter(([a, b]) => role === 'admin' ? ACTION_BY_ID[a].adminOnly || ACTION_BY_ID[b].adminOnly : true);
}

export function pairJourneys(maxSteps = 40): Journey[] {
  return [
    ...cover('user', 'pair-user', allPairs('user'), maxSteps),
    ...cover('admin', 'pair-admin', allPairs('admin'), maxSteps),
  ];
}

/** Small, fast, seedable PRNG (mulberry32). */
export function rng(seed: number) {
  let t = seed >>> 0;
  return () => {
    t = (t + 0x6d2b79f5) >>> 0;
    let r = Math.imul(t ^ (t >>> 15), 1 | t);
    r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r;
    return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
  };
}

export function randomJourney(seed: number, length = 25): Journey {
  const rand = rng(seed);
  const role: Role = rand() < 0.15 ? 'admin' : 'user';
  const steps: string[] = [];
  let pos = START;
  while (steps.length < length) {
    const tired = logins(steps) >= MAX_LOGINS;
    const options = available(pos, role).filter(a => !tired || a.id !== 'login');
    // A guest who never logs in only ever sees the checker and bio pages; nudge towards login.
    if (!tired && pos.state.startsWith('guest_') && rand() < 0.5) {
      const route = pathTo(pos, role, p => p.state === 'user_dashboard');
      if (route) { for (const id of route) { pos = step(pos, ACTION_BY_ID[id]); steps.push(id); } continue; }
    }
    if (!options.length) break;
    const a = options[Math.floor(rand() * options.length)];
    pos = step(pos, a);
    steps.push(a.id);
  }
  return { id: `rnd-${seed}`, role, steps: steps.slice(0, length), seed };
}

export function randomJourneys(baseSeed: number, count: number, length = 25): Journey[] {
  return Array.from({ length: count }, (_, i) => randomJourney(baseSeed + i, length));
}

/** Which journeys to run, from E2E_MODE (pr | nightly) and E2E_SEED / E2E_WALKS. */
export function plan(env: Record<string, string | undefined> = process.env): Journey[] {
  const mode = env.E2E_MODE ?? 'pr';
  const seed = Number(env.E2E_SEED ?? 20261014);
  const journeys = transitionJourneys();
  if (mode === 'pr') return [...journeys, ...randomJourneys(seed, Number(env.E2E_WALKS ?? 4))];
  return [...journeys, ...pairJourneys(), ...randomJourneys(seed, Number(env.E2E_WALKS ?? 40))];
}
