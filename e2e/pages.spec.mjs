// e2e/pages.spec.mjs - every module's landing page loads without failing.
//
// The breadth check. Saldi is a page-per-file PHP application with no
// framework and no build step, so a page can be broken - a fatal from a
// renamed include, a query that stopped returning rows - without anything
// else noticing. Nothing in the PHPUnit suites opens a page; this spec does,
// for every module a user can reach from the menu.
//
// It asserts more than "HTTP 200": the image runs with display_errors on, so
// a fatal lands in the response body with a 200 status. See
// support/app.mjs expectNoPhpFailure.
//
// PREREQUISITES: see e2e/workflow.spec.mjs.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.
// 20261022 CL/NTR Removed the stale "six stock pages" defect note; SD-619 fixed them and they are in PAGES.
// 20261027 CL/LH The stock balance list no longer ends the session on first open (MB-99, SD-623); its
//                test now asserts that both opens stay on the page instead of pinning the bounce.

import { test, expect } from "@playwright/test";
import { login, logout, captureDialogs, expectNoPhpFailure } from "./support/app.mjs";

/**
 * The landing page of each module, plus the reports a bookkeeper opens most.
 *
 * Read-only pages only: nothing here posts, deletes, approves, dunns or
 * emails. Anything that changes state belongs in a workflow spec where the
 * change can be asserted.
 */
const PAGES = [
  ["dashboard", "/saldi/index/dashboard.php"],
  ["main menu", "/saldi/index/main.php"],
  ["finance: journal list", "/saldi/finans/kladdeliste.php"],
  ["finance: cash journal", "/saldi/finans/kassekladde.php?returside=kladdeliste.php&tjek=-1"],
  ["finance: account statement", "/saldi/finans/kontospec.php"],
  ["finance: bank reconciliation", "/saldi/finans/bankReconcile.php"],
  ["finance: bank import", "/saldi/finans/bankimport.php"],
  ["finance: reports", "/saldi/finans/rapport.php"],
  ["finance: audit trail", "/saldi/finans/kontrolspor.php"],
  ["finance: budget", "/saldi/finans/budget.php"],
  ["debtor: customers", "/saldi/debitor/debitor.php"],
  ["debtor: order list", "/saldi/debitor/ordreliste.php"],
  ["debtor: account card", "/saldi/debitor/debitorkort.php"],
  ["debtor: reports", "/saldi/debitor/rapport.php"],
  ["debtor: employees", "/saldi/debitor/ansatte.php"],
  ["creditor: suppliers", "/saldi/kreditor/kreditor.php"],
  ["creditor: order list", "/saldi/kreditor/ordreliste.php"],
  ["creditor: account card", "/saldi/kreditor/kreditorkort.php"],
  ["creditor: reports", "/saldi/kreditor/rapport.php"],
  ["stock: stock status", "/saldi/lager/lagerstatus.php"],
  ["stock: reports", "/saldi/lager/rapport.php"],
  ["settings: users", "/saldi/systemdata/brugere.php"],
  ["settings: misc", "/saldi/systemdata/diverse.php"],
  ["settings: email", "/saldi/systemdata/email_settings.php"],
  ["stock: min/max", "/saldi/lager/minmaxstock.php"],
  ["stock: full BOM", "/saldi/lager/fuld_stykliste.php"],
];

test.describe("module pages load", () => {
  // Deliberately NOT serial: a sweep whose first failure skips the remaining
  // pages tells you almost nothing. Each page is judged on its own.
  let page;

  test.beforeAll(async ({ browser }) => {
    page = await browser.newPage();
    captureDialogs(page);
    await login(page);
  });

  test.afterAll(async () => {
    await logout(page);
    await page.close();
  });

  for (const [name, path] of PAGES) {
    test(`${name} loads without a PHP failure`, async () => {
      const response = await page.goto(path, { waitUntil: "domcontentloaded" });

      expect(response, `${name}: no response`).not.toBeNull();
      expect(response.status(), `${name}: HTTP ${response.status()}`).toBeLessThan(400);

      // Several pages issue a meta-refresh or a JS redirect on load; let it
      // settle before reading, or page.content() races the navigation.
      await page.waitForLoadState("networkidle").catch(() => {});

      // Scan the whole document, not just the visible text: a fatal can be
      // emitted inside <head> or after the closing tag and still be invisible.
      const html = await page.content().catch(() => "");
      expectNoPhpFailure(html, name);

      // The page produced a real document rather than dying mid-render.
      //
      // Deliberately not "the body has visible text" and not "the page has a
      // title": several pages (bank import, bank reconciliation) render an
      // empty working area until a bank account is configured, and most of
      // these pages set <title> from a variable that is empty on a fresh
      // tenant. Neither is a failure; a truncated document is.
      expect(html.length, `${name}: rendered a truncated document`).toBeGreaterThan(500);
      expect(html, `${name}: document was cut off mid-render`).toMatch(/<\/html>/i);

      // Every module page keeps the user logged in - a page that bounces to
      // the login screen, or renders the session-expired box, has lost it.
      expect(page.url(), `${name}: redirected to the login page`).not.toMatch(/index\/index\.php/);
      expect(html, `${name}: rendered the session-expired notice`).not.toMatch(/session er udl/i);
    });
  }
});

/**
 * The stock balance list keeps the session (SD-623, MB-99).
 *
 * It used to throw the user back to the login screen the first time it was
 * opened: a user without a warehouse link was sent to index/logud.php, which
 * ends the session. The page now offers the warehouses to choose from, or
 * shows the totals when the account has none set up.
 *
 * Both opens must stay on the page: the first is the fixed defect, the second
 * the invariant that the page is reachable at all.
 */
test.describe("the stock balance list", () => {
  test("stays on the page and keeps the session", async ({ browser }) => {
    const page = await browser.newPage();
    captureDialogs(page);
    await login(page);

    for (const open of ["first", "second"]) {
      await page.goto("/saldi/lager/beholdningsliste.php", { waitUntil: "domcontentloaded" });
      await page.waitForLoadState("networkidle").catch(() => {});
      expect(page.url(), `the ${open} open stays on the page`).toMatch(/beholdningsliste\.php/);

      const html = await page.content();
      expectNoPhpFailure(html, "stock balance list");
      expect(html, `the ${open} open keeps the session`).not.toMatch(/session er udl/i);
    }

    await logout(page);
    await page.close();
  });
});



