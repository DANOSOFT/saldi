// e2e/support/app.mjs - shared helpers for the Saldi e2e specs.
//
// The legacy UI has three habits that every spec has to deal with before it
// can test anything: it throws native alert()/confirm() dialogs, it covers the
// page with a first-visit tutorial overlay that swallows clicks, and it shows
// a force-logout interstitial when a stale `online` row exists. Handling those
// in one place keeps the specs about the workflow under test.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

import { expect } from "@playwright/test";

export const ACCOUNT = process.env.SALDI_E2E_ACCOUNT ?? "saldi_chatbot_test";
export const USER = process.env.SALDI_E2E_USER ?? "e2etest";
export const PASSWORD = process.env.SALDI_E2E_PASSWORD ?? "e2etest-local-2026";

/** Two plain (VAT-free) accounts from the standard Danish chart of accounts. */
export const DEBIT_ACCOUNT = "1050";
export const CREDIT_ACCOUNT = "1100";

/**
 * Accept every native dialog and return the array they are recorded into, so a
 * spec can assert on what the app said.
 *
 * @returns {string[]} messages, appended to as dialogs appear
 */
export function captureDialogs(page) {
  const messages = [];
  page.on("dialog", (dialog) => {
    messages.push(dialog.message());
    dialog.accept().catch(() => {});
  });
  return messages;
}

/**
 * Remove the first-visit tutorial overlay whenever it blocks an action.
 *
 * It reappears on every page load for a new user, so this is registered as a
 * locator handler rather than run once.
 */
export async function dismissTutorialOverlay(page) {
  await page.addLocatorHandler(page.locator("#tutorial-overlay"), async () => {
    // The evaluate can race a navigation - never let it fail the test.
    await page
      .evaluate(() => document.getElementById("tutorial-overlay")?.remove())
      .catch(() => {});
  });
}

/** Log in through the UI and land in the app shell. */
export async function login(page, { account = ACCOUNT, user = USER, password = PASSWORD } = {}) {
  await page.goto("/saldi/index/index.php");
  await page.locator('input[name="regnskab"]').fill(account);
  await page.locator('input[name="brugernavn"]').fill(user);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole("button", { name: "Login" }).click();

  // A stale `online` row from an aborted run shows a force-logout
  // interstitial; confirm through it.
  for (let i = 0; i < 3; i++) {
    const forceLogout = page.locator('input[name="force_logout"]');
    if (await forceLogout.isVisible({ timeout: 3_000 }).catch(() => false)) {
      await forceLogout.click();
    } else {
      break;
    }
  }

  await expect(page.locator("body")).toContainText(/Log ud|Oversigt/, { timeout: 15_000 });
  await dismissTutorialOverlay(page);
}

/** Leave a clean session behind so the next run does not hit force-logout. */
export async function logout(page) {
  await page.goto("/saldi/index/logud.php").catch(() => {});
}

/**
 * Symptoms of a PHP failure that still returns HTTP 200.
 *
 * Saldi runs with display_errors on in this image, so a fatal or a failed
 * query can land in the page body rather than in the status code. A page that
 * "loads" can still be broken, which is exactly what a smoke sweep is for.
 *
 * These patterns require PHP's full error format - the message AND the
 * `in <file> on line <n>` tail. Matching the bare words is not good enough:
 * several pages ship JavaScript error handlers whose own source contains the
 * strings "Fatal error", "parse error" and "Warning", and matching those
 * reports a healthy page as broken.
 */
export const PHP_FAILURE_PATTERNS = [
  /(Fatal|Parse|Recoverable) error[\s\S]{0,400}? on line <?\/?b?>?\s*\d+/i,
  /(Fatal|Parse|Recoverable) error:[\s\S]{0,400}? in \/\S+ on line \d+/i,
  /Uncaught \w*(Error|Exception):[\s\S]{0,200}? in \/\S+:\d+/,
  /pg_query\(\)[\s\S]{0,200}? in \/\S+ on line \d+/i,
];

/**
 * Assert a page body carries no PHP failure text.
 *
 * @param {string} body   the page's text content
 * @param {string} where  a label for the failure message
 */
export function expectNoPhpFailure(body, where) {
  for (const pattern of PHP_FAILURE_PATTERNS) {
    const match = body.match(pattern);
    if (match) {
      const at = Math.max(0, body.indexOf(match[0]) - 120);
      throw new Error(
        `${where} rendered a PHP failure (${match[0]}):\n...${body.slice(at, at + 400)}...`
      );
    }
  }
}
