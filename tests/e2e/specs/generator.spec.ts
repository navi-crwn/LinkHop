/** The generator itself (no browser needed). */
import { test, expect } from '@playwright/test';
import { ACTIONS } from '../model/app-graph';
import { allPairs, pairJourneys, randomJourney, transitionJourneys, walk } from '../lib/journeys';

test('transition journeys cover every action and are valid walks', () => {
  const journeys = transitionJourneys();
  const covered = new Set(journeys.flatMap(j => j.steps));
  expect(ACTIONS.map(a => a.id).filter(id => !covered.has(id))).toEqual([]);
  for (const j of journeys) expect(walk(j.steps, j.role), j.id).not.toBeNull();
});

test('pair journeys cover every pair', () => {
  const journeys = pairJourneys();
  for (const j of journeys) expect(walk(j.steps, j.role), j.id).not.toBeNull();
  const seen = new Set(journeys.flatMap(j => j.steps.slice(1).map((s, i) => `${j.steps[i]}>${s}`)));
  const missing = allPairs('user').map(p => p.join('>')).filter(p => !seen.has(p));
  expect(missing).toEqual([]);
});

test('random walks are reproducible from their seed', () => {
  expect(randomJourney(42)).toEqual(randomJourney(42));
  expect(randomJourney(42).steps).not.toEqual(randomJourney(43).steps);
  expect(walk(randomJourney(7).steps, randomJourney(7).role)).not.toBeNull();
});

test('impossible journeys are rejected', () => {
  expect(walk(['nav_links'], 'user')).toBeNull(); // not logged in yet
  expect(walk(['open_login', 'login', 'admin_control_center'], 'user')).toBeNull(); // not an admin
});
