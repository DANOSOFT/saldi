// 20261110 CDX/ASR Read-only report filters and document viewing remain available under deny enforcement.
// Uses a separately seeded read-only user, a harmless document fixture and an isolated tenant already in deny mode.
import { test, expect } from '@playwright/test';

const base = process.env.SALDI_ROLES_BASE;
test.describe('read-only role enforcement', () => {
  test.skip(!base || !process.env.SALDI_ROLE_READ_USER || !process.env.SALDI_ROLE_READ_PASSWORD || process.env.SALDI_ROLE_TEST_ENFORCEMENT !== 'deny', 'Requires explicit isolated deny-mode fixtures');
  let context, page;
  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext({ ignoreHTTPSErrors: true });
    page = await context.newPage();
    await page.goto(`${base}/index/index.php`);
    await page.locator('[name="regnskab"]').fill(process.env.SALDI_E2E_ACCOUNT);
    await page.locator('[name="brugernavn"]').fill(process.env.SALDI_ROLE_READ_USER);
    await page.locator('[name="password"]').fill(process.env.SALDI_ROLE_READ_PASSWORD);
    await page.getByRole('button', { name: 'Login', exact: true }).click();
    await page.waitForURL(/main\.php/);
  });
  test.afterAll(async () => {
    if (page) { await page.goto(`${base}/index/logud.php`); }
    if (context) { await context.close(); }
  });
  test('allows report filtering and rejects write requests', async () => {
    const response = await page.goto(`${base}/finans/rapport.php`);
    expect(response.status()).toBe(200);
    const filtered = await page.request.post(`${base}/finans/rapport.php`, { form: { rapportart: 'kontokort', submit: '' } });
    expect(filtered.status()).toBe(200);
    expect(await filtered.text()).toContain('rapportart');
    const write = await page.request.post(`${base}/finans/kassekladde.php`, { form: { save: '1' } });
    expect(write.status()).toBe(403);
    const unknownAction = await page.request.post(`${base}/finans/rapport.php`, { form: { rapportart: 'unrecognised_action' } });
    expect(unknownAction.status()).toBe(403);
  });
  test('serves authorised document previews and preserves tenant path checks', async () => {
    const document = await page.request.get(`${base}/includes/docsIncludes/docFile.php?k=doc&f=finance/roles-read-fixture.png`);
    expect(document.status()).toBe(200);
    expect(document.headers()['content-type']).toBe('image/png');
    const traversal = await page.request.get(`${base}/includes/docsIncludes/docFile.php?k=doc&f=../connect.php`);
    expect(traversal.status()).toBe(403);
  });
});
