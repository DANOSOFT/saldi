// 20260918 LOE SD-700 The pool list keeps its order and its position when a bilag is opened.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

// The pool table is built by the script below after it fetches _docPoolData.php, so these tests run
// the real thing in a browser: no tenant, no credentials, no PHP.
//
// The script lives in a PHP heredoc, so its text has to be taken out the way PHP emits it:
//   * a heredoc processes escapes like a double-quoted string, so '\\' becomes '\' (the source
//     writes \' and \. as \\' and \\., which the page serves as \' and \.);
//   * {$name} is interpolated by PHP - the five values documents.php computes are the only ones a
//     browser test needs, and ${name} resolves to the JS const of that name declared in the script.
const openMarker = '<script>\n(() => {';
const source = readFileSync(new URL('../../includes/docsIncludes/docPool.php', import.meta.url), 'utf8');
const jsStart = source.indexOf(openMarker) + '<script>\n'.length;
const jsEnd = source.indexOf('\nJS;', jsStart);

function unescapeHeredoc(text) {
    return text.replace(/\\(n|t|r|v|e|f|\\|\$|u\{[0-9A-Fa-f]+\}|x[0-9A-Fa-f]{1,2}|[0-7]{1,3})/g, (match, escape) => {
        switch (escape[0]) {
            case 'n': return '\n';
            case 't': return '\t';
            case 'r': return '\r';
            case 'v': return '\v';
            case 'e': return '\x1b';
            case 'f': return '\f';
            case '\\': return '\\';
            case '$': return '$';
            case 'x': return String.fromCharCode(parseInt(escape.slice(1), 16));
            case 'u': return String.fromCodePoint(parseInt(escape.slice(2, -1), 16));
            default: return String.fromCharCode(parseInt(escape, 8));
        }
    });
}

const script = unescapeHeredoc(source.slice(jsStart, source.lastIndexOf('</script>', jsEnd)))
    .replace('{$JsSum}', '""')
    .replace('{$JsDato}', '""')
    .replace('{$buttonColorJs}', '"#0b5ed7"')
    .replace('{$buttonTxtColorJs}', '"#ffffff"')
    .replace('{$lightButtonColorJs}', '"#e7f1ff"');

// _docPoolData.php returns the pool in SQL order: file_date DESC, updated DESC.
const poolRows = [
    { filename: 'bilag-A.pdf', amount: '300,00', date: '2026-09-18 02:45:27' },
    { filename: 'bilag-B.pdf', amount: '200,00', date: '2026-09-18 02:45:05' },
    { filename: 'bilag-C.pdf', amount: '150,00', date: '2026-09-18 02:44:36' },
    { filename: 'kvittering.pdf', amount: '100,00', date: '2026-09-18 02:44:00' },
].map((row, index) => ({
    ...row,
    subject: row.filename,
    account: '',
    href: `documents.php?openPool=1&poolFile=${encodeURIComponent(row.filename)}`,
    invoiceNumber: '',
    description: '',
    currency: '',
    fil_nr: index + 1,
}));

const poolUrl = 'http://saldi.test/includes/documents.php?openPool=1&source=kassekladde';

async function loadPool(page) {
    await page.addScriptTag({ content: script });
    await expect(page.locator('#fileListContainer tbody tr').first()).toBeVisible();
}

async function openPool(page, { rows = poolRows, selected = '' } = {}) {
    await page.route('http://saldi.test/**', route => {
        if (route.request().url().includes('_docPoolData.php')) return route.fulfill({ json: rows });
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body>
            <input id="poolSearchBox" value="" oninput="filterPoolFiles()">
            <div id="fileListContainer" style="height: 300px; overflow-y: auto;"></div>
            </body></html>` });
    });
    await page.goto(poolUrl + (selected ? `&poolFile=${encodeURIComponent(selected)}` : ''));
    await loadPool(page);
}

const renderedOrder = page => page.$$eval('#fileListContainer tbody tr', rows => rows.map(row => row.dataset.poolFile));

test('the bilag open in the preview pane keeps its place in the list', async ({ page }) => {
    await openPool(page, { selected: 'kvittering.pdf' });

    expect(await renderedOrder(page)).toEqual(poolRows.map(row => row.filename));
    expect(await page.$$eval('#fileListContainer tbody tr[data-selected="true"]', rows => rows.map(row => row.dataset.poolFile)))
        .toEqual(['kvittering.pdf']);
});

test('search text and column sort survive the reload that opening a bilag performs', async ({ page }) => {
    await openPool(page);
    await page.locator('#poolSearchBox').fill('bilag');
    await page.evaluate(() => window.sortFiles('amount'));
    expect(await renderedOrder(page)).toEqual(['bilag-C.pdf', 'bilag-B.pdf', 'bilag-A.pdf']);

    await page.reload();
    await loadPool(page);

    await expect(page.locator('#poolSearchBox')).toHaveValue('bilag');
    expect(await renderedOrder(page)).toEqual(['bilag-C.pdf', 'bilag-B.pdf', 'bilag-A.pdf']);
});

test('the scroll position of the list survives the reload', async ({ page }) => {
    const rows = Array.from({ length: 60 }, (_, index) => ({
        ...poolRows[0],
        filename: `bilag-${index + 1}.pdf`,
        subject: `bilag ${index + 1}`,
        date: `2026-09-18 02:${String(index).padStart(2, '0')}:00`,
        href: `documents.php?openPool=1&poolFile=bilag-${index + 1}.pdf`,
        fil_nr: index + 1,
    }));
    await openPool(page, { rows });

    await page.locator('#fileListContainer').evaluate(element => { element.scrollTop = 400; });
    await page.waitForTimeout(300); // the scroll saver is debounced by 150 ms

    await page.reload();
    await loadPool(page);
    await page.waitForTimeout(50);

    expect(await page.locator('#fileListContainer').evaluate(element => element.scrollTop)).toBe(400);
});
