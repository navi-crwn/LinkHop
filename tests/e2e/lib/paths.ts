import path from 'node:path';
import { fileURLToPath } from 'node:url';

/** Where results, repro files and the coverage report go (git-ignored). */
export const RUNS_DIR = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'reports', 'runs');
