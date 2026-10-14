/**
 * Checks run after every step of a journey. A "problem" fails the journey; a
 * "warning" only goes into the report.
 */
import AxeBuilder from '@axe-core/playwright';
import type { Page, Response } from '@playwright/test';
import type { Action, StateId } from '../model/app-graph';
import { MODAL_STATES } from '../model/app-graph';

export interface Finding { kind: string; message: string; step: number; action: string; url: string }

const TOKEN = /\bzq[a-z0-9]{8}/g;

/** Collects everything the browser reports between two checks. */
export class Recorder {
  pageErrors: string[] = [];
  consoleErrors: string[] = [];
  csp: Array<{ directive: string; blocked: string; reportOnly: boolean }> = [];
  responses: Array<{ status: number; url: string; method: string; document: boolean }> = [];

  constructor(page: Page, private origin: string) {
    page.on('pageerror', e => this.pageErrors.push(e.message.split('\n')[0]));
    page.on('console', msg => {
      if (msg.type() !== 'error') return;
      const text = msg.text();
      // Failed sub-resources are reported by the response check instead; CSP by the listener below.
      if (text.startsWith('Failed to load resource') || text.includes('Content Security Policy')) return;
      this.consoleErrors.push(text.slice(0, 300));
    });
    page.on('response', (r: Response) => {
      if (!r.url().startsWith(this.origin)) return;
      this.responses.push({ status: r.status(), url: r.url(), method: r.request().method(), document: r.request().resourceType() === 'document' });
    });
    page.exposeFunction('__e2eCsp', (v: { directive: string; blocked: string; reportOnly: boolean }) => this.csp.push(v)).catch(() => {});
    page.addInitScript(() => {
      document.addEventListener('securitypolicyviolation', e => {
        (window as any).__e2eCsp?.({ directive: e.effectiveDirective, blocked: e.blockedURI, reportOnly: e.disposition === 'report' });
      });
    });
  }

  drain() {
    const out = { pageErrors: this.pageErrors, consoleErrors: this.consoleErrors, csp: this.csp, responses: this.responses };
    this.pageErrors = []; this.consoleErrors = []; this.csp = []; this.responses = [];
    return out;
  }
}

export interface CheckInput {
  page: Page;
  rec: Recorder;
  action: Action;
  step: number;
  state: StateId;
  role: 'user' | 'admin';
  myToken: string;
  lockedSecret: string;
  /** The visitor already unlocked the protected page in this session. */
  unlocked?: boolean;
}

export async function checkStep(i: CheckInput): Promise<{ problems: Finding[]; warnings: Finding[] }> {
  const problems: Finding[] = [];
  const warnings: Finding[] = [];
  const url = i.page.url();
  const add = (list: Finding[], kind: string, message: string) => list.push({ kind, message, step: i.step, action: i.action.id, url });
  const seen = i.rec.drain();

  for (const e of seen.pageErrors) add(problems, 'js-error', e);
  for (const e of seen.consoleErrors) add(problems, 'console-error', e);
  for (const c of seen.csp) add(c.reportOnly ? warnings : problems, c.reportOnly ? 'csp-report-only' : 'csp', `${c.directive} blocked ${c.blocked}`);
  for (const r of seen.responses) {
    const path = new URL(r.url).pathname;
    if (r.status >= 500) add(problems, 'http-5xx', `${r.method} ${path} -> ${r.status}`);
    else if (r.status === 419) add(problems, 'csrf-expired', `${r.method} ${path} -> 419`);
    else if (r.status === 429) add(problems, 'rate-limited', `${r.method} ${path} -> 429`);
    else if (r.document && r.status >= 400 && !i.action.expectsClientError) add(problems, 'http-4xx', `${r.method} ${path} -> ${r.status}`);
    else if (r.status >= 400 && !r.document) add(warnings, 'http-4xx-resource', `${r.method} ${path} -> ${r.status}`);
  }

  const dom = await i.page.evaluate(() => {
    const w = window.innerWidth, h = window.innerHeight;
    let overlay: string | null = null;
    let el: Element | null = document.elementFromPoint(w / 2, h / 2);
    while (el && el !== document.body && el !== document.documentElement) {
      const s = getComputedStyle(el);
      const r = el.getBoundingClientRect();
      if ((s.position === 'fixed' || s.position === 'absolute') && r.width >= w * 0.9 && r.height >= h * 0.9
        && s.pointerEvents !== 'none' && Number(s.opacity) > 0.05 && s.visibility !== 'hidden'
        && !el.querySelector('main, nav, form, a[href], button') ) {
        overlay = `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}.${[...el.classList].slice(0, 4).join('.')}`;
        break;
      }
      el = el.parentElement;
    }
    const inputs = [...document.querySelectorAll('input, textarea')].map(e => (e as HTMLInputElement).value).join(' ');
    return { overlay, text: (document.body?.innerText ?? '') + ' ' + inputs, html: document.documentElement.outerHTML };
  }).catch(() => null);
  if (!dom) return { problems, warnings };

  if (dom.overlay && !MODAL_STATES.includes(i.state)) add(problems, 'blocking-overlay', `an empty full-screen layer covers the page: ${dom.overlay}`);

  // Data leaks: another synthetic user's marker must never be visible to me.
  // Admins legitimately see everyone's data, so the check is for normal users.
  if (i.role === 'user') {
    const others = [...new Set(dom.text.match(TOKEN) ?? [])].filter(t => t !== i.myToken);
    if (others.length) add(problems, 'data-leak', `page shows data of other users: ${others.join(', ')}`);
  }
  // A password-protected bio page must not ship its links before it is unlocked.
  if (i.state === 'guest_bio_locked' && !i.unlocked && dom.html.includes(i.lockedSecret)) add(problems, 'locked-page-leak', 'locked bio page HTML contains a protected link');

  return { problems, warnings };
}

/** axe-core scan; serious/critical issues. Runs once per state per journey to keep journeys fast. */
export async function a11y(page: Page): Promise<string[]> {
  try {
    // preload: false stops axe from fetching cross-origin stylesheets itself (that fetch trips connect-src).
    const res = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).options({ preload: false }).analyze();
    return res.violations.filter(v => v.impact === 'serious' || v.impact === 'critical').map(v => `${v.id} (${v.impact}, ${v.nodes.length}x)`);
  } catch {
    return [];
  }
}
