// 20261110 CDX/ASR Role group safety, dependent access, filters and previews without database writes.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../javascript/usersRolesEditor.js', import.meta.url), 'utf8');
const labels = { 'finans.rapporter': 'Financial reports', 'debitor.ordre': 'Sales orders', 'dashboard.view': 'View dashboard', 'dashboard.block.kpis': 'Key figures', 'settings.backup': 'Backup', 'settings.roles.manage': 'Manage roles' };
const original = { 'finans.rapporter': 'read', 'debitor.ordre': 'none', 'dashboard.view': 'none', 'dashboard.block.kpis': 'read', 'settings.backup': 'none', 'settings.roles.manage': 'write' };
const target = { ...original, 'finans.rapporter': 'write', 'dashboard.view': 'read', 'settings.backup': 'read', 'settings.roles.manage': 'none' };
const text = { none: 'No access', read: 'Read only', write: 'Full access', save: 'Save changes', undo: 'Undo changes', pending: '%s unsaved changes', noChanges: 'No unsaved changes', newDraft: 'Draft', preview: 'Review changes', resetPreview: 'Review reset', deletePreview: 'Move users and delete', discardTitle: 'Unsaved changes', discardBody: 'Leave without saving?', discard: 'Discard', cancel: 'Cancel', added: 'Increased access', removed: 'Reduced access', details: 'Name and description', noPermissions: 'No permission changes', affected: 'Affected: %s active, %s invited, %s closed', nextView: 'Changes apply on the next page view', noUsers: 'No users assigned yet', expand: 'Expand all', collapse: 'Collapse all', standardDiff: 'Default differences', noStandardDiff: 'Matches defaults', fromStandard: 'Default to this role', groupApplied: '%s permissions changed; sensitive and locked unchanged', moveTo: 'Move to', moveDelete: 'Move and delete', delete: 'Delete', deleteEmpty: 'Delete role?', deleteBody: 'Move all users together', unsavedIgnored: 'Unsaved editor changes are not included', noTargets: 'No allowed roles', revenue: 'Revenue visibility changes', close: 'Close', newRole: 'New role', nameField: 'Name', descriptionField: 'Description' };

