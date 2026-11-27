// 20261030 CL/MMR SST-916 Ticking "Auto-lookup CVR nr." after the CVR-nr. was typed looks the number up, as leaving the field does.
// Self-contained: every request is answered by page.route (the page, and cvrapi.dk with a JSONP reply), so it needs
// neither the docker stack nor the seeded login, and it runs with the rest of e2e/ in CI.
import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const jquery = fileURLToPath(new URL('../javascript/jquery-3.6.4.min.js', import.meta.url));
const script = fileURLToPath(new URL('../javascript/cvrapiopslag.js', import.meta.url));

// cvrapi.dk's answer for the test number. The card calls it with JSONP, so the reply wraps it in the callback.
const company = { vat: 42914169, name: 'Testfirma ApS', address: 'Testvej 1', zipcode: '6400', city: 'Sønderborg', phone: '12345678', email: null, fax: null };

// A debitor card with the fields cvrapiopslag.js reads and writes. Returns the numbers sent to cvrapi.dk.
async function openCard(page, { checked = false, autoFields = null } = {}) {
    const lookups = [];
    await page.route(url => url.hostname === 'cvrapi.dk', route => {
        const url = new URL(route.request().url());
        lookups.push(url.searchParams.get('vat'));
        const callback = url.searchParams.get('callback');
        return route.fulfill({ contentType: 'application/javascript; charset=utf-8', body: `${callback}(${JSON.stringify(company)});` });
    });
    await page.route('http://saldi.test/**', route => route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><body>
        <form name="debitorkort">
        <input name="ny_kontonr"><input name="firmanavn"><input name="addr1"><input name="addr2"><input name="postnr"><input name="bynavn">
        <input type="checkbox" name="auto_lookup_cvr" ${checked ? 'checked' : ''} onchange="javascript:docChange = true;">
        <input name="cvrnr"><input name="tlf"><input name="kontakt_email_val[1]">
        <input name="sidst">
        </form></body></html>` }));
    await page.goto('http://saldi.test/debitor/debitorkort.php');
    if (autoFields) {
        await page.evaluate(fields => { window.cvrAutoFelter = fields; }, autoFields);
    }
    await page.addScriptTag({ path: jquery });
    await page.addScriptTag({ path: script });
    return lookups;
}

// Types into a field key by key (the lookup listens to keyup) and leaves it, as a user does.
async function typeAndLeave(page, name, value) {
    await page.locator(`[name="${name}"]`).pressSequentially(value);
    await page.locator('[name=sidst]').click();
}

test('ticking the box after the CVR-nr. was typed looks it up', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const lookups = await openCard(page);
    await typeAndLeave(page, 'cvrnr', '42914169');
    await page.waitForTimeout(600);
    expect(lookups, 'nothing is looked up while the box is off').toEqual([]);

    await page.locator('[name=auto_lookup_cvr]').check();
    await expect(page.locator('[name=firmanavn]')).toHaveValue('Testfirma ApS');
    await expect(page.locator('[name=addr1]')).toHaveValue('Testvej 1');
    await expect(page.locator('[name=postnr]')).toHaveValue('6400');
    await expect(page.locator('[name=bynavn]')).toHaveValue('Sønderborg');
    expect(lookups).toEqual(['42914169']);
    expect(await page.evaluate(() => window.docChange)).toBe(true);
    expect(errors).toEqual([]);
});

test('unticking and ticking again does not look the same number up twice', async ({ page }) => {
    const lookups = await openCard(page);
    await typeAndLeave(page, 'cvrnr', '42914169');
    await page.locator('[name=auto_lookup_cvr]').check();
    await expect(page.locator('[name=firmanavn]')).toHaveValue('Testfirma ApS');
    await page.locator('[name=auto_lookup_cvr]').uncheck();
    await page.locator('[name=auto_lookup_cvr]').check();
    await page.waitForTimeout(600);
    expect(lookups).toEqual(['42914169']);
});

test('ticking the box with fewer than 8 digits does nothing', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const lookups = await openCard(page);
    await typeAndLeave(page, 'cvrnr', '4291416');
    await page.locator('[name=auto_lookup_cvr]').check();
    await page.waitForTimeout(600);
    expect(lookups).toEqual([]);
    await expect(page.locator('[name=firmanavn]')).toHaveValue('');
    expect(errors).toEqual([]);
});

test('the box first and then the number still looks it up', async ({ page }) => {
    const lookups = await openCard(page, { checked: true });
    await page.locator('[name=cvrnr]').pressSequentially('42914169');
    await expect(page.locator('[name=firmanavn]')).toHaveValue('Testfirma ApS');
    expect(lookups).toEqual(['42914169']);
});

test('the customer no. is looked up when the CVR field is empty', async ({ page }) => {
    const lookups = await openCard(page);
    await typeAndLeave(page, 'ny_kontonr', '42914169');
    await page.locator('[name=auto_lookup_cvr]').check();
    await expect(page.locator('[name=firmanavn]')).toHaveValue('Testfirma ApS');
    expect(lookups).toEqual(['42914169']);
});

test('the customer no. is not looked up where cvrAutoFelter leaves it out (sager)', async ({ page }) => {
    const lookups = await openCard(page, { autoFields: ['cvrnr'] });
    await typeAndLeave(page, 'ny_kontonr', '42914169');
    await page.locator('[name=auto_lookup_cvr]').check();
    await page.waitForTimeout(600);
    expect(lookups).toEqual([]);
});

test('with both fields filled the CVR field is the one looked up', async ({ page }) => {
    const lookups = await openCard(page);
    await typeAndLeave(page, 'ny_kontonr', '12345678');
    await typeAndLeave(page, 'cvrnr', '42914169');
    await page.locator('[name=auto_lookup_cvr]').check();
    await expect(page.locator('[name=firmanavn]')).toHaveValue('Testfirma ApS');
    expect(lookups).toEqual(['42914169']);
});
