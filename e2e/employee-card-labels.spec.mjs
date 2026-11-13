// 20261110 CDX/NTR Keep employee information card labels visible and wrapping through CSS in every language.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

const employeePage = readFileSync(new URL('../sager/ansatte.php', import.meta.url), 'utf8');
const employeeBody = readFileSync(new URL('../sager/ansatte_body.php', import.meta.url), 'utf8');
const formStyles = readFileSync(new URL('../css/form.css', import.meta.url), 'utf8');
const hyphenator = readFileSync(new URL('../javascript/hyphenator.js', import.meta.url), 'utf8');
// Use the production stylesheet link, label class, and any legacy hyphenation script still on the page.
const stylesheet = employeePage.match(/<link[^>]*href=\\"\.\.\/css\/form\.css[^>]*>/)[0].replaceAll('\\"', '"');
const labelClass = employeeBody.match(/<td\s+class=\\"(leftTableCell[^"\\]*)\\"/)[1];
const scripts = (employeePage.match(/<script[^>]*src=\\"\.\.\/javascript\/hyphenator\.js[^>]*><\/script>(?:\s*<script(?![^>]*\bsrc=)[^>]*>[\s\S]*?<\/script>)?/)?.[0] ?? '').replaceAll('\\"', '"');
const translations = readFileSync(new URL('../importfiler/tekster.csv', import.meta.url), 'utf8')
    .split('\n').find(row => row.startsWith('622\t')).split('\t');

for (const [language, column] of [['da', 1], ['en', 2], ['no', 3]]) {
    test(`employee card labels wrap through CSS in ${language}`, async ({ page }) => {
        const errors = [];
        const patternRequests = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('dialog', dialog => {
            errors.push(dialog.message());
            return dialog.dismiss();
        });
        await page.route('**/*', route => {
            const url = new URL(route.request().url());
            if (url.pathname.endsWith('/javascript/hyphenator.js')) {
                return route.fulfill({ contentType: 'application/javascript', body: hyphenator });
            }
            if (url.pathname.endsWith('/css/form.css')) {
                return route.fulfill({ contentType: 'text/css', body: formStyles });
            }
            if (url.hostname === 'saldi.test' && url.pathname === '/sager/ansatte.php') {
                return route.fulfill({ contentType: 'text/html; charset=utf-8', body: `<!doctype html>
                    <html lang="${language}"><head>${stylesheet}${scripts}</head><body>
                    <table style="table-layout:fixed; width:330px; font:12px/18px Arial">
                    <tr><td class="${labelClass}">${translations[column].trim()}</td>
                    <td class="rightTableCell"><input type="checkbox" name="box[6]" checked></td></tr>
                    <tr><td class="${labelClass}">ScaffoldingCertificationQualification</td>
                    <td class="rightTableCell"><input name="box[7]" value="saved value"></td></tr></table>
                    </body></html>` });
            }
            patternRequests.push(route.request().url());
            return route.abort();
        });

        await page.goto('https://saldi.test/sager/ansatte.php');
        const labels = page.locator('.leftTableCell');
        await expect(labels.first()).toBeVisible({ timeout: 2000 });
        await expect(labels.first()).toHaveText(translations[column].trim());
        await expect(page.locator('[name="box[6]"]')).toBeChecked();
        await expect(page.locator('[name="box[7]"]')).toHaveValue('saved value');
        for (const label of await labels.all()) {
            await expect(label).toHaveCSS('overflow-wrap', 'break-word');
            await expect(label).toHaveCSS('hyphens', 'auto');
            const layout = await label.evaluate(element => {
                const range = document.createRange();
                range.selectNodeContents(element);
                return { lines: range.getClientRects().length, scrollWidth: element.scrollWidth, width: element.clientWidth };
            });
            expect(layout.lines).toBeGreaterThan(1);
            expect(layout.scrollWidth).toBeLessThanOrEqual(layout.width);
            expect(await label.textContent()).not.toContain('\u00ad');
        }
        expect(await page.evaluate(() => typeof window.Hyphenator)).toBe('undefined');
        expect(patternRequests).toEqual([]);
        expect(errors).toEqual([]);
    });
}
