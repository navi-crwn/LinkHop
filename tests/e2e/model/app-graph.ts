/**
 * Map of LinkHop as a state machine for the synthetic-user suite.
 *
 * Accounts live at the hub (HOP ID): there is no sign-in form here. The `login`
 * action uses the test-only hook POST /_e2e/login, which does what the SSO
 * callback does after a verified ticket (routes/e2e.php, E2E_HOOKS=true only).
 *
 * A state is "where the user is" (page + condition). An action is anything a
 * person can do from a state; `to` is the state it should lead to. Journeys are
 * walks over this graph, so every new page or control should be added here.
 */
import type { Page } from '@playwright/test';

export type StateId =
  | 'guest_check' | 'guest_bio_public' | 'guest_bio_locked'
  | 'user_dashboard' | 'user_links' | 'user_link_modal' | 'user_link_created' | 'user_link_show' | 'user_link_edit'
  | 'user_bio_index' | 'user_bio_editor' | 'user_bio_block_editor' | 'user_analytics' | 'user_settings'
  | 'user_folders' | 'user_folder_modal' | 'user_tags' | 'user_tag_modal'
  | 'admin_retired';

export interface Persona {
  token: string; email: string; password: string; admin: boolean;
  bio: string; locked: string; lockedSecret: string; links: string[];
}

export interface Ctx {
  page: Page;
  me: Persona;
  /** Tokens of every other persona created in this run (must never show up on my pages). */
  others: () => string[];
  /** Free-form memory between steps, e.g. block order before a reload. */
  memo: Record<string, unknown>;
}

export interface Action {
  id: string;
  from: StateId[] | 'any-user' | 'any';
  to: StateId | 'same';
  /** Steps that only make sense for an admin. */
  adminOnly?: boolean;
  /** Expected to raise a validation error / 4xx; the oracle tolerates 4xx for this step. */
  expectsClientError?: boolean;
  run: (ctx: Ctx) => Promise<void>;
}

const USER_STATES: StateId[] = ['user_dashboard', 'user_links', 'user_link_show', 'user_link_edit', 'user_bio_index', 'user_bio_editor',
  'user_analytics', 'user_settings', 'user_folders', 'user_tags', 'admin_retired'];
export const GUEST_STATES: StateId[] = ['guest_check', 'guest_bio_public', 'guest_bio_locked'];
export const MODAL_STATES: StateId[] = ['user_link_modal', 'user_link_created', 'user_bio_block_editor', 'user_folder_modal', 'user_tag_modal'];

/** Click a sidebar link when it is visible (desktop), otherwise navigate like the mobile drawer would. */
async function nav(page: Page, path: string) {
  const link = page.locator(`a.nav-item[href$="${path}"], a.nav-sub[href$="${path}"]`).first();
  if (await link.isVisible().catch(() => false)) {
    await link.click();
    // The bio editor asks before leaving with unsaved changes; a person confirms.
    // isVisible() looks only once (its timeout is ignored), so wait for either the
    // new page or the dialog, whichever comes first.
    const arrived = page.waitForURL(u => u.pathname.endsWith(path), { waitUntil: 'domcontentloaded' });
    const discard = page.getByRole('button', { name: 'Discard Changes' });
    const confirmed = discard.waitFor({ state: 'visible' }).then(() => discard.click()).catch(() => {});
    await Promise.race([arrived, confirmed]);
    await arrived;
  } else {
    await page.goto(path);
  }
}

const editorData = (page: Page) => page.evaluate(() => {
  const el = document.querySelector('[x-data="window.bioEditorV2()"]') as any;
  const c = (window as any).Alpine?.$data(el);
  return c ? c.blocks.map((b: any) => `${b.id}`) : null;
});

