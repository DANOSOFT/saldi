// 20261110 CDX/ASR CRM module access stays separate from financial links and calendar changes.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// Exercise the production renderers with an isolated dataset, before page-wide event wiring.
// No requests reach an application or a database.
const source = readFileSync(new URL('../javascript/crm.js', import.meta.url), 'utf8');
const boundary = source.indexOf('/* ================= hændelser ================= */');
if (boundary < 0) { throw new Error('CRM renderer boundary was not found'); }
const script = source.slice(0, boundary) + `
load(DATA);
window.crmAccessTest = {
  drawer(tab = 'deals', lead = false) { C[0].lead = lead; S.drawer = C[0].id; S.dtab = tab; renderDrawer(); },
  customers: renderCust,
  calendar: icalDialog,
  documentAllowed(lead) { return canDocument({lead}); },
  documentUrl(deal) { return documentUrl(C[0].id, true, deal); },
  update(flags) { Object.assign(DATA, flags); }
};
})();`;

async function fixture(page, flags = {}) {
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const doc = { id: 90, nr: '90001', st: 1, date: '2026-11-01', sum: 9876 };
  const data = {
    today: '2026-11-10', lang: 1, me: 2, statuses: [], groups: [], hist: [], emps: [],
    customers: [{ id: 10, n: 'CRM customer', nr: '1001', st: '', by: '', ppl: [], docs: [doc], since: '2026-01-01', rev: 54321, bal: 8765, late: 2, lead: false }],
    deals: [{ id: 8, cid: 10, t: 'CRM case', state: 'open', st: '', own: 0, v: 100, doc }],
    txt: {}, jobs: true, quick: false, canWrite: false, canOrder: false, canOrderRead: false,
    canDebtorRead: false, canDebtorWrite: false, canConvertLead: false,
    canRevenueRead: false, canBalanceRead: false, ical: 'https://calendar.invalid/example', ...flags
  };
  await page.route('http://crm-access.test/**', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><button id="toastU"></button><button id="toastX"></button><div id="drawer"></div><div id="v-cust"></div><div id="scrim"><div id="dlg"></div></div><script id="crm-data" type="application/json">${JSON.stringify(data)}</script>` }));
  await page.goto('http://crm-access.test/');
  await page.addScriptTag({ content: script });
  expect(errors, 'Production CRM renderers should initialize without errors').toEqual([]);
}

test('CRM access alone retains cases but hides debtor links, financial totals and sales documents', async ({ page }) => {
  await fixture(page, { canWrite: true });
  await page.evaluate(() => window.crmAccessTest.drawer());
  await expect(page.locator('#drawer')).toContainText('CRM case');
  await expect(page.locator('#drawer a[href*="debitorkort.php"], #drawer a[href*="jobliste.php"], #drawer a[href*="ordre.php"]')).toHaveCount(0);
  await expect(page.locator('#drawer [data-mkdoc]')).toHaveCount(0);
  await expect(page.locator('#drawer')).not.toContainText('Omsætning i år');
  await expect(page.locator('#drawer')).not.toContainText('Åbne poster');
  await expect(page.locator('#drawer')).not.toContainText('Åbne tilbud og ordrer');
  await expect(page.locator('[data-dtab="deals"]')).toHaveText('Sager 1');
  await page.evaluate(() => window.crmAccessTest.drawer('ppl'));
  await expect(page.locator('#drawer a[href*="debitorkort.php"]')).toHaveCount(0);
  await page.evaluate(() => window.crmAccessTest.customers());
  await expect(page.locator('#v-cust th')).toHaveCount(6);
  await expect(page.locator('#v-cust')).not.toContainText('Omsætning i år');
});

test('read access to orders shows documents and turnover but does not reveal debtor balances or enable lead conversion', async ({ page }) => {
  await fixture(page, { canOrderRead: true, canRevenueRead: true });
  await page.evaluate(() => window.crmAccessTest.drawer());
  await expect(page.locator('#drawer a[href*="ordre.php?id=90"]')).toHaveCount(1);
  await expect(page.locator('#drawer')).toContainText('Omsætning i år');
  await expect(page.locator('#drawer')).not.toContainText('Åbne poster');
  await expect(page.locator('#drawer a[href*="debitorkort.php"]')).toHaveCount(0);
  await expect(page.locator('#drawer [data-mkdoc]')).toHaveCount(0);
  expect(await page.evaluate(() => window.crmAccessTest.documentUrl(8))).not.toContain('crm_deal=');
  await page.evaluate(() => window.crmAccessTest.update({ canWrite: true, canOrder: true }));
  expect(await page.evaluate(() => window.crmAccessTest.documentUrl(8))).toContain('crm_deal=8');
  expect(await page.evaluate(() => window.crmAccessTest.documentAllowed(false))).toBe(true);
  expect(await page.evaluate(() => window.crmAccessTest.documentAllowed(true))).toBe(false);
  await page.evaluate(() => window.crmAccessTest.update({ canConvertLead: true }));
  expect(await page.evaluate(() => window.crmAccessTest.documentAllowed(true))).toBe(true);
});

test('debtor read allows cards and balances; adding contacts needs debtor write independently of CRM write', async ({ page }) => {
  await fixture(page, { canDebtorRead: true, canBalanceRead: true, canRevenueRead: true });
  await page.evaluate(() => window.crmAccessTest.drawer('ppl'));
  await expect(page.locator('#drawer a[href*="debitorkort.php"]')).toHaveCount(1);
  await expect(page.locator('#drawer a[href*="jobliste.php"]')).toHaveCount(1);
  await expect(page.locator('#drawer')).toContainText('Åbne poster');
  await expect(page.locator('#drawer')).not.toContainText('+ Tilføj kontaktperson på debitorkortet');
  await page.evaluate(() => { window.crmAccessTest.update({ canDebtorWrite: true }); window.crmAccessTest.drawer('ppl'); });
  await expect(page.locator('#drawer')).toContainText('+ Tilføj kontaktperson på debitorkortet');
});

test('read-only CRM can view an existing calendar link but cannot issue, rotate or disable it', async ({ page }) => {
  await fixture(page);
  await page.evaluate(() => window.crmAccessTest.calendar());
  await expect(page.locator('#icU')).toHaveValue('https://calendar.invalid/example');
  await expect(page.locator('#icCopy')).toBeVisible();
  await expect(page.locator('#icMake, #icNew, #icOff')).toHaveCount(0);
  await page.evaluate(() => { window.crmAccessTest.update({ ical: '' }); window.crmAccessTest.calendar(); });
  await expect(page.locator('#icMake')).toHaveCount(0);
  await page.evaluate(() => { window.crmAccessTest.update({ canWrite: true }); window.crmAccessTest.calendar(); });
  await expect(page.locator('#icMake')).toBeVisible();
  await page.evaluate(() => { window.crmAccessTest.update({ ical: 'https://calendar.invalid/example' }); window.crmAccessTest.calendar(); });
  await expect(page.locator('#icNew')).toBeVisible();
  await expect(page.locator('#icOff')).toBeVisible();
});
