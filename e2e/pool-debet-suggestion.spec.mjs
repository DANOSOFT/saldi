// 20261106 CL/LH SD-722 (PR #890 review) The Debet suggestion in the document pool: filled in only on a new line, follows Kredit when
//                Enter saves from Kredit, and a kreditor rejected with "×" leaves no other kreditor's suggestion behind.
// Self-contained: the pool's entry rows are a stub page and every request is answered by page.route, so it needs neither the
// docker stack nor the seeded login, and it runs with the rest of e2e/ in CI. The scripts are the real poolCapture.js and
// docPoolSaveNext.js; docPool.php's _saveRowFetch() is a stand-in that records what each save would send.
import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const capture = fileURLToPath(new URL('../javascript/poolCapture.js', import.meta.url));
const saveNext = fileURLToPath(new URL('../javascript/docPoolSaveNext.js', import.meta.url));

// The kreditors' histories: 30008 and 30009 went to 2100 and 2200 on 3 of their last 5 bilag; 30010 has a single bilag
const suggestions = {
    30008: { account: '2100', type: 'F', name: 'Varekøb i DK', moms: 'K1', count: 3, total: 5, reason: '3 af 5 bilag gik til 2100', alternatives: [] },
    30009: { account: '2200', type: 'F', name: 'Varekøb i EU', moms: 'K1', count: 3, total: 5, reason: '3 af 5 bilag gik til 2200', alternatives: [] },
    30010: { account: '2300', type: 'F', name: 'Fragt', moms: 'K1', count: 1, total: 1, reason: 'Det seneste bilag gik til 2300', alternatives: [] },
};

const texts = {
    suggested: 'Forslag', use: 'Brug forslag', reject: 'Afvis', ofTotal: '$antal af $total bilag', oneOther: '1 anden',
    others: '$antal andre', listHead: 'De sidste $antal bilag', searchOther: 'Søg anden konto',
};

// One pool entry row as docPool.php prints it, with the fields poolCapture.js and docPoolSaveNext.js read
function entry(id, kredit, style = '') {
    const pfx = `row_${id}`;
    return `<div class="kassebilag-entry" id="bilagEntry_${id}" style="${style}"><div class="topbar-fields-row">
        <div class="topbar-field"><label>Dato:</label><input id="${pfx}_Dato" value="01-11-2026"></div>
        <div class="topbar-field pool-account-field"><label>Debet:</label><div class="pool-account-inputs">
            <input id="${pfx}_DebetType" value="F"><input id="${pfx}_Debet" placeholder="Konto"></div>
            <div class="pool-account-name" id="${pfx}_DebetName"></div></div>
        <div class="topbar-field pool-account-field"><label>Kredit:</label><div class="pool-account-inputs">
            <input id="${pfx}_KreditType" value="K"><input id="${pfx}_Kredit" value="${kredit}" placeholder="Konto"></div>
            <div class="pool-account-name" id="${pfx}_KreditName"></div></div>
        <div class="topbar-field"><label>Beløb:</label><input id="${pfx}_Amount" value="1.250,00"></div>
        </div></div>`;
}

