// 20261110 CDX/ASR Read-only integration coverage of people navigation and invitation permission previews.
import { test, expect } from '@playwright/test';

const base = process.env.SALDI_ROLES_BASE;
const configured = base && process.env.SALDI_E2E_ACCOUNT && process.env.SALDI_E2E_USER && process.env.SALDI_E2E_PASSWORD;

test.describe('people access workflows', () => {
  test.skip(!configured, 'Requires an explicitly configured isolated role-test installation');
  test.describe.configure({ mode: 'serial' });
  let context, page;
  const failures = [];

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext({ ignoreHTTPSErrors: true });
    page = await context.newPage();
    page.on('pageerror', error => failures.push(error.message));
    page.on('dialog', dialog => dialog.accept());
    await page.goto(`${base}/index/index.php`);
    await page.locator('[name="regnskab"]').fill(process.env.SALDI_E2E_ACCOUNT);
    await page.locator('[name="brugernavn"]').fill(process.env.SALDI_E2E_USER);
    await page.locator('[name="password"]').fill(process.env.SALDI_E2E_PASSWORD);
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.waitForURL(/main\.php/);
  });

  test.afterAll(async () => {
    if (page) { await page.goto(`${base}/index/logud.php`); }
    if (context) { await context.close(); }
  });

  test('search counts and selection survive opening and closing a person card', async () => {
    await page.goto(`${base}/systemdata/usersRoles.php?tab=users`);
    const first = page.locator('#ur-users tbody tr').filter({ has: page.locator('input[name="ids[]"]') }).first();
    const query = await first.getAttribute('data-search');
    await page.locator('#ur-search').fill(query);
    await expect(page.locator('#ur-users tbody tr:not([hidden])')).toHaveCount(1);
    await expect(page.locator('[data-people-status=""] .ur-filter-count')).toHaveText('1');
    await first.locator('input[name="ids[]"]').check();
    const id = await first.locator('input[name="ids[]"]').inputValue();
    await first.locator('a').first().click();
    await expect(page.locator('#ur-drawer')).toBeVisible();
    await page.locator('#ur-drawer .sk-drawer-h a').click();
    await expect(page.locator('#ur-search')).toHaveValue(query);
    await expect(page.locator('#ur-users input[name="ids[]"]:checked')).toHaveValue(id);
  });

  test('invitation shows every registered permission separately and fits a narrow screen', async () => {
    await page.goto(`${base}/systemdata/usersRoles.php?tab=users&bruger=0`);
    const keys = await page.locator('#ur-access-data').evaluate(el => Object.keys(JSON.parse(el.textContent).keys).sort());
    expect(keys).toContain('crm.access');
    expect(await page.locator('#ur-access [data-key]').evaluateAll(rows => rows.map(row => row.dataset.key).sort())).toEqual(keys);
    const choice = await page.locator('#ur-role option:not([disabled])').first().getAttribute('value');
    await page.locator('#ur-role').selectOption(choice);
    await expect(page.locator('#ur-role-description')).not.toBeEmpty();
    await page.locator('#ur-access-details > summary').click();
    await expect(page.locator('#ur-access [data-key] strong')).toHaveCount(keys.length);
    await expect(page.locator('#ur-access [data-key="settings.integrations"] strong')).not.toBeEmpty();
    await expect(page.locator('#ur-access [data-key="settings.organisation.sensitive"] strong')).not.toBeEmpty();
    await expect(page.locator('.ur-mode-note')).toContainText(/Logtilstand|Håndhævelse/);
    await expect(page.locator('.ur-card-section[open]')).toHaveCount(0);
    await expect(page.locator('button[type="submit"][form="ur-user-form"]')).toHaveText('Send invitation');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.locator('#ur-drawer').evaluate(el => el.scrollWidth <= el.clientWidth + 1)).toBe(true);
    expect(failures).toEqual([]);
  });
});
