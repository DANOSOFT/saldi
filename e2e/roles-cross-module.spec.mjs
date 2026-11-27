// 20261110 CDX/ASR Live permission matrix, real navigation, CRM isolation and immediate revocation.
// Opt in with SALDI_ROLE_MATRIX_FILE: private credentials for disposable profiles in an isolated deny-mode tenant.
// 20261124 CL/ASR Comments state the current rule; no code change.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const fixture = process.env.SALDI_ROLE_MATRIX_FILE ? JSON.parse(readFileSync(process.env.SALDI_ROLE_MATRIX_FILE, 'utf8')) : null;
const base = fixture?.base;
const pages = [
  ['index/dashboard.php', 'dashboard.view'],
  ['finans/kladdeliste.php', 'finans.kassekladde'],
  ['finans/regnskab.php', 'finans.regnskab'],
  ['finans/rapport.php', 'finans.rapporter'],
  ['systemdata/kontoplan.php', 'system.kontoplan'],
  ['debitor/ordreliste.php', 'debitor.ordre'],
  ['debitor/debitor.php', 'debitor.konti'],
  ['debitor/rapport.php', 'debitor.rapporter'],
  ['kreditor/ordreliste.php', 'kreditor.ordre'],
  ['kreditor/kreditor.php', 'kreditor.konti'],
  ['kreditor/rapport.php', 'kreditor.rapporter'],
  ['lager/varer.php', 'lager.varer'],
  ['lager/modtageliste.php', 'lager.varemodtagelse'],
  ['lager/rapport.php', 'lager.rapporter'],
  ['debitor/crm.php', 'crm.access'],
  ['debitor/crmkalender.php', 'crm.access'],
  ['debitor/historik.php', 'crm.access'],
  ['systemdata/settingsSection.php?s=company.data', 'settings.company'],
  ['systemdata/settingsSection.php?s=finance.vat', 'settings.finance'],
  ['systemdata/settingsSection.php?s=sales.debtor_groups', 'settings.sales'],
  ['systemdata/settingsSection.php?s=purchase.creditor_groups', 'settings.purchase'],
  ['systemdata/settingsSection.php?s=items.units', 'settings.items'],
  ['systemdata/settingsSection.php?s=documents.backgrounds', 'settings.documents'],
  ['systemdata/usersRoles.php?tab=roles', 'settings.roles.manage'],
  ['systemdata/usersRoles.php?tab=log', 'settings.audit.read'],
];
const mutations = [
  'finans/bogfor.php?kladde_id=2147483647', 'debitor/bogfor.php?id=2147483647', 'debitor/levering.php?id=2147483647',
  'kreditor/bogfor.php?id=2147483647', 'kreditor/modtag.php?id=2147483647',
  'lager/slet_shopbinding.php?id=2147483647', 'lager/updateSetKostprise.php?id=2147483647',
  'debitor/ny_rykker.php?id=2147483647', 'debitor/afslut_rykker.php?id=2147483647',
  'includes/documents.php?source=kassekladde&sourceId=2147483647&unlinkDoc=2147483647',
  'debitor/ordre.php?id=2147483647&konto_id=2147483647',
  'lager/lagerstatus.php?ajour=1', 'lager/lagerstatus.php?opdater=2147483647',
];

async function login(browser, name) {
  const account = fixture.accounts[name];
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await page.goto(`${base}/index/index.php`);
  await page.locator('[name="regnskab"]').fill(fixture.account);
  await page.locator('[name="brugernavn"]').fill(account.user);
  await page.locator('[name="password"]').fill(account.password);
  await page.getByRole('button', { name: 'Login', exact: true }).click();
  await page.waitForURL(/main\.php/);
  return { context, page, account };
}

async function logout(session) {
  if (!session) return;
  await session.page.goto(`${base}/index/logud.php`);
  await session.context.close();
}