async function editor(page, isNew = false) {
  const config = { roleId: isNew ? 0 : 1, isNew, ownRole: false, source: 0, levels: original, defaults: { ...original, 'finans.rapporter': 'none' },
    roles: { 1: { name: 'Current', description: '', levels: original, allowed: true }, 2: { name: 'Target', description: 'Template description', levels: target, allowed: true }, 3: { name: 'Too privileged', levels: target, allowed: false } },
    counts: { active: 2, invited: 1, closed: 3, total: 6 }, labels, name: 'Current', displayName: 'Current', description: '', text };
  const row = key => `<div class="ur-permission" data-key="${key}" data-dangerous="${key === 'settings.backup' ? 1 : 0}" ${key === 'dashboard.block.kpis' ? 'data-dashboard-block' : ''} data-derive="finans.rapporter">
<div class="ur-permission-title">${labels[key]}<span class="ur-permission-changed" hidden>Changed</span>${key === 'dashboard.block.kpis' ? '<small data-dashboard-needs hidden>Requires dashboard</small><small data-dashboard-hint hidden>Cannot open report</small>' : ''}</div>
<input type="hidden" name="level[${key}]" value="${original[key]}" data-level-fallback>
${['none', 'read', 'write'].map(level => { const locked = (key === 'settings.roles.manage' && level !== 'write') || (key.startsWith('dashboard.') && level === 'write'); return `<label><input type="radio" name="level[${key}]" value="${level}" data-locked="${locked ? 1 : 0}" ${original[key] === level ? 'checked' : ''} ${locked ? 'disabled' : ''}>${text[level]}</label>`; }).join('')}</div>`;
  const group = (name, keys) => `<details data-role-group="${name}" open><summary>${name}<small data-group-summary></small></summary><button type="button" data-group-level="none">Set none</button><button type="button" data-group-level="write">Set full</button>${keys.map(row).join('')}</details>`;
  await page.route('http://roles-editor.test/**', route => route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html><style>[hidden]{display:none!important}</style><div id="ur-roles-editor">
<select id="ur-role-picker"><option value="/roles?rolle=1" selected>Current</option></select><a href="/roles?rolle=2">Other role</a>
<form id="ur-role-form"><div class="ur-role-savebar"><h2 data-role-title>Current</h2><span id="ur-role-change-count"></span><button type="button" id="ur-role-undo">Undo changes</button><button type="submit" id="ur-role-save">Save changes</button></div>
<input name="navn" value="Current" required><input name="beskrivelse" value=""><input name="role_source" type="hidden" value="0">
${isNew ? '<select id="ur-role-template"><option value="0">Blank</option><option value="2">Target</option></select>' : ''}
<input id="ur-role-search"><select id="ur-role-filter"><option value="all">All</option><option value="access">Has access</option><option value="changed">Changed</option></select><button type="button" id="ur-role-expand">Expand all</button><p id="ur-role-empty" hidden>No matches</p>
${group('work', ['finans.rapporter', 'debitor.ordre', 'settings.backup', 'settings.roles.manage'])}${group('dashboard', ['dashboard.view', 'dashboard.block.kpis'])}<p id="ur-role-feedback"></p></form>
<button id="ur-role-reset">Reset to default</button><button id="ur-role-delete">Delete</button><button id="ur-role-standard-diff">Default differences</button>
<form id="ur-role-reset-form"><input type="hidden" name="role_revision" value="old-revision"></form><form id="ur-role-delete-form"><input type="hidden" name="move_to" value="0"><input type="hidden" name="role_revision" value="old-revision"></form>
<dialog id="ur-role-dialog"><h2 id="ur-role-dialog-title"></h2><div id="ur-role-dialog-body"></div><button type="button" data-role-dialog-cancel>Cancel</button><button type="button" data-role-dialog-confirm>Confirm</button></dialog>
<script type="application/json" id="ur-role-editor-data">${JSON.stringify(config)}</script></div>` }));
  await page.goto('http://roles-editor.test/roles?rolle=1');
  await page.addScriptTag({ content: script });
}

const choice = (page, key, value) => page.locator(`input[type="radio"][name="level[${key}]"][value="${value}"]`);

test('group actions skip sensitive and locked permissions; filters find only the relevant rows', async ({ page }) => {
  await editor(page);
  await page.locator('[data-role-group="work"] [data-group-level="write"]').click();
  await expect(choice(page, 'finans.rapporter', 'write')).toBeChecked();
  await expect(choice(page, 'debitor.ordre', 'write')).toBeChecked();
  await expect(choice(page, 'settings.backup', 'none')).toBeChecked();
  await page.locator('#ur-role-filter').selectOption('changed');
  await expect(page.locator('.ur-permission:not([hidden])')).toHaveCount(2);
  await page.locator('#ur-role-search').fill('reports');
  await expect(page.locator('.ur-permission:not([hidden])')).toHaveCount(1);
  await page.locator('#ur-role-search').fill('no matching permission');
  await expect(page.locator('#ur-role-empty')).toBeVisible();
  await page.locator('#ur-role-search').fill('');
  await page.locator('#ur-role-filter').selectOption('all');
  await page.locator('[data-role-group="work"] [data-group-level="none"]').click();
  await expect(choice(page, 'settings.roles.manage', 'write')).toBeChecked();
  await expect(choice(page, 'settings.roles.manage', 'none')).toBeDisabled();
});

test('template enables dependent dashboard controls and Undo restores their access without losing stored choices', async ({ page }) => {
  await editor(page, true);
  await expect(choice(page, 'dashboard.block.kpis', 'read')).toBeDisabled();
  await page.locator('#ur-role-filter').selectOption('access');
  await expect(page.locator('[data-key="dashboard.block.kpis"]')).toBeHidden();
  await page.locator('#ur-role-template').selectOption('2');
  await expect(choice(page, 'dashboard.block.kpis', 'read')).toBeEnabled();
  await expect(page.locator('[data-key="dashboard.block.kpis"]')).toBeVisible();
  await page.locator('#ur-role-undo').click();
  await expect(page.locator('[name="role_source"]')).toHaveValue('0');
  await expect(choice(page, 'dashboard.block.kpis', 'read')).toBeDisabled();
  await expect(choice(page, 'dashboard.block.kpis', 'read')).toBeChecked();
  expect(await page.evaluate(() => window.docChange)).toBe(false);
  expect(await page.locator('#ur-role-form').evaluate(el => new FormData(el).get('level[dashboard.block.kpis]'))).toBe('read');
});

test('reset and move previews use stored access, include status counts and do not submit on Cancel', async ({ page }) => {
  await editor(page);
  await choice(page, 'settings.backup', 'write').check();
  await page.locator('#ur-role-reset').click();
  await expect(page.locator('#ur-role-dialog-body')).toContainText('2 active, 1 invited, 3 closed');
  await expect(page.locator('#ur-role-dialog-body')).toContainText('Unsaved editor changes are not included');
  await expect(page.locator('#ur-role-dialog-body')).toContainText('Read only → No access');
  await expect(page.locator('#ur-role-dialog-body')).not.toContainText('Backup');
  await page.locator('[data-role-dialog-cancel]').click();
  await expect(choice(page, 'settings.backup', 'write')).toBeChecked();
  await page.locator('#ur-role-delete').click();
  await expect(page.locator('#ur-role-dialog select option')).toHaveCount(1);
  await expect(page.locator('#ur-role-dialog select')).toHaveValue('2');
  await expect(page.locator('#ur-role-dialog-body')).toContainText('Increased access');
  await expect(page.locator('#ur-role-dialog-body')).toContainText('Reduced access');
  await expect(page.locator('#ur-role-dialog-body')).toContainText('Full access → No access');
  await expect(page.locator('#ur-role-delete-form [name="move_to"]')).toHaveValue('2');
  await expect(page.locator('#ur-role-delete-form [name="role_revision"]')).toHaveValue('old-revision');
  await page.locator('[data-role-dialog-cancel]').click();
  expect(await page.evaluate(() => window.docChange)).toBe(true);
});