export const ACTIONS: Action[] = [
  // ---- guests ----
  // link.hel.ink/ sends a guest to the hub (another host, not in the suite): the public pages here are the checker and bio pages.
  { id: 'open_check', from: 'any', to: 'guest_check', run: async ({ page }) => { await page.goto('/check'); } },
  {
    id: 'check_own_link', from: ['guest_check'], to: 'guest_check',
    run: async ({ page, me }) => {
      await page.fill('input[name="url"]', `${new URL(page.url()).origin}/${me.links[0]}`);
      await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('form[action$="/check"] button').first().click()]);
    },
  },
  {
    id: 'check_weird_input', from: ['guest_check'], to: 'guest_check', expectsClientError: true,
    run: async ({ page }) => {
      await page.fill('input[name="url"]', 'javascript:alert(1)//' + 'x'.repeat(300));
      await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('form[action$="/check"] button').first().click()]);
    },
  },
  { id: 'open_public_bio', from: 'any', to: 'guest_bio_public', run: async ({ page, me }) => { await page.goto(`/b/${me.bio}`); } },
  { id: 'open_locked_bio', from: 'any', to: 'guest_bio_locked', run: async ({ page, me }) => { await page.goto(`/b/${me.locked}`); } },
  {
    id: 'unlock_wrong_password', from: ['guest_bio_locked'], to: 'guest_bio_locked',
    run: async ({ page, memo }) => {
      if (memo.unlocked) return; // the session already unlocked this page: no form any more
      await page.fill('input[name="password"]', 'wrong-pass');
      await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('button[type=submit]').first().click()]);
    },
  },
  {
    id: 'unlock_right_password', from: ['guest_bio_locked'], to: 'guest_bio_public',
    run: async ({ page, memo }) => {
      if (memo.unlocked) return;
      await page.fill('input[name="password"]', 'secret123');
      await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('button[type=submit]').first().click()]);
      memo.unlocked = true;
    },
  },
  {
    // The hub's SSO ends in the callback signing the shared user in; the test hook does the same without the hub.
    id: 'login', from: GUEST_STATES, to: 'user_dashboard',
    run: async ({ page, me }) => {
      const res = await page.request.post('/_e2e/login', { form: { email: me.email } });
      if (!res.ok()) throw new Error(`/_e2e/login answered ${res.status()}`);
      await page.goto('/dashboard');
    },
  },
  {
    // A guest asking for a member page is sent to the hub's sign-in (the SSO start), never shown it.
    id: 'guest_dashboard_redirects_to_sso', from: GUEST_STATES, to: 'same',
    run: async ({ page }) => {
      const res = await page.request.get('/dashboard', { maxRedirects: 0 });
      const to = res.headers()['location'] ?? '';
      if (res.status() !== 302 || !to.includes('/auth/sso')) throw new Error(`INVARIANT guest /dashboard answered ${res.status()} ${to}, expected a redirect to /auth/sso`);
    },
  },

  // ---- signed-in navigation ----
  { id: 'nav_dashboard', from: 'any-user', to: 'user_dashboard', run: async ({ page }) => nav(page, '/dashboard') },
  { id: 'nav_links', from: 'any-user', to: 'user_links', run: async ({ page }) => nav(page, '/links') },
  { id: 'nav_bio', from: 'any-user', to: 'user_bio_index', run: async ({ page }) => nav(page, '/bio') },
  { id: 'nav_analytics', from: 'any-user', to: 'user_analytics', run: async ({ page }) => nav(page, '/analytics') },
  { id: 'nav_settings', from: 'any-user', to: 'user_settings', run: async ({ page }) => nav(page, '/settings') },
  { id: 'nav_folders', from: 'any-user', to: 'user_folders', run: async ({ page }) => nav(page, '/folders') },
  { id: 'nav_tags', from: 'any-user', to: 'user_tags', run: async ({ page }) => nav(page, '/tags') },
  { id: 'browser_reload', from: 'any-user', to: 'same', run: async ({ page }) => { await page.reload(); } },
  {
    // Log out ends here and goes on to the hub (another host): the suite only follows the first hop.
    id: 'logout', from: 'any-user', to: 'guest_check',
    run: async ({ page }) => {
      // The cookie banner sits on top of the sidebar's log out button; a person dismisses it first.
      const decline = page.locator('button:has-text("Decline")').first();
      if (await decline.isVisible().catch(() => false)) await decline.click();
      let btn = page.locator('button[aria-label="Log out"]').first();
      if (!(await btn.isVisible().catch(() => false))) {
        // Public pages and the mobile layout have no visible log out button: use the dashboard's.
        await page.goto('/dashboard');
        if (await decline.isVisible().catch(() => false)) await decline.click();
        btn = page.locator('button[aria-label="Log out"]').first();
      }
      const form = btn.locator('xpath=ancestor::form[1]');
      const token = await form.locator('input[name=_token]').inputValue();
      // Submit the form through the request API: the browser would follow the redirect to the hub.
      const res = await page.request.post('/logout', { form: { _token: token }, maxRedirects: 0 });
      if (res.status() !== 302) throw new Error(`POST /logout answered ${res.status()}`);
      await page.goto('/check');
    },
  },

  // ---- links ----
  {
    id: 'open_link_modal', from: ['user_dashboard', 'user_links', 'user_analytics'], to: 'user_link_modal',
    run: async ({ page }) => {
      await page.locator('.create-link-trigger').first().click();
      await page.locator('[x-data="linkCreationModal()"] input[x-model="formData.target_url"]').first().waitFor({ state: 'visible' });
    },
  },
  {
    id: 'modal_toggle_dropdowns', from: ['user_link_modal'], to: 'user_link_modal',
    run: async ({ page }) => {
      await page.locator('button:has-text("No Tags Selected")').first().click();
      await page.locator('button:has(span[x-text*="folders.find"])').first().click();
      await page.locator('[x-data="linkCreationModal()"] input[x-model="formData.target_url"]').first().click();
    },
  },
  {
    id: 'modal_create_link', from: ['user_link_modal'], to: 'user_link_created',
    run: async ({ page, me, memo }) => {
      const url = `https://example.com/${me.token}/new-${Date.now()}`;
      memo.createdUrl = url;
      await page.locator('[x-data="linkCreationModal()"] input[x-model="formData.target_url"]').first().fill(url);
      await page.locator('[x-data="linkCreationModal()"] button[type=submit]').first().click();
      await page.getByText('Link Created Successfully').first().waitFor({ timeout: 10000 });
    },
  },
  {
    id: 'modal_weird_url', from: ['user_link_modal'], to: 'user_link_modal', expectsClientError: true,
    run: async ({ page }) => {
      const input = page.locator('[x-data="linkCreationModal()"] input[x-model="formData.target_url"]').first();
      await input.fill('javascript:alert(document.cookie)');
      await page.locator('[x-data="linkCreationModal()"] button[type=submit]').first().click({ trial: false }).catch(() => {});
      await page.waitForTimeout(600);
      await input.fill('https://例え.テスト/' + 'ü'.repeat(40) + ' spaces ');
      await page.waitForTimeout(300);
    },
  },
  {
    id: 'modal_close', from: ['user_link_modal', 'user_link_created'], to: 'same',
    run: async ({ page }) => { await page.keyboard.press('Escape'); await page.waitForTimeout(300); },
  },
  {
    id: 'open_own_link', from: ['user_links'], to: 'user_link_show',
    run: async ({ page, me }) => { await page.goto(`/links/${await linkId(page, me.links[0])}`); },
  },
  {
    id: 'edit_own_link', from: ['user_link_show', 'user_links'], to: 'user_link_edit',
    run: async ({ page, me }) => { await page.goto(`/links/${await linkId(page, me.links[0])}/edit`); },
  },
  {
    id: 'save_link_edit', from: ['user_link_edit'], to: 'user_link_show',
    run: async ({ page }) => {
      await Promise.all([page.waitForLoadState('domcontentloaded'), page.locator('form[method="POST"] button[type=submit]').last().click()]);
    },
  },
  {
    id: 'idor_other_users_link', from: 'any-user', to: 'same', expectsClientError: true,
    run: async ({ page, me }) => {
      // Ids are sequential: probe a few low ids. A link that is not mine must be 403/404 for a normal user.
      if (me.admin) return;
      for (const id of [1, 2, 3]) {
        const res = await page.request.get(`/links/${id}/edit`);
        const body = await res.text();
        if (res.status() === 200 && !body.includes(me.token) && /name="target_url"|name="slug"/.test(body)) {
          throw new Error(`INVARIANT /links/${id}/edit of another user is readable (200)`);
        }
      }
    },
  },

  // ---- folders / tags ----
  { id: 'open_folder_modal', from: ['user_folders'], to: 'user_folder_modal', run: async ({ page }) => { await page.locator('button[data-call="openFolderModal"]').first().click(); await page.getByRole('heading', { name: 'Create New Folder' }).waitFor(); } },
  { id: 'close_folder_modal', from: ['user_folder_modal'], to: 'user_folders', run: async ({ page }) => { await page.keyboard.press('Escape'); await page.waitForTimeout(400); } },
  { id: 'open_tag_modal', from: ['user_tags'], to: 'user_tag_modal', run: async ({ page }) => { await page.locator('button[data-call="openTagModal"]').first().click(); await page.getByRole('heading', { name: 'Create New Tag' }).waitFor(); } },
  { id: 'close_tag_modal', from: ['user_tag_modal'], to: 'user_tags', run: async ({ page }) => { await page.keyboard.press('Escape'); await page.waitForTimeout(400); } },

  // ---- link in bio ----
  { id: 'open_bio_editor', from: ['user_bio_index'], to: 'user_bio_editor', run: async ({ page, me }) => { await page.goto(`/bio/${me.bio}/edit`); await page.waitForSelector('#blocks-list .block-item'); } },
  {
    id: 'editor_add_link_block', from: ['user_bio_editor'], to: 'user_bio_block_editor',
    run: async ({ page }) => { await page.locator('button.quick-add-btn-mini:has-text("Link")').first().click(); await page.waitForTimeout(300); },
  },
  {
    id: 'block_editor_fill_and_close', from: ['user_bio_block_editor'], to: 'user_bio_editor',
    run: async ({ page, me }) => {
      const url = page.locator('input[placeholder="ex. https://longurl.com"]');
      if (await url.isVisible().catch(() => false)) await url.fill(`example.net/${me.token}/added`);
      const title = page.locator('input[placeholder="Button title"]');
      if (await title.isVisible().catch(() => false)) await title.fill(`${me.token}-added`);
      const cancel = page.locator('button:has-text("Cancel")').first();
      if (await cancel.isVisible().catch(() => false)) await cancel.click(); else await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
    },
  },
  {
    id: 'editor_drag_first_block', from: ['user_bio_editor'], to: 'user_bio_editor',
    run: async ({ page, memo }) => {
      const handles = page.locator('#blocks-list .block-item .block-drag-handle');
      if ((await handles.count()) < 3) return;
      const a = (await handles.nth(0).boundingBox())!, b = (await handles.nth(2).boundingBox())!;
      await page.mouse.move(a.x + a.width / 2, a.y + a.height / 2);
      await page.mouse.down();
      for (let i = 1; i <= 15; i++) await page.mouse.move(a.x + a.width / 2, a.y + (b.y - a.y + 12) * i / 15 + a.height / 2);
      await page.mouse.up();
      await page.waitForTimeout(1500); // reorder autosave is debounced
      memo.orderBeforeReload = await editorData(page);
    },
  },
  {
    // Keyboard alternative to dragging: arrow keys on the focused drag handle.
    id: 'editor_keyboard_move_block', from: ['user_bio_editor'], to: 'user_bio_editor',
    run: async ({ page, memo }) => {
      const handles = page.locator('#blocks-list .block-item .block-drag-handle');
      if ((await handles.count()) < 2) return;
      const before = await editorData(page);
      await handles.first().focus();
      await page.keyboard.press('ArrowDown');
      await page.waitForTimeout(1500); // reorder autosave is debounced
      const after = await editorData(page);
      if (before && after && before[0] !== after[1]) throw new Error(`INVARIANT keyboard move did not move the first block: before=${before} after=${after}`);
      const focused = await page.evaluate(() => document.activeElement?.classList.contains('block-drag-handle'));
      if (!focused) throw new Error('INVARIANT focus left the drag handle after a keyboard move');
      memo.orderBeforeReload = after;
    },
  },
  {
    // Block editor dialog: focus moves in, Tab stays in, Escape closes and returns focus.
    id: 'editor_dialog_keyboard', from: ['user_bio_editor'], to: 'user_bio_editor',
    run: async ({ page }) => {
      const edit = page.locator('#blocks-list .block-item .block-action-btn:not(.delete)').first();
      if (!(await edit.isVisible().catch(() => false))) return;
      await edit.focus();
      await page.keyboard.press('Enter');
      const dialog = page.getByRole('dialog', { name: 'Edit Block' });
      await dialog.waitFor();
      await page.waitForTimeout(100);
      const inside = () => page.evaluate(() => !!document.activeElement?.closest('[role="dialog"]'));
      if (!(await inside())) throw new Error('INVARIANT focus did not move into the block editor dialog');
      for (let i = 0; i < 30; i++) await page.keyboard.press('Tab');
      if (!(await inside())) throw new Error('INVARIANT Tab moved focus out of the block editor dialog');
      await page.keyboard.press('Escape');
      await dialog.waitFor({ state: 'detached' });
      await page.waitForTimeout(100);
      const back = await page.evaluate(() => document.activeElement?.classList.contains('block-action-btn'));
      if (!back) throw new Error('INVARIANT focus did not return to the edit button after Escape');
    },
  },
  {
    id: 'editor_toggle_block', from: ['user_bio_editor'], to: 'user_bio_editor',
    run: async ({ page }) => { await page.locator('#blocks-list .block-item .toggle-switch').first().click(); },
  },
  {
    id: 'editor_save', from: ['user_bio_editor'], to: 'user_bio_editor',
    run: async ({ page, memo }) => {
      await page.getByRole('button', { name: 'Save Changes' }).first().click();
      await page.waitForTimeout(2000);
      memo.orderBeforeReload = await editorData(page);
    },
  },
  {
    id: 'editor_reload_keeps_order', from: ['user_bio_editor'], to: 'user_bio_editor',
    run: async ({ page, memo }) => {
      await page.reload();
      await page.waitForSelector('#blocks-list .block-item');
      await page.waitForTimeout(500);
      const now = await editorData(page);
      const before = memo.orderBeforeReload as string[] | undefined;
      // Unsaved temp blocks (ids >= 1e9) are dropped on reload by design.
      const saved = (ids: string[]) => ids.filter(id => Number(id) < 1e9);
      if (before && now && JSON.stringify(saved(before)) !== JSON.stringify(saved(now))) {
        throw new Error(`INVARIANT block order changed after reload: before=${saved(before)} after=${saved(now)}`);
      }
      memo.orderBeforeReload = now;
    },
  },

  // ---- admin ----
  // The admin pages moved to the HOP control center (Imperium). Without HOP_CONTROL_URL,
  // /admin/* shows where they went; the old pages must be gone for good.
  {
    id: 'admin_control_center', from: 'any-user', to: 'admin_retired', adminOnly: true,
    run: async ({ page }) => {
      await page.goto('/admin');
      await page.getByRole('heading', { name: 'The admin panel moved' }).waitFor();
      for (const path of ['/admin/users', '/admin/links', '/admin/abuse', '/admin/bio']) {
        const res = await page.request.get(path, { maxRedirects: 0 });
        const body = await res.text();
        if (res.status() === 200 && !body.includes('The admin panel moved')) throw new Error(`INVARIANT ${path} still serves an admin page`);
      }
    },
  },
];

async function linkId(page: Page, slug: string): Promise<string> {
  const html = await page.request.get('/links').then(r => r.text());
  // The list shows the link as /l/slug (the hel.ink/l/{slug} form); /slug is the form before the move.
  const m = html.match(new RegExp(`href="[^"]*/links/([^"/]+)"[^>]*>\\s*(?:/l)?/${slug}\\s*<`));
  if (!m) throw new Error(`could not find my link ${slug} on /links`);
  return m[1];
}

export const ACTION_BY_ID = Object.fromEntries(ACTIONS.map(a => [a.id, a]));

export function allowedFrom(action: Action, state: StateId, isAdmin: boolean): boolean {
  if (action.adminOnly && !isAdmin) return false;
  if (action.from === 'any') return true;
  if (action.from === 'any-user') return USER_STATES.includes(state);
  return action.from.includes(state);
}

export const ALL_STATES: StateId[] = [...GUEST_STATES, ...USER_STATES, ...MODAL_STATES];
