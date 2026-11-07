// 20261105 CL/LH The public booking pages show createBooking's refusal and ask for no payment link (testlab round 1, rental-public-booking).
// api.php answers 409 for a stand booked or blocked since the page drew its calendar, and 400 for a booking it cannot make.
// remoteBooking/index.js and mysale/remote.js went on to vibrantPaymentLink.php with no booking id, rejected that booking
// and said the payment link failed. Self-contained: the page's own markup and scripts from this checkout, and every request
// answered by page.route, so it needs neither the docker stack nor the seeded login, and it runs with the rest of e2e/ in CI.
// 20261105 CL/LH After the payment both pages say the confirmation was mailed only when sendMail.php answers sent: true (testlab round 1, rental-public-booking).
//                They said so before sendMail.php was asked, also when it answered "Mail kunne ikke sendes".
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

// What an api.php action or a script answers unless a test says otherwise, as [body, status].
const defaults = {
    getAllProducts: [[product]],
    getAllDates: [stands],
    getClosedDates: [[]],
    getTerms: [{ terms: '' }],
    createCust: [{ id: 7 }],
    'getDB.php': [{ db: 'saldi_1', id: 7 }],
    'vibrantPaymentLink.php': [{ error: 'not asked for in this test' }],
};

const refusals = {
    409: { error: 'The stand is not free in that period' },
    400: { error: 'Invalid booking' },
};

// Serves the page at `path` and records what it asks the server (api.php by action, other scripts by name) and what it
// posts. `answers` overrides `defaults`; a string body is sent as HTML, the way PHP answers after a fatal error.
async function openPage(page, path, answers) {
    const asked = [];
    const posted = {};
    const dialogs = [];
    page.on('dialog', dialog => {
        dialogs.push(dialog.message());
        return dialog.accept();
    });
    // The payment window shows Vibrant's page; the tests answer its payment intent instead.
    await page.addInitScript(() => { window.open = () => null; });
    await page.route(url => url.hostname !== 'saldi.test', route => route.abort());
    await page.route('http://saldi.test/**', route => {
        const url = new URL(route.request().url());
        const file = url.pathname.replace(/^\//, '');
        const script = file.split('/').pop();
        const name = script === 'api.php' ? [...url.searchParams.keys()][0] : script;
        const answer = answers[name] ?? defaults[name] ?? (script === 'api.php' ? [{}] : null);
        if (answer) {
            asked.push(name);
            posted[name] = route.request().postData();
            const [body, status = 200] = answer;
            return typeof body === 'string'
                ? route.fulfill({ status, contentType: 'text/html; charset=utf-8', body })
                : route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
        }
        if (file === path) {
            // The page's own markup, without its PHP.
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: readFileSync(repo + file, 'utf8').replace(/<\?php[\s\S]*?\?>/g, '') });
        }
        if (existsSync(repo + file)) return route.fulfill({ path: repo + file });
        return route.fulfill({ status: 404, body: '' });
    });
    return { asked, posted, dialogs };
}

// Product, the first period, today in the calendar: the steps both pages share.
async function chooseToday(page) {
    await page.locator('.products button').first().click();
    await page.locator('.next').click();
    await page.locator('.flatpickr-day.today').click();
}

// remoteBooking: today's stand for a new customer, up to createBooking.
async function bookOnRemoteBooking(page) {
    await page.goto('http://saldi.test/remoteBooking/index.php?db=saldi_1');
    await chooseToday(page);
    await page.locator('.rental').click();
    for (const [name, value] of Object.entries({ name: 'Chartest Kunde', addr: 'Testvej 1', city: 'Aarhus', zip: '8000', tlf: '12345678', email: 'kunde@example.invalid' })) {
        await page.locator(`input[name=${name}]`).fill(value);
    }
    await page.locator('#newUser').click();
}

// mysale: today's stand for the shop's own customer, up to createBooking.
async function bookOnMysale(page) {
    await page.goto('http://saldi.test/mysale/remoteBooking.php?id=chartest');
    await chooseToday(page);
    await page.locator('#terms').check();
    await page.locator('.rental').click();
}

const messages = {
    409: 'Den valgte periode er ikke længere ledig. Opdater siden, og vælg en anden periode.',
};

for (const status of [409, 400]) {
    test(`remoteBooking: a ${status} from createBooking is shown and no payment link is asked for`, async ({ page }) => {
        const { asked, dialogs } = await openPage(page, 'remoteBooking/index.php', { createBooking: [refusals[status], status] });
        await bookOnRemoteBooking(page);

        await expect.poll(() => dialogs).toEqual([messages[status] ?? 'Bookingen kunne ikke oprettes. Kontakt udlejeren.']);
        expect(asked).toContain('createBooking');
        expect(asked, 'no payment link and no rejected booking').not.toContain('vibrantPaymentLink.php');
        expect(asked).not.toContain('updateBooking');
        await expect(page.locator('#loading')).toBeHidden();
    });

    test(`mysale: a ${status} from createBooking is shown and no payment link is asked for`, async ({ page }) => {
        const { asked, dialogs } = await openPage(page, 'mysale/remoteBooking.php', { createBooking: [refusals[status], status] });
        await bookOnMysale(page);

        await expect.poll(() => dialogs).toEqual([messages[status] ?? 'Bookingen kunne ikke oprettes. Kontakt butikken.']);
        expect(asked).toContain('createBooking');
        expect(asked, 'no payment link and no rejected booking').not.toContain('vibrantPaymentLink.php');
        expect(asked).not.toContain('updateBooking');
        await expect(page.locator('#loading')).toBeHidden();
    });
}

// A booking made, its payment link, Vibrant's answer that it is paid, and the approval.
const paid = {
    createBooking: [{ id: 11 }],
    'vibrantPaymentLink.php': [{ id: 'pi_11', url: 'http://saldi.test/pay' }],
    'vibrantPaymentIntent.php': [{ state: 'succeeded' }],
    updateBooking: [{ msg: 'Booking updated' }],
};

// sendMail.php's answers: the mail went out, PHPMailer threw (the testlab's SMTP), or PHP died before answering.
const mails = {
    sent: [{ sent: true, msg: 'Mail sendt til kunde@example.invalid' }],
    'not sent': [{ sent: false, msg: 'Mail kunne ikke sendes til kunde@example.invalid' }],
    'no answer': ['<b>Fatal error</b>', 500],
};

const pages = {
    remoteBooking: { path: 'remoteBooking/index.php', book: bookOnRemoteBooking, ask: 'Kontakt udlejeren.' },
    mysale: { path: 'mysale/remoteBooking.php', book: bookOnMysale, ask: 'Kontakt butikken.' },
};

for (const [name, { path, book, ask }] of Object.entries(pages)) {
    for (const [mail, answer] of Object.entries(mails)) {
        test(`${name}: after the payment the page says the confirmation was mailed only when it was (${mail})`, async ({ page }) => {
            const { asked, posted } = await openPage(page, path, { ...paid, 'sendMail.php': answer });
            await book(page);

            const thanks = page.getByRole('heading', { name: 'Tak for din bestilling!' });
            await expect(thanks).toBeVisible();
            await expect.poll(() => asked).toContain('sendMail.php');
            await expect(thanks.locator('xpath=..').locator('p')).toHaveText(mail === 'sent'
                ? 'Vi har sendt en ordrebekræftelse mail med ordre.'
                : `Ordrebekræftelsen kunne ikke sendes på mail. ${ask}`);
            expect(JSON.parse(posted['sendMail.php']).booking_id).toBe(11);
        });
    }
}
