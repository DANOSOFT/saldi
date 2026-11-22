// 20261122 CDX/ASR Reproduce overlapping approval saves, stale views and voucher-zero confirmations.
import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

/** Load the real controls with deferred replies so every request order is deterministic. */
async function controls(page) {
  await page.setContent(`<div><span id="kk-balance-status"></span></div>
    <input id="kkGodkendtAlle" type="checkbox">
    <input id="line1" type="checkbox" class="kk-godkendt" data-line-id="10" data-bilag="0" data-snapshot="first">
    <input id="line2" type="checkbox" class="kk-godkendt" data-line-id="11" data-bilag="0" data-snapshot="second">
    <input id="line3" type="checkbox" class="kk-godkendt" data-line-id="12" data-bilag="1" data-snapshot="third">
    <button id="kkPostApproved">Post</button>`);
  await page.evaluate(() => {
    window.requests = []; window.alerts = []; window.stored = {}; window.confirmations = [];
    window.alert = message => window.alerts.push(message);
    window.confirm = message => { window.confirmations.push(message); return true; };
    window.fetch = (url, options) => new Promise(resolve => {
      const data = Object.fromEntries(options.body.entries());
      window.requests.push({ data, reply: result => {
        if (result.ok) data.ids.split(',').forEach(id => { window.stored[id] = data.godkendt === '1'; });
        resolve({ json: () => Promise.resolve(result) });
      } });
    });
  });
  await page.addScriptTag({ path: fileURLToPath(new URL('../javascript/journalApproval.js', import.meta.url)) });
  await page.evaluate(() => window.saldiJournalApproval({ label: 'Approved', of: 'of', kladde: 1,
    failed: 'Save failed', changed: 'Reload changed lines', none: 'None', mixed: 'Mixed:', linesIn: 'lines in', confirm: 'vouchers' }));
}

/** Reply to one deferred save and let the queue drain. */
async function reply(page, index, result = { ok: true }) {
  await expect.poll(() => page.evaluate(() => window.requests.length)).toBeGreaterThan(index);
  await page.evaluate(({ index, result }) => window.requests[index].reply(result), { index, result });
}

test('rapid toggles are ordered and an older failure cannot revert the latest selection', async ({ page }) => {
  await controls(page);
  await page.locator('#line1').check();
  await page.locator('#line1').uncheck();
  await page.locator('#line1').check();
  expect(await page.evaluate(() => window.requests.length)).toBe(1);
  await expect(page.locator('#kkPostApproved')).toBeDisabled();
  await reply(page, 0, { ok: false, error: 'db' });
  await expect(page.locator('#line1')).toBeChecked();
  await reply(page, 1);
  await reply(page, 2);
  await expect(page.locator('#kkPostApproved')).toBeEnabled();
  expect(await page.evaluate(() => window.requests.map(r => r.data.godkendt))).toEqual(['1', '0', '1']);
  expect(await page.evaluate(() => window.stored['10'])).toBe(true);
  await expect(page.locator('#kk-godkendt-status')).toHaveText('Approved: 1 of 3');
});

test('two failed toggles restore the last confirmed value', async ({ page }) => {
  await controls(page);
  await page.locator('#line1').check();
  await page.locator('#line1').uncheck();
  await reply(page, 0, { ok: false });
  await reply(page, 1, { ok: false });
  await expect(page.locator('#kkPostApproved')).toBeEnabled();
  await expect(page.locator('#line1')).not.toBeChecked();
  await expect(page.locator('#kk-godkendt-status')).toHaveText('Approved: 0 of 3');
});

test('header and individual actions share ordering while disjoint lines save independently', async ({ page }) => {
  await controls(page);
  await page.locator('#line1').check();
  await page.locator('#line3').check();
  await expect.poll(() => page.evaluate(() => window.requests.length)).toBe(2);
  await page.locator('#kkGodkendtAlle').check();
  await page.locator('#line2').uncheck();
  expect(await page.evaluate(() => window.requests.length)).toBe(2);
  await reply(page, 1);
  await reply(page, 0);
  await reply(page, 2);
  await reply(page, 3);
  await expect(page.locator('#kkPostApproved')).toBeEnabled();
  expect(await page.evaluate(() => window.stored)).toEqual({ 10: true, 11: false, 12: true });
  expect(await page.evaluate(() => JSON.parse(window.requests[2].data.snapshots))).toEqual({ 10: 'first', 11: 'second', 12: 'third' });
});

test('a stale view requires reload before more approvals or posting', async ({ page }) => {
  await controls(page);
  await page.locator('#line1').check();
  await reply(page, 0, { ok: false, error: 'changed' });
  await expect(page.locator('#line1')).toBeDisabled();
  await expect(page.locator('#kkGodkendtAlle')).toBeDisabled();
  await expect(page.locator('#kkPostApproved')).toBeDisabled();
  expect(await page.evaluate(() => window.kkConfirmPostApproved())).toBe(false);
  expect(await page.evaluate(() => window.alerts)).toContain('Reload changed lines');
});

test('confirmation leaves a mixed voucher zero intact and counts a complete one once', async ({ page }) => {
  await controls(page);
  await page.evaluate(() => { document.querySelector('#line1').checked = true; document.querySelector('#line3').checked = true; });
  expect(await page.evaluate(() => window.kkConfirmPostApproved())).toBe(true);
  expect(await page.evaluate(() => window.confirmations.at(-1))).toBe('1 lines in 1 vouchers\n\nMixed: 0');
  await page.evaluate(() => { document.querySelector('#line2').checked = true; document.querySelector('#line3').checked = false; });
  expect(await page.evaluate(() => window.kkConfirmPostApproved())).toBe(true);
  expect(await page.evaluate(() => window.confirmations.at(-1))).toBe('2 lines in 1 vouchers');
});
