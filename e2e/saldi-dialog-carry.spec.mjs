// 20261126 ASR Created for review M1: Saldi's message box (javascript/saldi-dialog.js) and pages that move on by themselves.
//              includes/luk.php waits while a message is open (SaldiDialog.busy()) before its fallback moves on.
//              The open-posts report (debitor/rapport.php) writes itself over its loading page with document.open(), and before that it lets the box step aside (SaldiDialog.retire()).
//              A message open on the loading page is then shown on the written report, and later messages still show.
// Self-contained like saldi-dialog-prompt.spec.mjs: the snippets are cut from luk.php and rapport.php and run on stub pages
// with the real saldi-dialog.js, and every request is answered by page.route, so it needs neither the docker stack nor a login.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const repo = fileURLToPath(new URL('..', import.meta.url));

// includes/luk.php: the fallback when the popup cannot close, a function of the URL it moves on to.
const lukWait = /print "<script>(\(function \(url\) \{.*?\}\))\(\$lukFallbackJs\);<\/script>";/.exec(readFileSync(repo + 'includes/luk.php', 'utf8'))?.[1];
// debitor/rapport.php: the open-posts shell's document.open() before the report is written over it.
const beginDoc = /print "(function beginDoc\(\)\{.*?\} \})";/.exec(readFileSync(repo + 'debitor/rapport.php', 'utf8'))?.[1];

const HEAD = `<meta charset="utf-8"><script>window.SALDI_DIALOG_CFG = {t:{}, hidden:[], csrf:'', prefUrl:''};</script>
<script src="/javascript/saldi-dialog.js"></script>`;
const REPORT = `<!doctype html><html><head>${HEAD}</head><body><p id="report">Rapport</p></body></html>`;
const PAGES = {
    '/luk': `<!doctype html><html><head>${HEAD}</head><body><p>luk</p>
<script>alert('Rykker 3-1 er ikke sendt.');</script><script>${lukWait}('/next');</script></body></html>`,
    '/luk-quiet': `<!doctype html><html><head>${HEAD}</head><body><p>luk</p><script>${lukWait}('/next');</script></body></html>`,
    '/shell': `<!doctype html><html><head>${HEAD}</head><body><div id="status">Indlæser...</div>
<script>alert('Rykkerdato udenfor regnskabsår');
var opened = false;
${beginDoc}
window.writeReport = function () { beginDoc(); document.write(${JSON.stringify(REPORT).replace(/<\//g, '<\\/')}); document.close(); };</script></body></html>`,
};

async function openPage(page, path) {
    const browserBoxes = [];
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('dialog', dialog => { browserBoxes.push(dialog.type() + ':' + dialog.message()); return dialog.dismiss(); });
    await page.route(url => url.hostname !== 'saldi.test', route => route.abort());
    await page.route('http://saldi.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/javascript/saldi-dialog.js') return route.fulfill({ path: repo + 'javascript/saldi-dialog.js' });
        if (path === '/next') return route.fulfill({ contentType: 'text/html; charset=utf-8', body: '<!doctype html><title>next</title><p>next</p>' });
        return route.fulfill({ contentType: 'text/html; charset=utf-8', body: PAGES[path] ?? '' });
    });
    await page.goto('http://saldi.test' + path);
    return { browserBoxes, errors };
}

const message = page => page.locator('#sdlg-root .sdlg-msg');
const ok = page => page.locator('#sdlg-root .sdlg-primary').click();

test.describe('Saldi\'s message box on pages that move on by themselves', () => {
    test('luk.php: the fallback waits until the open message is read', async ({ page }) => {
        expect(lukWait, 'the fallback script of includes/luk.php').toBeTruthy();
        const box = await openPage(page, '/luk');
        await expect(message(page)).toHaveText('Rykker 3-1 er ikke sendt.');
        await page.waitForTimeout(2000);   // the fallback's second has passed twice
        expect(new URL(page.url()).pathname, 'still on luk.php while the message is open').toBe('/luk');
        await ok(page);
        await expect(page).toHaveTitle('next');
        expect(box.browserBoxes).toEqual([]);
        expect(box.errors).toEqual([]);
    });

    test('luk.php: without a message the fallback moves on after a second, as before', async ({ page }) => {
        const box = await openPage(page, '/luk-quiet');
        await expect(page).toHaveTitle('next', { timeout: 5000 });
        expect(box.errors).toEqual([]);
    });

    test('open-posts report: a message open on the loading page is shown on the written report, and later ones too', async ({ page }) => {
        expect(beginDoc, 'beginDoc() of the open-posts shell in debitor/rapport.php').toBeTruthy();
        const box = await openPage(page, '/shell');
        await expect(message(page)).toHaveText('Rykkerdato udenfor regnskabsår');
        await page.evaluate(() => window.writeReport());
        await expect(page.locator('#report')).toHaveText('Rapport');
        await expect(message(page), 'the message is not wiped by document.open()').toHaveText('Rykkerdato udenfor regnskabsår');
        await ok(page);
        await expect(message(page)).toHaveCount(0);
        await page.evaluate(() => alert('Senere besked'));
        await expect(message(page), 'a later message is not held back by the wiped box').toHaveText('Senere besked');
        await ok(page);
        await expect(message(page)).toHaveCount(0);
        expect(box.browserBoxes, 'the box, not the browser\'s own').toEqual([]);
        expect(box.errors).toEqual([]);
    });
});
