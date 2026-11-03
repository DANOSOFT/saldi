// 20260907 CDX/LH Exercise the real autocomplete script with typed historical counter-accounts.
// 20261027 CL/LH An invoice pick's "Fortryd" puts back the momskode of the side it puts back (PR #848 review).
import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const script = fileURLToPath(new URL('../../javascript/accountAutocomplete.js', import.meta.url));
const styles = fileURLToPath(new URL('../../css/accountAutocomplete.css', import.meta.url));

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

// A journal line with Kredit F 1610 and a momskode the user chose (E1; the account's default is K1), and an open
// post for debitor 91033. The account search answers the finance VAT lookup a little late, as the server does.
async function openInvoiceLine(page) {
    await page.route('http://saldi.test/**', async route => {
        const url = route.request().url();
        if (url.includes('invoiceSearch.php')) {
            return route.fulfill({ json: { results: [{
                faktnr: '7012', amount: 1000, kontonr: '91033', art: 'D', firmanavn: 'Kunde A/S',
                valuta: 'DKK', offsetAccount: '58000', transdate: '2026-10-01',
            }] } });
        }
        if (url.includes('accountSearch.php')) {
            await new Promise(resolve => setTimeout(resolve, 200));
            return route.fulfill({ json: { results: [{ kontonr: '1610', beskrivelse: 'Finance account', moms: 'K1' }] } });
        }
        const vat = '<option value=""></option><option value="K1">K1</option><option value="E1">E1</option>';
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body>
            <form id="kassekladde">
            <input name="besk1" value="Indbetaling"><input name="d_ty1" value="F"><input name="debe1" value="58000">
            <select name="dvat1">${vat}</select>
            <input name="k_ty1" value="F"><input name="kred1" value="1610">
            <select name="kvat1">${vat}</select>
            <input name="fakt1"><input name="belo1" value="1.000,00"><input name="valu1" value="DKK">
            <input type="checkbox" name="moms1">
            </form></body></html>` });
    });
    await page.goto('http://saldi.test/finans/kassekladde.php');
    await page.locator('[name=kvat1]').selectOption('E1');
    await page.addStyleTag({ path: styles });
    await page.addScriptTag({ path: script });
    const fakt = page.locator('[name=fakt1]');
    await fakt.click();
    await fakt.pressSequentially('7012');
    await page.locator('.account-autocomplete-item[data-faktnr="7012"]:visible').click();
    await expect(page.locator('[name=kred1]')).toHaveClass(/autofill-conflict/);
}

test('invoice pick: Fortryd after "Brug fakturaens værdier" puts back the side\'s own momskode', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await openInvoiceLine(page);
    await page.locator('.autofill-notice-action', { hasText: 'Brug fakturaens værdier' }).click();
    await expect(page.locator('[name=k_ty1]')).toHaveValue('D');
    await expect(page.locator('[name=kred1]')).toHaveValue('91033');
    await expect(page.locator('[name=kvat1]')).toHaveValue('');

    // Putting F 1610 back starts the account's finance VAT lookup; its answer (K1) must not replace E1
    const answered = page.waitForResponse(response => response.url().includes('type=finance'));
    await page.locator('.autofill-notice-action', { hasText: 'Fortryd' }).click();
    await answered;
    await page.waitForTimeout(100);
    expect(await page.locator('[name=k_ty1]').inputValue()).toBe('F');
    expect(await page.locator('[name=kred1]').inputValue()).toBe('1610');
    expect(await page.locator('[name=kvat1]').inputValue()).toBe('E1');
    expect(errors).toEqual([]);
});

test('invoice pick: Fortryd from the restored account field keeps the side\'s own momskode', async ({ page }) => {
    await openInvoiceLine(page);
    await page.locator('.autofill-notice-action', { hasText: 'Brug fakturaens værdier' }).click();
    await expect(page.locator('[name=kvat1]')).toHaveValue('');
    // The cursor is in Kredit when Fortryd is clicked: leaving it must not look up F 1610's default code
    await page.locator('[name=kred1]').focus();
    await page.keyboard.press('Escape');
    await expect(page.locator('[name=kred1]')).toBeFocused();
    await page.locator('.autofill-notice-action', { hasText: 'Fortryd' }).click();
    await expect(page.locator('[name=fakt1]')).toBeFocused();
    await page.waitForTimeout(600);
    expect(await page.locator('[name=kred1]').inputValue()).toBe('1610');
    expect(await page.locator('[name=kvat1]').inputValue()).toBe('E1');
});