// Opens the pool with the given rows ([id, kredit, style]). Resolves to a reader of the saves _saveRowFetch() was asked for.
async function openPool(page, rows) {
    await page.route('http://saldi.test/**', route => {
        const url = new URL(route.request().url());
        if (url.pathname.endsWith('/contraSuggestionLookup.php')) {
            const suggestion = suggestions[url.searchParams.get('kreditor')];
            return route.fulfill({ json: suggestion ? { suggestion, auto: true } : {} });
        }
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><meta charset="utf-8"></head><body>
            <div id="kassebilagTopBar"><div id="bilagRowsContainer">${rows.map(row => entry(...row)).join('')}</div></div>
            <script>
            window.saldiPoolCapture = { poolFile: '', kladdeId: 24, readOnly: false, fields: [], vendorMatch: '', reportEnabled: false,
                endpoint: 'docsIncludes/poolCaptureReport.php', suggestUrl: '../finans/kassekladde_includes/contraSuggestionLookup.php',
                texts: ${JSON.stringify(texts)} };
            window.saldiPoolSaveNext = { kladdeId: 24, bilag: 7, readOnly: false, texts: { mandatory: 'Obligatorisk', saving: 'Gemmer', stale: 'stale' } };
            window.saves = [];
            // docPool.php's _saveRowFetch(): what the row would be saved with
            window._saveRowFetch = function (rowId) {
                const value = name => document.getElementById('row_' + rowId + '_' + name).value;
                window.saves.push({ rowId, debet: value('Debet'), kredit: value('Kredit') });
                return Promise.resolve({ success: true, sourceId: 900 + window.saves.length });
            };
            // The next document opens in place (docPoolSwitch.js); here the page stays
            window.poolSwitch = function () {};
            </script></body></html>` });
    });
    await page.goto('http://saldi.test/includes/documents.php');
    await page.addScriptTag({ path: saveNext });
    await page.addScriptTag({ path: capture });
    return () => page.evaluate(() => window.saves);
}

const debet = (page, id) => page.locator(`#row_${id}_Debet`);

test('auto-fill writes Debet on a new line and only shows the placeholder on a saved line', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    // A saved line with only Kredit K30008, collapsed out of view as the pool shows the bilag's other lines, and a new line
    await openPool(page, [['501', '30008', 'display:none'], ['new', '30008']]);

    await expect(debet(page, 'new')).toHaveValue('2100');
    await expect(page.locator('#bilagEntry_new .pool-capture-badge')).toHaveText('Forslag');
    // "Gem alle" saves what the field holds: an account nobody saw must not be in it
    await page.waitForTimeout(200);
    await expect(debet(page, '501')).toHaveValue('');
    await expect(debet(page, '501')).toHaveAttribute('placeholder', '2100 ↵');
    await expect(debet(page, '501')).not.toHaveClass(/pool-suggested/);
    expect(errors).toEqual([]);
});

test('Enter in Kredit after another kreditor was typed does not save the first kreditor\'s account', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const saves = await openPool(page, [['new', '']]);
    const kredit = page.locator('#row_new_Kredit');

    await kredit.fill('K30008');
    await kredit.press('Tab');
    await expect(debet(page, 'new')).toHaveValue('2100');

    // Retyped and Enter without leaving the field: no change event before the save
    await kredit.click();
    await kredit.fill('K30009');
    await kredit.press('Enter');
    // The save would start in the same keydown
    expect(await saves(), 'the first Enter shows the new kreditor\'s account instead of saving the old one').toEqual([]);
    await expect(debet(page, 'new')).toHaveValue('2200');

    await kredit.focus();
    await kredit.press('Enter');
    await expect.poll(saves).toEqual([{ rowId: 'new', debet: '2200', kredit: 'K30009' }]);
    expect(errors).toEqual([]);
});

test('back on a kreditor rejected with × the line keeps nothing of the kreditor shown in between', async ({ page }) => {
    await openPool(page, [['new', '']]);
    const kredit = page.locator('#row_new_Kredit');
    const setKredit = async value => {
        await kredit.fill(value);
        await kredit.press('Tab');
    };

    await setKredit('K30008');
    await expect(debet(page, 'new')).toHaveValue('2100');
    await page.locator('#bilagEntry_new .pool-suggest .pool-capture-x').click();
    await expect(debet(page, 'new')).toHaveValue('');

    // 30010 rests on a single bilag, so it is the placeholder and the name under Debet
    await setKredit('K30010');
    await expect(debet(page, 'new')).toHaveAttribute('placeholder', '2300 ↵');
    await expect(page.locator('#row_new_DebetName')).toHaveText('Fragt');

    await setKredit('K30008');
    await page.waitForTimeout(200);
    await expect(page.locator('#bilagEntry_new .pool-suggest')).toHaveCount(0);
    await expect(debet(page, 'new')).toHaveAttribute('placeholder', 'Konto');
    await expect(page.locator('#row_new_DebetName')).toHaveText('');
    await expect(debet(page, 'new')).toHaveValue('');
});
