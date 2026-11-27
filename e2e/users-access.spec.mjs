// 20261110 CDX/ASR Permission previews and list context regression tests without database writes or login credentials.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const view = readFileSync(new URL('../systemdata/usersRolesIncludes/view.php', import.meta.url), 'utf8');
const inlineScript = view.match(/function ur_view_script\(array \$vm\): void[\s\S]*?<script>([\s\S]*?)<\/script>/)[1];
const accessScript = readFileSync(new URL('../javascript/usersAccess.js', import.meta.url), 'utf8');
const peopleScript = readFileSync(new URL('../javascript/usersPeople.js', import.meta.url), 'utf8');
const dialogs = `<div id="ur-dlg" hidden><h3 id="ur-dlg-title"></h3><div id="ur-dlg-body"></div><div id="ur-dlg-row"></div></div>
<template id="ur-dlg-cancel"><button type="button" data-dlg-close>Cancel</button></template>
<div id="ur-pguard" hidden><h3 id="ur-pguard-t"></h3><p id="ur-pguard-b"></p><button data-pguard-no>Keep editing</button><button data-pguard-yes>Discard</button></div>`;
const accessData = {
  levels: { 1: { 'debitor.konti': 'read' }, 2: { 'debitor.konti': 'write', 'settings.backup': 'write' } },
  roles: { 1: { name: 'Reader', description: 'View customers' }, 2: { name: 'Backup', description: 'Customers and backup', changed: true } },
  users: { 42: { name: 'Test person', role: 1, auditor: false, status: 'active' }, 43: { name: 'Invited person', role: 1, auditor: false, status: 'invited' }, 44: { name: 'Closed person', role: 1, auditor: false, status: 'closed' } },
  keys: { 'debitor.konti': { label: 'Customers', group: 'work' }, 'settings.backup': { label: 'Backup', group: 'sensitive' }, 'settings.integrations': { label: 'Integrations', group: 'sensitive' } },
  groups: { work: 'Work', sensitive: 'Sensitive' },
  mode: 'log', auditorRole: 0, levelLabels: { none: 'None', read: 'Read', write: 'Write' },
  text: { confirm: 'Confirm access change', apply: 'Save access', added: 'Increased access', removed: 'Reduced access', unchanged: 'No permission changes', accessCount: '%s of %s permissions grant access', newRole: 'New role', none: 'No role', current: 'Current role', affected: 'Affected users: %s', affectedStates: 'Affected users: %s active · %s invited · %s closed', rolesOnly: 'Only selected assignments change' },
};

async function card(page) {
  await page.route('http://people.test/**', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `${dialogs}
<aside id="ur-drawer" data-close="/usersRoles.php?tab=users"><a id="close-card" href="/usersRoles.php?tab=users">Close card</a>
<form id="ur-user-form" data-original-role="1" data-new="0"><input name="id" value="42" type="hidden"><input name="email" value="test@example.invalid"><input name="revisor" type="checkbox">
<select id="ur-role" name="role_id"><option value="1">Reader</option><option value="2">Backup</option></select>
<p id="ur-role-description"></p><p id="ur-role-customized" hidden>Customized</p><p id="ur-auditor-note" hidden>Auditor</p>
<div id="ur-access">${Object.keys(accessData.keys).map(key => `<li data-key="${key}"><span>${accessData.keys[key].label}</span><strong></strong></li>`).join('')}</div><span id="ur-access-count"></span><div id="ur-user-access-diff" hidden></div></form>
<button type="submit" form="ur-user-form">Save</button></aside><script type="application/json" id="ur-access-data">${JSON.stringify(accessData)}</script>` }));
  await page.goto('http://people.test/usersRoles.php?tab=users&bruger=42');
  await page.addScriptTag({ content: inlineScript });
  await page.addScriptTag({ content: accessScript });
  await page.addScriptTag({ content: `window.UR_PEOPLE = {discardTitle:'Unsaved changes', discardBody:'Keep or discard changes?', discardVerb:'Discard'};` });
  await page.addScriptTag({ content: peopleScript });
}

test('sensitive permissions stay individual and assignment shows exact changes', async ({ page }) => {
  await card(page);
  await page.locator('#ur-role').selectOption('2');
  await expect(page.locator('[data-key="settings.backup"] strong')).toHaveText('Write');
  await expect(page.locator('[data-key="settings.integrations"] strong')).toHaveText('None');
  await expect(page.locator('#ur-access-count')).toHaveText('2 of 3 permissions grant access');
  await expect(page.locator('#ur-role-customized')).toBeVisible();
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(page.locator('#ur-dlg')).toBeVisible();
  await expect(page.locator('#ur-dlg-body')).toContainText('Customers: Read → Write');
  await expect(page.locator('#ur-dlg-body')).toContainText('Backup: None → Write');
  await expect(page.locator('#ur-dlg-body')).not.toContainText('Integrations:');
  await page.getByRole('button', { name: 'Cancel', exact: true }).click();
  await page.getByRole('link', { name: 'Close card' }).click();
  await expect(page.locator('#ur-pguard')).toBeVisible();
});

test('confirmed assignment submits exactly once and does not ask to discard', async ({ page }) => {
  await card(page);
  await page.evaluate(() => {
    window.saved = 0;
    document.getElementById('ur-user-form').addEventListener('submit', event => {
      if (event.defaultPrevented) { return; }
      event.preventDefault();
      window.saved++;
    });
  });
  await page.locator('#ur-role').selectOption('2');
  await page.getByRole('button', { name: 'Save', exact: true }).click();
  await page.getByRole('button', { name: 'Save access', exact: true }).click();
  await expect.poll(() => page.evaluate(() => window.saved)).toBe(1);
  await expect(page.locator('#ur-dlg')).toBeHidden();
  await expect(page.locator('#ur-pguard')).toBeHidden();
});

