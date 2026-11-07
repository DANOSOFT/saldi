// 20261105 CL/LH The public booking pages show createBooking's refusal and ask for no payment link (testlab round 1, rental-public-booking).
// api.php answers 409 for a stand booked or blocked since the page drew its calendar, and 400 for a booking it cannot make.
// remoteBooking/index.js and mysale/remote.js went on to vibrantPaymentLink.php with no booking id, rejected that booking
// and said the payment link failed. Self-contained: the page's own markup and scripts from this checkout, and every request
// answered by page.route, so it needs neither the docker stack nor the seeded login, and it runs with the rest of e2e/ in CI.
import { test, expect } from '@playwright/test';
import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const repo = fileURLToPath(new URL('..', import.meta.url));

test.use({ timezoneId: 'Europe/Copenhagen' });

// One product with one stand that has no booking: every day from today is free in the calendar.
const product = {
    product_id: '5', id: '5', product_name: 'Reol', descript: null, unit: 'Uge', price: 100, sku: 'R1',
    periods: [{ amount: 1 }], m_antal: '0', m_rabat: '0', m_type: '', choose_periods: 0, max: 0,
};
const stands = [{ id: '2', rt_from: null, rt_to: null }];

const refusals = {
    409: { error: 'The stand is not free in that period' },
    400: { error: 'Invalid booking' },
};

// Serves the page at `page` and records what it asks the server. createBooking answers `status`.
async function openPage(page, path, status) {
    const asked = [];
    const dialogs = [];
    page.on('dialog', dialog => {
        dialogs.push(dialog.message());
        return dialog.accept();
    });
    await page.route(url => url.hostname !== 'saldi.test', route => route.abort());
    await page.route('http://saldi.test/**', route => {
        const url = new URL(route.request().url());
        const file = url.pathname.replace(/^\//, '');
        const json = (body, code = 200) => route.fulfill({ status: code, contentType: 'application/json', body: JSON.stringify(body) });
        if (file.endsWith('api.php')) {
            const action = [...url.searchParams.keys()][0];
            asked.push(action);
            switch (action) {
                case 'getAllProducts': return json([product]);
                case 'getAllDates': return json(stands);
                case 'getClosedDates': return json([]);
                case 'getTerms': return json({ terms: '' });
                case 'createCust': return json({ id: 7 });
                case 'createBooking': return json(refusals[status], status);
                default: return json({});
            }
        }
        if (file.endsWith('getDB.php')) return json({ db: 'saldi_1', id: 7 });
        if (file.endsWith('vibrantPaymentLink.php')) {
            asked.push('vibrantPaymentLink.php');
            return json({ error: 'not asked for in this test' });
        }
        if (file === path) {
            // The page's own markup, without its PHP.
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: readFileSync(repo + file, 'utf8').replace(/<\?php[\s\S]*?\?>/g, '') });
        }
        if (existsSync(repo + file)) return route.fulfill({ path: repo + file });
        return route.fulfill({ status: 404, body: '' });
    });
    return { asked, dialogs };
}

// Product, the first period, today in the calendar: the steps both pages share.
async function chooseToday(page) {
    await page.locator('.products button').first().click();
    await page.locator('.next').click();
    await page.locator('.flatpickr-day.today').click();
}

const messages = {
    409: 'Den valgte periode er ikke længere ledig. Opdater siden, og vælg en anden periode.',
};

for (const status of [409, 400]) {
    test(`remoteBooking: a ${status} from createBooking is shown and no payment link is asked for`, async ({ page }) => {
        const { asked, dialogs } = await openPage(page, 'remoteBooking/index.php', status);
        await page.goto('http://saldi.test/remoteBooking/index.php?db=saldi_1');
        await chooseToday(page);
        await page.locator('.rental').click();
        for (const [name, value] of Object.entries({ name: 'Chartest Kunde', addr: 'Testvej 1', city: 'Aarhus', zip: '8000', tlf: '12345678', email: 'kunde@example.invalid' })) {
            await page.locator(`input[name=${name}]`).fill(value);
        }
        await page.locator('#newUser').click();

        await expect.poll(() => dialogs).toEqual([messages[status] ?? 'Bookingen kunne ikke oprettes. Kontakt udlejeren.']);
        expect(asked).toContain('createBooking');
        expect(asked, 'no payment link and no rejected booking').not.toContain('vibrantPaymentLink.php');
        expect(asked).not.toContain('updateBooking');
        await expect(page.locator('#loading')).toBeHidden();
    });

    test(`mysale: a ${status} from createBooking is shown and no payment link is asked for`, async ({ page }) => {
        const { asked, dialogs } = await openPage(page, 'mysale/remoteBooking.php', status);
        await page.goto('http://saldi.test/mysale/remoteBooking.php?id=chartest');
        await chooseToday(page);
        await page.locator('#terms').check();
        await page.locator('.rental').click();

        await expect.poll(() => dialogs).toEqual([messages[status] ?? 'Bookingen kunne ikke oprettes. Kontakt butikken.']);
        expect(asked).toContain('createBooking');
        expect(asked, 'no payment link and no rejected booking').not.toContain('vibrantPaymentLink.php');
        expect(asked).not.toContain('updateBooking');
        await expect(page.locator('#loading')).toBeHidden();
    });
}
