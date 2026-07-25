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
];

test.describe("module pages load", () => {
  // Deliberately NOT serial: a sweep whose first failure skips the remaining
  // nineteen pages tells you almost nothing. Each page is judged on its own.
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
 * DEFECTS PINNED: six stock pages cannot be opened at all.
 *
 * Five of them - including the item list, which is the main way anyone looks
 * at stock - include `../includes/dkdecimal.php`. That file declares
 *
 *     function dkdecimal($tal) { ... }        // includes/dkdecimal.php:21
 *
 * with no `function_exists` guard, while `includes/stdFunc/dkDecimal.php:27`
 * declares a guarded two-argument `dkdecimal($tal, $decimaler)` that
 * `includes/online.php` has already loaded by the time the page gets there:
 *
 *     PHP Fatal error: Cannot redeclare dkdecimal() (previously declared in
 *     .../includes/stdFunc/dkDecimal.php:27) in .../includes/dkdecimal.php on line 21
 *
 * The sixth, minmaxstock.php, dies on its own:
 *
 *     PHP Fatal error: Uncaught TypeError: in_array(): Argument #2
 *     ($haystack) must be of type array, null given in .../minmaxstock.php:140
 *
 * All six answer HTTP 500 to a fully authenticated user. They are pinned here
 * rather than listed above so the sweep stays green on current code; when the
 * duplicate function is removed and the in_array guard added, these tests
 * fail and the pages should move into PAGES.
 *
 * Seven more files include the same unguarded dkdecimal.php
 * (debitor/basis_stykliste.php, debitor/udlign_openpost.php,
 * finans/simuler.php, produktion/ordre.php, produktion/ordreliste.php,
 * produktion/rapport.php, lager/vvsimport.php). Those happen to survive
 * today because of their include order - they are one edit away from the same
 * fatal.
 */
/**
 * DEFECT PINNED: the stock balance list throws the user back to the login
 * screen the first time it is opened in a session.
 *
 * Reproduced deterministically: log in, open
 * /saldi/lager/beholdningsliste.php, and the browser ends up at
 * /saldi/index/index.php. Navigating to it a second time works and keeps
 * working for the rest of the session. It happens whether or not another
 * stock page was visited first, so it is the page's own first request that
 * loses the session, not an interaction with a neighbour.
 *
 * The mechanism is not identified here. Worth noting that the page starts
 * with its module number commented out - `# $modulnr=9;`
 * (lager/beholdningsliste.php:25) - while includes/online.php:331 authorises
 * with `substr($rettigheder, $modulnr, 1)`; that is the shape of thing to
 * look at first, but this test only pins the behaviour that was observed.
 *
 * The second half of this test is the invariant that actually matters: the
 * page IS reachable, so whatever is fixed must not break it.
 */
test.describe("the stock balance list", () => {
  test("bounces to the login page on first open, then works", async ({ browser }) => {
    const page = await browser.newPage();
    captureDialogs(page);
    await login(page);

    await page.goto("/saldi/lager/beholdningsliste.php", { waitUntil: "domcontentloaded" });
    await page.waitForLoadState("networkidle").catch(() => {});
    expect(page.url(), "first open is bounced to the login page").toMatch(/index\/index\.php/);

    await page.goto("/saldi/lager/beholdningsliste.php", { waitUntil: "domcontentloaded" });
    await page.waitForLoadState("networkidle").catch(() => {});
    expect(page.url(), "the second open stays on the page").toMatch(/beholdningsliste\.php/);

    const html = await page.content();
    expectNoPhpFailure(html, "stock balance list");

    await logout(page);
    await page.close();
  });
});

const DEAD_PAGES = [
  ["stock: units", "/saldi/lager/enheder.php"],
  ["stock: item list", "/saldi/lager/vareliste.php"],
  ["stock: full BOM", "/saldi/lager/fuld_stykliste.php"],
  ["stock: update cost prices", "/saldi/lager/opdater_kostpriser.php"],
  ["stock: item import", "/saldi/lager/vareimport.php"],
  ["stock: min/max", "/saldi/lager/minmaxstock.php"],
];

test.describe("stock pages that cannot be opened", () => {
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

  for (const [name, path] of DEAD_PAGES) {
    test(`${name} answers 500 for an authenticated user`, async () => {
      const response = await page.goto(path, { waitUntil: "domcontentloaded" });

      expect(response, `${name}: no response`).not.toBeNull();
      expect(
        response.status(),
        `${name} is no longer fatal - see the docblock above and move it into PAGES`
      ).toBe(500);
    });
  }
});
