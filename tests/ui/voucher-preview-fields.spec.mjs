// 20260917 CDX/LAH Verify new voucher values survive document-preview navigation.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../includes/docsIncludes/docPool.php', import.meta.url), 'utf8');
const script = source.slice(source.indexOf('    function _collectRow('), source.indexOf('    function _buildFormData('));

async function openVoucher(page, newRow = true) {
    await page.route('http://saldi.test/**', route => route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' }));
    await page.goto('http://saldi.test/includes/documents.php?source=kassekladde&sourceId=0&kladde_id=42&bilag=440');
    if (newRow) {
        await page.evaluate(() => {
            const row = document.createElement('div');
            row.id = 'bilagEntry_new';
            ['Bilag', 'Dato', 'Faktura', 'Beskrivelse', 'Debet', 'Kredit', 'Amount', 'Afd', 'Projekt', 'Valuta', 'Momsfri', 'Forfald'].forEach(field => {
                const input = document.createElement('input');
                input.id = 'row_new_' + field;
                input.type = field === 'Momsfri' ? 'checkbox' : 'text';
                row.appendChild(input);
            });
            document.body.appendChild(row);
        });
    }
    await page.addScriptTag({ content: script });
}

const preview = 'documents.php?source=kassekladde&sourceId=0&kladde_id=42&bilag=440&poolFile=invoice.pdf';

async function navigate(page, href = preview) {
    await Promise.all([
        page.waitForURL(url => url.searchParams.get('poolFile') === 'invoice.pdf'),
        page.evaluate(href => window.openPoolFile(href), href),
    ]);
    return new URL(page.url()).searchParams;
}

test('preview carries current new-row fields, including both account numbers', async ({ page }) => {
    await openVoucher(page);
    const values = {
        Bilag: '441', Dato: '03-06-2026', Faktura: '750594', Beskrivelse: 'Test & "quoted" <text>',
        Debet: '6400', Kredit: '58300', Amount: '1.048,38', Afd: '2', Projekt: 'P & 1', Valuta: 'DKK', Forfald: '30-06-2026',
    };
    for (const [field, value] of Object.entries(values)) {
        await page.locator('#row_new_' + field).fill(value);
    }
    await page.locator('#row_new_Momsfri').check();
    const params = await navigate(page);
    const mapping = { Bilag: 'bilag', Dato: 'dato', Faktura: 'fakturanr', Beskrivelse: 'beskrivelse', Debet: 'debet', Kredit: 'kredit', Amount: 'sum', Afd: 'afd', Projekt: 'projekt', Valuta: 'valuta', Forfald: 'forfald' };
    for (const [field, parameter] of Object.entries(mapping)) {
        expect(params.get(parameter)).toBe(values[field]);
    }
    expect(params.get('momsfri')).toBe('1');
    expect(params.get('sourceId')).toBe('0');
    expect(params.get('kladde_id')).toBe('42');
});

test('explicitly cleared fields and zero amounts replace stale preview parameters', async ({ page }) => {
    await openVoucher(page);
    await page.locator('#row_new_Amount').fill('0');
    const params = await navigate(page, preview + '&debet=9999&momsfri=1&sum=55');
    expect(params.get('debet')).toBe('');
    expect(params.get('sum')).toBe('0');
    expect(params.get('momsfri')).toBe('0');
    expect(params.getAll('sum')).toEqual(['0']);
});

test('saved-line preview keeps its source ID without adding new-row values', async ({ page }) => {
    await openVoucher(page, false);
    const href = preview.replace('sourceId=0', 'sourceId=123');
    const params = await navigate(page, href);
    expect(params.get('sourceId')).toBe('123');
    expect(params.has('debet')).toBe(false);
    expect(params.has('sum')).toBe(false);
});
