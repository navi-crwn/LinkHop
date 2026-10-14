/**
 * Builds the coverage report after a run: which states, actions and action
 * pairs were exercised, plus warnings (report-only CSP, axe) grouped by count.
 * Writes reports/runs/coverage.{json,md} and appends the markdown to the GitHub
 * job summary when running in Actions.
 */
import fs from 'node:fs';
import path from 'node:path';
import { RUNS_DIR } from './paths';
import { ACTIONS, ALL_STATES } from '../model/app-graph';
import { allPairs } from './journeys';

export default function coverageReport() {
  const dir = RUNS_DIR;
  if (!fs.existsSync(dir)) return;
  const rows = fs.readdirSync(dir).filter(f => f.startsWith('results-')).flatMap(f =>
    fs.readFileSync(path.join(dir, f), 'utf8').trim().split('\n').filter(Boolean).map(l => JSON.parse(l)));
  if (!rows.length) return;

  const states = new Set<string>(rows.flatMap(r => r.visited));
  const actions = new Set<string>(rows.flatMap(r => r.executed));
  const pairs = new Set<string>(rows.flatMap(r => r.executed.slice(1).map((s: string, i: number) => `${r.executed[i]}>${s}`)));
  const allPairKeys = [...allPairs('user'), ...allPairs('admin')].map(p => p.join('>'));
  const count = (list: string[]) => Object.entries(list.reduce<Record<string, number>>((m, k) => ((m[k] = (m[k] ?? 0) + 1), m), {})).sort((a, b) => b[1] - a[1]);
  const warnings = count(rows.flatMap(r => r.warnings.map((w: any) => `${w.kind}: ${w.message.replace(/\/zq[a-z0-9]+/g, '/{token}')}`)));
  const axe = count(rows.flatMap(r => Object.entries(r.a11y as Record<string, string[]>).flatMap(([s, v]) => v.map(x => `${s}: ${x.replace(/, \d+x/, '')}`))));
  const failed = rows.filter(r => !r.ok);

  const pct = (a: number, b: number) => `${a}/${b} (${Math.round((100 * a) / Math.max(1, b))}%)`;
  const summary = {
    journeys: rows.length,
    failed: failed.map(r => ({ id: r.id, problem: r.problems[0] })),
    states: { covered: states.size, total: ALL_STATES.length, missing: ALL_STATES.filter(s => !states.has(s)) },
    actions: { covered: actions.size, total: ACTIONS.length, missing: ACTIONS.map(a => a.id).filter(a => !actions.has(a)) },
    pairs: { covered: allPairKeys.filter(p => pairs.has(p)).length, total: allPairKeys.length },
    warnings: Object.fromEntries(warnings),
    a11y: Object.fromEntries(axe),
  };
  fs.writeFileSync(path.join(dir, 'coverage.json'), JSON.stringify(summary, null, 2));

  const md = [
    `## Synthetic users: ${rows.length - failed.length}/${rows.length} journeys passed`,
    '',
    `- States: ${pct(summary.states.covered, summary.states.total)}${summary.states.missing.length ? `, missing: ${summary.states.missing.join(', ')}` : ''}`,
    `- Actions: ${pct(summary.actions.covered, summary.actions.total)}${summary.actions.missing.length ? `, missing: ${summary.actions.missing.join(', ')}` : ''}`,
    `- Action pairs: ${pct(summary.pairs.covered, summary.pairs.total)}`,
    '',
    ...(failed.length ? ['### Failures', ...failed.map(r => `- **${r.id}**: ${r.problems[0].kind} at \`${r.problems[0].action}\`: ${r.problems[0].message}`), ''] : []),
    ...(warnings.length ? ['### Warnings (not failing)', ...warnings.slice(0, 25).map(([k, n]) => `- ${n}x ${k}`), ''] : []),
    ...(axe.length ? ['### Accessibility (axe, serious/critical, not failing)', ...axe.slice(0, 30).map(([k, n]) => `- ${n}x ${k}`), ''] : []),
  ].join('\n');
  fs.writeFileSync(path.join(dir, 'coverage.md'), md);
  if (process.env.GITHUB_STEP_SUMMARY) fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, md + '\n');
  console.log('\n' + md);
}
