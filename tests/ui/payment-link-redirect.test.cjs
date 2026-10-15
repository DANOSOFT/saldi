// tests/ui/payment-link-redirect.test.cjs — 20261001 CDX/LH Created.
// Run: SALDI_PLAYWRIGHT_MODULE=/path/to/playwright node --test tests/ui/payment-link-redirect.test.cjs
// PHP fixtures run only in saldimobile-web; all provider navigation is intercepted.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const { createInterface } = require('node:readline');
const { chromium } = require(process.env.SALDI_PLAYWRIGHT_MODULE || 'playwright');

const origins = [
    'https://api.vipps.no', 'https://apitest.vipps.no',
    'https://pay.vipps.no', 'https://pay-mt.vipps.no',
    'https://pay.mobilepay.dk', 'https://pay-mt.mobilepay.dk',
    'https://pay.mobilepay.fi', 'https://pay-mt.mobilepay.fi',
];

for (const origin of origins) {
    test(`payment form reaches ${origin} through the server redirect`, { timeout: 45000 }, async () => {
        const fixture = spawn('docker', ['exec', '-i', '-w', '/var/www/html/saldi',
            'saldimobile-web', 'php', 'tests/restapi/support/payment_link_browser_fixture.php', origin]);
        let diagnostics = '';
        fixture.stderr.on('data', (chunk) => { diagnostics += chunk; });
        const lines = createInterface({ input: fixture.stdout })[Symbol.asyncIterator]();
        const finished = new Promise((resolve, reject) => {
            fixture.on('error', reject);
            fixture.on('exit', (code) => resolve(code));
        });
        let browser;
        try {
            const first = await lines.next();
            assert.equal(first.done, false, diagnostics);
            const { query } = JSON.parse(first.value);
            browser = await chromium.launch({
                executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/usr/bin/chromium',
                proxy: { server: 'http://127.0.0.1:1', bypass: '127.0.0.1,localhost' },
            });
            const page = await browser.newPage();
            const base = process.env.SALDI_BROWSER_BASE_URL || 'http://127.0.0.1:5110/saldi';
            const violations = [];
            page.on('console', (message) => {
                if (/form-action|Content Security Policy/i.test(message.text())) {
                    violations.push(message.text());
                }
            });
            const interceptionErrors = [];
            const session = await page.context().newCDPSession(page);
            // Match only HTTPS so each external redirect hop pauses independently; local Saldi requests run normally.
            await session.send('Fetch.enable', { patterns: [{ urlPattern: 'https://*', requestStage: 'Request' }] });
            session.on('Fetch.requestPaused', async ({ requestId, request }) => {
                try {
                    const url = new URL(request.url);
                    if (!origins.includes(url.origin)) {
                        await session.send('Fetch.failRequest', { requestId, errorReason: 'BlockedByClient' });
                    } else if (url.origin === 'https://api.vipps.no') {
                        await session.send('Fetch.fulfillRequest', { requestId, responseCode: 302,
                            responseHeaders: [{ name: 'Location', value: 'https://pay.mobilepay.dk/landing' }] });
                    } else {
                        await session.send('Fetch.fulfillRequest', { requestId, responseCode: 200,
                            responseHeaders: [{ name: 'Content-Type', value: 'text/html' }],
                            body: Buffer.from('<h1>Local provider landing page</h1>').toString('base64') });
                    }
                } catch (error) {
                    interceptionErrors.push(error.message);
                }
            });
            const preview = await page.goto(`${base}/debitor/payments/link/betal.php?${query}`);
            assert.equal(preview.status(), 200);
            await page.getByRole('button', { name: 'Betal med MobilePay' }).click();
            const destination = origin === 'https://api.vipps.no' ? 'https://pay.mobilepay.dk' : origin;
            await page.waitForURL((url) => url.origin === destination, { timeout: 15000 });
            assert.equal(await page.locator('h1').textContent(), 'Local provider landing page');
            assert.deepEqual(violations, []);
            assert.deepEqual(interceptionErrors, []);
            fixture.stdin.end('verify\n');
            const verified = await lines.next();
            assert.deepEqual(JSON.parse(verified.value), { attempts: 1, state: 'created' });
        } finally {
            if (browser) await browser.close();
            if (!fixture.stdin.writableEnded) fixture.stdin.end('cleanup\n');
            assert.equal(await finished, 0, diagnostics);
        }
    });
}