test('list filters, sorting, selection and page survive opening and closing a card', async ({ page }) => {
  const list = `<form id="ur-bulk" data-state-scope="tenant-a-user-1"><input id="ur-search"><select id="ur-role-filter"><option value="">All</option><option value="2">Backup</option></select>
<select id="ur-status-filter"><option value="">All</option><option value="active">Active</option></select><select id="ur-2fa-filter"><option value="">All</option><option value="1">With 2FA</option></select>
<button type="button" data-people-status="">All</button><button type="button" data-people-status="active">Has access</button>
<div id="ur-bulkbar" hidden><b id="ur-bulk-n"></b></div><table id="ur-users"><thead><tr><th><input id="ur-check-all" type="checkbox"></th><th data-sort>Name</th></tr></thead><tbody>
${Array.from({ length: 60 }, (_, i) => `<tr data-search="person ${i}" data-status="active" data-role="2" data-tfa="1" data-href="?tab=users&bruger=${i + 1}"><td><input type="checkbox" name="ids[]" value="${i + 1}"></td><td data-v="person ${i}"><a href="?tab=users&bruger=${i + 1}">Person ${i}</a></td></tr>`).join('')}</tbody></table><div id="ur-empty" hidden></div><span id="ur-foot-text" data-format="%s of %s"></span><span id="ur-pager"><button type="button" data-page="-1">Previous</button><span id="ur-pager-text" data-format="%s–%s of %s"></span><button type="button" data-page="1">Next</button></span></form>`;
  await page.route('http://people.test/**', route => {
    const drawer = new URL(route.request().url()).searchParams.has('bruger') ? '<aside id="ur-drawer" data-close="?tab=users"><a id="close" href="?tab=users">Close</a></aside>' : '';
    const scopedList = new URL(route.request().url()).searchParams.get('tenant') === 'b' ? list.replace('tenant-a-user-1', 'tenant-b-user-1') : list;
    return route.fulfill({ contentType: 'text/html; charset=utf-8', body: dialogs + scopedList + drawer });
  });
  const init = async () => { await page.addScriptTag({ content: inlineScript }); };
  await page.goto('http://people.test/usersRoles.php?tab=users');
  await init();
  await page.locator('#ur-search').fill('person');
  await page.locator('#ur-role-filter').selectOption('2');
  await page.locator('#ur-2fa-filter').selectOption('1');
  await page.locator('[data-people-status="active"]').click();
  await page.locator('th[data-sort]').click();
  await page.getByRole('button', { name: 'Next' }).click();
  const visible = page.locator('#ur-users tbody tr:not([hidden])');
  await visible.first().locator('input').check();
  await visible.first().locator('a').click();
  await init();
  await page.locator('#close').click();
  await init();
  await expect(page.locator('#ur-search')).toHaveValue('person');
  await expect(page.locator('#ur-role-filter')).toHaveValue('2');
  await expect(page.locator('#ur-2fa-filter')).toHaveValue('1');
  await expect(page.locator('#ur-status-filter')).toHaveValue('active');
  await expect(page.locator('#ur-pager-text')).toHaveText('51–60 of 60');
  await expect(page.locator('th[data-sort]')).toHaveAttribute('aria-sort', 'ascending');
  await expect(page.locator('#ur-users input[name="ids[]"]:checked')).toHaveCount(1);
  // A different tenant/user never inherits names, filters or selected ids from this account.
  await page.goto('http://people.test/usersRoles.php?tab=users&tenant=b');
  await init();
  await expect(page.locator('#ur-search')).toHaveValue('');
  await expect(page.locator('#ur-role-filter')).toHaveValue('');
  await expect(page.locator('#ur-users input[name="ids[]"]:checked')).toHaveCount(0);
});


test('bulk confirmation counts active, invited and closed users and lists exact changes', async ({ page }) => {
  await page.route('http://people.test/**', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `${dialogs}
<form id="ur-bulk"><select name="role_id"><option value="2">Backup</option></select><div id="ur-bulkbar"><b id="ur-bulk-n"></b><button type="submit" name="bulk" value="role" data-applies="notself" data-bulk-confirm="Assign role">Assign role</button></div>
<table id="ur-users"><tbody>${[42, 43, 44].map(id => `<tr data-search="person" data-status="${accessData.users[id].status}" data-role="1" data-tfa="0"><td><input type="checkbox" name="ids[]" value="${id}" checked></td></tr>`).join('')}</tbody></table></form>
<script type="application/json" id="ur-access-data">${JSON.stringify(accessData)}</script>` }));
  await page.goto('http://people.test/usersRoles.php?tab=users');
  await page.addScriptTag({ content: inlineScript });
  await page.addScriptTag({ content: accessScript });
  await page.getByRole('button', { name: 'Assign role', exact: true }).click();
  await expect(page.locator('#ur-dlg-body')).toContainText('1 active · 1 invited · 1 closed');
  await expect(page.locator('#ur-dlg-body details')).toHaveCount(3);
  await expect(page.locator('#ur-dlg-body details').first()).toContainText('Backup: None → Write');
  await expect(page.locator('#ur-dlg-body details').first()).not.toContainText('Integrations:');
});