for (const name of ['admin', 'read', 'none', 'crm_read', 'crm_write', 'sales_no_crm', 'report']) {
  test.describe(`live access: ${name}`, () => {
    test.skip(!fixture, 'Requires disposable role matrix fixtures');
    let session;
    test.beforeAll(async ({ browser }) => { session = await login(browser, name); });
    test.afterAll(async () => { await logout(session); });

    test('direct pages follow the granted module and settings permissions', async () => {
      for (const [path, key] of pages) {
        const response = await session.page.request.get(`${base}/${path}`);
        const granted = ['read', 'write'].includes(session.account.levels[key]);
        const logFallback = name === 'read' && key === 'settings.roles.manage';
        expect.soft(response.status(), `${name} ${path} (${key})`).toBe(granted || logFallback ? 200 : 403);
        if (logFallback) expect(await response.text()).not.toContain('id="ur-role-form"');
        if (granted) {
          expect.soft(await response.text(), `${name} ${path} renders without a fatal PHP error`).not.toMatch(/Fatal error:|Uncaught (?:Error|TypeError|PDOException)/);
        }
      }
    });

    test('navigation and landing page expose usable modules', async () => {
      const { page } = session;
      await page.goto(`${base}/index/main.php`);
      if (name === 'crm_read' || name === 'crm_write') {
        await expect(page).toHaveURL(/#\/debitor\/crm\.php/);
        await page.locator('#debitor').click();
        const link = page.locator('.sidebar a').filter({ hasText: /^CRM$/ });
        await expect(link).toBeVisible();
        await link.click();
        const crm = page.frameLocator('.content-iframe');
        await expect(crm.locator('#crm')).toBeVisible();
        for (const tab of ['Sager', 'Kalender', 'Kunder', 'Aktivitet', 'Opfølgning']) {
          await crm.getByRole('tab', { name: new RegExp('^' + tab) }).click();
          await expect(crm.getByRole('tab', { name: new RegExp('^' + tab) })).toHaveAttribute('aria-selected', 'true');
        }
        await expect(crm.locator('#newBtn')).toBeVisible({ visible: name === 'crm_write' });
        await expect(page.locator('.sidebar a[onclick*="/debitor/debitor.php"]')).toHaveCount(0);
      } else if (name === 'none' || name === 'sales_no_crm') {
        await expect(page.locator('.sidebar a').filter({ hasText: /^CRM$/ })).toHaveCount(0);
        if (name === 'none') await expect(page).toHaveURL(/#\/systemdata\/personalSettings\.php/);
      } else if (name === 'report') {
        await expect(page).toHaveURL(/#\/finans\/rapport\.php/);
      } else {
        for (const [group, path] of [['finans', '/finans/kladdeliste.php'], ['debitor', '/debitor/debitor.php'], ['kreditor', '/kreditor/kreditor.php'], ['lager', '/lager/varer.php']]) {
          await page.locator(`#${group}`).click();
          await page.locator(`.sidebar a[onclick*="${path}"]`).click();
          await expect(page.frameLocator('.content-iframe').locator('body')).not.toContainText('Du har ikke adgang');
        }
      }
    });

    if (['read', 'none', 'crm_read', 'crm_write', 'report'].includes(name)) {
      test('direct posting, delivery and stock mutations are refused', async () => {
        for (const path of mutations) {
          const response = await session.page.request.get(`${base}/${path}`);
          expect.soft(response.status(), `${name} ${path}`).toBe(403);
        }
        const write = await session.page.request.post(`${base}/finans/kassekladde.php`, { form: { save: '1' } });
        expect(write.status()).toBe(403);
      });
    }
  });
}

test.describe('CRM writes and permission changes', () => {
  test.skip(!fixture, 'Requires disposable role matrix fixtures');
  let writer, reader, admin;
  test.beforeAll(async ({ browser }) => {
    writer = await login(browser, 'crm_write');
    reader = await login(browser, 'crm_read');
    admin = await login(browser, 'admin');
  });
  test.afterAll(async () => { for (const session of [writer, reader, admin]) await logout(session); });

  test('CRM-only writer can create a lead and note; read-only cannot modify it or create orders', async () => {
    const page = writer.page;
    await page.goto(`${base}/debitor/crm.php?tab=customers`);
    await page.getByRole('tab', { name: /^Kunder/ }).click();
    await page.locator('#newBtn').click();
    await page.locator('#leadForm [name="n"]').fill(`${fixture.prefix} browser lead`);
    await page.locator('#leadForm [name="own"]').selectOption('0');
    await page.locator('#leadSave').click();
    await expect(page.locator('#scrim')).not.toHaveClass(/open/);
    await expect(page.locator('#drawer')).toContainText(`${fixture.prefix} browser lead`);
    const data = await page.locator('#crm-data').textContent();
    const csrf = JSON.parse(data).csrf;
    const api = async (session, action, extra = {}) => {
      const token = await session.page.locator('#crm-data').evaluate(el => JSON.parse(el.textContent).csrf);
      return session.page.request.post(`${base}/debitor/crmApi.php`, { headers: { 'X-CSRF-Token': token }, data: { action, ...extra } });
    };
    const current = await (await api(writer, 'data')).json();
    const lead = current.data.customers.find(row => row.n === `${fixture.prefix} browser lead`);
    expect(lead?.lead).toBeTruthy();
    const note = await api(writer, 'add_entry', { cid: lead.id, type: 'note', note: 'Permission regression note', owner: 0 });
    expect(note.status()).toBe(200);
    expect((await note.json()).ok).toBe(true);
    await reader.page.goto(`${base}/debitor/crm.php?drawer=${lead.id}`);
    const blocked = await api(reader, 'update_entry', { id: (await note.json()).result.id, note: 'Must not save' });
    expect(blocked.status()).toBe(403);
    const order = await api(writer, 'prepare_document', { cid: lead.id, kind: 'order', accept: true });
    expect((await order.json()).error).toBe('noaccess');
    const noCsrf = await page.request.post(`${base}/debitor/crmApi.php`, { data: { action: 'save_lead', n: 'Must not save' } });
    expect(noCsrf.status()).toBe(403);
    expect(csrf.length).toBeGreaterThan(20);
  });

  test('revoking CRM through the role editor blocks an existing session and calendar link', async () => {
    const page = writer.page;
    await page.goto(`${base}/debitor/crm.php`);
    const token = await page.locator('#crm-data').evaluate(el => JSON.parse(el.textContent).csrf);
    const feedResponse = await page.request.post(`${base}/debitor/crmApi.php`, { headers: { 'X-CSRF-Token': token }, data: { action: 'ical', mode: 'new' } });
    const feedData = await feedResponse.json();
    const url = feedData.result?.url || feedData.result;
    expect(typeof url).toBe('string');
    expect((await page.request.get(url)).status()).toBe(200);
    async function change(level) {
      await admin.page.goto(`${base}/systemdata/usersRoles.php?tab=roles&rolle=${writer.account.role}`);
      await admin.page.locator('[name="level[crm.access]"][value="' + level + '"][type="radio"]').check();
      await admin.page.locator('#ur-role-save').click();
      await expect(admin.page.locator('#ur-role-dialog-body')).toContainText('Kunder, sager, aktiviteter og kalender');
      await admin.page.locator('[data-role-dialog-confirm]').click();
      await admin.page.waitForURL(/msg=role/);
    }
    try {
      await change('none');
      expect((await page.request.get(`${base}/debitor/crm.php`)).status()).toBe(403);
      expect((await page.request.post(`${base}/debitor/crmApi.php`, { headers: { 'X-CSRF-Token': token }, data: { action: 'data' } })).status()).toBe(403);
      expect((await page.request.get(url)).status()).toBe(404);
    } finally {
      await change('write');
    }
    expect((await page.request.get(`${base}/debitor/crm.php`)).status()).toBe(200);
    // ordre.php links a CRM case only with the session's CSRF token (crm_csrf), also for an administrator.
    expect((await admin.page.request.get(`${base}/debitor/ordre.php?id=2147483647&crm_deal=2147483647`)).status()).toBe(403);
  });

  test('legacy bookmarks preserve access and reject retired mutation paths', async () => {
    for (const session of [writer, reader]) {
      const bookmark = await session.page.request.get(`${base}/debitor/historikkort.php?id=1`, { maxRedirects: 0 });
      expect(bookmark.status()).toBe(302);
      expect(bookmark.headers().location).toContain('crm.php?tab=cust&drawer=1');
      for (const path of ['debitor/historikkort.php?id=2147483647&handling=slet', 'debitor/crmkalender.php?action=toggle&id=2147483647']) {
        expect((await session.page.request.get(`${base}/${path}`)).status()).toBe(session === writer ? 405 : 403);
      }
      expect((await session.page.request.get(`${base}/debitor/debitor.php?valg=historik`)).status()).toBe(200);
    }
  });
});

test.describe('reports and documents under enforcement', () => {
  test.skip(!fixture, 'Requires disposable role matrix fixtures and harmless document images');
  let reader, sales;
  test.beforeAll(async ({ browser }) => {
    reader = await login(browser, 'read');
    sales = await login(browser, 'sales_no_crm');
  });
  test.afterAll(async () => { await logout(reader); await logout(sales); });

  test('read-only report forms work while arbitrary actions and renderers are refused', async () => {
    for (const module of ['debitor', 'kreditor']) {
      const response = await reader.page.request.post(`${base}/${module}/rapport.php`, { form: { dato: '2026', konto: '', openpost: '1' } });
      expect(response.status()).toBe(200);
      expect(await response.text()).not.toMatch(/Fatal error:|Uncaught (?:Error|TypeError)/);
      const write = await reader.page.request.post(`${base}/${module}/rapport.php`, { form: { rapportart: 'openpost', submit: 'slet' } });
      expect(write.status()).toBe(403);
      const arbitrary = await reader.page.request.get(`${base}/${module}/rapport.php?rapportart=db_modify`);
      expect(arbitrary.status()).toBe(400);
    }
  });

  test('document previews require their owning module, including normalized paths', async () => {
    const endpoint = `${base}/includes/docsIncludes/docFile.php?k=doc&f=`;
    expect((await reader.page.request.get(endpoint + 'finance/roles-read-fixture.png')).status()).toBe(200);
    expect((await sales.page.request.get(endpoint + 'finance/roles-read-fixture.png')).status()).toBe(403);
    expect((await sales.page.request.get(endpoint + 'debitor/orders/roles-read-fixture.png')).status()).toBe(200);
    expect((await sales.page.request.get(endpoint + 'debitor/../finance/roles-read-fixture.png')).status()).toBe(403);
    expect((await sales.page.request.get(`${base}/debitor/historikkort.php?id=1`)).status()).toBe(403);
    expect((await sales.page.request.get(`${base}/debitor/increaseInvoiceNumber.php?db=roles_test_20261110_552`)).status()).toBe(410);
  });

  test('supplier lookups cannot be opened through the customer endpoint without supplier access', async () => {
    for (const endpoint of ['debitor/accountLookupData.php?o_art=KO', 'kreditor/accountLookupData.php']) {
      expect((await sales.page.request.get(`${base}/${endpoint}`)).status()).toBe(403);
      expect((await reader.page.request.get(`${base}/${endpoint}`)).status()).toBe(200);
    }
    expect((await sales.page.request.post(`${base}/debitor/accountLookupData.php?o_art=KO`, { form: { search: '' } })).status()).toBe(403);
  });
});
