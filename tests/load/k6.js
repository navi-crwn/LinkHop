/**
 * Load scenarios for HEL.ink (k6). Covers the public, high-traffic paths:
 * short-link redirects, public bio pages, bio link clicks, the link checker,
 * and signed-in dashboard views.
 *
 *   k6 run tests/load/k6.js                                   # against the local E2E server
 *   BASE_URL=https://staging.example k6 run tests/load/k6.js  # needs BIO_SLUG + LINK_SLUG there
 *
 * Against the E2E server (E2E_HOOKS=true) the data is created in setup().
 * Never point this at production: it creates load and, with hooks, accounts.
 */
import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8130';
const SCALE = Number(__ENV.SCALE || 1);
const ip = () => `198.19.${Math.floor(Math.random() * 254) + 1}.${Math.floor(Math.random() * 254) + 1}`;

export const options = {
  scenarios: {
    redirects: { executor: 'ramping-arrival-rate', exec: 'redirects', startRate: 5, timeUnit: '1s', preAllocatedVUs: 20 * SCALE, maxVUs: 60 * SCALE,
      stages: [{ target: 20 * SCALE, duration: '30s' }, { target: 40 * SCALE, duration: '1m' }, { target: 0, duration: '15s' }] },
    bio_pages: { executor: 'ramping-arrival-rate', exec: 'bioPages', startRate: 2, timeUnit: '1s', preAllocatedVUs: 10 * SCALE, maxVUs: 40 * SCALE,
      stages: [{ target: 10 * SCALE, duration: '30s' }, { target: 20 * SCALE, duration: '1m' }, { target: 0, duration: '15s' }] },
    link_check: { executor: 'constant-arrival-rate', exec: 'linkCheck', rate: 2 * SCALE, timeUnit: '1s', duration: '1m45s', preAllocatedVUs: 5 * SCALE },
    dashboards: { executor: 'constant-vus', exec: 'dashboards', vus: 3 * SCALE, duration: '1m45s' },
  },
  thresholds: {
    'http_req_failed{scenario:redirects}': ['rate<0.01'],
    'http_req_failed{scenario:bio_pages}': ['rate<0.01'],
    'http_req_duration{scenario:redirects}': ['p(95)<800'],
    'http_req_duration{scenario:bio_pages}': ['p(95)<1500'],
    'http_req_duration{scenario:dashboards}': ['p(95)<2500'],
    checks: ['rate>0.99'],
  },
};

export function setup() {
  if (__ENV.BIO_SLUG && __ENV.LINK_SLUG) {
    return { bio: __ENV.BIO_SLUG, links: [__ENV.LINK_SLUG], email: __ENV.LOGIN_EMAIL, password: __ENV.LOGIN_PASSWORD };
  }
  const res = http.post(`${BASE}/_e2e/persona`, null, { headers: { 'X-Forwarded-For': ip() } });
  if (res.status !== 200) throw new Error(`no BIO_SLUG/LINK_SLUG given and ${BASE}/_e2e/persona answered ${res.status}`);
  return res.json();
}

const headers = () => ({ 'X-Forwarded-For': ip() });

export function redirects(data) {
  const slug = data.links[Math.floor(Math.random() * data.links.length)];
  const res = http.get(`${BASE}/${slug}`, { redirects: 0, headers: headers(), tags: { name: 'GET /{slug}' } });
  check(res, { 'short link redirects': r => r.status === 301 || r.status === 302 });
}

export function bioPages(data) {
  const h = headers();
  const page = http.get(`${BASE}/b/${data.bio}`, { headers: h, tags: { name: 'GET /b/{slug}' } });
  check(page, { 'bio page renders': r => r.status === 200 });
  // Follow one of the block links like a visitor would (the URL is whatever the page renders).
  const m = page.body && page.body.match(/href="([^"]*\/b\/click\/[^"]+)"/);
  if (m) {
    const click = http.get(m[1].startsWith('http') ? m[1] : `${BASE}${m[1]}`, { redirects: 0, headers: h, tags: { name: 'GET bio click' } });
    check(click, { 'bio click redirects': r => r.status >= 300 && r.status < 400 });
  }
  sleep(Math.random());
}

export function linkCheck(data) {
  const res = http.get(`${BASE}/check?url=${encodeURIComponent(`${BASE}/${data.links[0]}`)}`, { headers: headers(), tags: { name: 'GET /check' } });
  check(res, { 'link check answers': r => r.status === 200 || r.status === 429 });
}

export function dashboards(data) {
  if (!data.email) return sleep(1);
  const jar = http.cookieJar();
  const h = headers();
  const login = http.get(`${BASE}/login`, { headers: h });
  const token = (login.body.match(/name="_token" value="([^"]+)"/) || [])[1];
  http.post(`${BASE}/login`, { _token: token, email: data.email, password: data.password }, { headers: h, tags: { name: 'POST /login' } });
  for (const path of ['/dashboard', '/links', '/bio', '/analytics']) {
    const res = http.get(`${BASE}${path}`, { headers: h, tags: { name: `GET ${path}` } });
    check(res, { [`${path} renders`]: r => r.status === 200 && !r.url.includes('/login') });
    sleep(1 + Math.random() * 2);
  }
  jar.clear(BASE);
}
