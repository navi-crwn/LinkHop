import fs from 'node:fs';
import path from 'node:path';
import { RUNS_DIR } from './paths';

/** Start every run with an empty results folder so the coverage report only counts this run. */
export default function globalSetup() {
  const dir = RUNS_DIR;
  fs.mkdirSync(dir, { recursive: true });
  for (const f of fs.readdirSync(dir)) if (/^(results-.*\.jsonl|repro-.*\.json|coverage\.(md|json))$/.test(f)) fs.rmSync(path.join(dir, f));
}
