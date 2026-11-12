// 20261110 CDX/NTR Exercise real Gantt page callbacks with legacy jQuery and failed-save recovery.
import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const asset = path => fileURLToPath(new URL(path, import.meta.url));
const failureMessage = 'The dates could not be saved. The schedule will reload.';
const pages = ['planlaeg_sag.php', 'planlaeg_sager.php', 'planlaeg_opgaver.php'];

/** Load each page's real chart initializer, substituting only PHP-rendered data and translations. */
async function openChart(page, name, status = 200) {
    const source = readFileSync(asset(`../sager/${name}`), 'utf8');
    expect(source).toContain('src="../javascript/saveGanttPlanDates.js?v=20261110"');
    const initializer = source.match(/\$\(function \(\) \{[\s\S]*?\n\t\t\}\);/)[0]
        .replace(/<\?php echo (.*?); \?>/g, (_, expression) => {
            if (expression === '$ganttdata' || expression === '$ganttMonthNames') return '[]';
            if (expression === '$ganttDaysText') return '"days"';
            if (expression.startsWith('jsString([findtekst(')) return JSON.stringify(failureMessage);
            throw new Error(`Unexpected PHP expression: ${expression}`);
        });
    const requests = [];
    const dialogs = [];
    const errors = [];
    let loads = 0;
    page.on('pageerror', error => errors.push(error.message));
    page.on('dialog', async dialog => {
        dialogs.push(dialog.message());
        await dialog.accept();
    });
    await page.route('http://saldi.test/sager/**', async route => {
        if (route.request().method() === 'POST') {
            requests.push({
                endpoint: new URL(route.request().url()).pathname.split('/').pop(),
                post: Object.fromEntries(new URLSearchParams(route.request().postData()))
            });
            await route.fulfill({ status, body: '' });
        } else {
            loads++;
            await route.fulfill({ contentType: 'text/html', body: '<!doctype html><div id="ganttChart"></div>' });
        }
    });
    await page.goto(`http://saldi.test/sager/${name}`);
    await page.addScriptTag({ path: asset('../javascript/jquery-1.4.2.js') });
    await page.addScriptTag({ path: asset('../javascript/date-da-DK.js') });
    await page.addScriptTag({ path: asset('../javascript/saveGanttPlanDates.js') });
    // Capture the callbacks registered by the real page without rendering unrelated chart markup.
    await page.evaluate(() => {
        $.fn.ganttView = function (options) { window.chartBehavior = options.behavior; };
    });
    await page.addScriptTag({ content: initializer });
    await page.waitForFunction(() => window.chartBehavior);
    return { requests, dialogs, errors, loads: () => loads };
}

/** Invoke a real page callback with dates and the identifiers supplied by that chart's bars. */
async function changeDates(page, name, event, target, id = '23') {
    await page.evaluate(({ name, event, target, id }) => {
        const data = {
            start: new Date(2026, 10, 6),
            end: new Date(2026, 10, 7),
            ...(target === 'case' ? { id } : { opg_id: '34' })
        };
        // Single-case bars explicitly set the unused ID; overview bars omit it.
        if (name === 'planlaeg_sag.php') data[target === 'case' ? 'opg_id' : 'id'] = '0';
        window.chartBehavior[event](data);
    }, { name, event, target, id });
}

for (const name of pages) {
    for (const event of ['onDrag', 'onResize']) {
        for (const target of ['case', 'task']) {
            test(`${name} ${event} saves ${target} dates with a zero unused ID`, async ({ page }) => {
                const state = await openChart(page, name);
                await changeDates(page, name, event, target);
                await expect.poll(() => state.requests.length).toBe(1);
                expect(state.requests[0]).toEqual({
                    endpoint: name === 'planlaeg_sag.php' ? 'ajax_planlaeg_sag.php' : 'ajax_planlaeg_sager.php',
                    post: {
                        planfra: '06-11-2026', plantil: '07-11-2026',
                        id: target === 'case' ? '23' : '0', opg_id: target === 'task' ? '34' : '0'
                    }
                });
                expect(state.dialogs).toEqual([]);
                expect(state.loads()).toBe(1);
                expect(state.errors).toEqual([]);
            });
        }
        for (const status of [400, 403, 500]) {
            test(`${name} ${event} reports HTTP ${status} and reloads saved dates`, async ({ page }) => {
                const state = await openChart(page, name, status);
                await changeDates(page, name, event, 'case');
                await expect.poll(state.loads).toBe(2);
                expect(state.dialogs).toEqual([failureMessage]);
                expect(state.requests).toHaveLength(1);
                expect(state.errors).toEqual([]);
            });
        }
    }
    test(`${name} preserves supplied invalid IDs for server validation`, async ({ page }) => {
        const state = await openChart(page, name, 400);
        await changeDates(page, name, 'onDrag', 'case', "23' OR '1'='1");
        await expect.poll(state.loads).toBe(2);
        expect(state.requests[0].post.id).toBe("23' OR '1'='1");
        expect(state.dialogs).toEqual([failureMessage]);
    });
}
