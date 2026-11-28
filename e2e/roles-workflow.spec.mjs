// 20261110 CDX/ASR Real role drafts, impact confirmation, conflicting saves and responsive navigation.
// Opt in with SALDI_ROLES_BASE and the ordinary SALDI_E2E_* credentials of an isolated test tenant.
import { test, expect } from '@playwright/test';

const base = process.env.SALDI_ROLES_BASE;
const configured = base && process.env.SALDI_E2E_ACCOUNT && process.env.SALDI_E2E_USER && process.env.SALDI_E2E_PASSWORD;

test.describe('roles administration workflows', () => {
  test.skip(!configured, 'Requires an explicitly configured isolated role-test installation');
  test.describe.configure({ mode: 'serial' });
  let context, page, roleId;
  const roleName = `Role regression ${Date.now()}`;
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
    if (roleId && context) {
      // Teardown through the same protected endpoint; no users are assigned to this fixture role.
      const cleanup = await context.newPage();
      await cleanup.goto(`${base}/systemdata/usersRoles.php?tab=roles&rolle=${roleId}`);
      const form = cleanup.locator('#ur-role-delete-form');
      if (await form.count()) {
        await form.evaluate(el => el.submit());
        await cleanup.waitForLoadState('networkidle');
      }
      await cleanup.close();
    }
    if (page) { await page.goto(`${base}/index/logud.php`); }
    if (context) { await context.close(); }
  });

  test('protects own access and fits all permission choices at 390 pixels', async () => {
    await page.goto(`${base}/systemdata/usersRoles.php?tab=roles`);
    const registeredKeys = await page.locator('#ur-role-editor-data').evaluate(el => Object.keys(JSON.parse(el.textContent).labels).sort());
    const renderedKeys = await page.locator('.ur-permission').evaluateAll(rows => rows.map(row => row.dataset.key).sort());
    expect(renderedKeys, 'Every registered permission must be editable, including new module groups').toEqual(registeredKeys);
    await expect(page.locator('[data-role-group="crm"] [data-key="crm.access"]')).toHaveCount(1);
    await expect(page.locator('[name="level[settings.roles.manage]"][value="none"][type="radio"]')).toBeDisabled();
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#ur-role-picker')).toBeVisible();
    await expect(page.locator('.ur-role-nav')).toBeHidden();
    const fits = await page.locator('#ur-role-form').evaluate(el => el.scrollWidth <= el.clientWidth + 1);
    expect(fits, 'The complete editor must fit without horizontal scrolling').toBe(true);
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.evaluate(() => window.scrollTo(0, 1000));
    await expect(page.locator('.ur-role-savebar')).toBeInViewport();
    await expect(page.locator('.ur-level-head')).toBeInViewport();
    const sticky = await page.locator('.ur-role-savebar').evaluate(el => el.getBoundingClientRect().top);
    expect(sticky, 'The role name and save controls stay at the top while scrolling').toBeGreaterThanOrEqual(-1);
    expect(sticky).toBeLessThan(10);
  });

  test('template remains a draft, Undo restores it and navigation protects edits', async () => {
    await page.goto(`${base}/systemdata/usersRoles.php?tab=roles&rolle=0`);
    await page.locator('#ur-role-template').selectOption({ label: 'Bogholder' });
    await expect(page.locator('#ur-role-undo')).toBeEnabled();
    await page.locator('#ur-role-undo').click();
    await expect(page.locator('#ur-role-undo')).toBeDisabled();
    await page.locator('[name="navn"]').fill(roleName);
    await page.locator('.ur-role-nav a').first().click();
    await expect(page.locator('#ur-role-dialog')).toBeVisible();
    await page.locator('[data-role-dialog-cancel]').click();
    await expect(page.locator('[name="navn"]')).toHaveValue(roleName);
  });

  test('saves only after showing the exact permission change', async () => {
    await page.locator('[name="level[finans.rapporter]"][value="read"][type="radio"]').check();
    await page.locator('#ur-role-save').click();
    await expect(page.locator('#ur-role-dialog-body')).toContainText('Finansrapport');
    await expect(page.locator('#ur-role-dialog-body')).toContainText('Ingen adgang → Kun visning');
    await page.locator('[data-role-dialog-confirm]').click();
    await page.waitForURL(/msg=role/);
    roleId = new URL(page.url()).searchParams.get('rolle');
    expect(Number(roleId)).toBeGreaterThan(0);
    await expect(page.locator('[name="level[finans.rapporter]"][value="read"][type="radio"]')).toBeChecked();
    await expect(page.locator('.ur-role-nav')).toContainText(roleName);
  });

  test('rejects a second editor saving an old revision', async () => {
    const second = await context.newPage();
    await second.goto(`${base}/systemdata/usersRoles.php?tab=roles&rolle=${roleId}`);
    await page.locator('[name="beskrivelse"]').fill('First editor saved this description');
    await page.locator('#ur-role-save').click();
    await page.locator('[data-role-dialog-confirm]').click();
    await page.waitForURL(/msg=role/);
    await expect(page.locator('[name="beskrivelse"]')).toHaveValue('First editor saved this description');
    await second.locator('[name="beskrivelse"]').fill('Stale editor must not overwrite');
    await second.locator('#ur-role-save').click();
    await second.locator('[data-role-dialog-confirm]').click();
    await second.waitForURL(/msg=rolestale/);
    await expect(second.locator('[name="beskrivelse"]')).toHaveValue('First editor saved this description');
    await second.close();
  });

  test('finds roles for rettigheder without empty settings groups', async () => {
    await page.goto(`${base}/systemdata/settings.php`);
    await page.locator('#sh-search').fill('rettigheder');
    await expect(page.locator('.sh-row:visible .sh-link-t').first()).toHaveText('Roller');
    await expect(page.locator('.sh-panel:visible')).toHaveCount(1);
    expect(failures).toEqual([]);
  });
});
