// 20260907 CDX/LH Exercise the real autocomplete script with typed historical counter-accounts.
// 20261027 CL/LH (CodeRabbit, #890) A failed earlier VAT lookup must not clear the code a newer lookup of the same account set.
import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const script = fileURLToPath(new URL('../../javascript/accountAutocomplete.js', import.meta.url));

async function openForm(page, side, initialType, rows, lookup = false) {
    const accountField = side === 'debet' ? 'debe1' : 'kred1';
    const typeField = side === 'debet' ? 'd_ty1' : 'k_ty1';
    await page.route('http://saldi.test/**', route => {
        if (route.request().url().includes('accountSearch.php')) {
            return route.fulfill({ json: { results: [{ kontonr: '990003', beskrivelse: 'Lookup account', moms: 'K1' }] } });
        }
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body>
            <form id="kassekladde">
            <input name="d_ty1" value="F"><input name="debe1">
            <select name="dvat1"><option value=""></option><option value="K1">K1</option></select>
            <input name="k_ty1" value="F"><input name="kred1">
            <select name="kvat1"><option value=""></option><option value="K1">K1</option></select>
            <input name="belo1"><input type="checkbox" name="moms1">
            </form></body></html>` });
    });
    await page.goto('http://saldi.test/finans/kassekladde.php');
    await page.locator(`[name=${typeField}]`).fill(initialType);
    await page.locator(`[name=${accountField}]`).evaluate((input, options) => {
        input.dataset.lastPostings = JSON.stringify({ heading: 'Recent counter-accounts', rows: options.rows });
        window.saldiAutocompleteOptions = { showLastPostings: options.rows.length > 0, showAccountLookup: options.lookup };
    }, { rows, lookup });
    await page.addScriptTag({ path: script });
}

for (const side of ['debet', 'kredit']) {
    for (const accountType of ['D', 'K', 'F']) {
        test(`${side} historical ${accountType} selection updates account type and VAT`, async ({ page }) => {
            const accountField = side === 'debet' ? 'debe1' : 'kred1';
            const typeField = side === 'debet' ? 'd_ty1' : 'k_ty1';
            const vatField = side === 'debet' ? 'dvat1' : 'kvat1';
            const initialType = accountType === 'F' ? 'K' : 'F';
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await openForm(page, side, initialType, [
                { kontonr: '990003', art: accountType, dato: '07-09-2025', bilag: '10', tekst: 'Prior payment' },
            ]);
            await page.locator(`[name=${accountField}]`).click();
            const suggestion = page.locator('.account-autocomplete-last-posting-item:visible');
            const suggestionText = await suggestion.innerText();
            await suggestion.click();
            await expect(page.locator(`[name=${accountField}]`)).toHaveValue('990003');
            await expect(page.locator(`[name=${typeField}]`)).toHaveValue(accountType);
            expect(suggestionText).toContain(`${accountType} 990003`);
            await expect(page.locator(`[name=${vatField}]`)).toHaveValue(accountType === 'F' ? 'K1' : '');
            expect(errors).toEqual([]);
        });
    }
}

test('arrow keys move one row and Enter keeps the highlighted supplier type', async ({ page }) => {
    await openForm(page, 'kredit', 'F', [
        { kontonr: '4000', art: 'F', tekst: 'Expense' },
        { kontonr: '990003', art: 'K', tekst: 'Supplier' },
    ]);
    const field = page.locator('[name=kred1]');
    await field.click();
    await expect(page.locator('.account-autocomplete-last-posting-item:visible')).toHaveCount(2);
    await field.press('ArrowDown');
    await expect(page.locator('.account-autocomplete-item.selected:visible')).toHaveAttribute('data-kontonr', '4000');
    await field.press('ArrowDown');
    await expect(page.locator('.account-autocomplete-item.selected:visible')).toHaveAttribute('data-kontonr', '990003');
    await field.press('Enter');
    await expect(field).toHaveValue('990003');
    await expect(page.locator('[name=k_ty1]')).toHaveValue('K');
});

test('ordinary supplier lookup retains the selected account type', async ({ page }) => {
    await openForm(page, 'kredit', 'K', [], true);
    const field = page.locator('[name=kred1]');
    await field.fill('990003');
    await page.locator('.account-autocomplete-item[data-kontonr="990003"]:visible').click();
    await expect(field).toHaveValue('990003');
    await expect(page.locator('[name=k_ty1]')).toHaveValue('K');
});

test('a failed earlier VAT lookup does not clear the code a newer lookup of the same account set', async ({ page }) => {
    await openForm(page, 'kredit', 'F', []);
    // The first VAT lookup is held and then fails; every later one is answered at once.
    let releaseFirst;
    const firstHeld = new Promise(resolve => { releaseFirst = resolve; });
    let lookups = 0;
    await page.route(url => url.pathname.endsWith('/accountSearch.php') && url.searchParams.get('exact') === '1', async route => {
        lookups++;
        if (lookups === 1) {
            await firstHeld;
            return route.abort();
        }
        return route.fulfill({ json: { results: [{ kontonr: '990003', beskrivelse: 'Lookup account', moms: 'K1' }] } });
    });
    // Keep every request the page starts, so the test can wait until all of them have settled.
    await page.evaluate(() => {
        const realFetch = window.fetch;
        window.startedFetches = [];
        window.fetch = function () {
            const request = realFetch.apply(this, arguments);
            window.startedFetches.push(request);
            return request;
        };
    });
    const setAccount = value => page.locator('[name=kred1]').evaluate((input, accountNo) => {
        input.value = accountNo;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);

    await setAccount('990003');
    await expect.poll(() => lookups).toBe(1);
    await setAccount('');
    await setAccount('990003');
    await expect(page.locator('[name=kvat1]')).toHaveValue('K1');
    expect(lookups).toBe(2);

    releaseFirst();
    await page.evaluate(async () => {
        await Promise.allSettled(window.startedFetches);
        await new Promise(resolve => setTimeout(resolve, 0));
    });
    await expect(page.locator('[name=kvat1]')).toHaveValue('K1');
});
